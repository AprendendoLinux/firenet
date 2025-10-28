<?php
// relatorios.php

session_start();
require_once 'config.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Verifica as permissões do usuário
$permissoes = 'visualizar'; // Valor padrão
try {
    $stmt = $pdo->prepare("SELECT permissoes FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $permissoes = $user['permissoes'] ?? 'visualizar';
} catch (PDOException $e) {
    error_log("Erro ao buscar permissões: " . $e->getMessage());
}

$error = '';
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$mes = date('m'); // Mês atual como padrão
$ano = date('Y'); // Ano atual como padrão
$ultimo_vencimento = ''; // Para armazenar o último vencimento no formato MM/YYYY
$total_instalacoes = 0; // Inicializa total_instalacoes

// Processa o formulário de geração de relatório (apenas para administradores)
if ($permissoes === 'excluir' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gerar_relatorio'])) {
    include 'gerar-relatorio.php';
    if (empty($error) && !empty($identificador)) {
        // Armazena o identificador do novo relatório na sessão
        $_SESSION['novo_relatorio'] = $identificador;
        // Define mensagem de sucesso
        $_SESSION['success'] = "Relatório nº $identificador gerado com sucesso!";
    } else {
        $_SESSION['error'] = $error;
    }
}

// Busca o último vencimento registrado (apenas para administradores)
if ($permissoes === 'excluir') {
    try {
        $stmt = $pdo->query("SELECT data_vencimento FROM relatorios ORDER BY created_at DESC LIMIT 1");
        $ultimo_relatorio = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($ultimo_relatorio) {
            $data_vencimento = DateTime::createFromFormat('d/m/Y', $ultimo_relatorio['data_vencimento']);
            if ($data_vencimento) {
                $ultimo_mes = (int)$data_vencimento->format('m');
                $ultimo_ano = (int)$data_vencimento->format('Y');
                // Avança para o próximo mês
                $data_vencimento->modify('+1 month');
                $mes = (int)$data_vencimento->format('m');
                $ano = (int)$data_vencimento->format('Y');
                $ultimo_vencimento = sprintf("%02d/%04d", $ultimo_mes, $ultimo_ano);
            }
        }
    } catch (PDOException $e) {
        $error = "Erro ao buscar último vencimento: " . $e->getMessage();
        error_log($error);
    }

    // Verifica se há instalações suficientes (apenas para administradores)
    try {
        $stmt = $pdo->prepare("
            SELECT c.id
            FROM cadastros c
            LEFT JOIN relatorios r ON FIND_IN_SET(c.id, r.clientes_ids)
            WHERE c.status_instalacao = 'Instalação Concluída' AND r.id IS NULL
        ");
        $stmt->execute();
        $instalacoes_disponiveis = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_instalacoes = count($instalacoes_disponiveis);
    } catch (PDOException $e) {
        $error = "Erro ao verificar instalações: " . $e->getMessage();
        error_log($error);
    }
}

// Carrega relatórios antigos (para todos os usuários)
try {
    $stmt = $pdo->prepare("SELECT r.*, GROUP_CONCAT(c.nome) as clientes_nomes 
                           FROM relatorios r 
                           LEFT JOIN cadastros c ON FIND_IN_SET(c.id, r.clientes_ids) 
                           GROUP BY r.id 
                           ORDER BY r.created_at DESC");
    $stmt->execute();
    $relatorios_antigos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erro ao carregar relatórios antigos: " . $e->getMessage();
    error_log($error);
}

// Limpa mensagens da sessão
if (isset($_SESSION['success'])) {
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - FireNet Telecom</title>
    <link rel="stylesheet" href="css/relatorios.css">
</head>
<body>
    <?php include 'topo.php'; ?>

    <div class="container" data-total-instalacoes="<?php echo $total_instalacoes; ?>" data-ultimo-vencimento="<?php echo htmlspecialchars($ultimo_vencimento); ?>">
        <?php if (!empty($error)): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="message success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($permissoes === 'excluir'): ?>
            <div class="report-form">
                <h2>Gerar Relatório de Instalações Concluídas</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="mes">Mês de Vencimento do Boleto:</label>
                        <select id="mes" name="mes" required>
                            <?php
                            $mes_inicio = $mes; // Começa do próximo mês após o último vencimento
                            $ano_atual = date('Y');
                            for ($i = $mes_inicio; $i <= 12; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo $mes == $i ? 'selected' : ''; ?>><?php echo sprintf("%02d", $i); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="ano">Ano de Vencimento do Boleto:</label>
                        <select id="ano" name="ano" required>
                            <?php
                            $ano_inicio = $ano;
                            for ($i = $ano_inicio; $i <= $ano_atual + 4; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo $ano == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group button-group">
                        <button type="submit" name="gerar_relatorio">Gerar Relatório</button>
                        <button type="button" id="view-history">Relatórios Antigos</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Modal de relatórios antigos -->
        <div id="historyModal" class="modal">
            <div class="modal-content">
                <span class="close">×</span>
                <h2>Relatórios Antigos</h2>
                <?php if (empty($relatorios_antigos)): ?>
                    <p>Nenhum relatório encontrado.</p>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Identificador</th>
                                    <th>Data de Vencimento</th>
                                    <th>Clientes</th>
                                    <th>Data de Criação</th>
                                    <th>Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($relatorios_antigos as $relatorio): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($relatorio['identificador']); ?></td>
                                        <td><?php echo htmlspecialchars($relatorio['data_vencimento']); ?></td>
                                        <td><?php echo htmlspecialchars($relatorio['clientes_nomes']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($relatorio['created_at'])); ?></td>
                                        <td>
                                            <a href="imprimir-relatorio.php?identificador=<?php echo urlencode($relatorio['identificador']); ?>" target="_blank" class="view-report">Visualizar</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Modal de erro -->
        <div id="errorModal" class="modal">
            <div class="modal-content">
                <span class="close">×</span>
                <h2>Erro</h2>
                <p>Não é possível gerar o relatório porque as últimas três instalações ainda não foram concluídas.</p>
                <button class="modal-button">OK</button>
            </div>
        </div>

        <?php if (isset($_SESSION['novo_relatorio'])): ?>
            <script>
                // Abre o relatório em uma nova aba
                window.open('imprimir-relatorio.php?identificador=<?php echo urlencode($_SESSION['novo_relatorio']); ?>', '_blank');
                // Limpa a sessão
                <?php unset($_SESSION['novo_relatorio']); ?>
            </script>
        <?php endif; ?>
    </div>

    <script src="js/relatorios.js"></script>
</body>
</html>
