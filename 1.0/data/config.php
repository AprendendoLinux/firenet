<?php
// config.php
// Este arquivo deve ser incluído ANTES de qualquer outro arquivo que use sessões (ex.: auth.php, login.php, etc.).

// Carrega o autoloader do Composer
require_once __DIR__ . '/vendor/autoload.php';

// Verifica se a sessão já está ativa antes de configurar os parâmetros
if (session_status() === PHP_SESSION_NONE) {
    // Configurações da sessão (devem vir ANTES de session_start())
    ini_set('session.cookie_lifetime', 0); // Sessão persiste até o navegador ser fechado
    ini_set('session.gc_maxlifetime', 86400); // 24 horas de tempo de vida no servidor
    ini_set('session.cookie_httponly', 1); // Previne acesso ao cookie via JavaScript
    ini_set('session.cookie_secure', 1); // Cookie só é enviado via HTTPS

    // Configura parâmetros do cookie da sessão
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
}

// Inicia a sessão apenas se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configurações do banco de dados
$host = getenv('DB_HOST') ?: die('Erro: Variável de ambiente DB_HOST não configurada.');
$dbname = getenv('DB_NAME') ?: die('Erro: Variável de ambiente DB_NAME não configurada.');
$user = getenv('DB_USER') ?: die('Erro: Variável de ambiente DB_USER não configurada.');
$pass = getenv('DB_PASS') ?: die('Erro: Variável de ambiente DB_PASS não configurada.');

// Conexão com o banco de dados usando PDO
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET NAMES 'utf8mb4'"); // Garante que a conexão use UTF-8
} catch (PDOException $e) {
    error_log("Erro na conexão PDO: " . $e->getMessage());
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}

