const DEBUG = false; // Variável para ativar/desativar logs de depuração

// Função para log de depuração
function logDebug(message) {
    if (DEBUG) {
        console.log(`[DEBUG] ${message}`);
    }
}

// Função para log de erro
function logError(message) {
    if (DEBUG) {
        console.error(`[ERROR] ${message}`);
    }
}

document.getElementById('reset-form').addEventListener('submit', function(event) {
    event.preventDefault();

    const resetButton = document.getElementById('reset-button');
    const errorDiv = document.getElementById('reset-error');
    resetButton.disabled = true;
    resetButton.textContent = 'Enviando..';
    resetButton.classList.add('validating');
    errorDiv.style.display = 'none';
    errorDiv.textContent = '';

    const formData = new FormData(this);

    logDebug('Iniciando envio do formulário de recuperação de senha.');

    const timeoutPromise = new Promise((resolve, reject) => {
        setTimeout(() => {
            reject(new Error('Tempo limite de 6 segundos excedido ao enviar o link de redefinição.'));
        }, 6000);
    });

    const fetchPromise = fetch('', {
        method: 'POST',
        body: formData
    }).then(response => {
        if (!response.ok) {
            throw new Error(`Erro HTTP ${response.status}`);
        }
        return response.json();
    });

    Promise.race([fetchPromise, timeoutPromise])
        .then(result => {
            resetButton.textContent = 'Enviar Link de Redefinição';
            resetButton.classList.remove('validating');

            if (result.success) {
                logDebug('Link de redefinição enviado com sucesso, recarregando a página.');
                window.location.reload();
            } else {
                errorDiv.textContent = result.message;
                errorDiv.style.display = 'block';
                resetButton.disabled = false;
                logDebug(`Erro retornado pela API: ${result.message}`);
            }
        })
        .catch(error => {
            resetButton.textContent = 'Enviar Link de Redefinição';
            resetButton.classList.remove('validating');
            resetButton.disabled = false;
            errorDiv.textContent = error.message || 'Erro desconhecido.';
            errorDiv.style.display = 'block';
            logError(`Erro ao enviar o formulário: ${error.message}`);
        });
});
