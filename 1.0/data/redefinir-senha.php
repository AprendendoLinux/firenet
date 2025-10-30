<?php
// redefinir-senha.php

require_once 'config.php';

// Inicia a sessão apenas se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$success = '';
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if (empty($token)) {
    $error = "Token inválido ou não fornecido.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($token)) {
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';

    if (empty($password) || empty($confirm_password)) {
        $error = "Por favor, preencha todos os campos.";
    } elseif ($password !== $confirm_password) {
        $error = "As senhas não coincidem.";
    } elseif (strlen($password) < 8) {
        $error = "A senha deve ter pelo menos 8 caracteres.";
    } else {
        try {
            // Verifica o token
            $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()");
            $stmt->execute([$token]);
            $reset = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($reset) {
                // Atualiza a senha do usuário
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $reset['user_id']]);

                // Remove o token usado
                $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
                $stmt->execute([$token]);

                // Registrar log de redefinição de senha
                $stmt = $pdo->prepare("INSERT INTO access_logs (user_id, event) VALUES (?, ?)");
                $stmt->execute([$reset['user_id'], "Senha redefinida com sucesso"]);

                $success = "Senha redefinida com sucesso! Você pode agora fazer login com sua nova senha.";
            } else {
                $error = "Token inválido ou expirado.";
            }
        } catch (PDOException $e) {
            $error = "Erro na conexão com o banco de dados: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir Senha - FireNet Telecom</title>
    <link rel="stylesheet" href="css/redefinir-senha.css">
</head>
<body>
    <header>
        <a href='/'><img src="imagens/firenet.png" alt="Logotipo FireNet Telecom"></a>
    </header>

    <div class="container">
        <div class="reset-container">
            <h2>Redefinir Senha</h2>
            <?php if (!empty($success)): ?>
                <p class="success"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <?php if (empty($success)): ?>
                <form method="POST" action="">
                    <label for="password">Nova Senha</label>
                    <input type="password" id="password" name="password" required placeholder="Digite sua nova senha">
                    <label for="confirm_password">Confirmar Senha</label>
                    <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirme sua nova senha">
                    <button type="submit">Redefinir Senha</button>
                </form>
            <?php endif; ?>
            <form action="login.php">
                <button type="submit" class="back-button">Voltar para o Login</button>
            </form>
        </div>
    </div>

    <script src="js/redefinir-senha.js"></script>
</body>
</html>