// Criar tabelas se não existirem
try {
    // Tabela `usuarios`
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `usuarios` (
            `id` int NOT NULL AUTO_INCREMENT,
            `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `permissoes` enum('visualizar','excluir') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'visualizar',
            `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `2fa_enabled` tinyint(1) DEFAULT '0',
            `2fa_type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `2fa_secret` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `username` (`username`),
            UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Tabela `2fa_codes`
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `2fa_codes` (
            `id` int NOT NULL AUTO_INCREMENT,
            `user_id` int NOT NULL,
            `code` varchar(6) NOT NULL,
            `expires_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            CONSTRAINT `2fa_codes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1
    ");

    // Tabela `access_logs`
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `access_logs` (
            `id` int NOT NULL AUTO_INCREMENT,
            `user_id` int NOT NULL,
            `event` varchar(255) NOT NULL,
            `timestamp` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            CONSTRAINT `access_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1
    ");

    // Tabela `cadastros`
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `cadastros` (
            `id` int NOT NULL AUTO_INCREMENT,
            `nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `cpf` varchar(14) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `rg` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `data_nascimento` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `telefone` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `whatsapp` enum('Sim','Não') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `cep` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `rua` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `numero` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `complemento` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `ponto_referencia` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `bairro` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `plano` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `vencimento` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `nome_rede` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `senha_rede` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `lgpd` enum('Sim') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `data_cadastro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `data_instalacao` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `turno_instalacao` enum('Manhã','Tarde','Horário Comercial') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `status_instalacao` enum('Instalação Programada','Instalação Concluída','Instalação Não Realizada') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `observacoes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Tabela `password_resets`
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `password_resets` (
            `id` int NOT NULL AUTO_INCREMENT,
            `user_id` int NOT NULL,
            `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `expires_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Tabela `relatorios`
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `relatorios` (
            `id` int NOT NULL AUTO_INCREMENT,
            `identificador` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `data_vencimento` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `clientes_ids` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `identificador` (`identificador`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Tabela `system_settings`
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `system_settings` (
            `id` int NOT NULL AUTO_INCREMENT,
            `setting_key` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `setting_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `setting_key` (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Tabela `telegram_admins`
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `telegram_admins` (
            `id` int NOT NULL AUTO_INCREMENT,
            `chat_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `chat_id` (`chat_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Tabela `trusted_devices`
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `trusted_devices` (
            `id` int NOT NULL AUTO_INCREMENT,
            `user_id` int NOT NULL,
            `token` varchar(64) NOT NULL,
            `expires_at` datetime NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_user_token` (`user_id`,`token`)
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1
    ");

    // Inserir configurações padrão na tabela `system_settings`
    $pdo->exec("
        INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
        ('smtp_host', '', NOW()),
        ('smtp_username', '', NOW()),
        ('smtp_password', '', NOW()),
        ('smtp_port', '', NOW()),
        ('smtp_encryption', '', NOW()),
        ('smtp_auth', '', NOW()),
        ('telegram_bot_token', '', NOW()),
        ('recaptcha_enabled', '0', NOW()),
        ('recaptcha_site_key', '', NOW()),
        ('recaptcha_secret_key', '', NOW()),
        ('google_maps_api_key', '', NOW()),
        ('smtp_from_email', '', NOW()),
        ('smtp_from_name', '', NOW()),
        ('smtp_reply_to', '', NOW()),
        ('signup_popup_message', '', NOW())
    ");

    // Verificar e criar usuário inicial 'admin' se a tabela de usuários estiver vazia
    $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios");
    if ($stmt->fetchColumn() == 0) {
        $username = 'admin';
        $password = password_hash('admin', PASSWORD_BCRYPT);
        $email = 'admin@firenet.com';
        $stmt = $pdo->prepare("INSERT INTO usuarios (username, password, email, permissoes) VALUES (?, ?, ?, 'excluir')");
        $stmt->execute([$username, $password, $email]);
    }

} catch (PDOException $e) {
    error_log("Erro ao criar tabelas ou inserir dados iniciais: " . $e->getMessage());
    die("Erro ao inicializar o banco de dados: " . $e->getMessage());
}

// Carrega configurações do sistema do banco de dados
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    error_log("Erro ao carregar configurações do sistema: " . $e->getMessage());
    die("Erro ao carregar configurações do sistema: " . $e->getMessage());
}

// Inclui as classes do PHPMailer
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

// Importa os namespaces necessários
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Inicializa o objeto PHPMailer
$mail = new PHPMailer(true); // Habilita exceções para melhor depuração

try {
    // Configurações do servidor SMTP
    $mail->isSMTP(); // Define que usará SMTP
    $mail->Host = $settings['smtp_host'] ?? 'localhost'; // Valor padrão se não estiver no banco
    $mail->SMTPAuth = ($settings['smtp_auth'] ?? '0') == '1';
    $mail->Username = $settings['smtp_username'] ?? '';
    $mail->Password = $settings['smtp_password'] ?? '';
    $mail->SMTPSecure = $settings['smtp_encryption'] ?? ''; // Pode ser vazio, '', 'ssl' ou 'tls'
    $mail->Port = $settings['smtp_port'] ?? 25; // Porta padrão SMTP se não estiver no banco

    // Configurações de segurança adicionais
    $mail->SMTPKeepAlive = true; // Mantém a conexão SMTP ativa
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]; // Opções para servidores locais

    // Configurações de depuração (opcional)
    $mail->SMTPDebug = 0; // 0 = desativado, 1 = erros e mensagens, 2 = mensagens detalhadas
    $mail->Debugoutput = 'html'; // Formato da saída de depuração

    // Configurações de codificação
    $mail->CharSet = 'UTF-8'; // Codificação para suportar caracteres especiais
    $mail->Encoding = 'base64'; // Codificação do conteúdo do e-mail

    // Configurações do remetente
    $fromEmail = $settings['smtp_from_email'] ?? 'no-reply@seusite.com';
    $fromName = $settings['smtp_from_name'] ?? 'Sistema';
    if (filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $mail->setFrom($fromEmail, $fromName);
    } else {
        error_log("E-mail de remetente inválido: " . $fromEmail);
        $mail->setFrom('no-reply@seusite.com', 'Sistema'); // Fallback seguro
    }

    // Configurações do Reply-To
    if (!empty($settings['smtp_reply_to']) && filter_var($settings['smtp_reply_to'], FILTER_VALIDATE_EMAIL)) {
        $mail->addReplyTo($settings['smtp_reply_to'], $fromName);
    }

    // Configurações de autenticação avançada (se necessário)
    $mail->AuthType = 'LOGIN'; // Tipo de autenticação: LOGIN, PLAIN, NTLM, CRAM-MD5

} catch (Exception $e) {
    error_log("Erro na configuração do PHPMailer: " . $e->getMessage());
}

// Configurações do Telegram
$telegramBotToken = $settings['telegram_bot_token'] ?? '';

// Configurações do Google reCAPTCHA v2 Checkbox
$recaptcha_enabled = ($settings['recaptcha_enabled'] ?? '0') == '1';
$recaptcha_secret_key = $settings['recaptcha_secret_key'] ?? '';
$recaptcha_site_key = $settings['recaptcha_site_key'] ?? '';

// Função para buscar os chat_ids dos administradores no Telegram
function getTelegramChatIds($pdo) {
    try {
        $stmt = $pdo->query("SELECT chat_id FROM telegram_admins");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Erro ao buscar chat_ids do Telegram: " . $e->getMessage());
        return [];
    }
}

// Função para gerar código de 6 dígitos para 2FA
function generate2FACode() {
    return sprintf("%06d", mt_rand(0, 999999));
}

// Função para registrar logs
function registrarLog($pdo, $user_id, $event) {
    try {
        $stmt = $pdo->prepare("INSERT INTO access_logs (user_id, event) VALUES (?, ?)");
        $stmt->execute([$user_id, $event]);
    } catch (PDOException $e) {
        error_log("Erro ao registrar log: " . $e->getMessage());
    }
}

// Verifica e corrige usuários sem e-mail
try {
    $stmt = $pdo->query("SELECT id, username FROM usuarios WHERE email IS NULL OR email = ''");
    $users_without_email = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users_without_email as $user) {
        $new_email = $user['username'] . '@firenet.com';
        $stmt = $pdo->prepare("UPDATE usuarios SET email = ? WHERE id = ?");
        $stmt->execute([$new_email, $user['id']]);
    }
} catch (PDOException $e) {
    error_log("Erro ao corrigir e-mails de usuários: " . $e->getMessage());
}
?>