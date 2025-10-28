const debugEnabled = window.DEBUG_LOGS || false;

// Injetar CSS diretamente com seletor ainda mais específico
const style = document.createElement('style');
style.textContent = `
    .modal-content #smtp-form button.btn.btn-primary:disabled,
    .modal-content #recaptcha-form button.btn.btn-primary:disabled,
    .modal-content #telegram-form button.btn.btn-primary:disabled,
    .modal-content #maps-form button.btn.btn-primary:disabled,
    .modal-content #popup-form button.btn.btn-primary:disabled,
    button.disabled-state {
        background-color: #808080 !important; /* Cinza */
        opacity: 0.5 !important;
        cursor: not-allowed !important;
    }
    button.validating::after {
        content: '...';
        animation: dots 1.5s infinite;
        margin-left: 5px;
    }
    @keyframes dots {
        0% { content: '.'; }
        33% { content: '..'; }
        66% { content: '...'; }
        100% { content: '.'; }
    }
`;
document.head.appendChild(style);

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

function clearMessages() {
    debugLog('1. Limpando mensagens da interface');
    const messages = [
        'success-message',
        'error-message',
        'smtp-message',
        'test-result-message'
    ];
    messages.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.style.display = 'none';
            element.textContent = '';
        } else {
            debugError(`1. ERRO: Elemento ${id} não encontrado ao limpar mensagens`);
        }
    });
}

function openModal(modalId) {
    debugLog('2. Tentando abrir modal:', modalId);
    clearMessages();
    const modal = document.getElementById(modalId);
    if (modal) {
        debugLog('3. Modal encontrado, exibindo:', modalId);
        modal.style.display = 'flex';
        if (modalId === 'recaptcha-modal') {
            debugLog('4. Modal reCAPTCHA, chamando renderRecaptcha');
            renderRecaptcha();
        }
    } else {
        debugError('3. ERRO: Modal não encontrado:', modalId);
    }
}

function closeModal(modalId, delay = 0) {
    debugLog(`5. Fechando modal ${modalId} com atraso de ${delay}ms`);
    const modal = document.getElementById(modalId);
    setTimeout(() => {
        if (modal) {
            modal.style.display = 'none';
            debugLog(`5.1. Modal ${modalId} fechado`);
        } else {
            debugError(`5. ERRO: Modal ${modalId} não encontrado ao fechar`);
        }
        const errorDivs = document.getElementById(modalId)?.querySelectorAll('.modal-error');
        errorDivs?.forEach(div => {
            debugLog(`6. Limpando mensagens de erro no modal: ${modalId}`);
            div.style.display = 'none';
            div.textContent = '';
        });
        if (modalId === 'recaptcha-modal') {
            debugLog('7. Tentando resetar reCAPTCHA');
            const recaptchaWidget = document.getElementById('g-recaptcha');
            const recaptchaResponse = document.getElementById('g-recaptcha-response');
            if (typeof grecaptcha !== 'undefined' && recaptchaWidget && recaptchaWidget.innerHTML) {
                debugLog('7.1. Widget reCAPTCHA encontrado, resetando');
                try {
                    grecaptcha.reset();
                    if (recaptchaResponse) {
                        recaptchaResponse.value = '';
                        debugLog('7.2. Campo g-recaptcha-response limpo');
                    }
                } catch (error) {
                    debugError('7.1. ERRO ao resetar reCAPTCHA:', error.message);
                }
            } else {
                debugLog('7.1. Widget reCAPTCHA não encontrado ou grecaptcha não definido, pulando reset');
                if (recaptchaResponse) {
                    recaptchaResponse.value = '';
                    debugLog('7.2. Campo g-recaptcha-response limpo');
                }
            }
        }
    }, delay);
}

function validateAndCloseModal(modalId) {
    debugLog('8. Validando e fechando modal:', modalId);
    if (modalId === 'smtp-modal') {
        const smtpAuthCheckbox = document.getElementById('smtp_auth');
        const smtpUsername = document.querySelector('#smtp-form input[name="smtp_username"]')?.value.trim();
        const errorDiv = document.getElementById('smtp-error');

        if (smtpAuthCheckbox && smtpAuthCheckbox.checked && !smtpUsername) {
            debugLog('9. Erro: Usuário SMTP obrigatório');
            if (errorDiv) {
                errorDiv.textContent = 'O campo Usuário SMTP é obrigatório quando a autenticação SMTP está habilitada.';
                errorDiv.style.display = 'block';
            }
            return;
        }
    }
    closeModal(modalId);
}

