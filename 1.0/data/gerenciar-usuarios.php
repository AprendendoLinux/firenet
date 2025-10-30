<?php
// gerenciar-usuarios.php

// Configuração de depuração: defina como true para ativar os logs, false para desativar
$DEBUG_LOGS = false;

// Inicia o buffer de saída para evitar saídas acidentais
ob_start();

// Inicia a sessão
session_start();

// Inclui o arquivo de configuração e autenticação
require_once 'config.php';
require_once 'auth.php';

// Verifica se o usuário está logado e é administrador
verificarAutenticacao(true);

// Inicializa variáveis de erro e sucesso
$error = '';
$success = '';

try {
    $current_user_id = $_SESSION['user_id'];

    // Obtém as permissões do usuário logado para o menu de navegação
    $stmt = $pdo->prepare("SELECT permissoes FROM usuarios WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $permissoes = $user['permissoes'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['alterar_senha'])) {
            $user_id = (int)$_POST['user_id'];
            $nova_senha = trim($_POST['nova_senha']);
            $confirmar_senha = trim($_POST['confirmar_senha']);

            if (empty($nova_senha)) {
                $error = "A nova senha não pode estar vazia.";
            } elseif (strlen($nova_senha) < 6) {
                $error = "A senha deve ter pelo menos 6 caracteres.";
            } elseif ($nova_senha !== $confirmar_senha) {
                $error = "A nova senha e a confirmação não coincidem.";
            } else {
                $hashed_senha = password_hash($nova_senha, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_senha, $user_id]);
                $success = "Senha alterada com sucesso!";
                // Registrar log da ação
                $stmt = $pdo->prepare("SELECT username FROM usuarios WHERE id = ?");
                $stmt->execute([$user_id]);
                $target_user = $stmt->fetch(PDO::FETCH_ASSOC);
                registrarLog($pdo, $current_user_id, "Alterou a senha do usuário: " . $target_user['username']);
            }
        }

        if (isset($_POST['alterar_email'])) {
            $user_id = (int)$_POST['user_id'];
            $novo_email = trim($_POST['novo_email']);

            if (empty($novo_email) || !filter_var($novo_email, FILTER_VALIDATE_EMAIL)) {
                $error = "Por favor, insira um e-mail válido.";
            } else {
                // Verifica se o e-mail já existe em outro usuário
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
                $stmt->execute([$novo_email, $user_id]);
                if ($stmt->fetch()) {
                    $error = "Este e-mail já está em uso por outro usuário.";
                } else {
                    // Buscar e-mail antigo pra incluir no log
                    $stmt = $pdo->prepare("SELECT email, username FROM usuarios WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $target_user = $stmt->fetch(PDO::FETCH_ASSOC);
                    $email_antigo = $target_user['email'];

                    $stmt = $pdo->prepare("UPDATE usuarios SET email = ? WHERE id = ?");
                    $stmt->execute([$novo_email, $user_id]);
                    $success = "E-mail alterado com sucesso!";
                    // Registrar log da ação
                    registrarLog($pdo, $current_user_id, "Alterou o e-mail do usuário: " . $target_user['username'] . " (de '$email_antigo' para '$novo_email')");
                }
            }
        }

        if (isset($_POST['alterar_2fa'])) {
            $user_id = (int)$_POST['user_id'];
            $enable_2fa = isset($_POST['2fa_enabled']) ? 1 : 0;

            // Buscar estado anterior do 2FA e nome do usuário
            $stmt = $pdo->prepare("SELECT username, 2fa_enabled FROM usuarios WHERE id = ?");
            $stmt->execute([$user_id]);
            $target_user = $stmt->fetch(PDO::FETCH_ASSOC);
            $estado_2fa_antigo = $target_user['2fa_enabled'] ? 'Habilitado' : 'Desabilitado';

            // Atualiza 2fa_enabled, 2fa_type e 2fa_secret com base no estado do checkbox
            if ($enable_2fa) {
                $stmt = $pdo->prepare("UPDATE usuarios SET 2fa_enabled = ?, 2fa_type = ?, 2fa_secret = NULL WHERE id = ?");
                $stmt->execute([1, 'email', $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE usuarios SET 2fa_enabled = ?, 2fa_type = NULL, 2fa_secret = NULL WHERE id = ?");
                $stmt->execute([0, $user_id]);
            }

            $success = "Autenticação de dois fatores " . ($enable_2fa ? "habilitada" : "desabilitada") . " com sucesso!";
            // Registrar log da ação
            $novo_estado = $enable_2fa ? 'Habilitado' : 'Desabilitado';
            registrarLog($pdo, $current_user_id, "Alterou o 2FA do usuário: " . $target_user['username'] . " (de '$estado_2fa_antigo' para '$novo_estado')");
        }

        if (isset($_POST['alterar_permissoes'])) {
            $user_id = (int)$_POST['user_id'];
            $permissoes = isset($_POST['permissoes']) && $_POST['permissoes'] === 'excluir' ? 'excluir' : 'visualizar';

            if ($user_id === $current_user_id) {
                $error = "Você não pode alterar suas próprias permissões.";
            } else {
                // Buscar estado anterior e nome do usuário
                $stmt = $pdo->prepare("SELECT username, permissoes FROM usuarios WHERE id = ?");
                $stmt->execute([$user_id]);
                $target_user = $stmt->fetch(PDO::FETCH_ASSOC);
                $estado_permissoes_antigo = $target_user['permissoes'] === 'excluir' ? 'Administrador' : 'Visualizar';

                $stmt = $pdo->prepare("UPDATE usuarios SET permissoes = ? WHERE id = ?");
                $stmt->execute([$permissoes, $user_id]);
                $success = "Permissões alteradas com sucesso!";
                // Registrar log da ação
                $novo_estado = $permissoes === 'excluir' ? 'Administrador' : 'Visualizar';
                registrarLog($pdo, $current_user_id, "Alterou as permissões do usuário: " . $target_user['username'] . " (de '$estado_permissoes_antigo' para '$novo_estado')");
            }
        }

        if (isset($_POST['alterar_telegram'])) {
            $user_id = (int)$_POST['user_id'];
            $telegram_id = trim($_POST['telegram_id']);

            // Buscar o username do usuário
            $stmt = $pdo->prepare("SELECT username FROM usuarios WHERE id = ?");
            $stmt->execute([$user_id]);
            $target_user = $stmt->fetch(PDO::FETCH_ASSOC);
            $username = $target_user['username'];

            // Valida o Telegram ID
            if (empty($telegram_id)) {
                // Se o campo estiver vazio, remove o registro da tabela telegram_admins
                $stmt = $pdo->prepare("DELETE FROM telegram_admins WHERE name = ?");
                $stmt->execute([$username]);
                $success = "ID do Telegram removido com sucesso para o usuário: $username.";
                registrarLog($pdo, $current_user_id, "Removeu o ID do Telegram do usuário: $username");
            } elseif (!preg_match('/^-?\d+$/', $telegram_id)) {
                $error = "O ID do Telegram deve conter apenas números.";
            } else {
                // Verifica se o chat_id já está associado a outro usuário
                $stmt = $pdo->prepare("SELECT name FROM telegram_admins WHERE chat_id = ? AND name != ?");
                $stmt->execute([$telegram_id, $username]);
                if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                    $error = "Este ID do Telegram já está associado a outro usuário.";
                } else {
                    // Verifica se o usuário já tem um registro
                    $stmt = $pdo->prepare("SELECT chat_id FROM telegram_admins WHERE name = ?");
                    $stmt->execute([$username]);
                    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                        // Atualiza o registro existente
                        $stmt = $pdo->prepare("UPDATE telegram_admins SET chat_id = ? WHERE name = ?");
                        $result = $stmt->execute([$telegram_id, $username]);
                    } else {
                        // Insere um novo registro
                        $stmt = $pdo->prepare("INSERT INTO telegram_admins (chat_id, name) VALUES (?, ?)");
                        $result = $stmt->execute([$telegram_id, $username]);
                    }

                    if ($result) {
                        $success = "ID do Telegram atualizado com sucesso para o usuário: $username.";
                        registrarLog($pdo, $current_user_id, "Atualizou o ID do Telegram do usuário: $username para $telegram_id");
                    } else {
                        $error = "Erro ao atualizar o ID do Telegram.";
                        if ($DEBUG_LOGS) {
                            error_log("Falha ao atualizar Telegram ID para user_id: $user_id");
                        }
                    }
                }
            }
        }

        if (isset($_POST['excluir_usuario'])) {
            $user_id = (int)$_POST['user_id'];

            if ($user_id === $current_user_id) {
                $error = "Você não pode excluir seu próprio usuário.";
            } else {
                // Buscar nome do usuário antes de excluir
                $stmt = $pdo->prepare("SELECT username FROM usuarios WHERE id = ?");
                $stmt->execute([$user_id]);
                $target_user = $stmt->fetch(PDO::FETCH_ASSOC);

                $stmt = $pdo->prepare("DELETE FROM 2fa_codes WHERE user_id = ?");
                $stmt->execute([$user_id]);

                // Remover o Telegram ID associado, se existir
                $stmt = $pdo->prepare("DELETE FROM telegram_admins WHERE name = ?");
                $stmt->execute([$target_user['username']]);

                $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
                $stmt->execute([$user_id]);
                $success = "Usuário excluído com sucesso!";
                // Registrar log da ação
                registrarLog($pdo, $current_user_id, "Excluiu o usuário: " . $target_user['username']);
            }
        }
    }

    // Busca os usuários e seus Telegram IDs
    $stmt = $pdo->query("SELECT id, username, email, permissoes, 2fa_enabled FROM usuarios ORDER BY username");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Busca os Telegram IDs
    $telegram_ids = [];
    foreach ($usuarios as $usuario) {
        $stmt = $pdo->prepare("SELECT chat_id FROM telegram_admins WHERE name = ?");
        $stmt->execute([$usuario['username']]);
        $telegram_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $telegram_ids[$usuario['id']] = $telegram_data['chat_id'] ?? '';
    }

    // Busca os logs de acesso de cada usuário
    $logs = [];
    foreach ($usuarios as $usuario) {
	//$stmt = $pdo->prepare("SELECT event, timestamp FROM access_logs WHERE user_id = ? ORDER BY timestamp DESC LIMIT 50");
	$stmt = $pdo->prepare("SELECT event, timestamp FROM access_logs WHERE user_id = ? ORDER BY timestamp DESC");
        $stmt->execute([$usuario['id']]);
        $logs[$usuario['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    $error = "Erro na conexão com o banco de dados: " . $e->getMessage();
    if ($DEBUG_LOGS) {
        error_log("Erro PDO: " . $e->getMessage());
    }
} catch (Exception $e) {
    $error = "Erro inesperado: " . $e->getMessage();
    if ($DEBUG_LOGS) {
        error_log("Erro Exception: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - FireNet Telecom</title>
    <link rel="stylesheet" href="/css/gerenciar-usuarios.css?v=1">
</head>
<body>
    <?php include 'topo.php'; ?>

    <div class="container">
        <div class="manage-container">
            <h2>Painel de Gerenciamento de Usuários</h2>
            <?php if (!empty($success)): ?>
                <p class="success"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>
            <?php if (!empty($error) && !isset($_POST['alterar_senha']) && !isset($_POST['alterar_email']) && !isset($_POST['alterar_telegram'])): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>

            <div class="create-button">
                <a href="/criar-usuario.php" class="action-button criar">Criar Usuário</a>
            </div>

            <table class="users-table">
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>E-mail</th>
                        <th>Permissões</th>
                        <th>2FA</th>
                        <th>Telegram ID</th>
                        <th>Logs de Acesso</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td data-label="Usuário"><?php echo htmlspecialchars($usuario['username']); ?></td>
                            <td data-label="E-mail"><?php echo htmlspecialchars($usuario['email']); ?></td>
                            <td data-label="Permissões"><?php echo $usuario['permissoes'] === 'excluir' ? 'Administrador' : 'Visualizar'; ?></td>
                            <td data-label="2FA"><?php echo $usuario['2fa_enabled'] ? 'Habilitado' : 'Desabilitado'; ?></td>
                            <td data-label="Telegram ID"><?php echo htmlspecialchars($telegram_ids[$usuario['id']] ?: 'Não configurado'); ?></td>
                            <td data-label="Logs de Acesso">
                                <button class="action-button logs" onclick="openModal('logs-<?php echo $usuario['id']; ?>')">Logs</button>
                            </td>
                            <td data-label="Ações">
                                <div selection-type="action" class="action-buttons">
                                    <button class="action-button senha" onclick="openModal('senha-<?php echo $usuario['id']; ?>')">Senha</button>
                                    <button class="action-button email" onclick="openModal('email-<?php echo $usuario['id']; ?>')">E-mail</button>
                                    <button class="action-button 2fa" onclick="openModal('2fa-<?php echo $usuario['id']; ?>')">2FA</button>
                                    <button class="action-button telegram" onclick="openModal('telegram-<?php echo $usuario['id']; ?>')">Telegram ID</button>
                                    <button class="action-button permissoes" onclick="openModal('permissoes-<?php echo $usuario['id']; ?>')" <?php echo $usuario['id'] === $current_user_id ? 'disabled' : ''; ?>>Permissões</button>
                                    <button class="action-button excluir" onclick="openModal('excluir-<?php echo $usuario['id']; ?>')" <?php echo $usuario['id'] === $current_user_id ? 'disabled' : ''; ?>>Excluir</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php foreach ($usuarios as $usuario): ?>
                <div id="senha-<?php echo $usuario['id']; ?>" class="modal">
                    <div class="modal-content" style="width: 350px;">
                        <h3>Gerenciar Senha de <?php echo htmlspecialchars($usuario['username']); ?></h3>
                        <?php if (!empty($error) && isset($_POST['alterar_senha']) && (int)$_POST['user_id'] === $usuario['id']): ?>
                            <p class="modal-error"><?php echo htmlspecialchars($error); ?></p>
                        <?php endif; ?>
                        <form id="form-senha-<?php echo $usuario['id']; ?>" method="POST" action="">
                            <input type="hidden" name="user_id" value="<?php echo $usuario['id']; ?>">
                            <input type="password" name="nova_senha" id="nova_senha-<?php echo $usuario['id']; ?>" placeholder="Nova senha" minlength="6" required>
                            <input type="password" name="confirmar_senha" id="confirmar_senha-<?php echo $usuario['id']; ?>" placeholder="Confirmar nova senha" minlength="6" required>
                            <button type="submit" name="alterar_senha">Salvar Alterações</button>
                            <button type="button" class="close-button" onclick="closeModal('senha-<?php echo $usuario['id']; ?>')">Cancelar</button>
                        </form>
                    </div>
                </div>

                <div id="email-<?php echo $usuario['id']; ?>" class="modal">
                    <div class="modal-content" style="width: 350px;">
                        <h3>Gerenciar E-mail de <?php echo htmlspecialchars($usuario['username']); ?></h3>
                        <?php if (!empty($error) && isset($_POST['alterar_email']) && (int)$_POST['user_id'] === $usuario['id']): ?>
                            <p class="modal-error"><?php echo htmlspecialchars($error); ?></p>
                        <?php endif; ?>
                        <form id="form-email-<?php echo $usuario['id']; ?>" method="POST" action="">
                            <input type="hidden" name="user_id" value="<?php echo $usuario['id']; ?>">
                            <input type="email" name="novo_email" id="novo_email-<?php echo $usuario['id']; ?>" placeholder="Novo e-mail" value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                            <button type="submit" name="alterar_email">Salvar Alterações</button>
                            <button type="button" class="close-button" onclick="closeModal('email-<?php echo $usuario['id']; ?>')">Cancelar</button>
                        </form>
                    </div>
                </div>

                <div id="2fa-<?php echo $usuario['id']; ?>" class="modal">
                    <div class="modal-content" style="width: 350px;">
                        <h3>Gerenciar 2FA de <?php echo htmlspecialchars($usuario['username']); ?></h3>
                        <form method="POST" action="">
                            <input type="hidden" name="user_id" value="<?php echo $usuario['id']; ?>">
                            <label>
                                <input type="checkbox" name="2fa_enabled" <?php echo $usuario['2fa_enabled'] ? 'checked' : ''; ?>>
                                Habilitar Autenticação de Dois Fatores
                            </label>
                            <button type="submit" name="alterar_2fa">Salvar Alterações</button>
                            <button type="button" class="close-button" onclick="closeModal('2fa-<?php echo $usuario['id']; ?>')">Cancelar</button>
                        </form>
                    </div>
                </div>

                <div id="telegram-<?php echo $usuario['id']; ?>" class="modal">
                    <div class="modal-content" style="width: 350px;">
                        <h3>Gerenciar Telegram ID de <?php echo htmlspecialchars($usuario['username']); ?></h3>
                        <?php if (!empty($error) && isset($_POST['alterar_telegram']) && (int)$_POST['user_id'] === $usuario['id']): ?>
                            <p class="modal-error"><?php echo htmlspecialchars($error); ?></p>
                        <?php endif; ?>
                        <form id="form-telegram-<?php echo $usuario['id']; ?>" method="POST" action="">
                            <input type="hidden" name="user_id" value="<?php echo $usuario['id']; ?>">
                            <input type="text" name="telegram_id" id="telegram_id-<?php echo $usuario['id']; ?>" placeholder="Digite o Telegram ID" value="<?php echo htmlspecialchars($telegram_ids[$usuario['id']]); ?>">
                            <small>Deixe em branco para remover o Telegram ID.</small>
                            <button type="submit" name="alterar_telegram">Salvar Alterações</button>
                            <button type="button" class="close-button" onclick="closeModal('telegram-<?php echo $usuario['id']; ?>')">Cancelar</button>
                        </form>
                    </div>
                </div>

                <div id="permissoes-<?php echo $usuario['id']; ?>" class="modal">
                    <div class="modal-content" style="width: 350px;">
                        <h3>Gerenciar Permissões de <?php echo htmlspecialchars($usuario['username']); ?></h3>
                        <form method="POST" action="">
                            <input type="hidden" name="user_id" value="<?php echo $usuario['id']; ?>">
                            <label>
                                <input type="checkbox" name="permissoes" value="excluir" <?php echo $usuario['permissoes'] === 'excluir' ? 'checked' : ''; ?>>
                                Tornar Administrador (Permissão: Excluir)
                            </label>
                            <button type="submit" name="alterar_permissoes">Salvar Alterações</button>
                            <button type="button" class="close-button" onclick="closeModal('permissoes-<?php echo $usuario['id']; ?>')">Cancelar</button>
                        </form>
                    </div>
                </div>

                <div id="excluir-<?php echo $usuario['id']; ?>" class="modal">
                    <div class="modal-content" style="width: 350px;">
                        <h3>Excluir Usuário <?php echo htmlspecialchars($usuario['username']); ?></h3>
                        <p>Tem certeza que deseja excluir este usuário? Esta ação não pode ser desfeita.</p>
                        <form method="POST" action="">
                            <input type="hidden" name="user_id" value="<?php echo $usuario['id']; ?>">
                            <button type="submit" name="excluir_usuario">Confirmar Exclusão</button>
                            <button type="button" class="close-button" onclick="closeModal('excluir-<?php echo $usuario['id']; ?>')">Cancelar</button>
                        </form>
                    </div>
                </div>

                <div id="logs-<?php echo $usuario['id']; ?>" class="modal">
                    <div class="modal-content" style="width: 600px;">
                        <h3>Logs de Acesso de <?php echo htmlspecialchars($usuario['username']); ?></h3>
                        <div class="logs-table-container">
                            <table class="logs-table">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Evento</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($logs[$usuario['id']])): ?>
                                        <tr>
                                            <td colspan="2">Nenhum log disponível.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($logs[$usuario['id']] as $log): ?>
                                            <tr>
                                                <td data-label="Data"><?php echo date('d/m/Y H:i:s', strtotime($log['timestamp'])); ?></td>
                                                <td data-label="Evento"><?php echo htmlspecialchars($log['event']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <button type="button" class="close-button" onclick="closeModal('logs-<?php echo $usuario['id']; ?>')">Fechar</button>
                    </div>
                </div>
            <?php endforeach; ?>

            <a href="/clientes.php" class="back-button">Voltar</a>
        </div>
    </div>

    <script>
        window.DEBUG_LOGS = <?php echo json_encode($DEBUG_LOGS); ?>;
    </script>
    <script src="/js/gerenciar-usuarios.js?v=1"></script>
</body>
</html>
<?php
// Limpa o buffer de saída ao final do script
ob_end_flush();
?>
