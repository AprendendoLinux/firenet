<?php
// cadastro.php

// Inclui o arquivo de configuração do banco de dados e PHPMailer
require_once 'config.php';

// Configurações de cabeçalho para permitir requisições cross-origin
header('Access-Control-Allow-Origin: *'); // Ajuste para seu domínio em produção

// Função para sanitizar entradas
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Função para validar CPF
function validarCPF($cpf) {
    // Remove caracteres não numéricos
    $cpf = preg_replace('/\D/', '', $cpf);

    // Verifica se o CPF tem 11 dígitos
    if (strlen($cpf) !== 11) {
        return false;
    }

    // Verifica se todos os dígitos são iguais
    if (preg_match('/^(\d)\1+$/', $cpf)) {
        return false;
    }

    // Calcula os dígitos verificadores
    for ($t = 9; $t < 11; $t++) {
        $d = 0;
        for ($c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }

    return true;
}

try {
    // Verifica se a requisição é POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido. Use POST.');
    }

    // Captura e sanitiza os dados do formulário
    $nome = isset($_POST['nome']) ? sanitize(ucwords(strtolower(trim($_POST['nome'])))) : null;
    $cpf = isset($_POST['cpf']) ? sanitize($_POST['cpf']) : null;
    $rg = isset($_POST['rg']) ? sanitize($_POST['rg']) : null;
    $data_nascimento = isset($_POST['data_nascimento']) ? sanitize($_POST['data_nascimento']) : null;
    $telefone = isset($_POST['telefone']) ? sanitize($_POST['telefone']) : null;
    $whatsapp = isset($_POST['whatsapp']) ? sanitize($_POST['whatsapp']) : null;
    $email = isset($_POST['email']) ? filter_var($_POST['email'], FILTER_SANITIZE_EMAIL) : null;
    $cep = isset($_POST['cep']) ? sanitize($_POST['cep']) : null;
    $rua = isset($_POST['rua']) ? sanitize($_POST['rua']) : null;
    $numero = isset($_POST['numero']) ? sanitize($_POST['numero']) : null;
    $complemento = isset($_POST['complemento']) ? sanitize($_POST['complemento']) : null;
    $ponto_referencia = isset($_POST['ponto_referencia']) ? sanitize($_POST['ponto_referencia']) : null;
    $bairro = isset($_POST['bairro']) ? sanitize($_POST['bairro']) : null;
    $plano = isset($_POST['plano']) ? sanitize($_POST['plano']) : null;
    $vencimento = isset($_POST['vencimento']) ? sanitize($_POST['vencimento']) : null;
    $nome_rede = isset($_POST['nome_rede']) ? sanitize($_POST['nome_rede']) : null;
    $senha_rede = isset($_POST['senha_rede']) ? sanitize($_POST['senha_rede']) : null;
    $lgpd = isset($_POST['lgpd']) && $_POST['lgpd'] === 'Sim' ? 'Sim' : null;

    // Validações básicas
    if (!$nome || !$cpf || !$rg || !$data_nascimento || !$telefone || !$whatsapp || !$email || !$cep || !$rua || !$numero || !$ponto_referencia || !$bairro || !$plano || !$vencimento || !$nome_rede || !$senha_rede || !$lgpd) {
        throw new Exception('Todos os campos obrigatórios devem ser preenchidos.');
    }

    // Valida o CPF
    if (!validarCPF($cpf)) {
        throw new Exception('CPF inválido.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('E-mail inválido.');
    }

    if (!in_array($whatsapp, ['Sim', 'Não'])) {
        throw new Exception('Valor inválido para WhatsApp.');
    }

    if (!in_array($vencimento, ['Dia 5', 'Dia 10', 'Dia 15'])) {
        throw new Exception('Dia de vencimento inválido.');
    }

    if (!in_array($plano, ['500 MEGA', '600 MEGA', '700 MEGA', '800 MEGA'])) {
        throw new Exception('Plano inválido.');
    }

    // Valida a senha_rede (mínimo 8, máximo 10 caracteres)
    if (strlen($senha_rede) < 8 || strlen($senha_rede) > 10) {
        throw new Exception('A senha da rede deve ter entre 8 e 10 caracteres.');
    }

    // Converte a data de nascimento de AAAA-MM-DD para DD/MM/AAAA
    if ($data_nascimento) {
        $dateObj = DateTime::createFromFormat('Y-m-d', $data_nascimento);
        if ($dateObj === false) {
            throw new Exception('Data de nascimento inválida.');
        }
        $data_nascimento = $dateObj->format('d/m/Y');
    }

    // Preparar a query de inserção (sem cidade e estado)
    $stmt = $pdo->prepare("
        INSERT INTO cadastros (
            nome, cpf, rg, data_nascimento, telefone, whatsapp, email, cep, rua, numero,
            complemento, ponto_referencia, bairro, plano, vencimento, nome_rede, senha_rede, lgpd
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    // Executar a query com os dados sanitizados
    $stmt->execute([
        $nome,
        $cpf,
        $rg,
        $data_nascimento,
        $telefone,
        $whatsapp,
        $email,
        $cep,
        $rua,
        $numero,
        $complemento,
        $ponto_referencia,
        $bairro,
        $plano,
        $vencimento,
        $nome_rede,
        $senha_rede,
        $lgpd
    ]);

    // Enviar e-mail de boas-vindas para o cliente
    try {
        $mail->clearAllRecipients(); // Limpa os destinatários anteriores
        $mail->addAddress($email, $nome); // Adiciona o e-mail do cliente
        $mail->isHTML(true); // Define o formato do e-mail como HTML
        $mail->Subject = 'Bem-vindo(a) à FireNet Telecom!';
        $mail->Body = "
            <h2>Olá, $nome!</h2>
            <p>Seja bem-vindo(a) à FireNet Telecom! Estamos muito felizes por você fazer parte da nossa família.</p>
            <p>Sua inscrição foi realizada com sucesso, e em breve entraremos em contato para confirmar os próximos passos.</p>
            <p>Se precisar de ajuda, entre em contato conosco pelo WhatsApp: <a href='https://wa.me/5521997123987'>(21) 99712-3987</a>.</p>
            <p>Atenciosamente,<br>Equipe FireNet Telecom</p>
        ";
        $mail->AltBody = "Olá, $nome!\n\nSeja bem-vindo(a) à FireNet Telecom! Estamos muito felizes por você fazer parte da nossa família.\nSua inscrição foi realizada com sucesso, e em breve entraremos em contato para confirmar os próximos passos.\nSe precisar de ajuda, entre em contato conosco pelo WhatsApp: (21) 99712-3987.\n\nAtenciosamente,\nEquipe FireNet Telecom";
        $mail->send();
    } catch (Exception $e) {
        // Log do erro (opcional, para depuração)
        error_log("Erro ao enviar e-mail para o cliente: " . $mail->ErrorInfo);
        // Não interrompe o fluxo, apenas registra o erro
    }

    // Consultar todos os e-mails distintos dos usuários cadastrados na tabela 'usuarios'
    try {
        $stmt = $pdo->prepare("SELECT DISTINCT email FROM usuarios");
        $stmt->execute();
        $emails = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Enviar e-mail de notificação para cada usuário cadastrado
        foreach ($emails as $userEmail) {
            try {
                $mail->clearAllRecipients(); // Limpa os destinatários anteriores
                $mail->addAddress($userEmail); // Adiciona o e-mail do usuário
                $mail->isHTML(true); // Define o formato do e-mail como HTML
                $mail->Subject = 'Novo Cadastro na FireNet Telecom';
                $mail->Body = "
                    <h2>Novo Cadastro Realizado</h2>
                    <p>Um novo cliente se cadastrou na FireNet Telecom. Aqui estão os detalhes:</p>
                    <ul>
                        <li><strong>Nome:</strong> $nome</li>
                        <li><strong>E-mail:</strong> $email</li>
                        <li><strong>Telefone:</strong> $telefone</li>
                        <li><strong>Plano Escolhido:</strong> $plano</li>
                        <li><strong>Dia do Vencimento:</strong> $vencimento</li>
                        <li><strong>Data de Nascimento:</strong> $data_nascimento</li>
                    </ul>
                    <p>Por favor, entre em contato com o cliente para os próximos passos.</p>
                    <p>Atenciosamente,<br>Servidor FireNet Telecom</p>
                ";
                $mail->AltBody = "Novo Cadastro Realizado\n\nUm novo cliente se cadastrou na FireNet Telecom. Aqui estão os detalhes:\n- Nome: $nome\n- E-mail: $email\n- Telefone: $telefone\n- Plano Escolhido: $plano\n- Dia do Vencimento: $vencimento\n- Data de Nascimento: $data_nascimento\n\nPor favor, entre em contato com o cliente para os próximos passos.\n\nAtenciosamente,\nServidor FireNet Telecom";
                $mail->send();
            } catch (Exception $e) {
                // Log do erro (opcional, para depuração)
                error_log("Erro ao enviar e-mail para $userEmail: " . $mail->ErrorInfo);
                // Continua o loop, não interrompe o fluxo
            }
        }
    } catch (Exception $e) {
        // Log do erro (opcional, para depuração)
        error_log("Erro ao consultar e-mails dos usuários: " . $e->getMessage());
        // Não interrompe o fluxo, apenas registra o erro
    }

    // Chama o script de envio de mensagem ao Telegram em background
    try {
        // Escapa os argumentos para evitar injeção de comando
        $nomeEscaped = escapeshellarg($nome);
        $emailEscaped = escapeshellarg($email);
        $telefoneEscaped = escapeshellarg($telefone);
        $planoEscaped = escapeshellarg($plano);
        $vencimentoEscaped = escapeshellarg($vencimento);
        $dataNascimentoEscaped = escapeshellarg($data_nascimento);

        // Monta o comando para executar o script em background
        $command = "php send_telegram_message.php $nomeEscaped $emailEscaped $telefoneEscaped $planoEscaped $vencimentoEscaped $dataNascimentoEscaped > /dev/null 2>&1 &";

        // Executa o comando em background
        exec($command);
    } catch (Exception $e) {
        // Log do erro (opcional, para depuração)
        error_log("Erro ao chamar o script de envio de mensagem para o Telegram: " . $e->getMessage());
        // Não interrompe o fluxo, apenas registra o erro
    }

    // Redireciona para a página de sucesso imediatamente
    header('Location: sucesso.html');
    exit();

} catch (Exception $e) {
    // Em caso de erro, exibe uma página estilizada
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>FireNet Telecom - Erro</title>
        <link rel="stylesheet" href="css/cadastro.css">
    </head>
    <body>
        <header>
            <a href='/'><img src="imagens/firenet.png" alt="Logotipo FireNet Telecom"></a>
        </header>

        <nav>
            <a href="index.php#inicio">Início</a>
            <a href="index.php#vantagens">Vantagens</a>
            <a href="index.php#planos">Planos</a>
            <a href="index.php#cobertura">Cobertura</a>
            <a href="index.php#central-cliente">Central do Cliente</a>
            <a href="cadastro.html">Cadastro</a>
            <a href="index.php#contato">Contato</a>
            <a href="login.php">Área do Consultor</a>
        </nav>

        <div class="container">
            <div class="section">
                <h2>Erro</h2>
                <p><?php echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'); ?></p>
                <p><a href="index.php" style="color: #ff0000; text-decoration: none;">Voltar</a></p>
            </div>
        </div>

        <footer>
            <p>© 2025 FireNet Telecom - Todos os direitos reservados.</p>
        </footer>
    </body>
    </html>
    <?php
}
?>
