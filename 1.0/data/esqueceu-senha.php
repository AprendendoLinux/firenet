<?php
// esqueceu-senha.php

require_once 'config.php';

// Inicia a sessão apenas se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json'); // Define o tipo de resposta como JSON
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';

    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Por favor, insira o e-mail cadastrado.']);
        exit;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Por favor, insira um e-mail válido.']);
        exit;
    } else {
        try {
            // Verifica se o e-mail existe na tabela usuarios
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Gera um token único
                $token = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Remove tokens anteriores para o usuário
                $stmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
                $stmt->execute([$user['id']]);

                // Armazena o novo token no banco
                $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$user['id'], $token, $expires_at]);

                // Gera o link de redefinição
                $reset_link = "https://" . $_SERVER['HTTP_HOST'] . "/redefinir-senha.php?token=" . urlencode($token);

                // Envia o e-mail com o link
                try {
                    $mail->clearAllRecipients();
                    $mail->addAddress($user['email'], $user['username']);
                    $mail->isHTML(true);
                    $mail->Subject = 'Redefinição de Senha - FireNet Telecom';
                    $mail->Body = "
                        <h2>Olá, {$user['username']}!</h2>
                        <p>Você solicitou a redefinição de sua senha. Clique no link abaixo para criar uma nova senha:</p>
                        <p><a href='$reset_link'>Redefinir Senha</a></p>
                        <p>Este link expira em 1 hora.</p>
                        <p>Se você não solicitou esta redefinição, ignore este e-mail.</p>
                        <p>Atenciosamente,<br>Equipe FireNet Telecom</p>
                    ";
                    $mail->AltBody = "Olá, {$user['username']}!\n\nVocê solicitou a redefinição de sua senha. Acesse o link abaixo para criar uma nova senha:\n$reset_link\n\nEste link expira em 1 hora.\nSe você não solicitou esta redefinição, ignore este e-mail.\n\nAtenciosamente,\nEquipe FireNet Telecom";
                    $mail->send();
                    echo json_encode(['success' => true, 'message' => 'Um link de redefinição foi enviado para o seu e-mail.']);
                    exit;
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Erro ao enviar o e-mail: ' . $mail->ErrorInfo]);
                    exit;
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'E-mail não cadastrado.']);
                exit;
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erro na conexão com o banco de dados: ' . $e->getMessage()]);
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Esqueci a Senha - FireNet Telecom</title>
    <link rel="stylesheet" href="css/esqueceu-senha.css">
</head>
<body>
    <header>
        <a href='/'><img src="imagens/firenet.png" alt="Logotipo FireNet Telecom"></a>
    </header>

    <div class="container">
        <div class="reset-container">
            <h2>Recuperar Senha</h2>
            <form method="POST" action="" id="reset-form">
                <div id="reset-error" class="error" style="display: none;"></div>
                <label for="email">E-mail Cadastrado</label>
                <input type="email" id="email" name="email" required placeholder="Digite seu e-mail">
                <button type="submit" id="reset-button">Enviar Link de Redefinição</button>
            </form>
            <form action="login.php">
                <button type="submit" class="back-button">Voltar para o Login</button>
            </form>
        </div>
    </div>

    <script src="js/esqueceu-senha.js"></script>
</body>
</html>
