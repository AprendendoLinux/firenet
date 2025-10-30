<?php
// editar-cliente.php

// Inclui o arquivo de configuração, autenticação e topo
require_once 'config.php';
require_once 'auth.php';
require_once 'topo.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Verifica permissões
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT permissoes FROM usuarios WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$permissoes = $user['permissoes'];

if ($permissoes !== 'excluir') {
    header('Location: clientes.php');
    exit();
}

// Verifica se o ID do cliente foi fornecido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: clientes.php');
    exit();
}

$cliente_id = (int)$_GET['id'];
$success = '';
$error = '';

try {
    // Busca os dados atuais do cliente
    $stmt = $pdo->prepare("SELECT * FROM cadastros WHERE id = ?");
    $stmt->execute([$cliente_id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cliente) {
        header('Location: clientes.php');
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_cliente'])) {
        // Campos editáveis
        $nome = trim($_POST['nome']) ?: null;
        $cpf = trim($_POST['cpf']) ?: null;
        $rg = trim($_POST['rg']) ?: null;
        $data_nascimento = $_POST['data_nascimento'] ? DateTime::createFromFormat('Y-m-d', $_POST['data_nascimento'])->format('d/m/Y') : null;
        $telefone = trim($_POST['telefone']) ?: null;
        $whatsapp = $_POST['whatsapp'] === 'Sim' ? 'Sim' : 'Não';
        $email = trim($_POST['email']) ?: null;
        $cep = trim($_POST['cep']) ?: null;
        $rua = trim($_POST['rua']) ?: null;
        $numero = trim($_POST['numero']) ?: null;
        $complemento = trim($_POST['complemento']) ?: null;
        $ponto_referencia = trim($_POST['ponto_referencia']) ?: null;
        $bairro = trim($_POST['bairro']) ?: null;
        $plano = $_POST['plano'] ?: null;
        $vencimento = $_POST['vencimento'] ?: null;
        $nome_rede = trim($_POST['nome_rede']) ?: null;
        $senha_rede = trim($_POST['senha_rede']) ?: null;

        // Validações básicas
        if (empty($nome)) {
            $error = "O nome é obrigatório.";
        } elseif (empty($cpf) || !preg_match('/^\d{3}\.\d{3}\.\d{3}-\d{2}$/', $cpf)) {
            $error = "O CPF é obrigatório e deve estar no formato 123.456.789-00.";
        } elseif ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "O e-mail informado não é válido.";
        } elseif ($cep && !preg_match('/^\d{5}-\d{3}$/', $cep)) {
            $error = "O CEP deve estar no formato 12345-678.";
        } elseif ($vencimento && !in_array($vencimento, ['Dia 5', 'Dia 10', 'Dia 15'])) {
            $error = "O dia de vencimento deve ser 'Dia 5', 'Dia 10' ou 'Dia 15'.";
        } elseif ($plano && !in_array($plano, ['800 MEGA', '700 MEGA', '600 MEGA', '500 MEGA'])) {
            $error = "O plano deve ser '800 MEGA', '700 MEGA', '600 MEGA' ou '500 MEGA'.";
        } else {
            // Compara valores antigos e novos pra registrar logs
            $log_entries = [];
            if ($nome !== $cliente['nome']) {
                $log_entries[] = "Alterou o campo 'nome' do cliente de '" . $cliente['nome'] . "' para '$nome'";
            }
            if ($cpf !== $cliente['cpf']) {
                $log_entries[] = "Alterou o campo 'cpf' do cliente de '" . $cliente['cpf'] . "' para '$cpf'";
            }
            if ($rg !== $cliente['rg']) {
                $log_entries[] = "Alterou o campo 'rg' do cliente de '" . ($cliente['rg'] ?? 'Não definido') . "' para '" . ($rg ?? 'Não definido') . "'";
            }
            if ($data_nascimento !== $cliente['data_nascimento']) {
                $log_entries[] = "Alterou o campo 'data de nascimento' do cliente de '" . ($cliente['data_nascimento'] ?? 'Não definida') . "' para '" . ($data_nascimento ?? 'Não definida') . "'";
            }
            if ($telefone !== $cliente['telefone']) {
                $log_entries[] = "Alterou o campo 'telefone' do cliente de '" . ($cliente['telefone'] ?? 'Não definido') . "' para '" . ($telefone ?? 'Não definido') . "'";
            }
            if ($whatsapp !== $cliente['whatsapp']) {
                $log_entries[] = "Alterou o campo 'whatsapp' do cliente de '" . ($cliente['whatsapp'] ?? 'Não definido') . "' para '$whatsapp'";
            }
            if ($email !== $cliente['email']) {
                $log_entries[] = "Alterou o campo 'email' do cliente de '" . ($cliente['email'] ?? 'Não definido') . "' para '" . ($email ?? 'Não definido') . "'";
            }
            if ($cep !== $cliente['cep']) {
                $log_entries[] = "Alterou o campo 'CEP' do cliente de '" . ($cliente['cep'] ?? 'Não definido') . "' para '" . ($cep ?? 'Não definido') . "'";
            }
            if ($rua !== $cliente['rua']) {
                $log_entries[] = "Alterou o campo 'rua' do cliente de '" . ($cliente['rua'] ?? 'Não definida') . "' para '" . ($rua ?? 'Não definida') . "'";
            }
            if ($numero !== $cliente['numero']) {
                $log_entries[] = "Alterou o campo 'número' do cliente de '" . ($cliente['numero'] ?? 'Não definido') . "' para '" . ($numero ?? 'Não definido') . "'";
            }
            if ($complemento !== $cliente['complemento']) {
                $log_entries[] = "Alterou o campo 'complemento' do cliente de '" . ($cliente['complemento'] ?? 'Não definido') . "' para '" . ($complemento ?? 'Não definido') . "'";
            }
            if ($ponto_referencia !== $cliente['ponto_referencia']) {
                $log_entries[] = "Alterou o campo 'ponto de referência' do cliente de '" . ($cliente['ponto_referencia'] ?? 'Não definido') . "' para '" . ($ponto_referencia ?? 'Não definido') . "'";
            }
            if ($bairro !== $cliente['bairro']) {
                $log_entries[] = "Alterou o campo 'bairro' do cliente de '" . ($cliente['bairro'] ?? 'Não definido') . "' para '" . ($bairro ?? 'Não definido') . "'";
            }
            if ($plano !== $cliente['plano']) {
                $log_entries[] = "Alterou o campo 'plano' do cliente de '" . ($cliente['plano'] ?? 'Não definido') . "' para '" . ($plano ?? 'Não definido') . "'";
            }
            if ($vencimento !== $cliente['vencimento']) {
                $log_entries[] = "Alterou o campo 'dia de vencimento' do cliente de '" . ($cliente['vencimento'] ?? 'Não definido') . "' para '" . ($vencimento ?? 'Não definido') . "'";
            }
            if ($nome_rede !== $cliente['nome_rede']) {
                $log_entries[] = "Alterou o campo 'nome da rede' do cliente de '" . ($cliente['nome_rede'] ?? 'Não definido') . "' para '" . ($nome_rede ?? 'Não definido') . "'";
            }
            if ($senha_rede !== $cliente['senha_rede']) {
                $log_entries[] = "Alterou o campo 'senha da rede' do cliente de '" . ($cliente['senha_rede'] ?? 'Não definida') . "' para '" . ($senha_rede ?? 'Não definida') . "'";
            }

            // Atualiza os dados no banco
            $stmt = $pdo->prepare("UPDATE cadastros SET nome = ?, cpf = ?, rg = ?, data_nascimento = ?, telefone = ?, whatsapp = ?, email = ?, cep = ?, rua = ?, numero = ?, complemento = ?, ponto_referencia = ?, bairro = ?, plano = ?, vencimento = ?, nome_rede = ?, senha_rede = ? WHERE id = ?");
            $stmt->execute([$nome, $cpf, $rg, $data_nascimento, $telefone, $whatsapp, $email, $cep, $rua, $numero, $complemento, $ponto_referencia, $bairro, $plano, $vencimento, $nome_rede, $senha_rede, $cliente_id]);

            // Registra os logs
            foreach ($log_entries as $entry) {
                registrarLog($pdo, $user_id, $entry);
            }

            $success = "Dados do cliente atualizados com sucesso!";
            // Atualiza os dados do cliente pra exibição
            $stmt = $pdo->prepare("SELECT * FROM cadastros WHERE id = ?");
            $stmt->execute([$cliente_id]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) {
    $error = "Erro ao atualizar os dados: " . $e->getMessage();
}

// Função auxiliar pra converter data de d/m/Y pra Y-m-d, se válida (mantida pra data_nascimento)
function formatDateForInput($date) {
    if (!$date) {
        return '';
    }
    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
        try {
            return (new DateTime(str_replace('/', '-', $date)))->format('Y-m-d');
        } catch (Exception $e) {
            error_log("Erro ao converter data '$date': " . $e->getMessage());
            return '';
        }
    }
    return '';
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cliente - FireNet Telecom</title>
    <link rel="stylesheet" href="css/editar-cliente.css">
</head>
<body>
    <div class="container">
        <h2>Editar Cliente: <?php echo htmlspecialchars($cliente['nome']); ?></h2>

        <?php if (!empty($success)): ?>
            <div class="message success"><?php echo htmlspecialchars($success); ?></div>
            <script>
                setTimeout(function() {
                    window.location.href = 'clientes.php';
                }, 0600); // Redireciona após 2 segundos
            </script>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="atualizar_cliente" value="1">

            <!-- Dados Pessoais -->
            <label for="nome">Nome:</label>
            <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($cliente['nome']); ?>" required>

            <label for="cpf">CPF:</label>
            <input type="text" id="cpf" name="cpf" value="<?php echo htmlspecialchars($cliente['cpf']); ?>" maxlength="14" required>

            <label for="rg">RG:</label>
            <input type="text" id="rg" name="rg" value="<?php echo htmlspecialchars($cliente['rg'] ?? ''); ?>">

            <label for="data_nascimento">Data de Nascimento:</label>
            <input type="date" id="data_nascimento" name="data_nascimento" value="<?php echo formatDateForInput($cliente['data_nascimento']); ?>">

            <label for="telefone">Telefone:</label>
            <input type="text" id="telefone" name="telefone" value="<?php echo htmlspecialchars($cliente['telefone'] ?? ''); ?>">

            <label for="whatsapp">WhatsApp:</label>
            <select id="whatsapp" name="whatsapp">
                <option value="Sim" <?php echo ($cliente['whatsapp'] === 'Sim') ? 'selected' : ''; ?>>Sim</option>
                <option value="Não" <?php echo ($cliente['whatsapp'] === 'Não') ? 'selected' : ''; ?>>Não</option>
            </select>

            <label for="email">E-mail:</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($cliente['email'] ?? ''); ?>">

            <!-- Divisor -->
            <div class="divider"></div>

            <!-- Endereço -->
            <label for="cep">CEP:</label>
            <input type="text" id="cep" name="cep" value="<?php echo htmlspecialchars($cliente['cep'] ?? ''); ?>" maxlength="9">

            <label for="rua">Rua:</label>
            <input type="text" id="rua" name="rua" value="<?php echo htmlspecialchars($cliente['rua'] ?? ''); ?>">

            <label for="numero">Número:</label>
            <input type="text" id="numero" name="numero" value="<?php echo htmlspecialchars($cliente['numero'] ?? ''); ?>">

            <label for="complemento">Complemento:</label>
            <input type="text" id="complemento" name="complemento" value="<?php echo htmlspecialchars($cliente['complemento'] ?? ''); ?>">

            <label for="ponto_referencia">Ponto de Referência:</label>
            <input type="text" id="ponto_referencia" name="ponto_referencia" value="<?php echo htmlspecialchars($cliente['ponto_referencia'] ?? ''); ?>">

            <label for="bairro">Bairro:</label>
            <input type="text" id="bairro" name="bairro" value="<?php echo htmlspecialchars($cliente['bairro'] ?? ''); ?>">

            <label for="plano">Plano:</label>
            <select id="plano" name="plano">
                <option value="">Selecione</option>
                <option value="800 MEGA" <?php echo $cliente['plano'] === '800 MEGA' ? 'selected' : ''; ?>>800 MEGA</option>
                <option value="700 MEGA" <?php echo $cliente['plano'] === '700 MEGA' ? 'selected' : ''; ?>>700 MEGA</option>
                <option value="600 MEGA" <?php echo $cliente['plano'] === '600 MEGA' ? 'selected' : ''; ?>>600 MEGA</option>
                <option value="500 MEGA" <?php echo $cliente['plano'] === '500 MEGA' ? 'selected' : ''; ?>>500 MEGA</option>
            </select>

            <!-- Divisor -->
            <div class="divider"></div>

            <!-- Dados do Plano -->
            <label for="vencimento">Dia de Vencimento:</label>
            <select id="vencimento" name="vencimento">
                <option value="">Selecione</option>
                <option value="Dia 5" <?php echo $cliente['vencimento'] === 'Dia 5' ? 'selected' : ''; ?>>Dia 5</option>
                <option value="Dia 10" <?php echo $cliente['vencimento'] === 'Dia 10' ? 'selected' : ''; ?>>Dia 10</option>
                <option value="Dia 15" <?php echo $cliente['vencimento'] === 'Dia 15' ? 'selected' : ''; ?>>Dia 15</option>
            </select>

            <label for="nome_rede">Nome da Rede:</label>
            <input type="text" id="nome_rede" name="nome_rede" value="<?php echo htmlspecialchars($cliente['nome_rede'] ?? ''); ?>">

            <label for="senha_rede">Senha da Rede:</label>
            <input type="text" id="senha_rede" name="senha_rede" value="<?php echo htmlspecialchars($cliente['senha_rede'] ?? ''); ?>">

            <button type="submit">Salvar Alterações</button>
        </form>

        <a href="clientes.php" class="back-button">Cancelar</a>
    </div>

    <script src="js/editar-cliente.js"></script>
</body>
</html>
