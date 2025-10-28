(function() {
    const DEBUG = false; // Variável para ativar/desativar logs de depuração

    // Função para log de depuração
    function logDebug(message) {
        if (DEBUG) {
            console.log(`[DEBUG] ${message}`);
        }
    }

    function mascaraCPF(cpf) {
        cpf = cpf.replace(/\D/g, '');
        cpf = cpf.replace(/(\d{3})(\d)/, '$1.$2');
        cpf = cpf.replace(/(\d{3})(\d)/, '$1.$2');
        cpf = cpf.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        logDebug(`CPF formatado: ${cpf}`);
        return cpf;
    }

    function mascaraCEP(cep) {
        cep = cep.replace(/\D/g, '');
        cep = cep.replace(/(\d{5})(\d)/, '$1-$2');
        logDebug(`CEP formatado: ${cep}`);
        return cep;
    }

    document.addEventListener('DOMContentLoaded', function() {
        const cpfInput = document.getElementById('cpf');
        cpfInput.addEventListener('input', function(e) {
            e.target.value = mascaraCPF(e.target.value);
        });

        const cepInput = document.getElementById('cep');
        cepInput.addEventListener('input', function(e) {
            e.target.value = mascaraCEP(e.target.value);
        });

        const form = document.querySelector('form[method="POST"]');
        if (form) {
            form.addEventListener('submit', function(event) {
                const nome = document.getElementById('nome').value.trim();
                const cpf = document.getElementById('cpf').value.trim();
                const cep = document.getElementById('cep').value.trim();
                const email = document.getElementById('email').value.trim();
                const plano = document.getElementById('plano').value;
                const vencimento = document.getElementById('vencimento').value;

                if (!nome) {
                    logDebug('Nome não preenchido - validação no lado do cliente.');
                    alert('O nome é obrigatório.');
                    event.preventDefault();
                    return;
                }

                if (!cpf || !/^\d{3}\.\d{3}\.\d{3}-\d{2}$/.test(cpf)) {
                    logDebug('CPF inválido - validação no lado do cliente.');
                    alert('O CPF é obrigatório e deve estar no formato 123.456.789-00.');
                    event.preventDefault();
                    return;
                }

                if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    logDebug('E-mail inválido - validação no lado do cliente.');
                    alert('O e-mail informado não é válido.');
                    event.preventDefault();
                    return;
                }

                if (cep && !/^\d{5}-\d{3}$/.test(cep)) {
                    logDebug('CEP inválido - validação no lado do cliente.');
                    alert('O CEP deve estar no formato 12345-678.');
                    event.preventDefault();
                    return;
                }

                const validVencimentos = ['Dia 5', 'Dia 10', 'Dia 15'];
                if (vencimento && !validVencimentos.includes(vencimento)) {
                    logDebug('Dia de vencimento inválido - validação no lado do cliente.');
                    alert("O dia de vencimento deve ser 'Dia 5', 'Dia 10' ou 'Dia 15'.");
                    event.preventDefault();
                    return;
                }

                const validPlanos = ['800 MEGA', '700 MEGA', '600 MEGA', '500 MEGA'];
                if (plano && !validPlanos.includes(plano)) {
                    logDebug('Plano inválido - validação no lado do cliente.');
                    alert("O plano deve ser '800 MEGA', '700 MEGA', '600 MEGA' ou '500 MEGA'.");
                    event.preventDefault();
                    return;
                }

                logDebug('Validações no lado do cliente passaram; enviando formulário.');
            });
        }

        logDebug('Scripts de formatação de CPF e CEP carregados.');
    });
})();
