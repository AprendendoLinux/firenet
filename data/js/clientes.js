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

function mascaraCPF(cpf) {
    cpf = cpf.replace(/\D/g, '');
    cpf = cpf.replace(/(\d{3})(\d)/, '$1.$2');
    cpf = cpf.replace(/(\d{3})(\d)/, '$1.$2');
    cpf = cpf.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    return cpf;
}

document.addEventListener('DOMContentLoaded', function() {
    debugLog('1. Página carregada');

    const cpfInput = document.getElementById('search_cpf');
    if (cpfInput) {
        debugLog('2. Campo CPF encontrado');
        cpfInput.addEventListener('input', function(e) {
            e.target.value = mascaraCPF(e.target.value);
        });
    } else {
        debugError('2. ERRO: Campo CPF não encontrado');
    }

    const modals = document.querySelectorAll('.modal');
    const editButtons = document.querySelectorAll('.edit-btn');
    const closeButtons = document.querySelectorAll('.close-btn');

    debugLog('3. Modais encontrados:', modals.length);
    debugLog('4. Botões de edição encontrados:', editButtons.length);
    debugLog('5. Botões de fechar encontrados:', closeButtons.length);

    editButtons.forEach((button, index) => {
        debugLog(`6. Associando evento ao botão Editar ${index + 1}, data-modal: ${button.getAttribute('data-modal')}`);
        button.addEventListener('click', function() {
            const modalId = this.getAttribute('data-modal');
            debugLog(`7. Botão Editar clicado, modal: ${modalId}`);
            const modal = document.getElementById(modalId);
            if (modal) {
                debugLog(`8. Modal ${modalId} encontrado, exibindo`);
                modal.style.display = 'flex';

                setTimeout(() => {
                    const form = modal.querySelector('.modal-content form');
                    const saveBtn = form ? form.querySelector('button[type="submit"]') : null;
                    debugLog(`9. Formulário no modal ${modalId}: ${form ? 'encontrado' : 'NÃO encontrado'}`);
                    if (form) {
                        debugLog('10. HTML do formulário:', form.outerHTML);
                    }
                    debugLog(`11. Botão Salvar no modal ${modalId}: ${saveBtn ? 'encontrado' : 'NÃO encontrado'}`);
                    if (!saveBtn) {
                        const fallbackBtn = modal.querySelector('button[type="submit"]');
                        debugLog(`12. Tentativa de busca alternativa do botão Salvar: ${fallbackBtn ? 'encontrado' : 'NÃO encontrado'}`);
                    }
                    if (form && saveBtn) {
                        debugLog('13. Estado do botão Salvar:', {
                            disabled: saveBtn.disabled,
                            textContent: saveBtn.textContent,
                            classList: Array.from(saveBtn.classList)
                        });
                        debugLog('14. Validando formulário:', {
                            tagName: form.tagName,
                            method: form.method,
                            action: form.action,
                            elements: form.elements.length
                        });
                    }
                }, 100);
            } else {
                debugError(`8. ERRO: Modal ${modalId} não encontrado`);
            }
        });
    });

    closeButtons.forEach((button, index) => {
        debugLog(`15. Associando evento ao botão Fechar ${index + 1}`);
        button.addEventListener('click', function() {
            debugLog('16. Botão Fechar clicado');
            const modal = this.closest('.modal');
            modal.style.display = 'none';
            const messageDiv = modal.querySelector('.modal-error, .modal-success');
            if (messageDiv) {
                debugLog('17. Removendo mensagem de erro/sucesso');
                messageDiv.remove();
            }
        });
    });

    modals.forEach((modal, index) => {
        debugLog(`18. Associando evento de clique fora do modal ${index + 1}`);
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                debugLog('19. Clicado fora do modal');
                this.style.display = 'none';
                const messageDiv = this.querySelector('.modal-error, .modal-success');
                if (messageDiv) {
                    debugLog('20. Removendo mensagem de erro/sucesso');
                    messageDiv.remove();
                }
            }
        });
    });

    debugLog('21. Configurando delegação de eventos para formulários');
    document.addEventListener('submit', function(e) {
        if (e.target.matches('.modal-content form')) {
            debugLog('22. Formulário modal submetido');
            e.preventDefault();

            const form = e.target;
            debugLog('23. Validando formulário:', {
                tagName: form.tagName,
                method: form.method,
                action: form.action,
                elements: form.elements.length
            });

            let saveBtn = form.querySelector('button[type="submit"]');
            if (!saveBtn) {
                debugError('24. ERRO: Botão Salvar não encontrado no formulário');
                saveBtn = form.closest('.modal-content').querySelector('button[type="submit"]');
                debugLog(`25. Tentativa de busca alternativa do botão Salvar: ${saveBtn ? 'encontrado' : 'NÃO encontrado'}`);
            }

            if (saveBtn) {
                debugLog('26. Botão Salvar encontrado, iniciando processo');
                debugLog('27. Estado do botão antes de desabilitar:', {
                    disabled: saveBtn.disabled,
                    textContent: saveBtn.textContent,
                    classList: Array.from(saveBtn.classList)
                });

                saveBtn.disabled = true;
                saveBtn.textContent = 'Salvando';
                saveBtn.classList.add('saving');
                debugLog('28. Botão desabilitado e com animação');
            } else {
                debugLog('29. Prosseguindo sem manipulação do botão');
            }

            const formData = new FormData(form);
            formData.append('ajax_atualizar_instalacao', '1');
            debugLog('30. FormData preparado:', Array.from(formData.entries()));

            debugLog('31. Enviando requisição AJAX');
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                debugLog('32. Resposta recebida do servidor, status:', response.status);
                if (!response.ok) {
                    throw new Error('Erro na resposta do servidor: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                debugLog('33. Dados recebidos:', data);
                if (saveBtn) {
                    saveBtn.textContent = 'Salvar';
                    saveBtn.classList.remove('saving');
                }

                let messageDiv = form.querySelector('.modal-error, .modal-success');
                if (!messageDiv) {
                    messageDiv = document.createElement('div');
                    form.insertBefore(messageDiv, form.firstChild);
                }

                if (data.success) {
                    messageDiv.textContent = data.message;
                    messageDiv.className = 'modal-success';
                    debugLog('34. Sucesso, recarregando página em 1s');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    messageDiv.textContent = data.message;
                    messageDiv.className = 'modal-error';
                    if (saveBtn) {
                        saveBtn.disabled = false;
                    }
                    debugLog('35. Erro exibido, botão reabilitado');
                }
            })
            .catch(error => {
                debugError('36. ERRO na requisição AJAX:', error);
                if (saveBtn) {
                    saveBtn.textContent = 'Salvar';
                    saveBtn.classList.remove('saving');
                    saveBtn.disabled = false;
                }

                let messageDiv = form.querySelector('.modal-error, .modal-success');
                if (!messageDiv) {
                    messageDiv = document.createElement('div');
                    form.insertBefore(messageDiv, form.firstChild);
                }
                messageDiv.textContent = 'Erro ao atualizar os dados: ' + error.message;
                messageDiv.className = 'modal-error';
                debugLog('37. Erro exibido, botão reabilitado');
            });
        }
    });

    debugLog('38. Inicialização concluída');
});

debugLog('39. Script carregado com sucesso');
