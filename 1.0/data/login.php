<?php
// login.php

// Configuração para habilitar/desabilitar logs de debug
$enable_debug_logs = false; // Mude para false para desabilitar os logs de debug

// Inicia a sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inclui o arquivo de configuração do banco de dados e autenticação
require_once 'config.php';
require_once 'auth.php';

// Evitar cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

// Importa namespaces necessários
use OTPHP\TOTP;

// Verifica se o usuário já está logado
if (isset($_SESSION['user_id'])) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'redirect' => 'clientes.php']);
        exit();
    }
    header('Location: clientes.php');
    exit();
}

// Inicializa a variável de erro e sucesso
$error = '';
$success = '';

// Função para gerar um token seguro
function generateTrustedDeviceToken() {
    return bin2hex(random_bytes(32));
}

// Função para retornar uma resposta JSON e encerrar
function sendJsonResponse($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

// Determina se estamos em HTTPS, considerando proxies
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
           $_SERVER['SERVER_PORT'] == 443 ||
           (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

// Log para depuração
if ($enable_debug_logs) {
    error_log("[DEBUG] HTTPS detectado: " . ($isHttps ? 'true' : 'false'));
}

// Verificar se o parâmetro logout está presente
$justLoggedOut = isset($_GET['logout']) && $_GET['logout'] === 'true';
if ($enable_debug_logs) {
    error_log("[DEBUG] justLoggedOut: " . ($justLoggedOut ? 'true' : 'false'));
}

// Logar todos os cookies disponíveis para depuração
if ($enable_debug_logs) {
    error_log("[DEBUG] Cookies disponíveis: " . print_r($_COOKIE, true));
}

// Processa o formulário de login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SESSION['2fa_user_id']) && !isset($_POST['verify_2fa']) && !isset($_POST['resend_2fa']) && !isset($_POST['cancel_2fa'])) {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS);
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Por favor, preencha todos os campos.';
    } elseif ($recaptcha_enabled) {
        $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
        if (empty($recaptcha_response)) {
            $error = 'Por favor, confirme que você não é um robô.';
        } else {
            $url = 'https://www.google.com/recaptcha/api/siteverify?secret=' . urlencode($recaptcha_secret_key) . '&response=' . urlencode($recaptcha_response);
            $response = file_get_contents($url);
            $response_data = json_decode($response);

            if (!$response_data->success) {
                $error = 'Falha na verificação do reCAPTCHA. Tente novamente.';
            }
        }
    }

    if (empty($error)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Verifica se o dispositivo é confiável
                $isTrustedDevice = false;
                if (isset($_COOKIE['trusted_device_token'])) {
                    $token = $_COOKIE['trusted_device_token'];
                    if ($enable_debug_logs) {
                        error_log("[DEBUG] Cookie trusted_device_token encontrado: $token");
                    }

                    try {
                        $stmt = $pdo->prepare("SELECT * FROM trusted_devices WHERE token = ? AND user_id = ? AND expires_at > NOW()");
                        $stmt->execute([$token, $user['id']]);
                        $trusted_device = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($trusted_device) {
                            if ($enable_debug_logs) {
                                error_log("[DEBUG] Dispositivo confiável válido encontrado para user_id: " . $user['id']);
                            }
                            $isTrustedDevice = true;
                        } else {
                            if ($enable_debug_logs) {
                                error_log("[DEBUG] Dispositivo confiável não encontrado ou expirado para token: $token");
                            }
                            // Remover cookie inválido
                            setcookie('trusted_device_token', '', [
                                'expires' => time() - 3600,
                                'path' => '/',
                                'domain' => $_SERVER['HTTP_HOST'],
                                'secure' => $isHttps,
                                'httponly' => true,
                                'samesite' => 'Lax'
                            ]);
                            if ($enable_debug_logs) {
                                error_log("[DEBUG] Cookie trusted_device_token removido devido a token inválido ou expirado.");
                            }
                        }
                    } catch (PDOException $e) {
                        if ($enable_debug_logs) {
                            error_log("[ERROR] Erro ao verificar dispositivo confiável: " . $e->getMessage());
                        }
                    }
                } else {
                    if ($enable_debug_logs) {
                        error_log("[DEBUG] Nenhum cookie trusted_device_token encontrado.");
                    }
                }

                if ($user['2fa_enabled'] && !$isTrustedDevice) {
                    $_SESSION['2fa_user_id'] = $user['id'];
                    $_SESSION['2fa_type'] = $user['2fa_type'];

                    if ($user['2fa_type'] === 'totp') {
                        // Usuário já configurou o TOTP, apenas solicita o código
                    } elseif ($user['2fa_type'] === 'email') {
                        $code = sprintf("%06d", mt_rand(0, 999999));
                        $_SESSION['2fa_code'] = $code;

                        try {
                            $mail->clearAllRecipients();
                            $mail->addAddress($user['email'], $user['username']);
                            $mail->isHTML(true);
                            $mail->Subject = 'Código de Verificação - FireNet Telecom';
                            $mail->Body = "
                                <h2>Olá, {$user['username']}!</h2>
                                <p>Seu código de verificação para login é: <strong>$code</strong></p>
                                <p>Este código expira em 30 minutos.</p>
                                <p>Se você não solicitou este código, ignore este e-mail.</p>
                                <p>Atenciosamente,<br>Equipe FireNet Telecom</p>
                            ";
                            $mail->AltBody = "Olá, {$user['username']}!\n\nSeu código de verificação para login é: $code\nEste código expira em 30 minutos.\nSe você não solicitou este código, ignore este e-mail.\n\nAtenciosamente,\nEquipe FireNet Telecom";
                            $mail->send();
                            $success = 'Um código de verificação foi enviado para o seu e-mail.';
                            if ($enable_debug_logs) {
                                error_log("[DEBUG] E-mail de 2FA enviado para {$user['email']} com código $code");
                            }
                        } catch (Exception $e) {
                            $error = 'Erro ao enviar o código de verificação: ' . $mail->ErrorInfo;
                            if ($enable_debug_logs) {
                                error_log("[ERROR] Falha ao enviar e-mail de 2FA para {$user['email']}: " . $e->getMessage());
                            }
                        }
                    }
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];

                    $event = $isTrustedDevice ? "Login bem-sucedido via dispositivo confiável" : "Login bem-sucedido";
                    $stmt = $pdo->prepare("INSERT INTO access_logs (user_id, event) VALUES (?, ?)");
                    $stmt->execute([$user['id'], $event]);

                    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                        sendJsonResponse(['success' => true, 'redirect' => 'clientes.php']);
                    }
                    header('Location: clientes.php');
                    exit();
                }
            } else {
                $error = 'Usuário ou senha inválidos.';
            }
        } catch (PDOException $e) {
            if ($enable_debug_logs) {
                error_log("[ERROR] Erro ao processar login: " . $e->getMessage());
            }
            $error = 'Ocorreu um erro. Tente novamente mais tarde.';
        }

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            sendJsonResponse(['success' => empty($error), 'error' => $error, 'success_message' => $success]);
        }
    }
}

