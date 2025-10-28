<?php
// check_email.php

header('Content-Type: application/json');

require_once 'config.php';

try {
    $email = $_POST['email'] ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);

    if (!$email) {
        echo json_encode(['exists' => false, 'error' => 'E-mail não fornecido']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
    $stmt->execute([$email, $user_id]);
    $exists = $stmt->fetch() !== false;

    echo json_encode(['exists' => $exists]);
} catch (Exception $e) {
    echo json_encode(['exists' => false, 'error' => 'Erro ao verificar e-mail']);
}
?>