window.onclick = function(event) {
    const modals = document.getElementsByClassName('modal');
    for (let modal of modals) {
        if (event.target === modal) {
            debugLog('10. Clicado fora do modal:', modal.id);
            validateAndCloseModal(modal.id);
        }
    }
};

function renderRecaptcha() {
    debugLog('11. Iniciando renderRecaptcha');
    const siteKeyInput = document.getElementById('recaptcha_site_key');
    const widgetContainer = document.getElementById('recaptcha-widget');
    const errorDiv = document.getElementById('recaptcha-error');
    const enabledCheckbox = document.getElementById('recaptcha_enabled');
    const recaptchaResponse = document.getElementById('g-recaptcha-response');

    if (!enabledCheckbox || !enabledCheckbox.checked) {
        debugLog('12. reCAPTCHA desabilitado, limpando widget');
        if (widgetContainer) {
            widgetContainer.innerHTML = '';
        }
        if (recaptchaResponse) {
            recaptchaResponse.value = '';
        }
        return;
    }

    const siteKey = siteKeyInput?.value.trim();
    if (!siteKey) {
        debugLog('13. Erro: Site Key ausente');
        if (errorDiv) {
            errorDiv.textContent = 'Por favor, insira a Site Key para renderizar o reCAPTCHA.';
            errorDiv.style.display = 'block';
        }
        if (widgetContainer) {
            widgetContainer.innerHTML = '';
        }
        if (recaptchaResponse) {
            recaptchaResponse.value = '';
        }
        return;
    }

    if (errorDiv) {
        errorDiv.style.display = 'none';
        errorDiv.textContent = '';
    }
    debugLog('14. Mensagens de erro limpas');

    const existingWidget = document.getElementById('g-recaptcha');
    if (existingWidget && existingWidget.innerHTML && typeof grecaptcha !== 'undefined' && grecaptcha.getResponse()) {
        debugLog('15. Widget reCAPTCHA já existe e está ativo, pulando re-renderização');
        return;
    }

    if (widgetContainer) {
        widgetContainer.innerHTML = '<div id="g-recaptcha"></div>';
    }
    debugLog('15. Widget reCAPTCHA limpo e recriado');

    if (typeof grecaptcha === 'undefined') {
        debugError('15.1. ERRO: grecaptcha não está definido. Verifique se o script do reCAPTCHA foi carregado.');
        if (errorDiv) {
            errorDiv.textContent = 'Erro: O script do reCAPTCHA não foi carregado. Verifique sua conexão ou bloqueadores de anúncios.';
            errorDiv.style.display = 'block';
        }
        if (widgetContainer) {
            widgetContainer.innerHTML = '';
        }
        if (recaptchaResponse) {
            recaptchaResponse.value = '';
        }
        return;
    }

    try {
        const widgetId = grecaptcha.render('g-recaptcha', {
            'sitekey': siteKey,
            'callback': function(response) {
                debugLog('16. reCAPTCHA callback, resposta:', response);
                const recaptchaResponse = document.getElementById('g-recaptcha-response');
                if (recaptchaResponse) {
                    recaptchaResponse.value = response;
                    debugLog('16.1. Campo g-recaptcha-response preenchido com:', response);
                } else {
                    debugError('16. ERRO: Campo g-recaptcha-response não encontrado');
                }
            },
            'expired-callback': function() {
                debugLog('17. reCAPTCHA expirado');
                const recaptchaResponse = document.getElementById('g-recaptcha-response');
                if (recaptchaResponse) {
                    recaptchaResponse.value = '';
                    debugLog('17.1. Campo g-recaptcha-response limpo');
                }
                debugLog('17.2. Re-renderizando reCAPTCHA após expiração');
                renderRecaptcha();
            }
        });
        debugLog('18. reCAPTCHA renderizado com sucesso, widgetId:', widgetId);
    } catch (error) {
        debugError('18. Erro ao renderizar reCAPTCHA:', error.message);
        if (errorDiv) {
            errorDiv.textContent = 'Erro ao carregar o reCAPTCHA. Verifique a Site Key ou sua conexão.';
            errorDiv.style.display = 'block';
        }
        if (widgetContainer) {
            widgetContainer.innerHTML = '';
        }
        if (recaptchaResponse) {
            recaptchaResponse.value = '';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    debugLog('19. DOMContentLoaded disparado');

    function applyVisualEffects(buttons, mainBtn, text) {
        const originalTexts = new Map();
        buttons.forEach(btn => {
            originalTexts.set(btn, btn.textContent); // Armazenar o texto original de cada botão
            btn.disabled = true;
            btn.classList.add('disabled-state');
            btn.style.opacity = '0.5';
            btn.style.backgroundColor = '#808080';
            btn.setAttribute('data-force-opacity', '0.5');
            // Apenas o botão principal (mainBtn) recebe o texto "Validando, aguarde" e a animação
            if (btn === mainBtn) {
                btn.textContent = text;
                btn.classList.add('validating');
            }
            // Forçar renderização
            btn.offsetHeight; // Trigger reflow
            const computedStyle = window.getComputedStyle(btn);
            debugLog(`Estado do botão após aplicar efeitos (${btn.textContent}): disabled=${btn.disabled}, classList=${btn.classList.toString()}, text=${btn.textContent}, inlineOpacity=${btn.style.opacity}, computedOpacity=${computedStyle.opacity}, inlineBackground=${btn.style.backgroundColor}, computedBackground=${computedStyle.backgroundColor}`);
        });

        // Reaplicar estilo inline em intervalos para evitar sobrescrita
        const opacityInterval = setInterval(() => {
            let anyDisabled = false;
            buttons.forEach(btn => {
                if (btn.disabled) {
                    anyDisabled = true;
                    btn.style.opacity = '0.5';
                    btn.style.backgroundColor = '#808080';
                    btn.setAttribute('data-force-opacity', '0.5');
                    const newComputedStyle = window.getComputedStyle(btn);
                    debugLog(`Reaplicando estilo em intervalo (${btn.textContent}): inlineOpacity=${btn.style.opacity}, computedOpacity=${newComputedStyle.opacity}, inlineBackground=${btn.style.backgroundColor}, computedBackground=${newComputedStyle.backgroundColor}`);
                }
            });
            if (!anyDisabled) {
                clearInterval(opacityInterval);
            }
        }, 100);

        return originalTexts;
    }

    function restoreButton(buttons, originalTexts) {
        // Atraso mínimo de 1000ms para garantir que o efeito seja visível
        setTimeout(() => {
            buttons.forEach(btn => {
                btn.textContent = originalTexts.get(btn) || btn.textContent;
                btn.classList.remove('validating');
                btn.classList.remove('disabled-state');
                btn.disabled = false;
                btn.style.opacity = '';
                btn.style.backgroundColor = '';
                btn.removeAttribute('data-force-opacity');
                const computedStyle = window.getComputedStyle(btn);
                debugLog(`Estado do botão após restaurar (${btn.textContent}): disabled=${btn.disabled}, classList=${btn.classList.toString()}, text=${btn.textContent}, inlineOpacity=${btn.style.opacity}, computedOpacity=${computedStyle.opacity}, inlineBackground=${btn.style.backgroundColor}, computedBackground=${computedStyle.backgroundColor}`);
            });
        }, 1000);
    }

    const smtpForm = document.getElementById('smtp-form');
    if (smtpForm) {
        debugLog('20. Formulário SMTP encontrado, configurando evento de submissão');
        smtpForm.addEventListener('submit', function(event) {
            event.preventDefault();
            debugLog('21. Submissão do formulário SMTP interceptada');

            const submitter = event.submitter;
            const errorDiv = document.getElementById('smtp-error');
            if (errorDiv) {
                errorDiv.style.display = 'none';
                errorDiv.textContent = '';
            }
            debugLog('22. Mensagens de erro SMTP limpas');

            if (submitter && submitter.name === 'salvar_smtp') {
                debugLog('23. Botão de salvar clicado, validando configurações SMTP');

                const fromEmail = this.querySelector('[name="smtp_from_email"]')?.value.trim();
                const replyToEmail = this.querySelector('[name="smtp_reply_to"]')?.value.trim();
                const smtpAuthCheckbox = document.getElementById('smtp_auth');
                const smtpUsername = this.querySelector('[name="smtp_username"]')?.value.trim();

                if (fromEmail && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(fromEmail)) {
                    debugLog('24. Erro: E-mail do remetente inválido');
                    if (errorDiv) {
                        errorDiv.textContent = 'Por favor, insira um e-mail válido para o remetente.';
                        errorDiv.style.display = 'block';
                    }
                    return;
                }
                if (replyToEmail && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(replyToEmail)) {
                    debugLog('25. Erro: E-mail Reply-To inválido');
                    if (errorDiv) {
                        errorDiv.textContent = 'Por favor, insira um e-mail válido para respostas (Reply-To).';
                        errorDiv.style.display = 'block';
                    }
                    return;
                }
                if (smtpAuthCheckbox.checked && !smtpUsername) {
                    debugLog('26. Erro: Usuário SMTP obrigatório');
                    if (errorDiv) {
                        errorDiv.textContent = 'O campo Usuário SMTP é obrigatório quando a autenticação SMTP está habilitada.';
                        errorDiv.style.display = 'block';
                    }
                    return;
                }

                const saveBtn = document.querySelector('#smtp-form button[name="salvar_smtp"]');
                if (!saveBtn) {
                    debugError('27. ERRO: Botão de salvar SMTP não encontrado');
                    return;
                }
                const modal = document.getElementById('smtp-modal');
                const allButtons = modal ? Array.from(modal.querySelectorAll('button')) : [saveBtn];
                const originalTexts = applyVisualEffects(allButtons, saveBtn, 'Validando, aguarde');
                debugLog('27. Todos os botões do modal SMTP desabilitados e com animação');

                const formData = new FormData(this);
                formData.append('ajax_smtp_validate', '1');
                debugLog('28. FormData preparado:', Array.from(formData.entries()));

                const timeoutPromise = new Promise((resolve, reject) => {
                    setTimeout(() => {
                        debugLog('29. Timeout atingido ao validar SMTP');
                        reject(new Error('Tempo limite de 6 segundos excedido ao validar o servidor SMTP.'));
                    }, 6000);
                });

                const fetchPromise = fetch('configuracoes.php', {
                    method: 'POST',
                    body: formData
                }).then(response => {
                    debugLog('30. Resposta recebida do servidor');
                    if (!response.ok) {
                        throw new Error(`Erro HTTP ${response.status}`);
                    }
                    return response.json();
                });

                Promise.race([fetchPromise, timeoutPromise])
                    .then(result => {
                        debugLog('31. Dados recebidos:', result);
                        restoreButton(allButtons, originalTexts);

                        if (result.success) {
                            debugLog('32. Sucesso na validação SMTP, recarregando');
                            if (errorDiv) {
                                errorDiv.textContent = result.message;
                                errorDiv.className = 'success';
                                errorDiv.style.display = 'block';
                            }
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        } else {
                            debugLog('33. Erro na validação SMTP');
                            if (errorDiv) {
                                errorDiv.textContent = result.message;
                                errorDiv.className = 'modal-error';
                                errorDiv.style.display = 'block';
                            }
                        }
                    })
                    .catch(error => {
                        debugError('34. ERRO na requisição AJAX SMTP:', error);
                        if (errorDiv) {
                            errorDiv.textContent = error.message || 'Erro ao validar configurações SMTP.';
                            errorDiv.className = 'modal-error';
                            errorDiv.style.display = 'block';
                        }
                        restoreButton(allButtons, originalTexts);
                    });
            } else if (submitter && submitter.name === 'test_email_submit') {
                debugLog('35. Botão de teste clicado, enviando e-mail de teste');

                const testEmailInput = document.querySelector('.test-form input[name="test_email"]');
                const testEmail = testEmailInput?.value.trim();
                if (!testEmail) {
                    debugLog('36. Erro: E-mail de teste não fornecido');
                    if (errorDiv) {
                        errorDiv.textContent = 'Por favor, insira um e-mail para o teste.';
                        errorDiv.style.display = 'block';
                    }
                    return;
                }
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(testEmail)) {
                    debugLog('37. Erro: E-mail de teste inválido');
                    if (errorDiv) {
                        errorDiv.textContent = 'Por favor, insira um e-mail válido para o teste.';
                        errorDiv.style.display = 'block';
                    }
                    return;
                }

                const testBtn = document.querySelector('#smtp-form button[name="test_email_submit"]');
                if (!testBtn) {
                    debugError('38. ERRO: Botão de teste SMTP não encontrado');
                    return;
                }
                const modal = document.getElementById('smtp-modal');
                const allButtons = modal ? Array.from(modal.querySelectorAll('button')) : [testBtn];
                const originalTexts = applyVisualEffects(allButtons, testBtn, 'Enviando, aguarde');
                debugLog('38. Todos os botões do modal SMTP desabilitados e com animação');

                const formData = new FormData(this);
                formData.append('test_email_submit', '1');
                formData.append('test_email', testEmail);
                debugLog('39. FormData preparado para teste:', Array.from(formData.entries()));

                fetch('configuracoes.php', {
                    method: 'POST',
                    body: formData
                }).then(response => {
                    debugLog('40. Resposta recebida do teste de e-mail');
                    restoreButton(allButtons, originalTexts);

                    if (response.redirected) {
                        debugLog('41. Redirecionamento detectado, exibindo mensagem');
                        if (errorDiv) {
                            errorDiv.textContent = 'E-mail de teste enviado com sucesso!';
                            errorDiv.className = 'success';
                            errorDiv.style.display = 'block';
                        }
                        setTimeout(() => {
                            debugLog('42. Recarregando após teste de e-mail');
                            window.location.href = response.url;
                        }, 1000);
                    } else {
                        throw new Error('Resposta inesperada do servidor.');
                    }
                }).catch(error => {
                    debugError('43. ERRO ao enviar e-mail de teste:', error);
                    if (errorDiv) {
                        errorDiv.textContent = 'Erro ao enviar e-mail de teste: ' + error.message;
                        errorDiv.className = 'modal-error';
                        errorDiv.style.display = 'block';
                    }
                    restoreButton(allButtons, originalTexts);
                });
            } else {
                debugLog('44. Não é o botão de salvar nem de teste, permitindo submissão normal');
                smtpForm.submit();
            }
        });
    } else {
        debugError('20. ERRO: Formulário SMTP não encontrado');
    }

    const recaptchaForm = document.getElementById('recaptcha-form');
    if (recaptchaForm) {
        debugLog('45. Formulário reCAPTCHA encontrado, configurando evento de submissão');
        recaptchaForm.addEventListener('submit', function(event) {
            event.preventDefault();
            debugLog('46. Submissão do formulário reCAPTCHA interceptada');

            const errorDiv = document.getElementById('recaptcha-error');
            if (errorDiv) {
                errorDiv.style.display = 'none';
                errorDiv.textContent = '';
            }
            debugLog('47. Mensagens de erro reCAPTCHA limpas');

            const saveBtn = document.querySelector('#recaptcha-form button[name="salvar_recaptcha"]');
            if (!saveBtn) {
                debugError('48. ERRO: Botão de salvar reCAPTCHA não encontrado');
                return;
            }
            const modal = document.getElementById('recaptcha-modal');
            const allButtons = modal ? Array.from(modal.querySelectorAll('button')) : [saveBtn];
            const originalTexts = applyVisualEffects(allButtons, saveBtn, 'Validando, aguarde');
            debugLog('48. Todos os botões do modal reCAPTCHA desabilitados e com animação');

            const enabledCheckbox = document.getElementById('recaptcha_enabled');
            if (!enabledCheckbox?.checked) {
                debugLog('49. reCAPTCHA desabilitado, enviando sem validação');
                const formData = new FormData(recaptchaForm);
                formData.append('ajax_recaptcha_validate', '1');

                fetch('configuracoes.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    debugLog('50. Resposta recebida do servidor');
                    if (!response.ok) {
                        throw new Error('Erro HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    debugLog('51. Dados recebidos:', data);
                    restoreButton(allButtons, originalTexts);

                    if (data.success) {
                        debugLog('52. Sucesso na validação reCAPTCHA');
                        const successMessage = document.getElementById('success-message');
                        if (successMessage) {
                            successMessage.textContent = data.message;
                            successMessage.className = 'success';
                            successMessage.style.display = 'block';
                        } else {
                            debugError('52. ERRO: Elemento success-message não encontrado');
                        }
                        closeModal('recaptcha-modal', 1000);
                    } else {
                        debugLog('52. Erro na validação reCAPTCHA:', data.message);
                        if (errorDiv) {
                            errorDiv.textContent = data.message;
                            errorDiv.style.display = 'block';
                        } else {
                            debugError('52. ERRO: Elemento recaptcha-error não encontrado');
                        }
                    }
                })
                .catch(error => {
                    debugError('53. Erro na requisição AJAX reCAPTCHA:', error);
                    if (errorDiv) {
                        errorDiv.textContent = 'Erro ao salvar configurações: ' + error.message;
                        errorDiv.style.display = 'block';
                    } else {
                        debugError('53. ERRO: Elemento recaptcha-error não encontrado ao exibir erro AJAX');
                    }
                    restoreButton(allButtons, originalTexts);
                });
                return;
            }

            const siteKey = document.getElementById('recaptcha_site_key')?.value.trim();
            const secretKey = document.querySelector('#recaptcha-form input[name="recaptcha_secret_key"]')?.value.trim();

            if (!siteKey || !secretKey) {
                debugLog('54. Erro: Site Key ou Secret Key ausentes');
                if (errorDiv) {
                    errorDiv.textContent = 'Site Key e Secret Key são obrigatórios quando o reCAPTCHA está habilitado.';
                    errorDiv.style.display = 'block';
                }
                restoreButton(allButtons, originalTexts);
                debugLog('54.1. Botões restaurados após validação inicial falha');
                return;
            }

            let recaptchaResponse = document.getElementById('g-recaptcha-response')?.value;
            debugLog('55. Resposta do reCAPTCHA (via campo):', recaptchaResponse);

            if (!recaptchaResponse && typeof grecaptcha !== 'undefined') {
                recaptchaResponse = grecaptcha.getResponse();
                debugLog('55.1. Resposta obtida via grecaptcha.getResponse():', recaptchaResponse);
                if (recaptchaResponse) {
                    const recaptchaResponseField = document.getElementById('g-recaptcha-response');
                    if (recaptchaResponseField) {
                        recaptchaResponseField.value = recaptchaResponse;
                        debugLog('55.2. Campo g-recaptcha-response preenchido com:', recaptchaResponse);
                    }
                }
            }

            if (!recaptchaResponse) {
                debugLog('55. Erro: Resposta do reCAPTCHA ausente');
                if (errorDiv) {
                    errorDiv.textContent = 'Por favor, marque o checkbox do reCAPTCHA para validar as credenciais.';
                    errorDiv.style.display = 'block';
                }
                restoreButton(allButtons, originalTexts);
                debugLog('55.3. Botões restaurados após validação reCAPTCHA falha');
                return;
            }

            const formData = new FormData(recaptchaForm);
            formData.append('ajax_recaptcha_validate', '1');
            formData.append('g-recaptcha-response', recaptchaResponse);
            debugLog('56. FormData preparado:', Object.fromEntries(formData));

            fetch('configuracoes.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                debugLog('57. Resposta recebida do servidor');
                if (!response.ok) {
                    throw new Error('Erro HTTP ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                debugLog('58. Dados recebidos:', data);
                restoreButton(allButtons, originalTexts);

                if (data.success) {
                    debugLog('59. Sucesso na validação reCAPTCHA');
                    const successMessage = document.getElementById('success-message');
                    if (successMessage) {
                        successMessage.textContent = data.message;
                        successMessage.className = 'success';
                        successMessage.style.display = 'block';
                    } else {
                        debugError('59. ERRO: Elemento success-message não encontrado');
                    }
                    closeModal('recaptcha-modal', 1000);
                } else {
                    debugLog('59. Erro na validação reCAPTCHA:', data.message);
                    if (errorDiv) {
                        errorDiv.textContent = data.message;
                        errorDiv.style.display = 'block';
                    } else {
                        debugError('59. ERRO: Elemento recaptcha-error não encontrado');
                    }
                }
            })
            .catch(error => {
                debugError('60. Erro na requisição AJAX reCAPTCHA:', error);
                if (errorDiv) {
                    errorDiv.textContent = 'Erro ao salvar configurações: ' + error.message;
                    errorDiv.style.display = 'block';
                } else {
                    debugError('60. ERRO: Elemento recaptcha-error não encontrado ao exibir erro AJAX');
                }
                restoreButton(allButtons, originalTexts);
            });
        });

        const enabledCheckbox = document.getElementById('recaptcha_enabled');
        if (enabledCheckbox) {
            enabledCheckbox.addEventListener('change', function() {
                debugLog('61. Checkbox reCAPTCHA alterado:', enabledCheckbox.checked);
                renderRecaptcha();
            });
        }

        const siteKeyInput = document.getElementById('recaptcha_site_key');
        if (siteKeyInput) {
            siteKeyInput.addEventListener('input', function() {
                debugLog('62. Site Key alterada, re-renderizando reCAPTCHA');
                renderRecaptcha();
            });
        }
    } else {
        debugError('45. ERRO: Formulário reCAPTCHA não encontrado');
    }

    const telegramForm = document.getElementById('telegram-form');
    if (telegramForm) {
        debugLog('63. Formulário Telegram encontrado, configurando evento de submissão');
        telegramForm.addEventListener('submit', function(event) {
            event.preventDefault();
            debugLog('64. Submissão do formulário Telegram interceptada');

            const errorDiv = document.getElementById('telegram-error');
            if (errorDiv) {
                errorDiv.style.display = 'none';
                errorDiv.textContent = '';
            }
            debugLog('65. Mensagens de erro Telegram limpas');

            const saveBtn = document.querySelector('#telegram-form button[name="salvar_telegram"]');
            if (!saveBtn) {
                debugError('66. ERRO: Botão de salvar Telegram não encontrado');
                return;
            }
            const modal = document.getElementById('telegram-modal');
            const allButtons = modal ? Array.from(modal.querySelectorAll('button')) : [saveBtn];
            const originalTexts = applyVisualEffects(allButtons, saveBtn, 'Validando, aguarde');
            debugLog('66. Todos os botões do modal Telegram desabilitados e com animação');

            const formData = new FormData(telegramForm);
            formData.append('ajax_telegram_save', '1');
            formData.append('salvar_telegram', '1');
            debugLog('67. FormData preparado:', Object.fromEntries(formData));

            fetch('configuracoes.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                debugLog('68. Resposta recebida do servidor');
                if (!response.ok) {
                    throw new Error('Erro HTTP ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                debugLog('69. Dados recebidos:', data);
                restoreButton(allButtons, originalTexts);

                if (data.success) {
                    debugLog('70. Sucesso na validação Telegram');
                    const successMessage = document.getElementById('success-message');
                    if (successMessage) {
                        successMessage.textContent = data.message;
                        successMessage.className = data.warning ? 'warning' : 'success';
                        successMessage.style.display = 'block';
                    } else {
                        debugError('70. ERRO: Elemento success-message não encontrado');
                    }
                    closeModal('telegram-modal', 1000);
                } else {
                    debugLog('70. Erro na validação Telegram:', data.message);
                    if (errorDiv) {
                        errorDiv.textContent = 'Apenas um Token do Telegram pode ser cadastrado por vez.';
                        errorDiv.style.display = 'block';
                    } else {
                        debugError('70. ERRO: Elemento telegram-error não encontrado');
                    }
                }
            })
            .catch(error => {
                debugError('71. Erro na requisição AJAX Telegram:', error);
                if (errorDiv) {
                    errorDiv.textContent = 'Erro ao salvar configurações: ' + error.message;
                    errorDiv.style.display = 'block';
                } else {
                    debugError('71. ERRO: Elemento telegram-error não encontrado ao exibir erro AJAX');
                }
                restoreButton(allButtons, originalTexts);
            });
        });
    } else {
        debugError('63. ERRO: Formulário Telegram não encontrado');
    }

    const mapsForm = document.getElementById('maps-form');
    if (mapsForm) {
        debugLog('72. Formulário Google Maps encontrado, configurando evento de submissão');
        mapsForm.addEventListener('submit', function(event) {
            event.preventDefault();
            debugLog('73. Submissão do formulário Google Maps interceptada');

            const errorDiv = document.getElementById('maps-error');
            if (errorDiv) {
                errorDiv.style.display = 'none';
                errorDiv.textContent = '';
            }
            debugLog('74. Mensagens de erro Google Maps limpas');

            const saveBtn = document.querySelector('#maps-form button[name="salvar_maps"]');
            if (!saveBtn) {
                debugError('75. ERRO: Botão de salvar Google Maps não encontrado');
                return;
            }
            const modal = document.getElementById('maps-modal');
            const allButtons = modal ? Array.from(modal.querySelectorAll('button')) : [saveBtn];
            const originalTexts = applyVisualEffects(allButtons, saveBtn, 'Validando, aguarde');
            debugLog('75. Todos os botões do modal Google Maps desabilitados e com animação');

            const formData = new FormData(mapsForm);
            formData.append('ajax_maps_save', '1');
            formData.append('salvar_maps', '1');
            debugLog('76. FormData preparado:', Object.fromEntries(formData));

            fetch('configuracoes.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                debugLog('77. Resposta recebida do servidor');
                if (!response.ok) {
                    throw new Error('Erro HTTP ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                debugLog('78. Dados recebidos:', data);
                restoreButton(allButtons, originalTexts);

                if (data.success) {
                    debugLog('79. Sucesso na validação Google Maps');
                    const successMessage = document.getElementById('success-message');
                    if (successMessage) {
                        successMessage.textContent = data.message;
                        successMessage.className = data.warning ? 'warning' : 'success';
                        successMessage.style.display = 'block';
                    } else {
                        debugError('79. ERRO: Elemento success-message não encontrado');
                    }
                    closeModal('maps-modal', 1000);
                } else {
                    debugLog('79. Erro na validação Google Maps:', data.message);
                    if (errorDiv) {
                        errorDiv.textContent = data.message;
                        errorDiv.style.display = 'block';
                    } else {
                        debugError('79. ERRO: Elemento maps-error não encontrado');
                    }
                }
            })
            .catch(error => {
                debugError('80. Erro na requisição AJAX Google Maps:', error);
                if (errorDiv) {
                    errorDiv.textContent = 'Erro ao salvar configurações: ' + error.message;
                    errorDiv.style.display = 'block';
                } else {
                    debugError('80. ERRO: Elemento maps-error não encontrado ao exibir erro AJAX');
                }
                restoreButton(allButtons, originalTexts);
            });
        });
    } else {
        debugError('72. ERRO: Formulário Google Maps não encontrado');
    }

    const popupForm = document.getElementById('popup-form');
    if (popupForm) {
        debugLog('81. Formulário Pop-up encontrado, configurando evento de submissão');
        popupForm.addEventListener('submit', function(event) {
            event.preventDefault();
            debugLog('82. Submissão do formulário Pop-up interceptada');

            const errorDiv = document.getElementById('popup-error');
            if (errorDiv) {
                errorDiv.style.display = 'none';
                errorDiv.textContent = '';
            }
            debugLog('83. Mensagens de erro Pop-up limpas');

            const saveBtn = document.querySelector('#popup-form button[name="salvar_popup"]');
            if (!saveBtn) {
                debugError('84. ERRO: Botão de salvar Pop-up não encontrado');
                return;
            }
            const modal = document.getElementById('popup-modal');
            const allButtons = modal ? Array.from(modal.querySelectorAll('button')) : [saveBtn];
            const originalTexts = applyVisualEffects(allButtons, saveBtn, 'Validando, aguarde');
            debugLog('84. Todos os botões do modal Pop-up desabilitados e com animação');

            const popupMessage = this.querySelector('[name="popup_message"]')?.value.trim();
            if (!popupMessage) {
                debugLog('85. Erro: Texto do pop-up não fornecido');
                if (errorDiv) {
                    errorDiv.textContent = 'O texto do pop-up não pode estar vazio.';
                    errorDiv.style.display = 'block';
                }
                restoreButton(allButtons, originalTexts);
                debugLog('85.1. Botões restaurados após validação inicial falha');
                return;
            }

            const formData = new FormData(popupForm);
            formData.append('ajax_popup_save', '1');
            formData.append('salvar_popup', '1');
            debugLog('86. FormData preparado:', Object.fromEntries(formData));

            fetch('configuracoes.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                debugLog('87. Resposta recebida do servidor');
                if (!response.ok) {
                    throw new Error('Erro HTTP ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                debugLog('88. Dados recebidos:', data);
                restoreButton(allButtons, originalTexts);

                if (data.success) {
                    debugLog('89. Sucesso na validação Pop-up');
                    const successMessage = document.getElementById('success-message');
                    if (successMessage) {
                        successMessage.textContent = data.message;
                        successMessage.className = data.warning ? 'warning' : 'success';
                        successMessage.style.display = 'block';
                    } else {
                        debugError('89. ERRO: Elemento success-message não encontrado');
                    }
                    closeModal('popup-modal', 1000);
                } else {
                    debugLog('89. Erro na validação Pop-up:', data.message);
                    if (errorDiv) {
                        errorDiv.textContent = data.message;
                        errorDiv.style.display = 'block';
                    } else {
                        debugError('89. ERRO: Elemento popup-error não encontrado');
                    }
                }
            })
            .catch(error => {
                debugError('90. Erro na requisição AJAX Pop-up:', error);
                if (errorDiv) {
                    errorDiv.textContent = 'Erro ao salvar configurações: ' + error.message;
                    errorDiv.style.display = 'block';
                } else {
                    debugError('90. ERRO: Elemento popup-error não encontrado ao exibir erro AJAX');
                }
                restoreButton(allButtons, originalTexts);
            });
        });
    } else {
        debugError('81. ERRO: Formulário Pop-up não encontrado');
    }
});
