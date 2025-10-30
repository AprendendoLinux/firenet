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

// Função chamada quando o reCAPTCHA é carregado
function onRecaptchaLoad() {
    logDebug('reCAPTCHA carregado com sucesso');
}

document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', function(event) {
            event.preventDefault();

            const loginButton = document.getElementById('login-button');
            loginButton.disabled = true;
            loginButton.textContent = 'Validando';
            loginButton.classList.add('validating');

            const errorDiv = document.getElementById('login-error');
            errorDiv.style.display = 'none';
            errorDiv.textContent = '';

            const formData = new FormData(this);
            formData.append('ajax_login', '1');

            logDebug('Iniciando validação do formulário de login.');

            const timeoutPromise = new Promise((resolve, reject) => {
                setTimeout(() => {
                    reject(new Error('Tempo limite de 6 segundos excedido ao validar o login.'));
                }, 6000);
            });

            const fetchPromise = fetch('login.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(response => {
                logDebug('Resposta recebida do servidor.');
                return response.json();
            });

            Promise.race([fetchPromise, timeoutPromise])
                .then(result => {
                    logDebug('Resposta JSON processada: ' + JSON.stringify(result));
                    loginButton.textContent = 'Entrar';
                    loginButton.classList.remove('validating');

                    if (result.success) {
                        if (result.redirect) {
                            logDebug(`Login bem-sucedido, redirecionando para ${result.redirect}.`);
                            window.location.href = result.redirect;
                        } else {
                            logDebug('Login bem-sucedido, recarregando página para exibir formulário de 2FA.');
                            window.location.reload();
                        }
                    } else {
                        errorDiv.textContent = result.error || result.message;
                        errorDiv.style.display = 'block';
                        loginButton.disabled = false;
                        logDebug(`Erro retornado pela API: ${result.error || result.message}`);
                        // Reinicia o reCAPTCHA se estiver habilitado
                        if (typeof grecaptcha !== 'undefined' && grecaptcha.reset) {
                            logDebug('Reiniciando reCAPTCHA.');
                            grecaptcha.reset();
                        }
                    }
                })
                .catch(error => {
                    loginButton.textContent = 'Entrar';
                    loginButton.classList.remove('validating');
                    loginButton.disabled = false;
                    errorDiv.textContent = error.message || 'Erro ao validar o login.';
                    errorDiv.style.display = 'block';
                    logError(`Erro ao validar o login: ${error.message}`);
                    // Reinicia o reCAPTCHA se estiver habilitado
                    if (typeof grecaptcha !== 'undefined' && grecaptcha.reset) {
                        logDebug('Reiniciando reCAPTCHA após erro.');
                        grecaptcha.reset();
                    }
                });
        });
    } else {
        logDebug('Formulário de login não encontrado; provavelmente na etapa de 2FA.');
    }

    const twoFaForm = document.getElementById('2fa-form');
    if (twoFaForm) {
        twoFaForm.addEventListener('submit', function(event) {
            event.preventDefault();

            const verifyButton = twoFaForm.querySelector('button[type="submit"]');
            verifyButton.disabled = true;
            verifyButton.textContent = 'Validando';
            verifyButton.classList.add('validating');

            const errorDiv = document.getElementById('login-error');
            errorDiv.style.display = 'none';
            errorDiv.textContent = '';

            const formData = new FormData(this);
            formData.append('verify_2fa', '1');

            // Log para verificar os dados do formulário
            const trustDevice = document.getElementById('trust_device').checked;
            logDebug(`Checkbox trust_device marcado: ${trustDevice}`);
            logDebug('Dados do formulário 2FA: ' + JSON.stringify([...formData]));

            const timeoutPromise = new Promise((resolve, reject) => {
                setTimeout(() => {
                    reject(new Error('Tempo limite de 6 segundos excedido ao validar o código 2FA.'));
                }, 6000);
            });

            const fetchPromise = fetch('login.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(response => {
                logDebug('Resposta recebida do servidor para 2FA.');
                return response.json();
            });

            Promise.race([fetchPromise, timeoutPromise])
                .then(result => {
                    logDebug('Resposta JSON processada para 2FA: ' + JSON.stringify(result));
                    verifyButton.textContent = 'Verificar';
                    verifyButton.classList.remove('validating');

                    if (result.success) {
                        if (result.redirect) {
                            logDebug(`2FA validado com sucesso, redirecionando para ${result.redirect}.`);
                            window.location.href = result.redirect;
                        } else {
                            logDebug('2FA validado com sucesso, recarregando página.');
                            window.location.reload();
                        }
                    } else {
                        errorDiv.textContent = result.error || result.message;
                        errorDiv.style.display = 'block';
                        verifyButton.disabled = false;
                        logDebug(`Erro retornado pela API: ${result.error || result.message}`);
                    }
                })
                .catch(error => {
                    verifyButton.textContent = 'Verificar';
                    verifyButton.classList.remove('validating');
                    verifyButton.disabled = false;
                    errorDiv.textContent = error.message || 'Erro ao validar o código 2FA.';
                    errorDiv.style.display = 'block';
                    logError(`Erro ao validar o código 2FA: ${error.message}`);
                });
        });
    } else {
        logDebug('Formulário de 2FA não encontrado; provavelmente na etapa de login.');
    }
});
