<?php
// gerar-relatorio.php

require_once 'config.php';

$quantidade = 3; // Quantidade fixa
$vencimento_boleto = '';
$clientes = [];
$error = '';
$identificador = '';

// Verifica se é uma requisição para visualizar relatório antigo (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['identificador'])) {
    $identificador = $_GET['identificador'];
    try {
        $stmt = $pdo->prepare("SELECT data_vencimento, clientes_ids FROM relatorios WHERE identificador = ?");
        $stmt->execute([$identificador]);
        $relatorio = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$relatorio) {
            $error = "Relatório não encontrado.";
        } else {
            $vencimento_boleto = $relatorio['data_vencimento'];
            $clientes_ids = explode(',', $relatorio['clientes_ids']);
            $placeholders = rtrim(str_repeat('?,', count($clientes_ids)), ',');
            $stmt = $pdo->prepare("
                SELECT nome, cpf, rua, numero, complemento, bairro, cep, data_instalacao 
                FROM cadastros 
                WHERE id IN ($placeholders)
                ORDER BY STR_TO_DATE(data_instalacao, '%d/%m/%Y') DESC
            ");
            $stmt->execute($clientes_ids);
            $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $error = "Erro ao carregar relatório: " . $e->getMessage();
    }
}

// Verifica se é uma requisição para gerar novo relatório (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gerar_relatorio'])) {
    $mes = isset($_POST['mes']) ? (int)$_POST['mes'] : 0;
    $ano = isset($_POST['ano']) ? (int)$_POST['ano'] : 0;

    // Valida mês e ano
    if ($mes < 1 || $mes > 12) {
        $error = "Por favor, selecione um mês válido.";
    } elseif ($ano < date('Y') || $ano > (date('Y') + 4)) {
        $error = "Por favor, selecione um ano válido.";
    } else {
        // Verifica se o mês/ano é posterior ao último vencimento
        try {
            $stmt = $pdo->query("SELECT data_vencimento FROM relatorios ORDER BY created_at DESC LIMIT 1");
            $ultimo_relatorio = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($ultimo_relatorio) {
                $ultimo_date = DateTime::createFromFormat('d/m/Y', $ultimo_relatorio['data_vencimento']);
                $selecionado_date = DateTime::createFromFormat('d/m/Y', "01/$mes/$ano");
                if ($selecionado_date <= $ultimo_date) {
                    $error = "O mês selecionado deve ser posterior ao último vencimento registrado (" . $ultimo_date->format('m/Y') . ").";
                }
            }
        } catch (PDOException $e) {
            $error = "Erro ao verificar último vencimento: " . $e->getMessage();
        }
    }

    if (empty($error)) {
        $vencimento_boleto = sprintf("05/%02d/%04d", $mes, $ano);

        // Busca instalações disponíveis
        try {
            $stmt = $pdo->prepare("
                SELECT c.id, c.nome, c.cpf, c.rua, c.numero, c.complemento, c.bairro, c.cep, c.data_instalacao 
                FROM cadastros c
                LEFT JOIN relatorios r ON FIND_IN_SET(c.id, r.clientes_ids)
                WHERE c.status_instalacao = 'Instalação Concluída' AND c.data_instalacao IS NOT NULL AND r.id IS NULL
                ORDER BY STR_TO_DATE(c.data_instalacao, '%d/%m/%Y') DESC
                LIMIT ?
            ");
            $stmt->bindValue(1, $quantidade, PDO::PARAM_INT);
            $stmt->execute();
            $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($clientes) < $quantidade) {
                $error = "Não é possível gerar o relatório porque as últimas três instalações ainda não foram concluídas.";
            } else {
                // Gera identificador sequencial
                $stmt = $pdo->query("SELECT MAX(identificador) as ultimo FROM relatorios");
                $ultimo = $stmt->fetch(PDO::FETCH_ASSOC)['ultimo'];
                $identificador = sprintf("%04d", $ultimo ? (int)$ultimo + 1 : 1);

                // Salva o relatório na tabela
                $clientes_ids = implode(',', array_column($clientes, 'id'));
                $stmt = $pdo->prepare("INSERT INTO relatorios (identificador, data_vencimento, clientes_ids) VALUES (?, ?, ?)");
                $stmt->execute([$identificador, $vencimento_boleto, $clientes_ids]);

                // Registra a geração do relatório em access_logs
                $log_message = sprintf(
                    "Gerou o relatório '%s' com vencimento %s, clientes IDs=%s",
                    $identificador,
                    $vencimento_boleto,
                    $clientes_ids
                );
                registrarLog($pdo, $_SESSION['user_id'], $log_message);
            }
        } catch (PDOException $e) {
            $error = "Erro ao gerar relatório: " . $e->getMessage();
        }
    }
}
?>
