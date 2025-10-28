<?php
// logout.php

// Configuração para habilitar/desabilitar logs de debug
$enable_debug_logs = false; // Mude para false para desabilitar os logs de debug

// Inicia a sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inclui o arquivo de configuração do banco de dados
require_once 'config.php';

// Evita loops de logout
if (isset($_SESSION['logout_processed'])) {
    if ($enable_debug_logs) {
        error_log("[DEBUG] Logout já processado, redirecionando para login.php.");
    }
    header('Location: login.php?logout=true');
    exit();
}
$_SESSION['logout_processed'] = true;

// Determina se estamos em HTTPS, considerando proxies
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
           $_SERVER['SERVER_PORT'] == 443 ||
           (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

// Log para depuração
if ($enable_debug_logs) {
    error_log("[DEBUG] HTTPS detectado: " . ($isHttps ? 'true' : 'false'));
}

// Preserva o cookie trusted_device_token para manter o dispositivo confiável
if (isset($_COOKIE['trusted_device_token'])) {
    if ($enable_debug_logs) {
        error_log("[DEBUG] Cookie trusted_device_token preservado: " . $_COOKIE['trusted_device_token']);
    }
} else {
    if ($enable_debug_logs) {
        error_log("[DEBUG] Nenhum cookie trusted_device_token encontrado.");
    }
}

// Registra o evento de logout
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO access_logs (user_id, event) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user_id'], "Logout bem-sucedido"]);
        if ($enable_debug_logs) {
            error_log("[DEBUG] Evento de logout registrado para user_id: " . $_SESSION['user_id']);
        }
    } catch (PDOException $e) {
        if ($enable_debug_logs) {
            error_log("[ERROR] Erro ao registrar logout: " . $e->getMessage());
        }
    }
}

// Destrói a sessão
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();
if ($enable_debug_logs) {
    error_log("[DEBUG] Sessão destruída com sucesso.");
}

// Redireciona para a página de login
if ($enable_debug_logs) {
    error_log("[DEBUG] Redirecionando para login.php após logout.");
}
header('Location: login.php?logout=true');
exit();
?>
