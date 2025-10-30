(function() {
    'use strict';

    const DEBUG = false;

    function logDebug(message) {
        if (DEBUG) {
            console.log(`[DEBUG] ${message}`);
        }
    }

    function logError(message) {
        if (DEBUG) {
            console.error(`[ERROR] ${message}`);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        logDebug('Cabeçalho e navegação (topo.php) carregados.');

        const nav = document.querySelector('nav');
        if (!nav) {
            logError('Elemento <nav> não encontrado no DOM.');
            return;
        }

        // Listener genérico para capturar todos os cliques no document
        document.addEventListener('click', function(event) {
            const target = event.target;
            logDebug(`Clique detectado em: ${target.tagName} (class: ${target.className}, id: ${target.id}, href: ${target.getAttribute('href') || 'N/A'})`);
        }, { capture: true });

        // Listener direto no link "Sair"
        const logoutLink = document.querySelector('nav a[href="logout.php"]');
        if (logoutLink) {
            logDebug('Link "Sair" encontrado. Adicionando listener direto.');
            logoutLink.addEventListener('click', function(event) {
                logDebug('Botão "Sair" clicado (listener direto).');
                event.preventDefault();
                event.stopPropagation();
                logDebug('Forçando redirecionamento para logout.php (listener direto).');
                window.location.href = 'logout.php';
            }, { capture: true });
        } else {
            logError('Link "Sair" não encontrado no DOM.');
        }

        // Delegação de eventos para outros links dentro do <nav>
        nav.addEventListener('click', function(event) {
            const target = event.target.closest('a');
            if (!target) return;

            const href = target.getAttribute('href');
            logDebug(`Evento de clique capturado no link (delegação): ${href}`);

            if (href === 'logout.php') {
                logDebug('Botão "Sair" clicado (delegação).');
                event.stopPropagation();
                event.preventDefault();
                logDebug('Forçando redirecionamento para logout.php (delegação).');
                window.location.href = 'logout.php';
            }
        }, { capture: true });

        // Logar os links encontrados para depuração
        const navLinks = nav.querySelectorAll('a');
        logDebug(`Encontrados ${navLinks.length} links de navegação.`);
        navLinks.forEach((link, index) => {
            logDebug(`Link ${index + 1}: href="${link.getAttribute('href')}"`);
        });
    });
})();
