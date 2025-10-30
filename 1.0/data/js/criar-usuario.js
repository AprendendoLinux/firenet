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

document.getElementById('user-form').addEventListener('submit', function(event) {
    let isValid = true;
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const emailError = document.getElementById('email-error');
    const passwordError = document.getElementById('password-error');
    const confirmPasswordError = document.getElementById('confirm-password-error');
    const submitButton = document.getElementById('submit-button');
    const backButton = document.getElementById('back-button');

    // Resetar mensagens de erro
    emailError.style.display = 'none';
    passwordError.style.display = 'none';
    confirmPasswordError.style.display = 'none';
    emailInput.classList.remove('invalid');
    passwordInput.classList.remove('invalid');
    confirmPasswordInput.classList.remove('invalid');

    logDebug('Iniciando validação do formulário ao enviar.');

    // Validação de e-mail
    const email = emailInput.value.trim();
    if (!email) {
        emailError.textContent = 'O e-mail não pode estar vazio.';
        emailError.style.display = 'block';
        emailInput.classList.add('invalid');
        isValid = false;
        logDebug('E-mail vazio detectado.');
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        emailError.textContent = 'Por favor, insira um e-mail válido.';
        emailError.style.display = 'block';
        emailInput.classList.add('invalid');
        isValid = false;
        logDebug('E-mail inválido detectado.');
    }

    // Validação de senha
    const password = passwordInput.value.trim();
    if (!password) {
        passwordError.textContent = 'A senha não pode estar vazia.';
        passwordError.style.display = 'block';
        passwordInput.classList.add('invalid');
        isValid = false;
        logDebug('Senha vazia detectada.');
    } else if (password.length < 6) {
        passwordError.textContent = 'A senha deve ter pelo menos 6 caracteres.';
        passwordError.style.display = 'block';
        passwordInput.classList.add('invalid');
        isValid = false;
        logDebug('Senha com menos de 6 caracteres.');
    }

    // Validação de confirmação de senha
    const confirmPassword = confirmPasswordInput.value.trim();
    if (!confirmPassword) {
        confirmPasswordError.textContent = 'A confirmação de senha não pode estar vazia.';
        confirmPasswordError.style.display = 'block';
        confirmPasswordInput.classList.add('invalid');
        isValid = false;
        logDebug('Confirmação de senha vazia detectada.');
    } else if (password !== confirmPassword) {
        confirmPasswordError.textContent = 'A senha e a confirmação não coincidem.';
        confirmPasswordError.style.display = 'block';
        confirmPasswordInput.classList.add('invalid');
        isValid = false;
        logDebug('Senha e confirmação não coincidem.');
    }

    // Desabilitar botões se o formulário for válido
    if (isValid) {
        submitButton.disabled = true;
        backButton.classList.add('disabled');
        backButton.style.pointerEvents = 'none';
        logDebug('Formulário válido, desabilitando botões e enviando.');
    } else {
        event.preventDefault();
        logDebug('Formulário inválido, evitando envio.');
    }
});

// Validação em tempo real para o e-mail
document.getElementById('email').addEventListener('input', function() {
    const email = this.value.trim();
    const emailError = document.getElementById('email-error');
    const submitButton = document.getElementById('submit-button');

    emailError.style.display = 'none';
    this.classList.remove('invalid');

    logDebug(`Validando e-mail em tempo real: ${email}`);

    if (!email) {
        emailError.textContent = 'O e-mail não pode estar vazio.';
        emailError.style.display = 'block';
        this.classList.add('invalid');
        submitButton.disabled = true;
        logDebug('E-mail vazio durante validação em tempo real.');
        return;
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        emailError.textContent = 'Por favor, insira um e-mail válido.';
        emailError.style.display = 'block';
        this.classList.add('invalid');
        submitButton.disabled = true;
        logDebug('E-mail inválido durante validação em tempo real.');
        return;
    }

    logDebug(`Verificando se o e-mail ${email} já existe...`);
    fetch('/check_email.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `email=${encodeURIComponent(email)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.exists) {
            emailError.textContent = 'Este e-mail já está em uso por outro usuário.';
            emailError.style.display = 'block';
            this.classList.add('invalid');
            submitButton.disabled = true;
            logDebug('E-mail já existe.');
        } else {
            emailError.style.display = 'none';
            this.classList.remove('invalid');
            logDebug('E-mail disponível.');
            validateForm();
        }
    })
    .catch(error => {
        emailError.textContent = 'Erro ao verificar o e-mail.';
        emailError.style.display = 'block';
        this.classList.add('invalid');
        submitButton.disabled = true;
        logError(`Erro ao verificar o e-mail: ${error.message}`);
    });
});

