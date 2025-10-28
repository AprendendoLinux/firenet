<?php
// auth.php

require_once __DIR__ . '/vendor/autoload.php';
require_once 'config.php';

use OTPHP\TOTP;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

// Função para verificar autenticação
function verificarAutenticacao($requireAdmin = false) {
    global $pdo;

    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }

    if ($requireAdmin) {
        $stmt = $pdo->prepare("SELECT permissoes FROM usuarios WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || $user['permissoes'] !== 'excluir') {
            header('Location: clientes.php');
            exit();
        }
    }
}

// Função para inicializar TOTP
function initializeTOTP($secret = null, $username = '') {
    try {
        // Cria uma instância de TOTP
        $totp = TOTP::create($secret); // Cria com segredo (ou gera um novo se null)
        
        // Configurações do TOTP
        $totp->setLabel($username . '@FireNet');
        $totp->setIssuer('FireNet Telecom');
        $totp->setDigest('sha1'); // Algoritmo de hash
        $totp->setDigits(6); // 6 dígitos
        $totp->setPeriod(30); // Período de 30 segundos
        
        return $totp;
    } catch (Exception $e) {
        error_log("Erro ao inicializar TOTP: " . $e->getMessage());
        throw new Exception("Erro ao configurar o TOTP: " . $e->getMessage());
    }
}

// Função para gerar QR Code
function generateTOTPQRCode($totp) {
    try {
        $provisioningUri = $totp->getProvisioningUri();
        $qrCode = QrCode::create($provisioningUri)
            ->setSize(200)
            ->setMargin(10);
        $writer = new PngWriter();
        $result = $writer->write($qrCode);
        return 'data:image/png;base64,' . base64_encode($result->getString());
    } catch (Exception $e) {
        error_log("Erro ao gerar QR Code: " . $e->getMessage());
        throw new Exception("Erro ao gerar QR Code: " . $e->getMessage());
    }
}
?>
