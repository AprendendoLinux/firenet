<?php
// check_cpf.php

// Inclui o arquivo de configuração do banco de dados
require_once 'config.php';

// Configurações de cabeçalho para JSON e CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Ajuste para seu domínio em produção
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Verifica se a requisição é POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido.');
    }

    // Captura o CPF enviado
    $cpf = isset($_POST['cpf']) ? trim($_POST['cpf']) : null;

    if (!$cpf) {
        throw new Exception('CPF não fornecido.');
    }

    // Prepara a query para verificar se o CPF existe
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cadastros WHERE cpf = ?");
    $stmt->execute([$cpf]);
    $count = $stmt->fetchColumn();

    // Retorna o resultado como JSON
    echo json_encode(['exists' => $count > 0]);

} catch (Exception $e) {
    // Retorna erro como JSON
    echo json_encode(['error' => $e->getMessage()]);
}
?>
