const DEBUG = false; // Variável para ativar/desativar logs de depuração

// Função para log de depuração
function logDebug(message) {
    if (DEBUG) {
        console.log(`[DEBUG] ${message}`);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const printButton = document.querySelector('.no-print button');
    printButton.addEventListener('click', function() {
        logDebug('Botão de impressão clicado, iniciando impressão.');
        window.print();
    });
});