// Validação em tempo real para a senha
document.getElementById('password').addEventListener('input', function() {
    const password = this.value.trim();
    const passwordError = document.getElementById('password-error');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const confirmPasswordError = document.getElementById('confirm-password-error');
    const submitButton = document.getElementById('submit-button');

    passwordError.style.display = 'none';
    this.classList.remove('invalid');

    logDebug(`Validando senha em tempo real: ${password}`);

    if (!password) {
        passwordError.textContent = 'A senha não pode estar vazia.';
        passwordError.style.display = 'block';
        this.classList.add('invalid');
        submitButton.disabled = true;
        logDebug('Senha vazia durante validação em tempo real.');
    } else if (password.length < 6) {
        passwordError.textContent = 'A senha deve ter pelo menos 6 caracteres.';
        passwordError.style.display = 'block';
        this.classList.add('invalid');
        submitButton.disabled = true;
        logDebug('Senha com menos de 6 caracteres durante validação em tempo real.');
    } else {
        passwordError.style.display = 'none';
        this.classList.remove('invalid');
        // Revalidar confirmação de senha
        const confirmPassword = confirmPasswordInput.value.trim();
        if (confirmPassword && password !== confirmPassword) {
            confirmPasswordError.textContent = 'A senha e a confirmação não coincidem.';
            confirmPasswordError.style.display = 'block';
            confirmPasswordInput.classList.add('invalid');
            submitButton.disabled = true;
            logDebug('Confirmação de senha não coincide durante validação de senha.');
        } else {
            confirmPasswordError.style.display = 'none';
            confirmPasswordInput.classList.remove('invalid');
            logDebug('Senha válida, confirmação ajustada.');
        }
        validateForm();
    }
});

// Validação em tempo real para a confirmação de senha
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('password').value.trim();
    const confirmPassword = this.value.trim();
    const confirmPasswordError = document.getElementById('confirm-password-error');
    const submitButton = document.getElementById('submit-button');

    confirmPasswordError.style.display = 'none';
    this.classList.remove('invalid');

    logDebug(`Validando confirmação de senha em tempo real: ${confirmPassword}`);

    if (!confirmPassword) {
        confirmPasswordError.textContent = 'A confirmação de senha não pode estar vazia.';
        confirmPasswordError.style.display = 'block';
        this.classList.add('invalid');
        submitButton.disabled = true;
        logDebug('Confirmação de senha vazia durante validação em tempo real.');
    } else if (password !== confirmPassword) {
        confirmPasswordError.textContent = 'A senha e a confirmação não coincidem.';
        confirmPasswordError.style.display = 'block';
        this.classList.add('invalid');
        submitButton.disabled = true;
        logDebug('Confirmação de senha não coincide durante validação em tempo real.');
    } else {
        confirmPasswordError.style.display = 'none';
        this.classList.remove('invalid');
        logDebug('Confirmação de senha válida.');
        validateForm();
    }
});

// Função para validar o formulário completo em tempo real
function validateForm() {
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const submitButton = document.getElementById('submit-button');
    const email = emailInput.value.trim();
    const password = passwordInput.value.trim();
    const confirmPassword = confirmPasswordInput.value.trim();

    const isEmailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) && !emailInput.classList.contains('invalid');
    const isPasswordValid = password.length >= 6;
    const isConfirmPasswordValid = password === confirmPassword && confirmPassword !== '';

    if (isEmailValid && isPasswordValid && isConfirmPasswordValid) {
        submitButton.disabled = false;
        logDebug('Formulário válido, botão de envio habilitado.');
    } else {
        submitButton.disabled = true;
        logDebug('Formulário inválido, botão de envio desabilitado.');
    }
}

// Função para alternar visibilidade da senha
document.querySelectorAll('.toggle-password').forEach(toggle => {
    toggle.addEventListener('click', function() {
        const targetId = this.getAttribute('data-target');
        const input = document.getElementById(targetId);
        if (input.type === 'password') {
            input.type = 'text';
            this.textContent = '🙈'; // Ícone para "ocultar"
            logDebug(`Visibilidade da senha (${targetId}) alterada para visível.`);
        } else {
            input.type = 'password';
            this.textContent = '👁️'; // Ícone para "mostrar"
            logDebug(`Visibilidade da senha (${targetId}) alterada para oculta.`);
        }
    });
});
