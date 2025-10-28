<?php
// clientes.php

// Configuração de depuração: defina como true para ativar os logs, false para desabilitar
$DEBUG_LOGS = false;

session_start();
require_once 'config.php';
require_once 'auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT username, permissoes FROM usuarios WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        $error = "Usuário não encontrado.";
        session_destroy();
        header('Location: login.php');
        exit();
    }
    $username = $user['username'];
    $permissoes = $user['permissoes'];
} catch (PDOException $e) {
    $error = "Erro ao buscar dados do usuário: " . $e->getMessage();
    $permissoes = 'visualizar';
    $username = 'Usuário';
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_atualizar_instalacao'])) {
    header('Content-Type: application/json');
    if ($DEBUG_LOGS) {
        error_log('Requisição AJAX recebida');
    }
    if ($permissoes !== 'excluir') {
        echo json_encode(['success' => false, 'message' => "Você não tem permissão para editar dados de instalação."]);
        exit;
    }

    $cliente_id = (int)$_POST['cliente_id'];
    $data_instalacao = $_POST['data_instalacao'] ? DateTime::createFromFormat('Y-m-d', $_POST['data_instalacao'])->format('d/m/Y') : null;
    $turno_instalacao = $_POST['turno_instalacao'] ?: null;
    $status_instalacao = $_POST['status_instalacao'] ?: null;
    $observacoes = trim($_POST['observacoes']) ?: null;

    try {
        $stmt = $pdo->prepare("SELECT nome, data_instalacao, turno_instalacao, status_instalacao, observacoes FROM cadastros WHERE id = ?");
        $stmt->execute([$cliente_id]);
        $cliente_antigo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cliente_antigo) {
            echo json_encode(['success' => false, 'message' => "Cliente não encontrado."]);
            exit;
        }

        $cliente_nome = $cliente_antigo['nome'];
        $data_instalacao_antiga = $cliente_antigo['data_instalacao'];
        $turno_instalacao_antigo = $cliente_antigo['turno_instalacao'];
        $status_instalacao_antigo = $cliente_antigo['status_instalacao'];
        $observacoes_antiga = $cliente_antigo['observacoes'];

        $stmt = $pdo->prepare("UPDATE cadastros SET data_instalacao = ?, turno_instalacao = ?, status_instalacao = ?, observacoes = ? WHERE id = ?");
        $stmt->execute([$data_instalacao, $turno_instalacao, $status_instalacao, $observacoes, $cliente_id]);

        $log_entries = [];
        if ($data_instalacao !== $data_instalacao_antiga) {
            $log_entries[] = "Alterou a data de instalação do cliente '$cliente_nome' de '" . ($data_instalacao_antiga ?? 'Não definida') . "' para '" . ($data_instalacao ?? 'Não definida') . "'";
        }
        if ($turno_instalacao !== $turno_instalacao_antigo) {
            $log_entries[] = "Alterou o turno de instalação do cliente '$cliente_nome' de '" . ($turno_instalacao_antigo ?? 'Não definido') . "' para '" . ($turno_instalacao ?? 'Não definido') . "'";
        }
        if ($status_instalacao !== $status_instalacao_antigo) {
            $log_entries[] = "Alterou o status de instalação do cliente '$cliente_nome' de '" . ($status_instalacao_antigo ?? 'Não definido') . "' para '" . ($status_instalacao ?? 'Não definido') . "'";
        }
        if ($observacoes !== $observacoes_antiga) {
            $log_entries[] = "Alterou as observações do cliente '$cliente_nome' de '" . ($observacoes_antiga ?? 'Nenhuma') . "' para '" . ($observacoes ?? 'Nenhuma') . "'";
        }

        foreach ($log_entries as $entry) {
            registrarLog($pdo, $user_id, $entry);
        }

        $stmt = $pdo->prepare("SELECT nome, email FROM cadastros WHERE id = ?");
        $stmt->execute([$cliente_id]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cliente && $data_instalacao && filter_var($cliente['email'], FILTER_VALIDATE_EMAIL)) {
            try {
                $mail->clearAllRecipients();
                $mail->addAddress($cliente['email'], $cliente['nome']);
                $mail->isHTML(true);

                if ($status_instalacao === 'Instalação Concluída') {
                    $mail->Subject = 'Instalação Concluída - FireNet Telecom';
                    $mail->Body = "<h2>Olá, {$cliente['nome']}!</h2><p>Temos uma ótima notícia: a instalação do seu plano FireNet Telecom foi concluída com sucesso no dia $data_instalacao!</p><p>Agora você pode aproveitar a melhor internet da região, com velocidade e qualidade que só a FireNet oferece.</p><p>Se precisar de suporte ou tiver qualquer dúvida, é só nos chamar pelo WhatsApp: <a href='https://wa.me/5521997123987'>(21) 99712-3987</a>.</p><p>Bem-vindo(a) à nossa família!<br>Equipe FireNet Telecom</p>";
                    $mail->AltBody = "Olá, {$cliente['nome']}!\n\nTemos uma ótima notícia: a instalação do seu plano FireNet Telecom foi concluída com sucesso no dia $data_instalacao!\nAgora você pode aproveitar a melhor internet da região, com velocidade e qualidade que só a FireNet oferece.\nSe precisar de suporte ou tiver qualquer dúvida, é só nos chamar pelo WhatsApp: (21) 99712-3987.\n\nBem-vindo(a) à nossa família!\nEquipe FireNet Telecom";
                } elseif ($status_instalacao === 'Instalação Não Realizada') {
                    $mail->Subject = 'Instalação Não Realizada - FireNet Telecom';
                    $mail->Body = "<h2>Olá, {$cliente['nome']}!</h2><p>Infelizmente, a instalação do seu plano FireNet Telecom, agendada para o dia $data_instalacao, não pôde ser realizada.</p><p>Por favor, entre em contato conosco pelo WhatsApp <a href='https://wa.me/5521997123987'>(21) 99712-3987</a> para reagendar ou esclarecer qualquer dúvida.</p><p>Estamos à disposição para ajudá-lo(a)!<br>Equipe FireNet Telecom</p>";
                    $mail->AltBody = "Olá, {$cliente['nome']}!\n\nInfelizmente, a instalação do seu plano FireNet Telecom, agendada para o dia $data_instalacao, não pôde ser realizada.\nPor favor, entre em contato conosco pelo WhatsApp (21) 99712-3987 para reagendar ou esclarecer qualquer dúvida.\n\nEstamos à disposição para ajudá-lo(a)!\nEquipe FireNet Telecom";
                } else {
                    $mail->Subject = 'Agendamento de Instalação - FireNet Telecom';
                    $mail->Body = "<h2>Olá, {$cliente['nome']}!</h2><p>Estamos entrando em contato para informar que a instalação do seu plano FireNet Telecom foi agendada.</p><p><strong>Detalhes do Agendamento:</strong></p><ul><li><strong>Data:</strong> $data_instalacao</li><li><strong>Turno:</strong> " . ($turno_instalacao ?: 'Não especificado') . "</li><li><strong>Status:</strong> " . ($status_instalacao ?: 'Não definido') . "</li></ul><p>Se precisar de mais informações ou quiser ajustar o agendamento, entre em contato conosco pelo WhatsApp: <a href='https://wa.me/5521997123987'>(21) 99712-3987</a>.</p><p>Atenciosamente,<br>Equipe FireNet Telecom</p>";
                    $mail->AltBody = "Olá, {$cliente['nome']}!\n\nEstamos entrando em contato para informar que a instalação do seu plano FireNet Telecom foi agendada.\n\nDetalhes do Agendamento:\n- Data: $data_instalacao\n- Turno: " . ($turno_instalacao ?: 'Não especificado') . "\n- Status: " . ($status_instalacao ?: 'Não definido') . "\n\nSe precisar de mais informações ou quiser ajustar o agendamento, entre em contato conosco pelo WhatsApp: (21) 99712-3987.\n\nAtenciosamente,\nEquipe FireNet Telecom";
                }
                $mail->send();
            } catch (Exception $e) {
                if ($DEBUG_LOGS) {
                    error_log("Erro ao enviar e-mail para o cliente: " . $mail->ErrorInfo);
                }
                echo json_encode(['success' => true, 'message' => "Dados de instalação atualizados com sucesso, mas houve um erro ao enviar o e-mail: " . $e->getMessage()]);
                exit;
            }
        }
        echo json_encode(['success' => true, 'message' => "Dados de instalação atualizados com sucesso!"]);
        exit;
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => "Erro ao atualizar os dados: " . $e->getMessage()]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_instalacao'])) {
    if ($permissoes !== 'excluir') {
        $error = "Você não tem permissão para editar dados de instalação.";
    } else {
        $cliente_id = (int)$_POST['cliente_id'];
        $data_instalacao = $_POST['data_instalacao'] ? DateTime::createFromFormat('Y-m-d', $_POST['data_instalacao'])->format('d/m/Y') : null;
        $turno_instalacao = $_POST['turno_instalacao'] ?: null;
        $status_instalacao = $_POST['status_instalacao'] ?: null;
        $observacoes = trim($_POST['observacoes']) ?: null;

        try {
            $stmt = $pdo->prepare("SELECT nome, data_instalacao, turno_instalacao, status_instalacao, observacoes FROM cadastros WHERE id = ?");
            $stmt->execute([$cliente_id]);
            $cliente_antigo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$cliente_antigo) {
                $error = "Cliente não encontrado.";
            } else {
                $cliente_nome = $cliente_antigo['nome'];
                $data_instalacao_antiga = $cliente_antigo['data_instalacao'];
                $turno_instalacao_antigo = $cliente_antigo['turno_instalacao'];
                $status_instalacao_antigo = $cliente_antigo['status_instalacao'];
                $observacoes_antiga = $cliente_antigo['observacoes'];

                $stmt = $pdo->prepare("UPDATE cadastros SET data_instalacao = ?, turno_instalacao = ?, status_instalacao = ?, observacoes = ? WHERE id = ?");
                $stmt->execute([$data_instalacao, $turno_instalacao, $status_instalacao, $observacoes, $cliente_id]);
                $success = "Dados de instalação atualizados com sucesso!";

                $log_entries = [];
                if ($data_instalacao !== $data_instalacao_antiga) {
                    $log_entries[] = "Alterou a data de instalação do cliente '$cliente_nome' de '" . ($data_instalacao_antiga ?? 'Não definida') . "' para '" . ($data_instalacao ?? 'Não definida') . "'";
                }
                if ($turno_instalacao !== $turno_instalacao_antigo) {
                    $log_entries[] = "Alterou o turno de instalação do cliente '$cliente_nome' de '" . ($turno_instalacao_antigo ?? 'Não definido') . "' para '" . ($turno_instalacao ?? 'Não definido') . "'";
                }
                if ($status_instalacao !== $status_instalacao_antigo) {
                    $log_entries[] = "Alterou o status de instalação do cliente '$cliente_nome' de '" . ($status_instalacao_antigo ?? 'Não definido') . "' para '" . ($status_instalacao ?? 'Não definido') . "'";
                }
                if ($observacoes !== $observacoes_antiga) {
                    $log_entries[] = "Alterou as observações do cliente '$cliente_nome' de '" . ($observacoes_antiga ?? 'Nenhuma') . "' para '" . ($observacoes ?? 'Nenhuma') . "'";
                }

                foreach ($log_entries as $entry) {
                    registrarLog($pdo, $user_id, $entry);
                }

                $stmt = $pdo->prepare("SELECT nome, email FROM cadastros WHERE id = ?");
                $stmt->execute([$cliente_id]);
                $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($cliente && $data_instalacao && filter_var($cliente['email'], FILTER_VALIDATE_EMAIL)) {
                    try {
                        $mail->clearAllRecipients();
                        $mail->addAddress($cliente['email'], $cliente['nome']);
                        $mail->isHTML(true);

                        if ($status_instalacao === 'Instalação Concluída') {
                            $mail->Subject = 'Instalação Concluída - FireNet Telecom';
                            $mail->Body = "<h2>Olá, {$cliente['nome']}!</h2><p>Temos uma ótima notícia: a instalação do seu plano FireNet Telecom foi concluída com sucesso no dia $data_instalacao!</p><p>Agora você pode aproveitar a melhor internet da região, com velocidade e qualidade que só a FireNet oferece.</p><p>Se precisar de suporte ou tiver qualquer dúvida, é só nos chamar pelo WhatsApp: <a href='https://wa.me/5521997123987'>(21) 99712-3987</a>.</p><p>Bem-vindo(a) à nossa família!<br>Equipe FireNet Telecom</p>";
                            $mail->AltBody = "Olá, {$cliente['nome']}!\n\nTemos uma ótima notícia: a instalação do seu plano FireNet Telecom foi concluída com sucesso no dia $data_instalacao!\nAgora você pode aproveitar a melhor internet da região, com velocidade e qualidade que só a FireNet oferece.\nSe precisar de suporte ou tiver qualquer dúvida, é só nos chamar pelo WhatsApp: (21) 99712-3987.\n\nBem-vindo(a) à nossa família!\nEquipe FireNet Telecom";
                        } elseif ($status_instalacao === 'Instalação Não Realizada') {
                            $mail->Subject = 'Instalação Não Realizada - FireNet Telecom';
                            $mail->Body = "<h2>Olá, {$cliente['nome']}!</h2><p>Infelizmente, a instalação do seu plano FireNet Telecom, agendada para o dia $data_instalacao, não pôde ser realizada.</p><p>Por favor, entre em contato conosco pelo WhatsApp <a href='https://wa.me/5521997123987'>(21) 99712-3987</a> para reagendar ou esclarecer qualquer dúvida.</p><p>Estamos à disposição para ajudá-lo(a)!<br>Equipe FireNet Telecom</p>";
                            $mail->AltBody = "Olá, {$cliente['nome']}!\n\nInfelizmente, a instalação do seu plano FireNet Telecom, agendada para o dia $data_instalacao, não pôde ser realizada.\nPor favor, entre em contato conosco pelo WhatsApp (21) 99712-3987 para reagendar ou esclarecer qualquer dúvida.\n\nEstamos à disposição para ajudá-lo(a)!\nEquipe FireNet Telecom";
                        } else {
                            $mail->Subject = 'Agendamento de Instalação - FireNet Telecom';
                            $mail->Body = "<h2>Olá, {$cliente['nome']}!</h2><p>Estamos entrando em contato para informar que a instalação do seu plano FireNet Telecom foi agendada.</p><p><strong>Detalhes do Agendamento:</strong></p><ul><li><strong>Data:</strong> $data_instalacao</li><li><strong>Turno:</strong> " . ($turno_instalacao ?: 'Não especificado') . "</li><li><strong>Status:</strong> " . ($status_instalacao ?: 'Não definido') . "</li></ul><p>Se precisar de mais informações ou quiser ajustar o agendamento, entre em contato conosco pelo WhatsApp: <a href='https://wa.me/5521997123987'>(21) 99712-3987</a>.</p><p>Atenciosamente,<br>Equipe FireNet Telecom</p>";
                            $mail->AltBody = "Olá, {$cliente['nome']}!\n\nEstamos entrando em contato para informar que a instalação do seu plano FireNet Telecom foi agendada.\n\nDetalhes do Agendamento:\n- Data: $data_instalacao\n- Turno: " . ($turno_instalacao ?: 'Não especificado') . "\n- Status: " . ($status_instalacao ?: 'Não definido') . "\n\nSe precisar de mais informações ou quiser ajustar o agendamento, entre em contato conosco pelo WhatsApp: (21) 99712-3987.\n\nAtenciosamente,\nEquipe FireNet Telecom";
                        }
                        $mail->send();
                    } catch (Exception $e) {
                        if ($DEBUG_LOGS) {
                            error_log("Erro ao enviar e-mail para o cliente: " . $mail->ErrorInfo);
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "Erro ao atualizar os dados: " . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_cliente'])) {
    if ($permissoes !== 'excluir') {
        $error = "Você não tem permissão para excluir clientes.";
    } else {
        try {
            $cliente_id = (int)$_POST['cliente_id'];
            $stmt = $pdo->prepare("SELECT nome FROM cadastros WHERE id = ?");
            $stmt->execute([$cliente_id]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($cliente) {
                $cliente_nome = $cliente['nome'];
                $stmt = $pdo->prepare("DELETE FROM cadastros WHERE id = ?");
                $stmt->execute([$cliente_id]);
                $success = "Cliente excluído com sucesso!";
                registrarLog($pdo, $user_id, "Excluiu o cliente: '$cliente_nome' (ID: $cliente_id)");
                header('Location: clientes.php');
                exit();
            } else {
                $error = "Cliente não encontrado.";
            }
        } catch (PDOException $e) {
            $error = "Erro ao excluir cliente: " . $e->getMessage();
        }
    }
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM cadastros WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    $total_novos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $search_cpf = isset($_GET['search_cpf']) ? trim($_GET['search_cpf']) : '';
    $search_nome = isset($_GET['search_nome']) ? trim($_GET['search_nome']) : '';
    $order_by = isset($_GET['order_by']) && in_array($_GET['order_by'], ['data_cadastro', 'data_instalacao', 'nome']) ? $_GET['order_by'] : 'data_cadastro';
    $direction = isset($_GET['direction']) && in_array(strtoupper($_GET['direction']), ['ASC', 'DESC']) ? strtoupper($_GET['direction']) : 'DESC';
    $clientes = [];

    // Construir a query com base nos filtros de busca
    $query_params = [];
    $where_conditions = [];

    if (!empty($search_cpf)) {
        $search_cpf_clean = preg_replace('/[^0-9]/', '', $search_cpf);
        $where_conditions[] = "REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') LIKE ?";
        $query_params[] = '%' . $search_cpf_clean . '%';
    }

    if (!empty($search_nome)) {
        $where_conditions[] = "nome LIKE ?";
        $query_params[] = '%' . $search_nome . '%';
    }

    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    if ($order_by === 'data_cadastro') {
        $stmt = $pdo->prepare("SELECT * FROM cadastros $where_clause ORDER BY created_at $direction");
    } elseif ($order_by === 'data_instalacao') {
        $stmt = $pdo->prepare("SELECT * FROM cadastros $where_clause ORDER BY STR_TO_DATE(data_instalacao, '%d/%m/%Y') $direction");
    } else {
        $stmt = $pdo->prepare("SELECT * FROM cadastros $where_clause ORDER BY nome $direction");
    }

    $stmt->execute($query_params);
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Logs para depuração, condicionados pela variável $DEBUG_LOGS
    if ($DEBUG_LOGS) {
        error_log('Total de clientes encontrados: ' . count($clientes));
        error_log('Permissões do usuário: ' . $permissoes);
        error_log('Query executada: ' . "SELECT * FROM cadastros $where_clause ORDER BY created_at $direction");
        error_log('Parâmetros: ' . print_r($query_params, true));
    }
} catch (PDOException $e) {
    $error = "Erro ao consultar dados: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes - FireNet Telecom</title>
    <link rel="stylesheet" href="/css/clientes.css?v=1">
    <script>
        // Passa a variável de debug do PHP para o JavaScript
        window.DEBUG_LOGS = <?php echo json_encode($DEBUG_LOGS); ?>;
    </script>
    <script src="/js/clientes.js?v=1"></script>
</head>
<body>
    <?php include 'topo.php'; ?>

    <div class="container">
        <p class="welcome-message">Bem-vindo, <?php echo htmlspecialchars($username); ?> (<?php echo $permissoes === 'excluir' ? 'Administrador' : 'Usuário Comum'; ?>)</p>
        <?php if (!empty($success)): ?>
            <div class="message success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="summary">
            <h2>Resumo</h2>
            <p>Novos clientes (últimos 30 dias): <strong><?php echo $total_novos; ?></strong></p>
        </div>

        <div class="client-list">
            <h2>Lista de Clientes</h2>
            <form class="search-form" method="GET" action="">
                <label for="search_cpf">Buscar por CPF:</label>
                <input type="text" id="search_cpf" name="search_cpf" value="<?php echo htmlspecialchars($search_cpf); ?>" placeholder="Digite o CPF" maxlength="14">
                <label for="search_nome">Buscar por Nome:</label>
                <input type="text" id="search_nome" name="search_nome" value="<?php echo htmlspecialchars($search_nome); ?>" placeholder="Digite o Nome">
                <button type="submit">Buscar</button>
            </form>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Ações</th>
                            <th>
                                <a href="?order_by=data_cadastro&direction=<?php echo ($order_by === 'data_cadastro' && $direction === 'ASC') ? 'DESC' : 'ASC'; ?><?php echo !empty($search_cpf) ? '&search_cpf=' . urlencode($search_cpf) : ''; ?><?php echo !empty($search_nome) ? '&search_nome=' . urlencode($search_nome) : ''; ?>" class="sort-link <?php echo $order_by === 'data_cadastro' ? 'sort-active' : ''; ?>">
                                    Data de Cadastro
                                    <svg class="sort-icon" viewBox="0 0 24 24">
                                        <?php if ($order_by === 'data_cadastro' && $direction === 'ASC'): ?>
                                            <path d="M12 2L4 10H20L12 2Z"/>
                                        <?php else: ?>
                                            <path d="M12 22L20 14H4L12 22Z"/>
                                        <?php endif; ?>
                                    </svg>
                                </a>
                            </th>
                            <th>
                                <a href="?order_by=data_instalacao&direction=<?php echo ($order_by === 'data_instalacao' && $direction === 'ASC') ? 'DESC' : 'ASC'; ?><?php echo !empty($search_cpf) ? '&search_cpf=' . urlencode($search_cpf) : ''; ?><?php echo !empty($search_nome) ? '&search_nome=' . urlencode($search_nome) : ''; ?>" class="sort-link <?php echo $order_by === 'data_instalacao' ? 'sort-active' : ''; ?>">
                                    Data Instalação
                                    <svg class="sort-icon" viewBox="0 0 24 24">
                                        <?php if ($order_by === 'data_instalacao' && $direction === 'ASC'): ?>
                                            <path d="M12 2L4 10H20L12 2Z"/>
                                        <?php else: ?>
                                            <path d="M12 22L20 14H4L12 22Z"/>
                                        <?php endif; ?>
                                    </svg>
                                </a>
                            </th>
                            <?php if ($permissoes === 'excluir'): ?>
                                <th class="edit-column">Editar</th>
                            <?php endif; ?>
                            <th>Turno</th>
                            <th>Status</th>
                            <th>
                                <a href="?order_by=nome&direction=<?php echo ($order_by === 'nome' && $direction === 'ASC') ? 'DESC' : 'ASC'; ?><?php echo !empty($search_cpf) ? '&search_cpf=' . urlencode($search_cpf) : ''; ?><?php echo !empty($search_nome) ? '&search_nome=' . urlencode($search_nome) : ''; ?>" class="sort-link <?php echo $order_by === 'nome' ? 'sort-active' : ''; ?>">
                                    Nome
                                    <svg class="sort-icon" viewBox="0 0 24 24">
                                        <?php if ($order_by === 'nome' && $direction === 'ASC'): ?>
                                            <path d="M12 2L4 10H20L12 2Z"/>
                                        <?php else: ?>
                                            <path d="M12 22L20 14H4L12 22Z"/>
                                        <?php endif; ?>
                                    </svg>
                                </a>
                            </th>
                            <th>CPF</th>
                            <th>Telefone</th>
                            <th>Plano</th>
                            <th>Observações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes as $cliente): ?>
                            <tr>
                                <td>
                                    <div class="actions action-icons">
                                        <a href="imprimir-cliente.php?id=<?php echo $cliente['id']; ?>" target="_blank" title="Imprimir Ficha">🖨️</a>
                                        <?php if ($permissoes === 'excluir'): ?>
                                            <a href="editar-cliente.php?id=<?php echo $cliente['id']; ?>" title="Editar Cliente">✏️</a>
                                            <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja excluir este cliente?');">
                                                <input type="hidden" name="excluir_cliente" value="1">
                                                <input type="hidden" name="cliente_id" value="<?php echo $cliente['id']; ?>">
                                                <button type="submit" class="delete-btn" title="Excluir Cliente">🗑️</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $created_at = $cliente['created_at'] ? (new DateTime($cliente['created_at']))->format('d/m/Y') : 'Não definida';
                                    echo htmlspecialchars($created_at);
                                    ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($cliente['data_instalacao'] ?? 'Não definida'); ?>
                                </td>
                                <?php if ($permissoes === 'excluir'): ?>
                                    <td class="edit-column">
                                        <button class="edit-btn" data-modal="modal-<?php echo $cliente['id']; ?>">Editar</button>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <?php echo htmlspecialchars($cliente['turno_instalacao'] ?? 'Não definido'); ?>
                                </td>
                                <td>
                                    <?php
                                    if (empty($cliente['data_instalacao'])) {
                                        echo '<span class="status-nao-definido">Não definido</span>';
                                    } else {
                                        $status_class = '';
                                        switch ($cliente['status_instalacao']) {
                                            case 'Instalação Programada':
                                                $status_class = 'status-programada';
                                                break;
                                            case 'Instalação Concluída':
                                                $status_class = 'status-concluida';
                                                break;
                                            case 'Instalação Não Realizada':
                                                $status_class = 'status-nao-realizada';
                                                break;
                                        }
                                        echo '<span class="' . $status_class . '">' . htmlspecialchars($cliente['status_instalacao'] ?? 'Não definido') . '</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($cliente['nome']); ?></td>
                                <td><?php echo htmlspecialchars($cliente['cpf']); ?></td>
                                <td><?php echo htmlspecialchars($cliente['telefone']); ?></td>
                                <td><?php echo htmlspecialchars($cliente['plano']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($cliente['observacoes'] ?? 'Nenhuma'); ?>
                                </td>
                            </tr>
                            <?php if ($permissoes === 'excluir'): ?>
                                <div class="modal" id="modal-<?php echo $cliente['id']; ?>">
                                    <div class="modal-content">
                                        <button class="close-btn">✖</button>
                                        <h3>Editar Dados de Instalação</h3>
                                        <form method="POST" action="">
                                            <input type="hidden" name="atualizar_instalacao" value="1">
                                            <input type="hidden" name="cliente_id" value="<?php echo $cliente['id']; ?>">
                                            <label for="data_instalacao-<?php echo $cliente['id']; ?>">Data de Instalação:</label>
                                            <input type="date" id="data_instalacao-<?php echo $cliente['id']; ?>" name="data_instalacao" value="<?php
                                                echo $cliente['data_instalacao'] && preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $cliente['data_instalacao'])
                                                    ? (new DateTime(str_replace('/', '-', $cliente['data_instalacao'])))->format('Y-m-d')
                                                    : ($cliente['data_instalacao'] ?? '');
                                            ?>">
                                            <label for="turno_instalacao-<?php echo $cliente['id']; ?>">Turno:</label>
                                            <select id="turno_instalacao-<?php echo $cliente['id']; ?>" name="turno_instalacao">
                                                <option value="">Selecione</option>
                                                <option value="Manhã" <?php echo $cliente['turno_instalacao'] === 'Manhã' ? 'selected' : ''; ?>>Manhã</option>
                                                <option value="Tarde" <?php echo $cliente['turno_instalacao'] === 'Tarde' ? 'selected' : ''; ?>>Tarde</option>
                                                <option value="Horário Comercial" <?php echo $cliente['turno_instalacao'] === 'Horário Comercial' ? 'selected' : ''; ?>>Horário Comercial</option>
                                            </select>
                                            <label for="status_instalacao-<?php echo $cliente['id']; ?>">Status:</label>
                                            <select id="status_instalacao-<?php echo $cliente['id']; ?>" name="status_instalacao">
                                                <option value="">Selecione</option>
                                                <option value="Instalação Programada" <?php echo $cliente['status_instalacao'] === 'Instalação Programada' ? 'selected' : ''; ?>>Instalação Programada</option>
                                                <option value="Instalação Concluída" <?php echo $cliente['status_instalacao'] === 'Instalação Concluída' ? 'selected' : ''; ?>>Instalação Concluída</option>
                                                <option value="Instalação Não Realizada" <?php echo $cliente['status_instalacao'] === 'Instalação Não Realizada' ? 'selected' : ''; ?>>Instalação Não Realizada</option>
                                            </select>
                                            <label for="observacoes-<?php echo $cliente['id']; ?>">Observações:</label>
                                            <textarea id="observacoes-<?php echo $cliente['id']; ?>" name="observacoes"><?php echo htmlspecialchars($cliente['observacoes'] ?? ''); ?></textarea>
                                            <button type="submit">Salvar</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
