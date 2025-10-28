(function() {
    const DEBUG = false; // Ativei temporariamente para ajudar no diagnóstico

    function debugLog(...args) {
        if (DEBUG) {
            console.log(...args);
        }
    }

    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
            if (modalId === 'modal-2fa') {
                reset2FAForm();
            }
            enableAllButtonsInModal(modalId);
            debugLog(`Modal aberto: ${modalId}`);
        } else {
            debugLog(`Modal não encontrado: ${modalId}`);
        }
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
            modal.querySelectorAll('.modal-error').forEach(el => el.style.display = 'none');
            modal.querySelectorAll('.modal-success').forEach(el => el.style.display = 'none');
            if (modalId === 'modal-2fa') {
                reset2FAForm();
            }
            debugLog(`Modal fechado: ${modalId}`);
        } else {
            debugLog(`Modal não encontrado: ${modalId}`);
        }
    }

    function showError(modalId, message) {
        const errorDiv = document.getElementById(`${modalId}-error`);
        if (errorDiv) {
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
            debugLog(`Erro exibido em ${modalId}: ${message}`);
            // Armazenar o ID do modal para reabertura após recarregamento
            if (modalId === 'password' || modalId === 'telegram') {
                sessionStorage.setItem('reopenModal', `modal-${modalId}`);
                debugLog(`Modal ${modalId} armazenado para reabertura`);
            }
            // Recarregar a página após 1 segundo para permitir nova tentativa
            setTimeout(() => {
                debugLog(`Recarregando página após erro em ${modalId}`);
                location.reload();
            }, 1000);
        }
    }

    function showSuccess(modalId, message) {
        const successDiv = document.getElementById(`${modalId}-success`);
        if (successDiv) {
            successDiv.textContent = message;
            successDiv.style.display = 'block';
            debugLog(`Sucesso exibido em ${modalId}: ${message}`);
        }
    }

    function disableAllButtonsInModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        const buttons = modal.querySelectorAll('button');
        buttons.forEach(button => {
            button.disabled = true;
            button.classList.add('disabled');
        });
        debugLog(`Todos os botões desativados no modal: ${modalId}`);
    }

    function enableAllButtonsInModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        const buttons = modal.querySelectorAll('button');
        buttons.forEach(button => {
            button.disabled = false;
            button.classList.remove('disabled');
        });
        debugLog(`Todos os botões ativados no modal: ${modalId}`);
    }

    function reset2FAForm() {
        const errorDiv = document.getElementById('2fa-error');
        const successDiv = document.getElementById('2fa-success');
        const selectionDiv = document.getElementById('2fa-selection');
        const qrSectionDiv = document.getElementById('2fa-qr-section');
        const qrImage = document.getElementById('qr-code-image');
        if (errorDiv) errorDiv.style.display = 'none';
        if (successDiv) successDiv.style.display = 'none';
        if (selectionDiv) selectionDiv.style.display = 'block';
        if (qrSectionDiv) qrSectionDiv.style.display = 'none';
        if (qrImage) qrImage.src = '';
        debugLog('Formulário 2FA redefinido');
    }

    // Associar eventos aos botões de ação
    document.addEventListener('DOMContentLoaded', function() {
        const openPasswordModalBtn = document.getElementById('open-password-modal');
        const open2FAModalBtn = document.getElementById('open-2fa-modal');
        const openTelegramModalBtn = document.getElementById('open-telegram-modal');
        const openLogsModalBtn = document.getElementById('open-logs-modal');

        if (openPasswordModalBtn) {
            openPasswordModalBtn.addEventListener('click', () => openModal('modal-password'));
        } else {
            debugLog('Botão open-password-modal não encontrado');
        }
        if (open2FAModalBtn) {
            open2FAModalBtn.addEventListener('click', () => openModal('modal-2fa'));
        } else {
            debugLog('Botão open-2fa-modal não encontrado');
        }
        if (openTelegramModalBtn) {
            openTelegramModalBtn.addEventListener('click', () => openModal('modal-telegram'));
        } else {
            debugLog('Botão open-telegram-modal não encontrado');
        }
        if (openLogsModalBtn) {
            openLogsModalBtn.addEventListener('click', () => openModal('modal-logs'));
        } else {
            debugLog('Botão open-logs-modal não encontrado');
        }
        debugLog('Ouvintes de eventos dos botões de ação associados');

        // Associar eventos aos botões de fechar
        const closePasswordModalBtn = document.getElementById('close-password-modal');
        const close2FAModalBtn = document.getElementById('close-2fa-modal');
        const closeTelegramModalBtn = document.getElementById('close-telegram-modal');
        const closeLogsModalBtn = document.getElementById('close-logs-modal');

        if (closePasswordModalBtn) {
            closePasswordModalBtn.addEventListener('click', () => closeModal('modal-password'));
        } else {
            debugLog('Botão close-password-modal não encontrado');
        }
        if (close2FAModalBtn) {
            close2FAModalBtn.addEventListener('click', () => closeModal('modal-2fa'));
        } else {
            debugLog('Botão close-2fa-modal não encontrado');
        }
        if (closeTelegramModalBtn) {
            closeTelegramModalBtn.addEventListener('click', () => closeModal('modal-telegram'));
        } else {
            debugLog('Botão close-telegram-modal não encontrado');
        }
        if (closeLogsModalBtn) {
            closeLogsModalBtn.addEventListener('click', () => closeModal('modal-logs'));
        } else {
            debugLog('Botão close-logs-modal não encontrado');
        }
        debugLog('Ouvintes de eventos dos botões de fechar associados');

        // Garantir que os botões de ação estejam habilitados
        document.querySelectorAll('.action-button').forEach(button => {
            button.disabled = false;
            button.classList.remove('disabled');
            debugLog(`Botão de ação habilitado: ${button.id}`);
        });

        // Verificar se há um modal para reabrir após recarregamento
        const modalToReopen = sessionStorage.getItem('reopenModal');
        if (modalToReopen && (modalToReopen === 'modal-password' || modalToReopen === 'modal-telegram')) {
            openModal(modalToReopen);
            debugLog(`Modal reaberto após recarregamento: ${modalToReopen}`);
            sessionStorage.removeItem('reopenModal'); // Limpar para evitar reaberturas indesejadas
        }

        // Abrir modal de confirmação de 2FA, se necessário
        const confirmModal = document.getElementById('modal-confirm-2fa');
        if (confirmModal) {
            openModal('modal-confirm-2fa');
            debugLog('Modal confirm-2fa aberto ao carregar');
        }

        // Alternar visibilidade da senha no modal de senhas
        document.querySelectorAll('#modal-password .toggle-password').forEach(toggle => {
            toggle.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                if (input.type === 'password') {
                    input.type = 'text';
                    this.textContent = '🙈'; // Ícone para "ocultar"
                } else {
                    input.type = 'password';
                    this.textContent = '👁️'; // Ícone para "mostrar"
                }
                debugLog(`Visibilidade da senha alternada para ${targetId}`);
            });
        });
    });

    const twoFAEnabledCheckbox = document.getElementById('2fa_enabled');
    if (twoFAEnabledCheckbox) {
        twoFAEnabledCheckbox.addEventListener('change', function() {
            const typeOptions = document.getElementById('2fa-type-options');
            if (typeOptions) {
                typeOptions.style.display = this.checked ? 'block' : 'none';
                debugLog(`Checkbox 2FA alterado: ${this.checked}`);
            }
        });
    }

    // Modal de Configuração de 2FA (#modal-2fa)
    const twoFAForm = document.getElementById('2fa-form');
    if (twoFAForm) {
        twoFAForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const submitButton = document.getElementById('2fa-submit');
            if (submitButton) {
                submitButton.classList.add('validating');
            }
            const errorDiv = document.getElementById('2fa-error');
            const successDiv = document.getElementById('2fa-success');
            if (errorDiv) errorDiv.style.display = 'none';
            if (successDiv) successDiv.style.display = 'none';

            disableAllButtonsInModal('modal-2fa');

            const formData = new FormData();
            formData.append('ajax_2fa_validate', '1');
            formData.append('2fa_enabled', document.getElementById('2fa_enabled').checked ? '1' : '0');
            const type = document.querySelector('input[name="2fa_type"]:checked')?.value || 'email';
            formData.append('2fa_type', type);

            debugLog('Enviando formulário 2FA', { habilitado: document.getElementById('2fa_enabled').checked, tipo: type });

            fetch('meu-perfil.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro na resposta do servidor: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                debugLog('Resposta do formulário 2FA', data);
                if (data.success) {
                    showSuccess('2fa', data.message);
                    if (data.qrCode) {
                        const selectionDiv = document.getElementById('2fa-selection');
                        const qrSectionDiv = document.getElementById('2fa-qr-section');
                        const qrImage = document.getElementById('qr-code-image');
                        const secretKeyInput = document.getElementById('secret-key');
                        if (selectionDiv) selectionDiv.style.display = 'none';
                        if (qrSectionDiv) qrSectionDiv.style.display = 'block';
                        if (qrImage) qrImage.src = data.qrCode;
                        if (data.secret && secretKeyInput) {
                            secretKeyInput.value = data.secret;
                        }
                        showSuccess('2fa-qr', data.message);
                        enableAllButtonsInModal('modal-2fa');
                    } else {
                        setTimeout(() => {
                            closeModal('modal-2fa');
                            location.reload();
                        }, 650);
                    }
                } else {
                    showError('2fa', data.message);
                    enableAllButtonsInModal('modal-2fa');
                }
            })
            .catch(error => {
                showError('2fa', 'Erro ao processar a solicitação: ' + error.message);
                enableAllButtonsInModal('modal-2fa');
                debugLog('Erro no formulário 2FA', error);
            });
        });
    }

    const proceedButton = document.getElementById('proceed-totp-button');
    if (proceedButton) {
        proceedButton.addEventListener('click', function() {
            disableAllButtonsInModal('modal-2fa');
            closeModal('modal-2fa');
            location.reload();
            debugLog('Botão Prosseguir TOTP clicado');
        });
    }

    const cancelButton = document.getElementById('cancel-totp-button');
    if (cancelButton) {
        cancelButton.addEventListener('click', function() {
            disableAllButtonsInModal('modal-2fa');
            const errorDiv = document.getElementById('2fa-error');
            const successDiv = document.getElementById('2fa-success');
            const qrErrorDiv = document.getElementById('2fa-qr-error');
            const qrSuccessDiv = document.getElementById('2fa-qr-success');
            if (errorDiv) errorDiv.style.display = 'none';
            if (successDiv) successDiv.style.display = 'none';
            if (qrErrorDiv) qrErrorDiv.style.display = 'none';
            if (qrSuccessDiv) qrSuccessDiv.style.display = 'none';

            const formData = new FormData();
            formData.append('ajax_cancel_2fa', '1');

            debugLog('Botão Cancelar TOTP clicado');

            fetch('meu-perfil.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro na resposta do servidor: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                debugLog('Resposta do cancelamento TOTP', data);
                if (data.success) {
                    showSuccess('2fa', data.message);
                    setTimeout(() => {
                        closeModal('modal-2fa');
                        location.reload();
                    }, 650);
                } else {
                    showError('2fa', data.message);
                    enableAllButtonsInModal('modal-2fa');
                }
            })
            .catch(error => {
                showError('2fa', 'Erro ao processar a solicitação: ' + error.message);
                enableAllButtonsInModal('modal-2fa');
                debugLog('Erro no cancelamento TOTP', error);
            });
        });
    }

    const copySecretButton = document.getElementById('copy-secret-button');
    const secretKeyInput = document.getElementById('secret-key');
    if (copySecretButton && secretKeyInput) {
        copySecretButton.addEventListener('click', function() {
            secretKeyInput.select();
            try {
                document.execCommand('copy');
                copySecretButton.textContent = 'Copiado!';
                copySecretButton.classList.add('copied');
                setTimeout(() => {
                    copySecretButton.textContent = 'Copiar';
                    copySecretButton.classList.remove('copied');
                }, 2000);
                debugLog('Chave secreta copiada');
            } catch (err) {
                showError('2fa-qr', 'Erro ao copiar a chave.');
                debugLog('Erro ao copiar a chave secreta', err);
            }
        });
    }

    const confirm2FAForm = document.getElementById('confirm-2fa-form');
    if (confirm2FAForm) {
        confirm2FAForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const submitButton = document.getElementById('confirm-2fa-submit');
            if (submitButton) {
                submitButton.classList.add('validating');
            }
            const errorDiv = document.getElementById('confirm-2fa-error');
            const successDiv = document.getElementById('confirm-2fa-success');
            if (errorDiv) errorDiv.style.display = 'none';
            if (successDiv) successDiv.style.display = 'none';

            disableAllButtonsInModal('modal-confirm-2fa');

            const formData = new FormData(this);

            debugLog('Enviando formulário de confirmação 2FA');

            fetch('meu-perfil.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro na resposta do servidor: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                debugLog('Resposta da confirmação 2FA', data);
                if (data.success) {
                    showSuccess('confirm-2fa', data.message);
                    setTimeout(() => {
                        closeModal('modal-confirm-2fa');
                        location.reload();
                    }, 650);
                } else {
                    showError('confirm-2fa', data.message);
                    enableAllButtonsInModal('modal-confirm-2fa');
                }
            })
            .catch(error => {
                showError('confirm-2fa', 'Erro ao processar a solicitação: ' + error.message);
                enableAllButtonsInModal('modal-confirm-2fa');
                debugLog('Erro na confirmação 2FA', error);
            });
        });
    }

    const resend2FAButton = document.getElementById('resend-2fa-button');
    if (resend2FAButton) {
        resend2FAButton.addEventListener('click', function() {
            disableAllButtonsInModal('modal-confirm-2fa');
            const errorDiv = document.getElementById('confirm-2fa-error');
            const successDiv = document.getElementById('confirm-2fa-success');
            if (errorDiv) errorDiv.style.display = 'none';
            if (successDiv) successDiv.style.display = 'none';

            const formData = new FormData();
            formData.append('ajax_resend_2fa', '1');

            debugLog('Botão Reenviar Token 2FA clicado');

            fetch('meu-perfil.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro na resposta do servidor: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                debugLog('Resposta do reenvio 2FA', data);
                if (data.success) {
                    showSuccess('confirm-2fa', data.message);
                    enableAllButtonsInModal('modal-confirm-2fa');
                } else {
                    showError('confirm-2fa', data.message);
                    enableAllButtonsInModal('modal-confirm-2fa');
                }
            })
            .catch(error => {
                showError('confirm-2fa', 'Erro ao processar a solicitação: ' + error.message);
                enableAllButtonsInModal('modal-confirm-2fa');
                debugLog('Erro no reenvio 2FA', error);
            });
        });
    }

    const cancel2FAButton = document.getElementById('cancel-2fa-button');
    if (cancel2FAButton) {
        cancel2FAButton.addEventListener('click', function() {
            disableAllButtonsInModal('modal-confirm-2fa');
            const errorDiv = document.getElementById('confirm-2fa-error');
            const successDiv = document.getElementById('confirm-2fa-success');
            if (errorDiv) errorDiv.style.display = 'none';
            if (successDiv) successDiv.style.display = 'none';

            const formData = new FormData();
            formData.append('ajax_cancel_2fa', '1');

            debugLog('Botão Cancelar 2FA clicado');

            fetch('meu-perfil.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro na resposta do servidor: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                debugLog('Resposta do cancelamento 2FA', data);
                if (data.success) {
                    showSuccess('confirm-2fa', data.message);
                    setTimeout(() => {
                        closeModal('modal-confirm-2fa');
                        location.reload();
                    }, 650);
                } else {
                    showError('confirm-2fa', data.message);
                    enableAllButtonsInModal('modal-confirm-2fa');
                }
            })
            .catch(error => {
                showError('confirm-2fa', 'Erro ao processar a solicitação: ' + error.message);
                enableAllButtonsInModal('modal-confirm-2fa');
                debugLog('Erro no cancelamento 2FA', error);
            });
        });
    }

    const passwordForm = document.getElementById('password-form');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const submitButton = document.getElementById('password-submit');
            if (submitButton) {
                submitButton.classList.add('validating');
            }
            const errorDiv = document.getElementById('password-error');
            const successDiv = document.getElementById('password-success');
            if (errorDiv) errorDiv.style.display = 'none';
            if (successDiv) successDiv.style.display = 'none';

            disableAllButtonsInModal('modal-password');

            const formData = new FormData(this);

            debugLog('Enviando formulário de senha');

            fetch('meu-perfil.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro na resposta do servidor: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                debugLog('Resposta do formulário de senha', data);
                if (data.success) {
                    showSuccess('password', data.message);
                    setTimeout(() => {
                        closeModal('modal-password');
                    }, 650);
                } else {
                    showError('password', data.message);
                    enableAllButtonsInModal('modal-password');
                }
            })
            .catch(error => {
                showError('password', 'Erro ao processar a solicitação: ' + error.message);
                enableAllButtonsInModal('modal-password');
                debugLog('Erro no formulário de senha', error);
            });
        });
    }

    const telegramForm = document.getElementById('telegram-form');
    if (telegramForm) {
        telegramForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const submitButton = document.getElementById('telegram-submit');
            if (submitButton) {
                submitButton.classList.add('validating');
            }
            const errorDiv = document.getElementById('telegram-error');
            const successDiv = document.getElementById('telegram-success');
            if (errorDiv) errorDiv.style.display = 'none';
            if (successDiv) successDiv.style.display = 'none';

            disableAllButtonsInModal('modal-telegram');

            const formData = new FormData(this);

            debugLog('Enviando formulário de Telegram');

            fetch('meu-perfil.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro na resposta do servidor: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                debugLog('Resposta do formulário de Telegram', data);
                if (data.success) {
                    showSuccess('telegram', data.message);
                    setTimeout(() => {
                        closeModal('modal-telegram');
                        location.reload();
                    }, 650);
                } else {
                    showError('telegram', data.message);
                    enableAllButtonsInModal('modal-telegram');
                }
            })
            .catch(error => {
                showError('telegram', 'Erro ao processar a solicitação: ' + error.message);
                enableAllButtonsInModal('modal-telegram');
                debugLog('Erro no formulário de Telegram', error);
            });
        });
    }
})();
