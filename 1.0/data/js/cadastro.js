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

// Função para formatar CPF
document.getElementById('cpf').addEventListener('input', function (e) {
    let value = e.target.value.replace(/\D/g, ''); // Remove tudo que não é número
    if (value.length > 11) value = value.slice(0, 11); // Limita a 11 dígitos
    value = value.replace(/(\d{3})(\d)/, '$1.$2'); // Adiciona o primeiro ponto
    value = value.replace(/(\d{3})(\d)/, '$1.$2'); // Adiciona o segundo ponto
    value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2'); // Adiciona o hífen
    e.target.value = value;
    logDebug(`CPF formatado: ${value}`);
    validateCPF(); // Valida o CPF após cada alteração
});

// Função para verificar se o CPF já existe
async function checkCPFExists(cpf) {
    try {
        logDebug(`Verificando se o CPF ${cpf} já existe...`);
        const response = await fetch('check_cpf.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'cpf=' + encodeURIComponent(cpf)
        });
        const result = await response.json();
        logDebug(`Resultado da verificação de CPF: ${JSON.stringify(result)}`);
        return result.exists;
    } catch (error) {
        logError(`Erro ao verificar CPF: ${error.message}`);
        return false;
    }
}

// Função para validar CPF
async function validateCPF() {
    const cpfInput = document.getElementById('cpf');
    const cpfError = document.getElementById('cpf_error');
    let cpf = cpfInput.value.replace(/\D/g, ''); // Remove pontos e hífen

    logDebug(`Validando CPF: ${cpf}`);

    // Verifica se o CPF já está cadastrado
    if (cpf.length === 11) {
        const cpfExists = await checkCPFExists(cpfInput.value);
        if (cpfExists) {
            cpfError.textContent = 'Cliente já cadastrado, entre em contato com o suporte!';
            cpfError.style.display = 'block';
            logDebug('CPF já cadastrado.');
            return false;
        }
    }

    if (cpf.length !== 11) {
        cpfError.textContent = 'O CPF deve ter 11 dígitos.';
        cpfError.style.display = 'block';
        logDebug('CPF com tamanho inválido.');
        return false;
    }

    // Verifica se todos os dígitos são iguais
    if (/^(\d)\1+$/.test(cpf)) {
        cpfError.textContent = 'CPF inválido (todos os dígitos iguais).';
        cpfError.style.display = 'block';
        logDebug('CPF inválido: todos os dígitos iguais.');
        return false;
    }

    // Calcula os dígitos verificadores
    let sum = 0;
    for (let i = 0; i < 9; i++) {
        sum += parseInt(cpf.charAt(i)) * (10 - i);
    }
    let digit1 = (sum * 10) % 11;
    if (digit1 === 10) digit1 = 0;
    if (digit1 !== parseInt(cpf.charAt(9))) {
        cpfError.textContent = 'CPF inválido.';
        cpfError.style.display = 'block';
        logDebug('CPF inválido: primeiro dígito verificador incorreto.');
        return false;
    }

    sum = 0;
    for (let i = 0; i < 10; i++) {
        sum += parseInt(cpf.charAt(i)) * (11 - i);
    }
    let digit2 = (sum * 10) % 11;
    if (digit2 === 10) digit2 = 0;
    if (digit2 !== parseInt(cpf.charAt(10))) {
        cpfError.textContent = 'CPF inválido.';
        cpfError.style.display = 'block';
        logDebug('CPF inválido: segundo dígito verificador incorreto.');
        return false;
    }

    cpfError.textContent = '';
    cpfError.style.display = 'none';
    logDebug('CPF válido.');
    return true;
}

// Função para formatar RG
document.getElementById('rg').addEventListener('input', function (e) {
    let value = e.target.value.replace(/\D/g, ''); // Remove tudo que não é número
    if (value.length > 9) value = value.slice(0, 9); // Limita a 9 dígitos
    value = value.replace(/(\d{2})(\d)/, '$1.$2'); // Adiciona o primeiro ponto
    value = value.replace(/(\d{3})(\d)/, '$1.$2'); // Adiciona o segundo ponto
    value = value.replace(/(\d{3})(\d{1})$/, '$1-$2'); // Adiciona o hífen
    e.target.value = value;
    logDebug(`RG formatado: ${value}`);
});

// Função para formatar Telefone
document.getElementById('telefone').addEventListener('input', function (e) {
    let value = e.target.value.replace(/\D/g, ''); // Remove tudo que não é número
    if (value.length > 11) value = value.slice(0, 11); // Limita a 11 dígitos
    value = value.replace(/(\d{2})(\d)/, '($1) $2'); // Adiciona os parênteses e espaço
    value = value.replace(/(\d{5})(\d)/, '$1-$2'); // Adiciona o hífen
    e.target.value = value;
    logDebug(`Telefone formatado: ${value}`);
});

