const DEBUG = false; // Variável para ativar/desativar logs de depuração

// Função para log de depuração
function logDebug(message) {
    if (DEBUG) {
        console.log(`[DEBUG] ${message}`);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const resetForm = document.querySelector('form[method="POST"]');
    if (resetForm) {
        resetForm.addEventListener('submit', function(event) {
            logDebug('Formulário de redefinição de senha enviado.');
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (password !== confirmPassword) {
                logDebug('As senhas não coincidem - validação no lado do cliente.');
            } else if (password.length < 8) {
                logDebug('A senha tem menos de 8 caracteres - validação no lado do cliente.');
            } else {
                logDebug('Validações no lado do cliente passaram; enviando formulário.');
            }
        });
    } else {
        logDebug('Formulário de redefinição de senha não encontrado; provavelmente já redefinido.');
    }
});
