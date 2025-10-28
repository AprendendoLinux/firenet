<?php
// configuracoes.php

// Configuração de depuração: defina como true para ativar os logs, false para desativar
$DEBUG_LOGS = false;

// Inicia o buffer de saída para evitar saídas acidentais
ob_start();

require_once 'config.php';

// Inicia a sessão apenas se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica permissões do usuário
$permissoes = 'visualizar'; // Valor padrão
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT permissoes FROM usuarios WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $permissoes = $user['permissoes'] ?? 'visualizar';
    } catch (PDOException $e) {
        if ($DEBUG_LOGS) {
            error_log("Erro ao buscar permissões: " . $e->getMessage());
        }
        $_SESSION['error'] = "Erro ao verificar permissões.";
        header('Location: clientes.php');
        exit();
    }
}

// Verifica se o usuário tem permissão de administrador
if ($permissoes !== 'excluir') {
    $_SESSION['error'] = "Acesso restrito a administradores.";
    header('Location: clientes.php');
    exit();
}

$error = '';
$success = '';
$success_class = 'success'; // Classe padrão para mensagens de sucesso
$test_result = '';
$test_result_class = ''; // Classe para mensagens de teste
$smtp_message = '';
$smtp_message_class = '';

// Verifica se há mensagens do SMTP armazenadas na sessão
if (isset($_SESSION['smtp_message'])) {
    $smtp_message = $_SESSION['smtp_message'];
    $smtp_message_class = $_SESSION['smtp_message_class'] ?? 'success';
    // Limpa as mensagens da sessão após exibir
    unset($_SESSION['smtp_message']);
    unset($_SESSION['smtp_message_class']);
}

// Verifica se há mensagens de teste de e-mail na sessão
if (isset($_SESSION['test_result'])) {
    $test_result = $_SESSION['test_result'];
    $test_result_class = $_SESSION['test_result_class'] ?? 'test-success';
    // Limpa as mensagens da sessão após exibir
    unset($_SESSION['test_result']);
    unset($_SESSION['test_result_class']);
}

// Carrega configurações atuais
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    if ($DEBUG_LOGS) {
        error_log("Erro ao carregar configurações: " . $e->getMessage());
    }
    $error = "Erro ao carregar configurações: " . $e->getMessage();
}