// Função para formatar CEP e validar
document.getElementById('cep').addEventListener('input', function (e) {
    let value = e.target.value.replace(/\D/g, ''); // Remove tudo que não é número
    if (value.length > 8) value = value.slice(0, 8); // Limita a 8 dígitos
    value = value.replace(/(\d{5})(\d)/, '$1-$2'); // Adiciona o hífen
    e.target.value = value;

    const cepError = document.getElementById('cep_error');

    // Se o CEP tiver 8 dígitos, consulta a API ViaCEP
    if (value.length === 9) { // Formato XXXXX-XXX
        logDebug(`Consultando CEP: ${value}`);
        fetchCEP(value);
    } else {
        // Se o CEP for apagado ou incompleto, limpa os campos e a mensagem de erro
        document.getElementById('rua').value = '';
        document.getElementById('bairro').value = '';
        cepError.textContent = '';
        cepError.style.display = 'none';
        logDebug('CEP incompleto, campos de endereço limpos.');
    }
});

// Função para consultar o CEP na API ViaCEP
function fetchCEP(cep) {
    const url = `https://viacep.com.br/ws/${cep.replace('-', '')}/json/`;
    const cepError = document.getElementById('cep_error');
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.erro) {
                cepError.textContent = 'CEP não encontrado. Por favor, verifique o CEP digitado.';
                cepError.style.display = 'block';
                document.getElementById('rua').value = '';
                document.getElementById('bairro').value = '';
                logDebug('CEP não encontrado.');
            } else {
                cepError.textContent = '';
                cepError.style.display = 'none';
                document.getElementById('rua').value = data.logradouro || '';
                document.getElementById('bairro').value = data.bairro || '';
                logDebug(`CEP encontrado: Rua - ${data.logradouro}, Bairro - ${data.bairro}`);
            }
        })
        .catch(error => {
            logError(`Erro ao consultar o CEP: ${error.message}`);
            cepError.textContent = 'Erro ao consultar o CEP. Tente novamente mais tarde.';
            cepError.style.display = 'block';
            document.getElementById('rua').value = '';
            document.getElementById('bairro').value = '';
        });
}

// Validação da senha_rede
const senhaRedeInput = document.getElementById('senha_rede');
const senhaRedeError = document.getElementById('senha_rede_error');
const submitBtn = document.getElementById('submit-btn');

function validateSenhaRede() {
    const value = senhaRedeInput.value;
    logDebug(`Validando senha da rede: ${value}`);
    if (value.length < 8) {
        senhaRedeError.textContent = 'A senha deve ter no mínimo 8 caracteres.';
        senhaRedeError.style.display = 'block';
        logDebug('Senha da rede muito curta.');
        return false;
    } else if (value.length > 10) {
        senhaRedeError.textContent = 'A senha deve ter no máximo 10 caracteres.';
        senhaRedeError.style.display = 'block';
        logDebug('Senha da rede muito longa.');
        return false;
    } else {
        senhaRedeError.textContent = '';
        senhaRedeError.style.display = 'none';
        logDebug('Senha da rede válida.');
        return true;
    }
}

// Função para formatar o nome com iniciais maiúsculas
function formatarNome(nome) {
    const formatted = nome
        .toLowerCase()
        .replace(/(^|\s)\w/g, letra => letra.toUpperCase());
    logDebug(`Nome formatado: ${formatted}`);
    return formatted;
}

// Aplica a formatação ao campo Nome Completo
document.getElementById('nome').addEventListener('input', function (e) {
    e.target.value = formatarNome(e.target.value);
});

// Função para verificar se o formulário está válido
async function updateSubmitButton() {
    const isCpfValid = await validateCPF();
    const isSenhaValid = validateSenhaRede();
    submitBtn.disabled = !(isCpfValid && isSenhaValid);
    logDebug(`Botão de envio atualizado: ${!submitBtn.disabled ? 'Habilitado' : 'Desabilitado'}`);
}

// Eventos de validação em tempo real
document.getElementById('cpf').addEventListener('input', updateSubmitButton);
senhaRedeInput.addEventListener('input', updateSubmitButton);

// Validação inicial ao carregar a página
updateSubmitButton();

// Comportamento do botão Enviar
document.getElementById('cadastro-form').addEventListener('submit', function(event) {
    const submitButton = document.getElementById('submit-btn');
    const errorDiv = document.getElementById('form-error');
    errorDiv.style.display = 'none';
    errorDiv.textContent = '';

    // Desabilita o botão e mostra o estado de envio
    submitButton.disabled = true;
    submitButton.textContent = 'Enviando';
    submitButton.classList.add('submitting');
    logDebug('Formulário enviado, botão desabilitado e mostrando estado de envio.');
});
