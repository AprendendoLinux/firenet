<?php
// imprimir-relatorio.php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config.php';

$quantidade = 3; // Quantidade fixa
$vencimento_boleto = '';
$clientes = [];
$error = '';
$identificador = '';
$data_criacao = '';

$meses = [
    1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril', 5 => 'maio', 6 => 'junho',
    7 => 'julho', 8 => 'agosto', 9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro'
];

// Verifica se é uma requisição para visualizar relatório antigo
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['identificador'])) {
    include 'gerar-relatorio.php';
    // Obtém a data de criação do relatório
    if (empty($error) && !empty($identificador)) {
        try {
            $stmt = $pdo->prepare("SELECT created_at FROM relatorios WHERE identificador = ?");
            $stmt->execute([$identificador]);
            $relatorio = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($relatorio) {
                $data = new DateTime($relatorio['created_at']);
                $dia = $data->format('j');
                $mes = $meses[(int)$data->format('n')];
                $ano = $data->format('Y');
                $data_criacao = "Rio de Janeiro, $dia de $mes de $ano";
            } else {
                $error = "Relatório não encontrado.";
            }
        } catch (PDOException $e) {
            $error = "Erro ao carregar data do relatório: " . $e->getMessage();
        }
    }
} else {
    $error = "Acesso inválido.";
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Instalações - FireNet Telecom</title>
    <link rel="stylesheet" href="css/imprimir-relatorio.css">
</head>
<body>
    <?php if (!empty($error)): ?>
        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
    <?php else: ?>
        <div class="a4-page">
            <div class="header">
                <a href='/'><img src="imagens/firenet.png" alt="Logotipo FireNet Telecom"></a>
                <h1>Relatório de Instalações Concluídas</h1>
            </div>

            <div class="section">
                <h2>Dados do Relatório</h2>
                <p class="report-number"><strong>Relatório nº <?php echo htmlspecialchars($identificador); ?></strong></p>
                <p><strong><?php echo htmlspecialchars($data_criacao); ?>,</strong></p>
                <p>À equipe FireNet Telecom,</p>
                <p>Encaminho o relatório das últimas <?php echo $quantidade; ?> instalações concluídas sob minha responsabilidade:</p>
                <table>
                    <thead>
                        <tr>
                            <th>Nome Completo</th>
                            <th>CPF</th>
                            <th>Endereço</th>
                            <th>Data de Conclusão</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes as $cliente): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($cliente['nome']); ?></td>
                                <td><?php echo htmlspecialchars($cliente['cpf']); ?></td>
                                <td>
                                    <?php
                                    $endereco = htmlspecialchars($cliente['rua']) . ', ' . htmlspecialchars($cliente['numero']);
                                    if ($cliente['complemento']) {
                                        $endereco .= ', ' . htmlspecialchars($cliente['complemento']);
                                    }
                                    $endereco .= ' - ' . htmlspecialchars($cliente['bairro']) . ', ' . htmlspecialchars($cliente['cep']);
                                    echo $endereco;
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($cliente['data_instalacao']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="boleto-request">Solicito a baixa do meu boleto com vencimento em <strong><?php echo htmlspecialchars($vencimento_boleto); ?>.</strong></p>
                <p>Atenciosamente,<br>Luiz Henrique Marques Fagundes</p>
            </div>

            <div class="footer">
                <p>FireNet Telecom - © 2025 Todos os direitos reservados.</p>
            </div>
        </div>

        <div class="no-print">
            <button>Imprimir Relatório</button>
        </div>
    <?php endif; ?>

    <script src="js/imprimir-relatorio.js"></script>
</body>
</html>