// Processa requisições AJAX para validação SMTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_smtp_validate'])) {
    // Limpa qualquer saída acidental antes de enviar o JSON
    ob_end_clean();
    header('Content-Type: application/json');
    try {
        $smtp_host = trim($_POST['smtp_host'] ?? '');
        $smtp_username = trim($_POST['smtp_username'] ?? '');
        $smtp_password = trim($_POST['smtp_password'] ?? '');
        $smtp_port = trim($_POST['smtp_port'] ?? '');
        $smtp_encryption = trim($_POST['smtp_encryption'] ?? '');
        $smtp_auth = isset($_POST['smtp_auth']) ? '1' : '0';
        $smtp_from_email = trim($_POST['smtp_from_email'] ?? '');
        $smtp_from_name = trim($_POST['smtp_from_name'] ?? '');
        $smtp_reply_to = trim($_POST['smtp_reply_to'] ?? '');

        // Valida formato de e-mail para smtp_reply_to, se preenchido
        if (!empty($smtp_reply_to) && !filter_var($smtp_reply_to, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'O e-mail para respostas (Reply-To) é inválido.']);
            exit;
        }

        // Extrai o domínio do e-mail de remetente para HELO
        $helo_domain = '';
        if (!empty($smtp_from_email) && filter_var($smtp_from_email, FILTER_VALIDATE_EMAIL)) {
            $helo_domain = explode('@', $smtp_from_email)[1];
        }

        // Verifica se os campos obrigatórios estão preenchidos
        $required_fields = !empty($smtp_host) && !empty($smtp_port) && !empty($smtp_from_email) && !empty($smtp_from_name);

        // Prepara as atualizações
        $updates = [];
        $log_event = "Configurações do sistema atualizadas: ";
        if ($smtp_host !== ($settings['smtp_host'] ?? '')) {
            $updates['smtp_host'] = $smtp_host;
            $log_event .= "SMTP Host, ";
        }
        if ($smtp_username !== ($settings['smtp_username'] ?? '')) {
            $updates['smtp_username'] = $smtp_username;
            $log_event .= "SMTP Username, ";
        }
        if ($smtp_password !== '') {
            $updates['smtp_password'] = $smtp_password;
            $log_event .= "SMTP Password, ";
        }
        if ($smtp_port !== ($settings['smtp_port'] ?? '')) {
            $updates['smtp_port'] = $smtp_port;
            $log_event .= "SMTP Port, ";
        }
        if ($smtp_encryption !== ($settings['smtp_encryption'] ?? '')) {
            $updates['smtp_encryption'] = $smtp_encryption;
            $log_event .= "SMTP Encryption, ";
        }
        if ($smtp_auth !== ($settings['smtp_auth'] ?? '0')) {
            $updates['smtp_auth'] = $smtp_auth;
            $log_event .= "SMTP Auth, ";
        }
        if ($smtp_from_email !== ($settings['smtp_from_email'] ?? '')) {
            $updates['smtp_from_email'] = $smtp_from_email;
            $log_event .= "SMTP From Email, ";
        }
        if ($smtp_from_name !== ($settings['smtp_from_name'] ?? '')) {
            $updates['smtp_from_name'] = $smtp_from_name;
            $log_event .= "SMTP From Name, ";
        }
        if ($smtp_reply_to !== ($settings['smtp_reply_to'] ?? '')) {
            $updates['smtp_reply_to'] = $smtp_reply_to;
            $log_event .= "SMTP Reply-To, ";
        }

        // Salva as configurações no banco
        if (!empty($updates)) {
            foreach ($updates as $key => $value) {
                $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->execute([$value, $key]);
            }
            registrarLog($pdo, $_SESSION['user_id'], rtrim($log_event, ", "));
        }

        // Se autenticação SMTP estiver desabilitada, não valida e retorna sucesso
        if ($smtp_auth == '0') {
            $_SESSION['smtp_message'] = 'Configurações salvas com sucesso.';
            $_SESSION['smtp_message_class'] = 'success';
            echo json_encode(['success' => true, 'message' => 'Configurações salvas com sucesso.']);
            exit;
        }

        // Se autenticação SMTP está habilitada, valida a conexão e autenticação
        if (!$required_fields) {
            $_SESSION['smtp_message'] = 'Erro: Campos obrigatórios ausentes (Host, Porta, E-mail do Remetente, Nome do Remetente).';
            $_SESSION['smtp_message_class'] = 'error';
            echo json_encode(['success' => false, 'message' => 'Erro: Campos obrigatórios ausentes (Host, Porta, E-mail do Remetente, Nome do Remetente).']);
            exit;
        }

        if (empty($smtp_username)) {
            $_SESSION['smtp_message'] = 'Erro: O campo Usuário SMTP é obrigatório quando a autenticação SMTP está habilitada.';
            $_SESSION['smtp_message_class'] = 'error';
            echo json_encode(['success' => false, 'message' => 'Erro: O campo Usuário SMTP é obrigatório quando a autenticação SMTP está habilitada.']);
            exit;
        }

        $validation_password = $smtp_password ?: ($settings['smtp_password'] ?? '');
        if (empty($validation_password)) {
            $_SESSION['smtp_message'] = 'Erro: A senha SMTP está ausente e não há senha salva para validação.';
            $_SESSION['smtp_message_class'] = 'error';
            echo json_encode(['success' => false, 'message' => 'Erro: A senha SMTP está ausente e não há senha salva para validação.']);
            exit;
        }

        // Função para tentar conexão com retry
        $maxRetries = 3;
        $retryDelay = 5; // segundos
        $connected = false;

        for ($retry = 0; $retry < $maxRetries && !$connected; $retry++) {
            global $mail;
            $test_mail = clone $mail;
            $test_mail->Timeout = 15; // Aumentado para 15 segundos
            $test_mail->Host = $smtp_host;
            $test_mail->SMTPAuth = true;
            $test_mail->Username = $smtp_username;
            $test_mail->Password = $validation_password;
            $test_mail->SMTPSecure = $smtp_encryption ?: 'tls';
            $test_mail->Port = (int) $smtp_port;
            if (!empty($helo_domain)) {
                $test_mail->Helo = $helo_domain; // Usa o domínio do e-mail de remetente
            }
            $test_mail->SMTPDebug = 2;
            $test_mail->Debugoutput = function($str, $level) {
                error_log("SMTP Debug [$level]: $str");
            };

            if ($test_mail->smtpConnect()) {
                $connected = true;
                $test_mail->smtpClose();
                break;
            }

            if ($retry < $maxRetries - 1) {
                sleep($retryDelay); // Aguarda antes do próximo retry
            }
        }

        if (!$connected) {
            throw new Exception("Falha na conexão após $maxRetries tentativas. Erro 421-4.7.0 indica possível limitação temporária ou IP não autorizado no Google Workspace. Consulte: https://support.google.com/a/answer/3221692");
        }

        $_SESSION['smtp_message'] = 'Configurações atualizadas com sucesso! Autenticação SMTP validada após ' . ($retry + 1) . ' tentativa(s).';
        $_SESSION['smtp_message_class'] = 'success';
        echo json_encode(['success' => true, 'message' => 'Configurações atualizadas com sucesso! Autenticação SMTP validada.']);
        exit;
    } catch (Exception $e) {
        if ($DEBUG_LOGS) {
            error_log("Erro na validação SMTP: " . $e->getMessage());
        }
        $_SESSION['smtp_message'] = "Erro: " . $e->getMessage();
        $_SESSION['smtp_message_class'] = 'error';
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Processa requisições AJAX para validação reCAPTCHA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_recaptcha_validate'])) {
    // Limpa qualquer saída acidental antes de enviar o JSON
    ob_end_clean();
    header('Content-Type: application/json');
    try {
        $recaptcha_enabled = isset($_POST['recaptcha_enabled']) ? '1' : '0';
        $recaptcha_site_key = trim($_POST['recaptcha_site_key'] ?? '');
        $recaptcha_secret_key = trim($_POST['recaptcha_secret_key'] ?? '');
        $recaptcha_response = trim($_POST['g-recaptcha-response'] ?? '');

        // Validação inicial
        if ($recaptcha_enabled == '1') {
            if (empty($recaptcha_site_key) || empty($recaptcha_secret_key)) {
                echo json_encode(['success' => false, 'message' => 'Site Key e Secret Key são obrigatórios quando o reCAPTCHA está habilitado.']);
                exit;
            }
            if (empty($recaptcha_response)) {
                echo json_encode(['success' => false, 'message' => 'Por favor, marque o checkbox do reCAPTCHA para validar as credenciais.']);
                exit;
            }

            // Valida o token com a API do Google reCAPTCHA
            $url = 'https://www.google.com/recaptcha/api/siteverify';
            $data = [
                'secret' => $recaptcha_secret_key,
                'response' => $recaptcha_response,
                'remoteip' => $_SERVER['REMOTE_ADDR']
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 6); // Timeout de 6 segundos
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($response === false || !empty($curl_error)) {
                if ($DEBUG_LOGS) {
                    error_log("Erro ao conectar à API do reCAPTCHA: " . $curl_error);
                }
                echo json_encode(['success' => false, 'message' => 'Erro ao conectar à API do reCAPTCHA: ' . $curl_error]);
                exit;
            }

            if ($http_code !== 200) {
                if ($DEBUG_LOGS) {
                    error_log("Erro HTTP $http_code ao conectar à API do reCAPTCHA");
                }
                echo json_encode(['success' => false, 'message' => "Erro HTTP $http_code ao conectar à API do reCAPTCHA."]);
                exit;
            }

            $result = json_decode($response, true);
            if (!$result) {
                if ($DEBUG_LOGS) {
                    error_log("Erro ao processar a resposta da API do reCAPTCHA: Resposta inválida");
                }
                echo json_encode(['success' => false, 'message' => 'Erro ao processar a resposta da API do reCAPTCHA.']);
                exit;
            }

            // Log da resposta para depuração
            if ($DEBUG_LOGS) {
                error_log("Resposta da API reCAPTCHA: " . json_encode($result));
            }

            // Verifica se a validação foi bem-sucedida
            if (!$result['success']) {
                $error_codes = isset($result['error-codes']) ? implode(', ', $result['error-codes']) : 'Erro desconhecido';
                if (in_array('invalid-input-secret', $result['error-codes']) || in_array('missing-input-secret', $result['error-codes'])) {
                    echo json_encode(['success' => false, 'message' => 'A Secret Key do reCAPTCHA é inválida. Verifique as credenciais.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Falha na validação do reCAPTCHA: ' . $error_codes]);
                }
                exit;
            }
        }

        // Se chegou aqui, as configurações são válidas ou reCAPTCHA está desabilitado
        $updates = [];
        $log_event = "Configurações do sistema atualizadas: ";
        if ($recaptcha_enabled !== ($settings['recaptcha_enabled'] ?? '0')) {
            $updates['recaptcha_enabled'] = $recaptcha_enabled;
            $log_event .= "reCAPTCHA Enabled, ";
        }
        if ($recaptcha_site_key !== ($settings['recaptcha_site_key'] ?? '')) {
            $updates['recaptcha_site_key'] = $recaptcha_site_key;
            $log_event .= "reCAPTCHA Site Key, ";
        }
        if ($recaptcha_secret_key !== ($settings['recaptcha_secret_key'] ?? '')) {
            $updates['recaptcha_secret_key'] = $recaptcha_secret_key;
            $log_event .= "reCAPTCHA Secret Key, ";
        }

        if (!empty($updates)) {
            foreach ($updates as $key => $value) {
                $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->execute([$value, $key]);
            }
            registrarLog($pdo, $_SESSION['user_id'], rtrim($log_event, ", "));
        }

        echo json_encode(['success' => true, 'message' => 'Configurações do reCAPTCHA atualizadas com sucesso!']);
        exit;
    } catch (Exception $e) {
        if ($DEBUG_LOGS) {
            error_log("Erro na validação reCAPTCHA: " . $e->getMessage());
        }
        echo json_encode(['success' => false, 'message' => 'Erro ao validar configurações do reCAPTCHA: ' . $e->getMessage()]);
        exit;
    }
}

