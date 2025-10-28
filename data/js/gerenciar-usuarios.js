const debugEnabled = window.DEBUG_LOGS || false;

function debugLog(...args) {
    if (debugEnabled) {
        console.log(...args);
    }
}

function debugError(...args) {
    if (debugEnabled) {
        console.error(...args);
    }
}

function openModal(modalId) {
    debugLog('1. Abrindo modal:', modalId);
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        debugLog('2. Modal exibido com sucesso:', modalId);
    } else {
        debugError('2. ERRO: Modal não encontrado:', modalId);
    }
}

function closeModal(modalId) {
    debugLog('3. Fechando modal:', modalId);
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        debugLog('4. Modal fechado com sucesso:', modalId);
    } else {
        debugError('4. ERRO: Modal não encontrado:', modalId);
    }
}

window.onclick = function(event) {
    debugLog('5. Evento de clique na janela detectado');
    const modals = document.getElementsByClassName('modal');
    for (let modal of modals) {
        if (event.target === modal) {
            debugLog('6. Clique fora do modal, fechando:', modal.id);
            modal.style.display = 'none';
        }
    }
};

// Validação no lado do cliente para formulários de senha
document.querySelectorAll('[id^="form-senha-"]').forEach(form => {
    const formId = form.id;
    const userId = formId.replace('form-senha-', '');
    debugLog('7. Configurando validação para formulário de senha:', formId);

    form.addEventListener('submit', function(event) {
        debugLog('8. Submissão do formulário de senha:', formId);
        const novaSenha = document.getElementById(`nova_senha-${userId}`).value;
        const confirmarSenha = document.getElementById(`confirmar_senha-${userId}`).value;

        if (novaSenha !== confirmarSenha) {
            debugLog('9. Erro: Senhas não coincidem');
            event.preventDefault();
            const errorDiv = document.createElement('p');
            errorDiv.className = 'modal-error';
            errorDiv.textContent = 'A nova senha e a confirmação não coincidem.';
            const existingError = this.querySelector('.modal-error');
            if (existingError) {
                existingError.remove();
            }
            this.insertBefore(errorDiv, this.firstChild);
        } else {
            debugLog('10. Validação de senha passou');
        }
    });
});

// Validação no lado do cliente para formulários de e-mail
document.querySelectorAll('[id^="form-email-"]').forEach(form => {
    const formId = form.id;
    const userId = formId.replace('form-email-', '');
    debugLog('11. Configurando validação para formulário de e-mail:', formId);

    form.addEventListener('submit', function(event) {
        debugLog('12. Submissão do formulário de e-mail:', formId);
        const emailInput = document.getElementById(`novo_email-${userId}`);
        const email = emailInput.value;
        const errorDiv = document.createElement('p');
        errorDiv.className = 'modal-error';

        if (!email) {
            debugLog('13. Erro: E-mail vazio');
            event.preventDefault();
            errorDiv.textContent = 'O e-mail não pode estar vazio.';
            const existingError = this.querySelector('.modal-error');
            if (existingError) {
                existingError.remove();
            }
            this.insertBefore(errorDiv, emailInput.nextSibling);
            return;
        }

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            debugLog('14. Erro: E-mail inválido');
            event.preventDefault();
            errorDiv.textContent = 'Por favor, insira um e-mail válido.';
            const existingError = this.querySelector('.modal-error');
            if (existingError) {
                existingError.remove();
            }
            this.insertBefore(errorDiv, emailInput.nextSibling);
            return;
        }
        debugLog('15. Validação de e-mail passou');
    });

    // Validação AJAX para e-mail
    const emailInput = document.getElementById(`novo_email-${userId}`);
    emailInput.addEventListener('input', function() {
        debugLog('16. Evento de entrada no campo de e-mail:', `novo_email-${userId}`);
        const email = this.value;
        const submitButton = form.querySelector('button[type="submit"]');
        const errorDiv = document.createElement('p');
        errorDiv.className = 'modal-error';

        if (!email) {
            debugLog('17. Erro: E-mail vazio');
            errorDiv.textContent = 'O e-mail não pode estar vazio.';
            this.classList.add('invalid');
            submitButton.disabled = true;
            const existingError = form.querySelector('.modal-error');
            if (existingError) {
                existingError.remove();
            }
            form.insertBefore(errorDiv, this.nextSibling);
            return;
        }

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            debugLog('18. Erro: E-mail inválido');
            errorDiv.textContent = 'Por favor, insira um e-mail válido.';
            this.classList.add('invalid');
            submitButton.disabled = true;
            const existingError = form.querySelector('.modal-error');
            if (existingError) {
                existingError.remove();
            }
            form.insertBefore(errorDiv, this.nextSibling);
            return;
        }

        debugLog('19. Iniciando validação AJAX para e-mail:', email);
        fetch('/check_email.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `email=${encodeURIComponent(email)}&user_id=${userId}`
        })
        .then(response => {
            debugLog('20. Resposta recebida da validação AJAX');
            return response.json();
        })
        .then(data => {
            const existingError = form.querySelector('.modal-error');
            if (data.exists) {
                debugLog('21. Erro: E-mail já em uso');
                errorDiv.textContent = 'Este e-mail já está em uso por outro usuário.';
                this.classList.add('invalid');
                submitButton.disabled = true;
                if (existingError) {
                    existingError.remove();
                }
                form.insertBefore(errorDiv, this.nextSibling);
            } else {
                debugLog('22. E-mail válido e disponível');
                if (existingError) {
                    existingError.remove();
                }
                this.classList.remove('invalid');
                submitButton.disabled = false;
            }
        })
        .catch(error => {
            debugError('23. ERRO na validação AJAX:', error);
            errorDiv.textContent = 'Erro ao verificar o e-mail.';
            this.classList.add('invalid');
            submitButton.disabled = true;
            const existingError = form.querySelector('.modal-error');
            if (existingError) {
                existingError.remove();
            }
            form.insertBefore(errorDiv, this.nextSibling);
        });
    });
});

// Validação no lado do cliente para formulários de Telegram ID
document.querySelectorAll('[id^="form-telegram-"]').forEach(form => {
    const formId = form.id;
    const userId = formId.replace('form-telegram-', '');
    debugLog('24. Configurando validação para formulário de Telegram ID:', formId);

    form.addEventListener('submit', function(event) {
        debugLog('25. Submissão do formulário de Telegram ID:', formId);
        const telegramId = document.getElementById(`telegram_id-${userId}`).value.trim();
        if (telegramId && !/^-?\d+$/.test(telegramId)) {
            debugLog('26. Erro: Telegram ID inválido');
            event.preventDefault();
            const errorDiv = document.createElement('p');
            errorDiv.className = 'modal-error';
            errorDiv.textContent = 'O ID do Telegram deve conter apenas números.';
            const existingError = this.querySelector('.modal-error');
            if (existingError) {
                existingError.remove();
            }
            this.insertBefore(errorDiv, this.firstChild);
        } else {
            debugLog('27. Validação de Telegram ID passou');
        }
    });
});

debugLog('28. Script inicializado com sucesso');