// Processa a verificação do 2FA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_2fa']) && isset($_SESSION['2fa_user_id'])) {
    $code = trim($_POST['2fa_code'] ?? '');
    $user_id = $_SESSION['2fa_user_id'];

    if ($enable_debug_logs) {
        error_log("[DEBUG] Dados recebidos no formulário 2FA: " . print_r($_POST, true));
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $valid_code = false;
            if ($user['2fa_type'] === 'totp') {
                $totp = TOTP::create($user['2fa_secret']);
                $valid_code = $totp->verify($code);
            } elseif ($user['2fa_type'] === 'email') {
                $valid_code = ($code === $_SESSION['2fa_code']);
            }

            if ($valid_code) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                unset($_SESSION['2fa_user_id']);
                unset($_SESSION['2fa_type']);
                unset($_SESSION['2fa_code']);

                if (isset($_POST['trust_device']) && $_POST['trust_device'] === '1') {
                    $token = generateTrustedDeviceToken();
                    $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
                    $stmt = $pdo->prepare("INSERT INTO trusted_devices (user_id, token, expires_at) VALUES (?, ?, ?)");
                    $stmt->execute([$user['id'], $token, $expires_at]);

                    $cookieSet = setcookie('trusted_device_token', $token, [
                        'expires' => time() + (30 * 24 * 60 * 60),
                        'path' => '/',
                        'domain' => $_SERVER['HTTP_HOST'],
                        'secure' => $isHttps,
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);
                    if ($enable_debug_logs) {
                        if ($cookieSet) {
                            error_log("[DEBUG] Cookie trusted_device_token definido com sucesso. Token: $token");
                        } else {
                            error_log("[ERROR] Falha ao definir cookie trusted_device_token.");
                        }
                    }
                } else {
                    if ($enable_debug_logs) {
                        error_log("[DEBUG] Checkbox trust_device não marcado ou não enviado.");
                    }
                }

                $stmt = $pdo->prepare("INSERT INTO access_logs (user_id, event) VALUES (?, ?)");
                $stmt->execute([$user['id'], "Login bem-sucedido com 2FA"]);

                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    sendJsonResponse(['success' => true, 'redirect' => 'clientes.php']);
                }
                header('Location: clientes.php');
                exit();
            } else {
                $error = 'Código de verificação inválido.';
            }
        } else {
            $error = 'Usuário não encontrado.';
            unset($_SESSION['2fa_user_id']);
            unset($_SESSION['2fa_type']);
        }
    } catch (PDOException $e) {
        if ($enable_debug_logs) {
            error_log("[ERROR] Erro ao verificar 2FA: " . $e->getMessage());
        }
        $error = 'Ocorreu um erro. Tente novamente mais tarde.';
    }

    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        sendJsonResponse(['success' => empty($error), 'error' => $error]);
    }
}

