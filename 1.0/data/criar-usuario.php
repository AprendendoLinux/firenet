<?php
session_start();
require_once 'config.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Verifica permissões do usuário
$permissoes = 'visualizar'; // Valor padrão
try {
    $stmt = $pdo->prepare("SELECT permissoes FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $permissoes = $user['permissoes'] ?? 'visualizar';
} catch (PDOException $e) {
    error_log("Erro ao buscar permissões: " . $e->getMessage());
}

// Verifica se o usuário tem permissão para criar usuários
if ($permissoes !== 'excluir') {
    $_SESSION['error'] = "Você não tem permissão para criar usuários.";
    header('Location: clientes.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $permissoes = $_POST['permissoes'] ?? 'visualizar';

    // Validações
    if (empty($username) || empty($password) || empty($confirm_password) || empty($email)) {
        $error = "Por favor, preencha todos os campos obrigatórios.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Por favor, insira um e-mail válido.";
    } elseif (strlen($password) < 6) {
        $error = "A senha deve ter pelo menos 6 caracteres.";
    } elseif ($password !== $confirm_password) {
        $error = "A senha e a confirmação não coincidem.";
    } else {
        try {
            // Verifica se o e-mail já está em uso
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = "Este e-mail já está em uso.";
            } else {
                // Criação de novo usuário
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO usuarios (username, email, password, permissoes) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $email, $hashed_password, $permissoes]);
                $new_user_id = $pdo->lastInsertId();
                $success = "Usuário criado com sucesso!";
                registrarLog($pdo, $_SESSION['user_id'], "Novo usuário criado: ID $new_user_id");
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '23000' && strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'username') !== false) {
                $error = "O usuário não pode ser criado porque o nome de usuário já existe.";
            } else {
                error_log("Erro ao criar usuário: " . $e->getMessage());
                $error = "Erro ao criar usuário: " . $e->getMessage();
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
    <title>Criar Usuário - FireNet Telecom</title>
    <link rel="stylesheet" href="css/criar-usuario.css">
</head>
<body>
    <?php include 'topo.php'; ?>

    <div class="container">
        <div class="form-container">
            <h2>Criar Usuário</h2>
            <?php if (!empty($error)): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <p class="success"><?php echo htmlspecialchars($success); ?><meta http-equiv="refresh" content="1;url=gerenciar-usuarios.php"></p>
            <?php endif; ?>

            <form method="POST" id="user-form">
                <label for="username">Usuário:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                
                <label for="email">E-mail:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                <p id="email-error" class="form-error"></p>
                
                <label for="password">Senha:</label>
                <div class="password-container">
                    <input type="password" id="password" name="password" required>
                    <span class="toggle-password" data-target="password">👁️</span>
                </div>
                <p id="password-error" class="form-error"></p>
                
                <label for="confirm_password">Confirmar Senha:</label>
                <div class="password-container">
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <span class="toggle-password" data-target="confirm_password">👁️</span>
                </div>
                <p id="confirm-password-error" class="form-error"></p>
                
                <label for="permissoes">Permissões:</label>
                <select id="permissoes" name="permissoes">
                    <option value="visualizar" <?php echo ($_POST['permissoes'] ?? 'visualizar') === 'visualizar' ? 'selected' : ''; ?>>Visualizar</option>
                    <option value="excluir" <?php echo ($_POST['permissoes'] ?? '') === 'excluir' ? 'selected' : ''; ?>>Excluir</option>
                </select>
                
                <button type="submit" id="submit-button">Criar Usuário</button>
            </form>
            
            <a href="gerenciar-usuarios.php" class="back-button" id="back-button">Voltar</a>
        </div>
    </div>

    <script src="js/criar-usuario.js"></script>
</body>
</html>