// Processa requisições AJAX para configurações Telegram
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_telegram']) && isset($_POST['ajax_telegram_save'])) {
    // Adiciona log para depuração
    if ($DEBUG_LOGS) {
        error_log("Bloco AJAX do Telegram foi executado. POST: " . json_encode($_POST));
    }
    // Limpa qualquer saída acidental antes de enviar o JSON
    ob_end_clean();
    header('Content-Type: application/json');
    try {
        $telegram_bot_token = trim($_POST['telegram_bot_token'] ?? '');
        $updates = [];
        $log_event = "Configurações do sistema atualizadas: ";

        if ($telegram_bot_token !== ($settings['telegram_bot_token'] ?? '')) {
            $updates['telegram_bot_token'] = $telegram_bot_token;
            $log_event .= "Telegram Bot Token, ";
        }

        if (!empty($updates)) {
            foreach ($updates as $key => $value) {
                $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->execute([$value, $key]);
            }
            registrarLog($pdo, $_SESSION['user_id'], rtrim($log_event, ", "));
            echo json_encode(['success' => true, 'message' => 'Configurações do Telegram atualizadas com sucesso!']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Nenhuma alteração foi feita nas configurações do Telegram.', 'warning' => true]);
        }
        exit;
    } catch (Exception $e) {
        if ($DEBUG_LOGS) {
            error_log("Erro ao atualizar configurações do Telegram: " . $e->getMessage());
        }
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar configurações do Telegram: ' . $e->getMessage()]);
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Adiciona log para depuração se a condição do Telegram não for atendida
    if ($DEBUG_LOGS) {
        error_log("Bloco AJAX do Telegram NÃO foi executado. POST: " . json_encode($_POST));
    }
}

// Processa requisições AJAX para configurações Google Maps
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_maps']) && isset($_POST['ajax_maps_save'])) {
    // Adiciona log para depuração
    if ($DEBUG_LOGS) {
        error_log("Bloco AJAX do Google Maps foi executado. POST: " . json_encode($_POST));
    }
    // Limpa qualquer saída acidental antes de enviar o JSON
    ob_end_clean();
    header('Content-Type: application/json');
    try {
        $google_maps_api_key = trim($_POST['google_maps_api_key'] ?? '');
        $updates = [];
        $log_event = "Configurações do sistema atualizadas: ";

        if ($google_maps_api_key !== ($settings['google_maps_api_key'] ?? '')) {
            $updates['google_maps_api_key'] = $google_maps_api_key;
            $log_event .= "Google Maps API Key, ";
        }

        if (!empty($updates)) {
            foreach ($updates as $key => $value) {
                $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->execute([$value, $key]);
            }
            registrarLog($pdo, $_SESSION['user_id'], rtrim($log_event, ", "));
            echo json_encode(['success' => true, 'message' => 'Configurações do Google Maps atualizadas com sucesso!']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Nenhuma alteração foi feita nas configurações do Google Maps.', 'warning' => true]);
        }
        exit;
    } catch (Exception $e) {
        if ($DEBUG_LOGS) {
            error_log("Erro ao atualizar configurações do Google Maps: " . $e->getMessage());
        }
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar configurações do Google Maps: ' . $e->getMessage()]);
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Adiciona log para depuração se a condição do Google Maps não for atendida
    if ($DEBUG_LOGS) {
        error_log("Bloco AJAX do Google Maps NÃO foi executado. POST: " . json_encode($_POST));
    }
}

// Processa requisições AJAX para configurações do Pop-up
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_popup']) && isset($_POST['ajax_popup_save'])) {
    // Adiciona log para depuração
    if ($DEBUG_LOGS) {
        error_log("Bloco AJAX do Pop-up foi executado. POST: " . json_encode($_POST));
    }
    // Limpa qualquer saída acidental antes de enviar o JSON
    ob_end_clean();
    header('Content-Type: application/json');
    try {
        $popup_message = trim($_POST['popup_message'] ?? '');
        if (empty($popup_message)) {
            echo json_encode(['success' => false, 'message' => 'O texto do pop-up não pode estar vazio.']);
            exit;
        }

        $updates = [];
        $log_event = "Configurações do sistema atualizadas: ";
        if ($popup_message !== ($settings['signup_popup_message'] ?? '')) {
            $updates['signup_popup_message'] = $popup_message;
            $log_event .= "Texto do Pop-up, ";
        }

        if (!empty($updates)) {
            foreach ($updates as $key => $value) {
                $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->execute([$value, $key]);
            }
            registrarLog($pdo, $_SESSION['user_id'], rtrim($log_event, ", "));
            echo json_encode(['success' => true, 'message' => 'Texto do pop-up atualizado com sucesso!']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Nenhuma alteração foi feita no texto do pop-up.', 'warning' => true]);
        }
        exit;
    } catch (Exception $e) {
        if ($DEBUG_LOGS) {
            error_log("Erro ao atualizar texto do pop-up: " . $e->getMessage());
        }
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar texto do pop-up: ' . $e->getMessage()]);
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Adiciona log para depuração se a condição do Pop-up não for atendida
    if ($DEBUG_LOGS) {
        error_log("Bloco AJAX do Pop-up NÃO foi executado. POST: " . json_encode($_POST));
    }
}

// Processa o formulário de configurações (outras seções) - Mantido para compatibilidade com envios não-AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_smtp_validate']) && !isset($_POST['ajax_recaptcha_validate']) && !isset($_POST['ajax_telegram_save']) && !isset($_POST['ajax_maps_save']) && !isset($_POST['ajax_popup_save'])) {
    try {
        $updates = [];
        $log_event = "Configurações do sistema atualizadas: ";

        // Processa configurações Telegram
        if (isset($_POST['salvar_telegram'])) {
            $telegram_bot_token = trim($_POST['telegram_bot_token'] ?? '');
            if ($telegram_bot_token !== ($settings['telegram_bot_token'] ?? '')) {
                $updates['telegram_bot_token'] = $telegram_bot_token;
                $log_event .= "Telegram Bot Token, ";
            }
        }

        // Processa configurações Google Maps
        if (isset($_POST['salvar_maps'])) {
            $google_maps_api_key = trim($_POST['google_maps_api_key'] ?? '');
            if ($google_maps_api_key !== ($settings['google_maps_api_key'] ?? '')) {
                $updates['google_maps_api_key'] = $google_maps_api_key;
                $log_event .= "Google Maps API Key, ";
            }
        }

        // Processa configurações do Pop-up
        if (isset($_POST['salvar_popup'])) {
            $popup_message = trim($_POST['popup_message'] ?? '');
            if ($popup_message !== ($settings['signup_popup_message'] ?? '')) {
                $updates['signup_popup_message'] = $popup_message;
                $log_event .= "Texto do Pop-up, ";
            }
        }

        // Atualiza o banco
        if (!empty($updates) && empty($error)) {
            foreach ($updates as $key => $value) {
                $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->execute([$value, $key]);
            }
            $success = "Configurações atualizadas com sucesso!";
            registrarLog($pdo, $_SESSION['user_id'], rtrim($log_event, ", "));
            // Recarrega configurações
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } elseif (empty($updates) && empty($error)) {
            $success = "Nenhuma alteração foi feita.";
            $success_class = 'warning'; // Usa a classe warning para mensagem amarela
        }
    } catch (Exception $e) {
        if ($DEBUG_LOGS) {
            error_log("Erro ao atualizar configurações: " . $e->getMessage());
        }
        $error = "Erro ao atualizar configurações: " . $e->getMessage();
    }

    // Processa o teste de e-mail
    if (isset($_POST['test_email_submit'])) {
        $test_email = trim($_POST['test_email'] ?? '');
        if (empty($test_email)) {
            $_SESSION['test_result'] = "Por favor, insira um e-mail para teste.";
            $_SESSION['test_result_class'] = 'warning';
            header('Location: configuracoes.php');
            exit;
        } elseif (!filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['test_result'] = "E-mail de teste inválido.";
            $_SESSION['test_result_class'] = 'test-error';
            header('Location: configuracoes.php');
            exit;
        } else {
            // Verifica se os campos obrigatórios estão preenchidos
            $smtp_auth = ($settings['smtp_auth'] ?? '1') == '1';
            $required_fields = !empty($settings['smtp_host']) && !empty($settings['smtp_port']) && 
                            !empty($settings['smtp_from_email']) && !empty($settings['smtp_from_name']);

            if (!$required_fields) {
                $_SESSION['test_result'] = "Erro: Uma ou mais configurações SMTP estão ausentes (Host, Porta, E-mail do Remetente, Nome do Remetente). Verifique as configurações.";
                $_SESSION['test_result_class'] = 'test-error';
                header('Location: configuracoes.php');
                exit;
            }

            global $mail;
            try {
                $test_mail = clone $mail;
                $test_mail->SMTPDebug = 3; // Nível máximo de depuração
                $test_mail->Debugoutput = function($str, $level) use (&$test_result) {
                    $test_result .= "Debug [$level]: $str\n";
                    error_log("Test Email Debug [$level]: $str"); // Log detalhado
                };
                $test_mail->Timeout = 15; // Aumentado para 15 segundos
                $test_mail->Host = $settings['smtp_host'];
                $test_mail->Port = $settings['smtp_port'];

                // Extrai o domínio do e-mail de remetente para HELO
                $helo_domain = '';
                if (!empty($settings['smtp_from_email']) && filter_var($settings['smtp_from_email'], FILTER_VALIDATE_EMAIL)) {
                    $helo_domain = explode('@', $settings['smtp_from_email'])[1];
                }
                if (!empty($helo_domain)) {
                    $test_mail->Helo = $helo_domain; // Usa o domínio do e-mail de remetente
                }

                // Configuração condicional de autenticação e SSL
                if ($smtp_auth) {
                    $test_mail->SMTPAuth = true;
                    $test_mail->Username = $settings['smtp_username'] ?? '';
                    $test_mail->Password = $settings['smtp_password'] ?? '';
                    $test_mail->SMTPSecure = $settings['smtp_encryption'] ?? 'tls';
                    $test_mail->SMTPOptions = [
                        'ssl' => [
                            'verify_peer' => true,
                            'verify_peer_name' => true,
                            'allow_self_signed' => false
                        ]
                    ];
                } else {
                    $test_mail->SMTPAuth = false; // Desativa autenticação
                    $test_mail->SMTPSecure = ''; // Desativa SSL/TLS
                    $test_mail->SMTPAutoTLS = false; // Garante que TLS não seja tentado automaticamente
                    $test_mail->SMTPOptions = []; // Remove opções de SSL
                }

                $test_mail->clearAllRecipients();
                $test_mail->clearReplyTos();
                $test_mail->setFrom($settings['smtp_from_email'], $settings['smtp_from_name']);
                if (!empty($settings['smtp_reply_to'])) {
                    $test_mail->addReplyTo($settings['smtp_reply_to']);
                }
                $test_mail->addAddress($test_email);
                $test_mail->Subject = 'Teste de Configuração SMTP - FireNet Telecom';
                $test_mail->Body = 'Este é um e-mail de teste enviado para verificar as configurações SMTP do sistema FireNet Telecom.';
                $test_mail->isHTML(false); // Garante texto simples

                // Tenta enviar o e-mail
                if (!$test_mail->send()) {
                    throw new Exception("Falha ao enviar e-mail: " . $test_mail->ErrorInfo);
                }

                $_SESSION['test_result'] = "E-mail de teste enviado com sucesso para $test_email!";
                $_SESSION['test_result_class'] = 'test-success';
                registrarLog($pdo, $_SESSION['user_id'], "E-mail de teste SMTP enviado para $test_email");
                header('Location: configuracoes.php');
                exit;
            } catch (Exception $e) {
                if ($DEBUG_LOGS) {
                    error_log("Erro ao enviar e-mail de teste: " . $e->getMessage());
                }
                $_SESSION['test_result'] = "Erro ao enviar e-mail de teste: " . $e->getMessage() . "\n" . ($test_result ?? '');
                $_SESSION['test_result_class'] = 'test-error';
                header('Location: configuracoes.php');
                exit;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - FireNet Telecom</title>
    <link rel="stylesheet" href="/css/configuracoes.css?v=1">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body>
    <?php include 'topo.php'; ?>

    <div class="container">
        <div class="settings-container">
            <h2>Configurações do Sistema</h2>
            <div id="success-message" class="success" style="display: <?php echo !empty($success) ? 'block' : 'none'; ?>;">
                <?php echo htmlspecialchars($success); ?>
            </div>
            <div id="error-message" class="error" style="display: <?php echo !empty($error) ? 'block' : 'none'; ?>;">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <div id="smtp-message" class="<?php echo htmlspecialchars($smtp_message_class); ?>" style="display: <?php echo !empty($smtp_message) ? 'block' : 'none'; ?>;">
                <?php echo htmlspecialchars($smtp_message); ?>
            </div>
            <div id="test-result-message" class="<?php echo htmlspecialchars($test_result_class); ?>" style="display: <?php echo !empty($test_result) ? 'block' : 'none'; ?>;">
                <?php echo nl2br(htmlspecialchars($test_result)); ?>
            </div>

            <button class="action-button" onclick="openModal('smtp-modal')">Configurar SMTP</button>
            <button class="action-button" onclick="openModal('telegram-modal')">Configurar Telegram</button>
            <button class="action-button" onclick="openModal('recaptcha-modal')">Configurar reCAPTCHA</button>
            <button class="action-button" onclick="openModal('maps-modal')">Configurar Maps API</button>
            <button class="action-button" onclick="openModal('popup-modal')">Configurar Texto do Pop-up</button>

            <!-- Modal SMTP -->
            <div id="smtp-modal" class="modal">
                <div class="modal-content">
                    <h3>Configurações SMTP</h3>
                    <div id="smtp-error" class="modal-error" style="display: none;"></div>
                    <form id="smtp-form" method="POST" action="">
                        <input type="email" name="smtp_from_email" placeholder="E-mail do Remetente" value="<?php echo htmlspecialchars($settings['smtp_from_email'] ?? ''); ?>">
                        <input type="text" name="smtp_from_name" placeholder="Nome do Remetente" value="<?php echo htmlspecialchars($settings['smtp_from_name'] ?? ''); ?>">
                        <input type="email" name="smtp_reply_to" placeholder="E-mail para Respostas (opcional)" value="<?php echo htmlspecialchars($settings['smtp_reply_to'] ?? ''); ?>">
                        <input type="text" name="smtp_host" placeholder="Host SMTP" value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>">
                        <input type="text" name="smtp_username" placeholder="Usuário SMTP" value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>">
                        <input type="password" name="smtp_password" placeholder="Senha SMTP (deixe em branco para manter)">
                        <input type="number" name="smtp_port" placeholder="Porta SMTP" value="<?php echo htmlspecialchars($settings['smtp_port'] ?? ''); ?>">
                        <select name="smtp_encryption">
                            <option value="" <?php echo empty($settings['smtp_encryption']) ? 'selected' : ''; ?>>Nenhuma</option>
                            <option value="ssl" <?php echo ($settings['smtp_encryption'] ?? '') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                            <option value="tls" <?php echo ($settings['smtp_encryption'] ?? '') == 'tls' ? 'selected' : ''; ?>>TLS</option>
                        </select>
                        <div class="checkbox-container">
                            <input type="checkbox" id="smtp_auth" name="smtp_auth" <?php echo ($settings['smtp_auth'] ?? '0') == '1' ? 'checked' : ''; ?>>
                            <label for="smtp_auth" class="checkbox-label">Habilitar Autenticação SMTP</label>
                        </div>
                        <div class="test-form">
                            <input type="email" name="test_email" placeholder="Digite um e-mail para teste">
                            <button type="submit" name="test_email_submit" value="1">Enviar E-mail de Teste</button>
                        </div>
                        <button type="submit" name="salvar_smtp" id="save-smtp-btn">Salvar Alterações</button>
                        <button type="button" class="close-button" onclick="validateAndCloseModal('smtp-modal')">Cancelar</button>
                    </form>
                </div>
            </div>

            <!-- Modal Telegram -->
            <div id="telegram-modal" class="modal">
                <div class="modal-content">
                    <h3>Chave da API do bot do Telegram</h3>
                    <div id="telegram-error" class="modal-error" style="display: none;"></div>
                    <form id="telegram-form" method="POST" action="">
                        <input type="text" name="telegram_bot_token" placeholder="Bot Token" value="<?php echo htmlspecialchars($settings['telegram_bot_token'] ?? ''); ?>">
                        <button type="submit" name="salvar_telegram" value="1" id="save-telegram-btn">Salvar Alterações</button>
                        <button type="button" class="close-button" onclick="closeModal('telegram-modal')">Cancelar</button>
                    </form>
                </div>
            </div>

            <!-- Modal reCAPTCHA -->
            <div id="recaptcha-modal" class="modal">
                <div class="modal-content">
                    <h3>Credenciais Google reCAPTCHA</h3>
                    <div id="recaptcha-error" class="modal-error" style="display: none;"></div>
                    <form id="recaptcha-form" method="POST" action="" data-error="false">
                        <div class="checkbox-container">
                            <input type="checkbox" id="recaptcha_enabled" name="recaptcha_enabled" <?php echo ($settings['recaptcha_enabled'] ?? '0') == '1' ? 'checked' : ''; ?>>
                            <label for="recaptcha_enabled" class="checkbox-label">Habilitar reCAPTCHA</label>
                        </div>
                        <input type="text" name="recaptcha_site_key" id="recaptcha_site_key" placeholder="Site Key" value="<?php echo htmlspecialchars($settings['recaptcha_site_key'] ?? ''); ?>">
                        <input type="text" name="recaptcha_secret_key" placeholder="Secret Key" value="<?php echo htmlspecialchars($settings['recaptcha_secret_key'] ?? ''); ?>">
                        <div class="recaptcha-container">
                            <div id="recaptcha-widget"></div>
                        </div>
                        <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">
                        <button type="submit" name="salvar_recaptcha" id="save-recaptcha-btn">Salvar Alterações</button>
                        <button type="button" class="close-button" onclick="closeModal('recaptcha-modal')">Cancelar</button>
                    </form>
                </div>
            </div>

            <!-- Modal Maps API -->
            <div id="maps-modal" class="modal">
                <div class="modal-content">
                    <h3>Chave da API do Google Maps</h3>
                    <div id="maps-error" class="modal-error" style="display: none;"></div>
                    <form id="maps-form" method="POST" action="">
                        <input type="text" name="google_maps_api_key" placeholder="API Key" value="<?php echo htmlspecialchars($settings['google_maps_api_key'] ?? ''); ?>">
                        <button type="submit" name="salvar_maps" value="1" id="save-maps-btn">Salvar Alterações</button>
                        <button type="button" class="close-button" onclick="closeModal('maps-modal')">Cancelar</button>
                    </form>
                </div>
            </div>

            <!-- Modal Pop-up -->
            <div id="popup-modal" class="modal">
                <div class="modal-content">
                    <h3>Configurar Texto do Pop-up</h3>
                    <div id="popup-error" class="modal-error" style="display: none;"></div>
                    <form id="popup-form" method="POST" action="">
                        <textarea name="popup_message" placeholder="Digite o texto do pop-up" rows="4"><?php echo htmlspecialchars($settings['signup_popup_message'] ?? ''); ?></textarea>
                        <button type="submit" name="salvar_popup" value="1" id="save-popup-btn">Salvar Alterações</button>
                        <button type="button" class="close-button" onclick="closeModal('popup-modal')">Cancelar</button>
                    </form>
                </div>
            </div>

            <a href="clientes.php" class="back-button">Voltar</a>
        </div>
    </div>

    <script>
        window.DEBUG_LOGS = <?php echo json_encode($DEBUG_LOGS); ?>;
    </script>
    <script src="/js/configuracoes.js?v=1"></script>
</body>
</html>
<?php
// Limpa o buffer de saída ao final do script
ob_end_flush();
?>