// Processa o reenvio do código 2FA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_2fa']) && isset($_SESSION['2fa_user_id'])) {
    $user_id = $_SESSION['2fa_user_id'];

    try {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $user['2fa_type'] === 'email') {
            $code = sprintf("%06d", mt_rand(0, 999999));
            $_SESSION['2fa_code'] = $code;

            try {
                $mail->clearAllRecipients();
                $mail->addAddress($user['email'], $user['username']);
                $mail->isHTML(true);
                $mail->Subject = 'Novo Código de Verificação - FireNet Telecom';
                $mail->Body = "
                    <h2>Olá, {$user['username']}!</h2>
                    <p>Um novo código de verificação foi solicitado. Seu novo código é: <strong>$code</strong></p>
                    <p>Este código expira em 30 minutos.</p>
                    <p>Se você não solicitou este código, ignore este e-mail.</p>
                    <p>Atenciosamente,<br>Equipe FireNet Telecom</p>
                ";
                $mail->AltBody = "Olá, {$user['username']}!\n\nUm novo código de verificação foi solicitado. Seu novo código é: $code\nEste código expira em 30 minutos.\nSe você não solicitou este código, ignore este e-mail.\n\nAtenciosamente,\nEquipe FireNet Telecom";
                $mail->send();
                $success = 'Um novo código de verificação foi enviado para o seu e-mail.';
                if ($enable_debug_logs) {
                    error_log("[DEBUG] Novo e-mail de 2FA enviado para {$user['email']} com código $code");
                }
            } catch (Exception $e) {
                $error = 'Erro ao reenviar o código: ' . $mail->ErrorInfo;
                if ($enable_debug_logs) {
                    error_log("[ERROR] Falha ao reenviar e-mail de 2FA para {$user['email']}: " . $e->getMessage());
                }
            }
        }
    } catch (PDOException $e) {
        if ($enable_debug_logs) {
            error_log("[ERROR] Erro ao reenviar código 2FA: " . $e->getMessage());
        }
        $error = 'Ocorreu um erro ao reenviar o código. Tente novamente.';
    }

    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        sendJsonResponse(['success' => empty($error), 'error' => $error, 'success_message' => $success]);
    }
}

