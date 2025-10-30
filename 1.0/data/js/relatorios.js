(function() {
    const DEBUG = false; // Variável para ativar/desativar logs de depuração

    // Função para log de depuração
    function logDebug(message) {
        if (DEBUG) {
            console.log(`[DEBUG] ${message}`);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const reportForm = document.querySelector('form[method="POST"]');
        const errorModal = document.getElementById('errorModal');
        const historyModal = document.getElementById('historyModal');
        const closeErrorModal = errorModal.querySelector('.close');
        const closeHistoryModal = historyModal.querySelector('.close');
        const modalButton = errorModal.querySelector('.modal-button');
        const viewHistoryButton = document.getElementById('view-history');
        const container = document.querySelector('.container');
        const totalInstalacoes = parseInt(container.dataset.totalInstalacoes, 10);
        const ultimoVencimento = container.dataset.ultimoVencimento; // Formato MM/YYYY
        const successMessage = document.querySelector('.message.success');

        // Função para abrir um modal
        function openModal(modal, message, isSuccess = false) {
            if (message) {
                const modalTitle = modal.querySelector('h2');
                const modalContent = modal.querySelector('p');
                modalTitle.textContent = isSuccess ? 'Sucesso' : 'Erro';
                modalContent.textContent = message;
            }
            modal.style.display = 'block';
            logDebug(`${message ? (isSuccess ? 'Modal de sucesso' : 'Modal de erro') : 'Modal de histórico'} aberto: ${message || 'Relatórios Antigos'}`);
        }

        // Função para fechar um modal
        function closeModal(modal) {
            modal.style.display = 'none';
            logDebug(`Modal fechado: ${modal.id}`);
        }

        // Event listeners para fechar modais
        closeErrorModal.addEventListener('click', () => closeModal(errorModal));
        closeHistoryModal.addEventListener('click', () => closeModal(historyModal));
        modalButton.addEventListener('click', () => closeModal(errorModal));

        // Fechar modal ao clicar fora do conteúdo
        window.addEventListener('click', (event) => {
            if (event.target === errorModal) {
                closeModal(errorModal);
            } else if (event.target === historyModal) {
                closeModal(historyModal);
            }
        });

        // Abrir modal de relatórios antigos
        if (viewHistoryButton) {
            viewHistoryButton.addEventListener('click', () => {
                openModal(historyModal);
            });
        }

        // Exibe mensagem de sucesso, se existir
        if (successMessage) {
            openModal(errorModal, successMessage.textContent, true);
        }

        if (reportForm) {
            reportForm.addEventListener('submit', function(event) {
                logDebug('Formulário de geração de relatório enviado.');
                const mes = parseInt(document.getElementById('mes').value, 10);
                const ano = parseInt(document.getElementById('ano').value, 10);
                const anoAtual = new Date().getFullYear();
                const anoMaximo = anoAtual + 4;

                // Valida mês
                if (!mes || mes < 1 || mes > 12) {
                    logDebug('Mês inválido selecionado - validação no lado do cliente.');
                    openModal(errorModal, 'Por favor, selecione um mês válido.');
                    event.preventDefault();
                    return;
                }

                // Valida ano
                if (!ano || ano < anoAtual || ano > anoMaximo) {
                    logDebug('Ano inválido selecionado - validação no lado do cliente.');
                    openModal(errorModal, 'Por favor, selecione um ano válido.');
                    event.preventDefault();
                    return;
                }

                // Valida se o mês/ano é posterior ao último vencimento
                if (ultimoVencimento) {
                    const [ultimoMes, ultimoAno] = ultimoVencimento.split('/').map(Number);
                    const ultimoDate = new Date(ultimoAno, ultimoMes - 1);
                    const selecionadoDate = new Date(ano, mes - 1);
                    if (selecionadoDate <= ultimoDate) {
                        logDebug('Mês/ano selecionado não é posterior ao último vencimento.');
                        openModal(errorModal, `O mês selecionado deve ser posterior ao último vencimento registrado (${ultimoVencimento}).`);
                        event.preventDefault();
                        return;
                    }
                }

                // Verifica se há 3 instalações disponíveis
                if (totalInstalacoes < 3) {
                    logDebug('Tentativa de gerar relatório com menos de 3 instalações.');
                    openModal(errorModal, 'Não é possível gerar o relatório porque as últimas três instalações ainda não foram concluídas.');
                    event.preventDefault();
                    return;
                }

                logDebug(`Enviando formulário para gerar relatório com vencimento 05/${mes}/${ano}.`);
            });
        } else {
            logDebug('Formulário de geração de relatório não encontrado.');
        }
    });
})();
