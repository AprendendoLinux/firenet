<?php
// imprimir-cliente.php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID do cliente não fornecido ou inválido.");
}

$cliente_id = (int)$_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM cadastros WHERE id = ?");
$stmt->execute([$cliente_id]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    die("Cliente não encontrado.");
}

// Formatar created_at para DD/MM/AAAA
$created_at = $cliente['created_at'] ? (new DateTime($cliente['created_at']))->format('d/m/Y') : 'Não definida';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha do Cliente - FireNet Telecom</title>
    <link rel="stylesheet" href="css/imprimir-cliente.css">
</head>
<body>
    <div class="a4-page">
        <div class="header">
            <a href='/'><img src="imagens/firenet.png" alt="Logotipo FireNet Telecom"></a>
            <h1>Ficha do Cliente - FireNet Telecom</h1>
        </div>

        <div class="section">
            <h2>Dados Pessoais</h2>
            <p><strong>Nome:</strong> <?php echo htmlspecialchars($cliente['nome']); ?></p>
            <p><strong>CPF:</strong> <?php echo htmlspecialchars($cliente['cpf']); ?></p>
            <p><strong>RG:</strong> <?php echo htmlspecialchars($cliente['rg']); ?></p>
            <p><strong>Data de Nascimento:</strong> <?php echo htmlspecialchars($cliente['data_nascimento'] ?? 'Não definida'); ?></p>
            <p><strong>Telefone:</strong> <?php echo htmlspecialchars($cliente['telefone']); ?></p>
            <p><strong>WhatsApp:</strong> <?php echo htmlspecialchars($cliente['whatsapp']); ?></p>
            <p><strong>E-mail:</strong> <?php echo htmlspecialchars($cliente['email']); ?></p>
        </div>

        <div class="section">
            <h2>Endereço</h2>
            <p><strong>CEP:</strong> <?php echo htmlspecialchars($cliente['cep']); ?></p>
            <p><strong>Rua:</strong> <?php echo htmlspecialchars($cliente['rua']); ?></p>
            <p><strong>Número:</strong> <?php echo htmlspecialchars($cliente['numero']); ?></p>
            <p><strong>Complemento:</strong> <?php echo htmlspecialchars($cliente['complemento']) ?: 'Não informado'; ?></p>
            <p><strong>Ponto de Referência:</strong> <?php echo htmlspecialchars($cliente['ponto_referencia']); ?></p>
            <p><strong>Bairro:</strong> <?php echo htmlspecialchars($cliente['bairro']); ?></p>
        </div>

        <div class="section">
            <h2>Dados do Plano</h2>
            <p><strong>Plano:</strong> <?php echo htmlspecialchars($cliente['plano']); ?></p>
            <p><strong>Data de Vencimento:</strong> <?php echo htmlspecialchars($cliente['vencimento']); ?></p>
            <p><strong>Nome da Rede:</strong> <?php echo htmlspecialchars($cliente['nome_rede']); ?></p>
            <p><strong>Senha da Rede:</strong> <?php echo htmlspecialchars($cliente['senha_rede']); ?></p>
        </div>

        <div class="section">
            <h2>Instalação</h2>
            <p><strong>Data Prevista para Instalação:</strong> <?php echo htmlspecialchars($cliente['data_instalacao'] ?? 'Não definida'); ?></p>
            <p><strong>Turno:</strong> <?php echo htmlspecialchars($cliente['turno_instalacao'] ?? 'Não definido'); ?></p>
            <p><strong>Status:</strong> <span class="<?php
                if ($cliente['status_instalacao'] === 'Instalação Programada') {
                    echo 'status-programada';
                } elseif ($cliente['status_instalacao'] === 'Instalação Concluída') {
                    echo 'status-concluida';
                } elseif ($cliente['status_instalacao'] === 'Instalação Não Realizada') {
                    echo 'status-nao-realizada';
                }
            ?>"><?php echo htmlspecialchars($cliente['status_instalacao'] ?? 'Não definido'); ?></span></p>
            <p><strong>Observações:</strong> <?php echo htmlspecialchars($cliente['observacoes'] ?? 'Nenhuma'); ?></p>
        </div>

        <div class="section">
            <h2>Outros</h2>
            <p><strong>Aceite LGPD:</strong> <?php echo htmlspecialchars($cliente['lgpd']); ?></p>
            <p><strong>Data de Cadastro:</strong> <?php echo htmlspecialchars($created_at); ?></p>
        </div>

        <div class="footer">
            <p>FireNet Telecom - © 2025 Todos os direitos reservados.</p>
        </div>
    </div>

    <div class="no-print">
        <button>Imprimir Ficha</button>
    </div>

    <script src="js/imprimir-cliente.js"></script>
</body>
</html>