// Processa o cancelamento do 2FA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_2fa']) && isset($_SESSION['2fa_user_id'])) {
    unset($_SESSION['2fa_user_id']);
    unset($_SESSION['2fa_type']);
    unset($_SESSION['2fa_code']);
    $success = 'Verificação 2FA cancelada. Faça login novamente.';

    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        sendJsonResponse(['success' => true, 'success_message' => $success, 'redirect' => 'login.php']);
    }
    header('Location: login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FireNet Telecom</title>
    <link rel="stylesheet" href="css/login.css">
    <link rel="stylesheet" href="css/cadastro.css"> <!-- só aqui! -->
    <?php if ($recaptcha_enabled): ?>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
</head>
<body>
    <header>
        <a href='/'><img src="imagens/firenet.png" alt="Logotipo FireNet Telecom"></a>
    </header>

    <nav>
        <a href="/">Início</a>
        <a href="index.php#planos">Planos</a>
        <a href="index.php#vantagens">Vantagens</a>
        <a href="index.php#cobertura">Cobertura</a>
        <a href="index.php#central-cliente">Central do Cliente</a>
        <a href="cadastro.html">Cadastro</a>
        <a href="index.php#contato">Contato</a>
        <a href="login.php">Área do Consultor</a>
    </nav>

    <div class="container">
        <div class="login-container">
            <h2>Login</h2>
            <div id="login-error" class="error" style="display: none;"></div>
            <?php if (!empty($success)): ?>
                <p class="success"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <?php if (isset($_SESSION['2fa_user_id'])): ?>
                <form method="POST" action="" id="2fa-form">
                    <input type="hidden" name="verify_2fa" value="1">
                    <label for="2fa_code">Código de Verificação</label>
                    <input type="text" id="2fa_code" name="2fa_code" maxlength="6" placeholder="<?php echo $_SESSION['2fa_type'] === 'totp' ? 'Digite o código do seu app autenticador' : 'Digite o código enviado ao seu e-mail'; ?>">
                    <div class="trust-device">
                        <input type="checkbox" id="trust_device" name="trust_device" value="1">
                        <label for="trust_device">Marcar esse dispositivo como confiável por 30 dias</label>
                    </div>
                    <button type="submit">Verificar</button>
                </form>
                <?php if ($_SESSION['2fa_type'] === 'email'): ?>
                    <form method="POST" action="" id="resend-2fa-form">
                        <input type="hidden" name="resend_2fa" value="1">
                        <button type="submit" class="resend-button">Reenviar Código</button>
                    </form>
                <?php endif; ?>
                <form method="POST" action="" id="cancel-2fa-form">
                    <input type="hidden" name="cancel_2fa" value="1">
                    <button type="submit" class="cancel-button">Cancelar</button>
                </form>
            <?php else: ?>
                <form method="POST" action="" id="login-form">
                    <label for="username">Usuário</label>
                    <input type="text" id="username" name="username" required>
                    <label for="password">Senha</label>
                    <input type="password" id="password" name="password" required>
                    <div class="forgot-password">
                        <a href="esqueceu-senha.php">Esqueci a senha</a>
                    </div>
                    <?php if ($recaptcha_enabled): ?>
                        <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars($recaptcha_site_key); ?>" data-callback="onRecaptchaLoad"></div>
                    <?php endif; ?>
                    <button type="submit" id="login-button">Entrar</button>
                </form>
                <form action="index.php">
                    <button type="submit" class="home-button">Voltar para a página principal</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script src="js/login.js"></script>
</body>
</html>
