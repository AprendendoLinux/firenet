const DEBUG = false; // Variável para ativar/desativar logs de depuração

// Função para log de depuração
function logDebug(message) {
    if (DEBUG) {
        console.log(`[DEBUG] ${message}`);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const printButton = document.querySelector('.no-print button');
    if (printButton) {
        printButton.addEventListener('click', function() {
            logDebug('Botão de impressão clicado, iniciando impressão do relatório.');
            window.print();
        });
    } else {
        logDebug('Botão de impressão não encontrado na página.');
    }
});
