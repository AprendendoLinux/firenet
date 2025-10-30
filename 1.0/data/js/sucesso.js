const DEBUG = false; // Variável para ativar/desativar logs de depuração

// Função para log de depuração
function logDebug(message) {
    if (DEBUG) {
        console.log(`[DEBUG] ${message}`);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    logDebug('Página de sucesso carregada.');

    const backLink = document.querySelector('a[href="index.php"]');
    if (backLink) {
        backLink.addEventListener('click', function(event) {
            logDebug('Link "Voltar ao Início" clicado.');
        });
    } else {
        logDebug('Link "Voltar ao Início" não encontrado.');
    }
});
