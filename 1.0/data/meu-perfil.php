<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
require_once 'auth.php';

use OTPHP\TOTP;

// Definir o fuso horário do PHP para evitar problemas com a expiração do token
date_default_timezone_set('America/Sao_Paulo');

header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    header('Location: login.php');
    exit();
}
verificarAutenticacao(false);

// Sincronizar o fuso horário do banco de dados com o PHP
$pdo->exec("SET time_zone = '-03:00'");

// Processar solicitação de alteração de 2FA (habilitar/desabilitar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_2fa_validate'])) {
    header('Content-Type: application/json');
    try {
        // Verificar se há uma solicitação de 2FA em andamento
        if (isset($_SESSION['2fa_pending_action']) && $_SESSION['2fa_pending_user_id'] == $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'Já existe uma solicitação de 2FA em andamento.']);
            exit;
        }

        $enable_2fa = isset($_POST['2fa_enabled']) && $_POST['2fa_enabled'] === '1' ? 1 : 0;
        $type_2fa = isset($_POST['2fa_type']) ? trim($_POST['2fa_type']) : 'email';

        if ($enable_2fa && !in_array($type_2fa, ['email', 'totp'])) {
            echo json_encode(['success' => false, 'message' => 'Tipo de 2FA inválido.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT username, email, 2fa_enabled, 2fa_type, 2fa_secret FROM usuarios WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user_data) {
            echo json_encode(['success' => false, 'message' => 'Usuário não encontrado.']);
            exit;
        }

        // Se o usuário quer desabilitar o 2FA
        if (!$enable_2fa) {
            // Desabilitar diretamente, sem necessidade de confirmação
            $stmt = $pdo->prepare("UPDATE usuarios SET 2fa_enabled = 0, 2fa_type = NULL, 2fa_secret = NULL WHERE id = ?");
            $result = $stmt->execute([$_SESSION['user_id']]);
            if ($result) {
                // Limpar quaisquer tokens pendentes no banco de dados
                $stmt = $pdo->prepare("DELETE FROM 2fa_codes WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                // Limpar variáveis de sessão relacionadas a solicitações de 2FA
                unset($_SESSION['2fa_pending_action']);
                unset($_SESSION['2fa_pending_user_id']);
                unset($_SESSION['2fa_enable_state']);
                unset($_SESSION['2fa_type']);
                unset($_SESSION['2fa_temp_secret']);
                registrarLog($pdo, $_SESSION['user_id'], "Desabilitou o 2FA");
                echo json_encode(['success' => true, 'message' => '2FA desabilitado com sucesso.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao desabilitar o 2FA.']);
            }
            exit;
        }

        // Se o usuário quer habilitar o 2FA
        if ($enable_2fa && $type_2fa === 'totp') {
            $totp = initializeTOTP(null, $user_data['username']);
            $secret = $totp->getSecret();
            $_SESSION['2fa_pending_action'] = 'enable_2fa';
            $_SESSION['2fa_pending_user_id'] = $_SESSION['user_id'];
            $_SESSION['2fa_enable_state'] = $enable_2fa;
            $_SESSION['2fa_type'] = $type_2fa;
            $_SESSION['2fa_temp_secret'] = $secret;
            $qrCodeData = generateTOTPQRCode($totp);
            // Enviar a chave (secret) junto com o QR code no JSON
            echo json_encode([
                'success' => true,
                'message' => 'Escaneie o QR code com seu aplicativo autenticador.',
                'qrCode' => $qrCodeData,
                'secret' => $secret, // Adicionando a chave
                'requiresConfirmation' => true
            ]);
            exit;
        } elseif ($enable_2fa && $type_2fa === 'email') {
            $code = generate2FACode();
            $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            $stmt = $pdo->prepare("DELETE FROM 2fa_codes WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $stmt = $pdo->prepare("INSERT INTO 2fa_codes (user_id, code, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $code, $expires_at]);

            try {
                if (!$mail instanceof PHPMailer\PHPMailer\PHPMailer) {
                    echo json_encode(['success' => false, 'message' => 'Erro na configuração do e-mail.']);
                    exit;
                }
                $mail->clearAllRecipients();
                $mail->addAddress($user_data['email'], $user_data['username']);
                $mail->isHTML(true);
                $mail->Subject = 'Token de Confirmação - FireNet Telecom';
                $mail->Body = "<h2>Olá, {$user_data['username']}!</h2><p>Você solicitou a habilitação do 2FA (Autenticação de Dois Fatores) por e-mail.</p><p>Seu token de confirmação é: <strong>$code</strong></p><p>Este token expira em 30 minutos.</p><p>Se você não solicitou esta alteração, ignore este e-mail.</p><p>Atenciosamente,<br>Equipe FireNet Telecom</p>";
                $mail->AltBody = "Olá, {$user_data['username']}!\n\nVocê solicitou a habilitação do 2FA (Autenticação de Dois Fatores) por e-mail.\nSeu token de confirmação é: $code\nEste token expira em 30 minutos.\nSe você não solicitou esta alteração, ignore este e-mail.\n\nAtenciosamente,\nEquipe FireNet Telecom";
                $mail->send();
                $_SESSION['2fa_pending_action'] = 'enable_2fa';
                $_SESSION['2fa_pending_user_id'] = $_SESSION['user_id'];
                $_SESSION['2fa_enable_state'] = $enable_2fa;
                $_SESSION['2fa_type'] = $type_2fa;
                registrarLog($pdo, $_SESSION['user_id'], "Solicitou habilitação de 2FA por e-mail - token enviado");
                echo json_encode(['success' => true, 'message' => 'Um token de confirmação foi enviado para o seu e-mail.', 'requiresConfirmation' => true]);
                exit;
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Erro ao enviar o token de confirmação: ' . $mail->ErrorInfo]);
                exit;
            }
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao processar a solicitação: ' . $e->getMessage()]);
        exit;
    }
}

// Processar reenvio de token
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_resend_2fa'])) {
    header('Content-Type: application/json');
    try {
        if (!isset($_SESSION['2fa_pending_action']) || !isset($_SESSION['2fa_pending_user_id']) || $_SESSION['2fa_pending_user_id'] != $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'Sessão de 2FA não iniciada.']);
            exit;
        }

        $user_id = (int)$_SESSION['2fa_pending_user_id'];
        $type_2fa = isset($_SESSION['2fa_type']) ? $_SESSION['2fa_type'] : 'email';

        if ($type_2fa === 'totp') {
            echo json_encode(['success' => false, 'message' => 'Reenvio de token não disponível para 2FA TOTP.']);
            exit;
        }

        $code = generate2FACode();
        $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        $stmt = $pdo->prepare("DELETE FROM 2fa_codes WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $stmt = $pdo->prepare("INSERT INTO 2fa_codes (user_id, code, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $code, $expires_at]);

        $stmt = $pdo->prepare("SELECT username, email FROM usuarios WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user_data) {
            echo json_encode(['success' => false, 'message' => 'Usuário não encontrado.']);
            exit;
        }

        if (!$mail instanceof PHPMailer\PHPMailer\PHPMailer) {
            echo json_encode(['success' => false, 'message' => 'Erro na configuração do e-mail.']);
            exit;
        }

        $mail->clearAllRecipients();
        $mail->addAddress($user_data['email'], $user_data['username']);
        $mail->isHTML(true);
        $mail->Subject = 'Novo Token de Confirmação - FireNet Telecom';
        $mail->Body = "<h2>Olá, {$user_data['username']}!</h2><p>Você solicitou um novo token para habilitação do 2FA (Autenticação de Dois Fatores).</p><p>Seu novo token de confirmação é: <strong>$code</strong></p><p>Este token expira em 30 minutos.</p><p>Se você não solicitou este token, ignore este e-mail.</p><p>Atenciosamente,<br>Equipe FireNet Telecom</p>";
        $mail->AltBody = "Olá, {$user_data['username']}!\n\nVocê solicitou um novo token para habilitação do 2FA (Autenticação de Dois Fatores).\nSeu novo token de confirmação é: $code\nEste token expira em 30 minutos.\nSe você não solicitou este token, ignore este e-mail.\n\nAtenciosamente,\nEquipe FireNet Telecom";
        $mail->send();
        registrarLog($pdo, $user_id, "Reenviou token para habilitação de 2FA");
        echo json_encode(['success' => true, 'message' => 'Novo token enviado com sucesso! Verifique seu e-mail.']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao reenviar o token: ' . $mail->ErrorInfo]);
        exit;
    }
}

