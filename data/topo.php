<?php
// topo.php

// Inicia a sessão apenas se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inclui o arquivo de configuração
require_once 'config.php';

// Verifica permissões do usuário
$permissoes = 'visualizar'; // Valor padrão
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT permissoes FROM usuarios WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $permissoes = $user['permissoes'] ?? 'visualizar';
    } catch (PDOException $e) {
        error_log("Erro ao buscar permissões: " . $e->getMessage());
    }
}
?>

<!-- Incluindo o CSS e JavaScript diretamente aqui para demonstração -->
<link rel="stylesheet" href="css/topo.css">
<script src="js/topo.js"></script>

<header>
    <a href="/"><img src="imagens/firenet.png" alt="Logotipo FireNet Telecom"></a>
</header>
<nav>
    <a href="clientes.php">Clientes</a>
    <a href="relatorios.php">Relatórios</a>
    <?php if ($permissoes === 'excluir'): ?>
        <a href="configuracoes.php">Configurações</a>
        <a href="gerenciar-usuarios.php">Gerenciar Usuários</a>
    <?php endif; ?>
    <a href="meu-perfil.php">Meu Perfil</a>
    <a href="logout.php">Sair</a>
</nav>
