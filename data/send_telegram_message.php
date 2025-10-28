<?php
// send_telegram_message.php

// Inclui o arquivo de configuração
require_once 'config.php';

// Função para sanitizar entradas
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Recebe os dados passados como argumentos
if ($argc < 7) {
    die("Erro: Dados insuficientes para enviar a mensagem ao Telegram.\n");
}

$nome = sanitize($argv[1]);
$email = filter_var($argv[2], FILTER_SANITIZE_EMAIL);
$telefone = sanitize($argv[3]);
$plano = sanitize($argv[4]);
$vencimento = sanitize($argv[5]);
$data_nascimento = sanitize($argv[6]);

// Monta a mensagem
$message = "Novo Cadastro na FireNet Telecom\n\n" .
           "Nome: $nome\n" .
           "E-mail: $email\n" .
           "Telefone: $telefone\n" .
           "Plano: $plano\n" .
           "Data de Vencimento: $vencimento\n" .
           "Data de Nascimento: $data_nascimento\n" .
           "Por favor, entre em contato com o cliente.";

// Busca os chat_ids dos administradores
$chatIds = getTelegramChatIds($pdo);

// Envia a mensagem para cada administrador
foreach ($chatIds as $chatId) {
    // Monta a URL para a API do Telegram
    $telegramUrl = "https://api.telegram.org/bot$telegramBotToken/sendMessage?" . http_build_query([
        'chat_id' => $chatId,
        'text' => $message
    ]);

    // Envia a mensagem
    $response = file_get_contents($telegramUrl);

    // Verifica se a mensagem foi enviada com sucesso
    $responseData = json_decode($response, true);
    if (!$responseData['ok']) {
        error_log("Erro ao enviar mensagem para o Telegram (chat_id: $chatId): " . $responseData['description']);
    }

    // Pequeno atraso para evitar sobrecarga na API do Telegram
    usleep(500000); // 0,5 segundos
}
?>