// Processar cancelamento da solicitação de 2FA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_cancel_2fa'])) {
    header('Content-Type: application/json');
    try {
        $stmt = $pdo->prepare("DELETE FROM 2fa_codes WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        unset($_SESSION['2fa_pending_action']);
        unset($_SESSION['2fa_pending_user_id']);
        unset($_SESSION['2fa_enable_state']);
        unset($_SESSION['2fa_type']);
        unset($_SESSION['2fa_temp_secret']);
        registrarLog($pdo, $_SESSION['user_id'], "Cancelou solicitação de habilitação de 2FA");
        echo json_encode(['success' => true, 'message' => 'Solicitação de 2FA cancelada.']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao cancelar a solicitação de 2FA: ' . $e->getMessage()]);
        exit;
    }
}

// Processar a confirmação do token para habilitação do 2FA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_2fa'])) {
    header('Content-Type: application/json');
    try {
        $token = trim($_POST['2fa_token']);
        if (!isset($_SESSION['2fa_pending_action']) || $_SESSION['2fa_pending_action'] !== 'enable_2fa' || !isset($_SESSION['2fa_pending_user_id']) || $_SESSION['2fa_pending_user_id'] != $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'Sessão de 2FA não iniciada.']);
            exit;
        }

        $user_id = (int)$_SESSION['2fa_pending_user_id'];
        $enable_2fa = (int)$_SESSION['2fa_enable_state'];
        $type_2fa = $_SESSION['2fa_type'];

        if (empty($token)) {
            echo json_encode(['success' => false, 'message' => 'Por favor, insira o token de confirmação.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT username FROM usuarios WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user_data) {
            echo json_encode(['success' => false, 'message' => 'Usuário não encontrado.']);
            exit;
        }

        $valid = false;
        if ($type_2fa === 'email') {
            $stmt = $pdo->prepare("SELECT * FROM 2fa_codes WHERE user_id = ? AND code = ? AND expires_at > NOW()");
            $stmt->execute([$user_id, $token]);
            $code_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $valid = !!$code_data;
        } elseif ($type_2fa === 'totp') {
            $secret = $_SESSION['2fa_temp_secret'] ?? '';
            if (empty($secret)) {
                echo json_encode(['success' => false, 'message' => 'Segredo TOTP não encontrado. Tente novamente.']);
                exit;
            }
            $totp = initializeTOTP($secret, $user_data['username']);
            $valid = $totp->verify($token, time(), 1);
        }

        if ($valid) {
            // Habilitar o 2FA
            $secret = ($type_2fa === 'totp') ? ($_SESSION['2fa_temp_secret'] ?? null) : null;
            $stmt = $pdo->prepare("UPDATE usuarios SET 2fa_enabled = ?, 2fa_type = ?, 2fa_secret = ? WHERE id = ?");
            $result = $stmt->execute([$enable_2fa, $type_2fa, $secret, $user_id]);
            if ($result) {
                $message = "Autenticação de dois fatores " . ($type_2fa === 'totp' ? "TOTP habilitada" : "por e-mail habilitada") . " com sucesso.";
                registrarLog($pdo, $user_id, "Habilitou o 2FA (" . $type_2fa . ")");
                echo json_encode(['success' => true, 'message' => $message]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao habilitar o 2FA.']);
            }
            $stmt = $pdo->prepare("DELETE FROM 2fa_codes WHERE user_id = ?");
            $stmt->execute([$user_id]);
            unset($_SESSION['2fa_pending_action']);
            unset($_SESSION['2fa_pending_user_id']);
            unset($_SESSION['2fa_enable_state']);
            unset($_SESSION['2fa_type']);
            unset($_SESSION['2fa_temp_secret']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Token ou código inválido ou expirado.']);
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao processar a solicitação: ' . $e->getMessage()]);
        exit;
    }
}

// Processar alteração de senha
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alterar_senha'])) {
    header('Content-Type: application/json');
    try {
        $senha_antiga = trim($_POST['senha_antiga']);
        $nova_senha = trim($_POST['nova_senha']);
        $confirmar_senha = trim($_POST['confirmar_senha']);

        $stmt = $pdo->prepare("SELECT password FROM usuarios WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user_data) {
            echo json_encode(['success' => false, 'message' => 'Usuário não encontrado.']);
            exit;
        }

        if (empty($senha_antiga)) {
            echo json_encode(['success' => false, 'message' => 'Por favor, insira sua senha antiga.']);
            exit;
        } elseif (!password_verify($senha_antiga, $user_data['password'])) {
            echo json_encode(['success' => false, 'message' => 'Senha antiga incorreta.']);
            exit;
        } elseif (empty($nova_senha)) {
            echo json_encode(['success' => false, 'message' => 'A nova senha não pode estar vazia.']);
            exit;
        } elseif (strlen($nova_senha) < 6) {
            echo json_encode(['success' => false, 'message' => 'A nova senha deve ter pelo menos 6 caracteres.']);
            exit;
        } elseif ($nova_senha !== $confirmar_senha) {
            echo json_encode(['success' => false, 'message' => 'A nova senha e a confirmação não coincidem.']);
            exit;
        }

        $hashed_senha = password_hash($nova_senha, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
        $result = $stmt->execute([$hashed_senha, $_SESSION['user_id']]);
        if ($result) {
            registrarLog($pdo, $_SESSION['user_id'], "Alterou a própria senha");
            echo json_encode(['success' => true, 'message' => 'Senha alterada com sucesso!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar a senha.']);
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao processar a solicitação: ' . $e->getMessage()]);
        exit;
    }
}

// Processar alteração de ID do Telegram
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alterar_telegram'])) {
    header('Content-Type: application/json');
    try {
        $telegram_id = trim($_POST['telegram_id']);
        $stmt = $pdo->prepare("SELECT username FROM usuarios WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user_data) {
            echo json_encode(['success' => false, 'message' => 'Usuário não encontrado.']);
            exit;
        }

        if (empty($telegram_id)) {
            $stmt = $pdo->prepare("DELETE FROM telegram_admins WHERE name = ?");
            $stmt->execute([$user_data['username']]);
            registrarLog($pdo, $_SESSION['user_id'], "Removeu o ID do Telegram");
            echo json_encode(['success' => true, 'message' => 'ID do Telegram removido com sucesso.']);
            exit;
        }

        if (!preg_match('/^-?\d+$/', $telegram_id)) {
            echo json_encode(['success' => false, 'message' => 'O ID do Telegram deve conter apenas números.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT name FROM telegram_admins WHERE chat_id = ? AND name != ?");
        $stmt->execute([$telegram_id, $user_data['username']]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode(['success' => false, 'message' => 'Este ID do Telegram já está associado a outro usuário.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT chat_id FROM telegram_admins WHERE name = ?");
        $stmt->execute([$user_data['username']]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            $stmt = $pdo->prepare("UPDATE telegram_admins SET chat_id = ? WHERE name = ?");
            $result = $stmt->execute([$telegram_id, $user_data['username']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO telegram_admins (chat_id, name) VALUES (?, ?)");
            $result = $stmt->execute([$telegram_id, $user_data['username']]);
        }

        if ($result) {
            registrarLog($pdo, $_SESSION['user_id'], "Atualizou o ID do Telegram para $telegram_id");
            echo json_encode(['success' => true, 'message' => 'ID do Telegram atualizado com sucesso.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar o ID do Telegram.']);
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao processar a solicitação: ' . $e->getMessage()]);
        exit;
    }
}

$error = '';
$success = '';
$show_confirm_2fa = false;

try {
    $stmt = $pdo->prepare("SELECT id, username, email, 2fa_enabled, 2fa_type, 2fa_secret, password, permissoes FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_data) {
        $error = "Usuário não encontrado.";
        header('Location: login.php');
        exit();
    }

    $is_admin = $user_data['permissoes'] === 'excluir';

    $stmt = $pdo->prepare("SELECT chat_id FROM telegram_admins WHERE name = ?");
    $stmt->execute([$user_data['username']]);
    $telegram_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_chat_id = $telegram_data['chat_id'] ?? '';

    //$stmt = $pdo->prepare("SELECT event, timestamp FROM access_logs WHERE user_id = ? ORDER BY timestamp DESC LIMIT 50");
    $stmt = $pdo->prepare("SELECT event, timestamp FROM access_logs WHERE user_id = ? ORDER BY timestamp DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Verificar se há uma solicitação de habilitação de 2FA pendente
    $show_confirm_2fa = (
        isset($_SESSION['2fa_pending_action']) &&
        $_SESSION['2fa_pending_action'] === 'enable_2fa' &&
        isset($_SESSION['2fa_pending_user_id']) &&
        $_SESSION['2fa_pending_user_id'] == $_SESSION['user_id'] &&
        isset($_SESSION['2fa_enable_state']) &&
        isset($_SESSION['2fa_type'])
    );

} catch (PDOException $e) {
    $error = "Erro na conexão com o banco de dados: " . $e->getMessage();
} catch (Exception $e) {
    $error = "Erro inesperado: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Meu Perfil - FireNet Telecom</title>
    <link rel="stylesheet" href="css/topo.css">
    <link rel="stylesheet" href="css/meu-perfil.css">
</head>
<body>
    <?php include 'topo.php'; ?>
    <div class="container">
        <div class="security-container">
            <h2>Meu Perfil</h2>
            <?php if (!empty($error)): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <p class="success"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>
            <?php if ($show_confirm_2fa): ?>
                <div class="modal" id="modal-confirm-2fa">
                    <div class="modal-content" style="width: 350px;">
                        <h3>Confirmar Habilitação de 2FA</h3>
                        <div id="confirm-2fa-error" class="modal-error" style="display: none;"></div>
                        <div id="confirm-2fa-success" class="modal-success" style="display: none;"></div>
                        <form method="POST" action="" id="confirm-2fa-form">
                            <input type="hidden" name="confirm_2fa" value="1">
                            <label for="2fa_token"><?php echo ($_SESSION['2fa_type'] === 'totp') ? 'Código do Aplicativo Autenticador' : 'Token de Confirmação'; ?></label>
                            <input type="text" id="2fa_token" name="2fa_token" required maxlength="6" placeholder="<?php echo ($_SESSION['2fa_type'] === 'totp') ? 'Digite o código do aplicativo' : 'Digite o token'; ?>">
                            <button type="submit" id="confirm-2fa-submit">Confirmar</button>
                            <?php if ($_SESSION['2fa_type'] === 'email'): ?>
                                <button type="button" class="resend-button" id="resend-2fa-button">Reenviar Token</button>
                            <?php endif; ?>
                        </form>
                        <form method="POST" action="" id="cancel-2fa-form">
                            <input type="hidden" name="ajax_cancel_2fa" value="1">
                            <button type="button" class="close-button" id="cancel-2fa-button">Cancelar</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            <div class="security-section">
                <h3>Perfil do Usuário</h3>
                <p><strong>Usuário:</strong> <?php echo htmlspecialchars($user_data['username']); ?></p>
                <p><strong>E-mail:</strong> <?php echo htmlspecialchars($user_data['email']); ?></p>
                <p><strong>Perfil:</strong> <?php echo $is_admin ? 'Administrador' : 'Usuário'; ?></p>
                <p><strong>2FA:</strong> <?php echo $user_data['2fa_enabled'] ? 'Habilitado (' . ($user_data['2fa_type'] === 'totp' ? 'TOTP' : 'E-mail') . ')' : 'Desabilitado'; ?></p>
                <p><strong>ID do Telegram:</strong> <?php echo $current_chat_id ? htmlspecialchars($current_chat_id) : 'Não configurado'; ?></p>
            </div>
            <div class="security-section">
                <h3>Ações</h3>
                <div class="action-buttons">
                    <button class="action-button" id="open-password-modal">Alterar Senha</button>
                    <button class="action-button" id="open-2fa-modal">Alterar 2FA</button>
                    <button class="action-button" id="open-telegram-modal">Gerenciar Telegram ID</button>
                    <button class="action-button" id="open-logs-modal">Ver Logs</button>
                </div>
            </div>
            <a href="clientes.php" class="back-button">Voltar</a>
        </div>
    </div>
    <div class="modal" id="modal-password">
        <div class="modal-content" style="width: 350px;">
            <h3>Alterar Senha</h3>
            <div id="password-error" class="modal-error" style="display: none;"></div>
            <div id="password-success" class="modal-success" style="display: none;"></div>
            <form method="POST" action="" id="password-form">
                <input type="hidden" name="alterar_senha" value="1">
                <label for="senha_antiga">Senha Antiga</label>
                <div class="password-container">
                    <input type="password" id="senha_antiga" name="senha_antiga" required>
                    <span class="toggle-password" data-target="senha_antiga">👁️</span>
                </div>
                <p id="senha_antiga-error" class="form-error"></p>
                <label for="nova_senha">Nova Senha</label>
                <div class="password-container">
                    <input type="password" id="nova_senha" name="nova_senha" required>
                    <span class="toggle-password" data-target="nova_senha">👁️</span>
                </div>
                <p id="nova_senha-error" class="form-error"></p>
                <label for="confirmar_senha">Confirmar Nova Senha</label>
                <div class="password-container">
                    <input type="password" id="confirmar_senha" name="confirmar_senha" required>
                    <span class="toggle-password" data-target="confirmar_senha">👁️</span>
                </div>
                <p id="confirmar_senha-error" class="form-error"></p>
                <button type="submit" id="password-submit">Alterar</button>
                <button type="button" class="close-button" id="close-password-modal">Fechar</button>
            </form>
        </div>
    </div>
    <div class="modal" id="modal-2fa">
        <div class="modal-content" style="width: 350px;">
            <div id="2fa-selection">
                <h3>Configurar 2FA</h3>
                <div id="2fa-error" class="modal-error" style="display: none;"></div>
                <div id="2fa-success" class="modal-success" style="display: none;"></div>
                <form id="2fa-form">
                    <label>
                        <input type="checkbox" id="2fa_enabled" name="2fa_enabled" <?php echo $user_data['2fa_enabled'] ? 'checked' : ''; ?>>
                        Habilitar Autenticação de Dois Fatores
                    </label>
                    <div id="2fa-type-options" style="<?php echo $user_data['2fa_enabled'] ? '' : 'display: none;'; ?>">
                        <label>
                            <input type="radio" name="2fa_type" value="email" <?php echo $user_data['2fa_type'] === 'email' || !$user_data['2fa_enabled'] ? 'checked' : ''; ?>>
                            2FA por E-mail
                        </label>
                        <label>
                            <input type="radio" name="2fa_type" value="totp" <?php echo $user_data['2fa_type'] === 'totp' ? 'checked' : ''; ?>>
                            2FA por Aplicativo (TOTP)
                        </label>
                    </div>
                    <button type="submit" id="2fa-submit">Salvar</button>
                    <button type="button" class="close-button" id="close-2fa-modal">Fechar</button>
                </form>
            </div>
            <div id="2fa-qr-section" style="display: none;">
                <h3 class="qr-section-title">Configurar 2FA</h3>
                <div id="2fa-qr-error" class="modal-error" style="display: none;"></div>
                <div id="2fa-qr-success" class="modal-success" style="display: none;"></div>
                <p>Escaneie o QR code abaixo com seu aplicativo autenticador (como Google Authenticator) ou insira a chave abaixo e clique em "Prosseguir" para continuar.</p>
                <img src="" alt="QR Code" class="qr-code" id="qr-code-image">
                <div class="secret-key-container">
                    <label for="secret-key">Chave de Configuração:</label>
                    <div class="secret-key-wrapper">
                        <input type="text" id="secret-key" readonly>
                        <button type="button" id="copy-secret-button">Copiar</button>
                    </div>
                </div>
                <div class="button-container">
                    <button type="button" id="proceed-totp-button">Prosseguir</button>
                    <button type="button" id="cancel-totp-button">Cancelar</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal" id="modal-telegram">
        <div class="modal-content" style="width: 350px;">
            <h3>Alterar ID do Telegram</h3>
            <div id="telegram-error" class="modal-error" style="display: none;"></div>
            <div id="telegram-success" class="modal-success" style="display: none;"></div>
            <form method="POST" action="" id="telegram-form">
                <input type="hidden" name="alterar_telegram" value="1">
                <label for="telegram_id">ID do Telegram</label>
                <input type="text" id="telegram_id" name="telegram_id" value="<?php echo htmlspecialchars($current_chat_id); ?>" placeholder="Digite o ID do Telegram">
                <p style="font-size: 0.85em; color: #666;">Deixe em branco para remover o ID do Telegram.</p>
                <button type="submit" id="telegram-submit">Salvar</button>
                <button type="button" class="close-button" id="close-telegram-modal">Fechar</button>
            </form>
        </div>
    </div>
    <div class="modal" id="modal-logs">
        <div class="modal-content" style="width: 600px;">
            <h3>Logs de Acesso</h3>
            <div class="logs-table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Evento</th>
                            <th>Data e Hora</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['event']); ?></td>
                                <td><?php echo date('d/m/Y H:i:s', strtotime($log['timestamp'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="2">Nenhum log de acesso encontrado.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <button type="button" class="close-button" id="close-logs-modal">Fechar</button>
        </div>
    </div>
    <script src="js/topo.js"></script>
    <script src="js/meu-perfil.js"></script>
</body>
</html>
