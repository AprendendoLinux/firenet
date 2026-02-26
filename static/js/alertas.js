// static/js/alertas.js

// 1. Injetando CSS caprichado para refinar o visual do SweetAlert2
const swalEstilos = document.createElement('style');
swalEstilos.innerHTML = `
    /* Ícone menor usando font-size para NÃO anular a animação nativa */
    .swal2-icon.icone-menor {
        font-size: 0.70em !important; 
        margin: 1.5rem auto 0.5rem auto !important;
    }
    
    /* Título com visual mais sofisticado e peso correto */
    .swal2-title.titulo-elegante {
        font-size: 1.25rem !important;
        font-weight: 700 !important;
        color: #2b2b2b !important;
        padding: 0 !important;
        margin-bottom: 0.5rem !important;
    }

    /* Texto da mensagem mais suave e com respiro lateral */
    .swal2-html-container.texto-suave {
        font-size: 0.95rem !important;
        color: #6c757d !important;
        margin-top: 0 !important;
        padding: 0 1rem !important;
    }

    /* Caixa retangular com sombra premium e bordas modernas */
    .swal2-popup.caixa-retangular {
        border-radius: 16px !important;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08) !important;
        padding: 1rem 2rem 2rem 2rem !important;
    }
    
    /* Espaçamento perfeito para separar a mensagem dos botões */
    .swal2-actions.botoes-espacados {
        margin-top: 1.5rem !important;
    }
`;
document.head.appendChild(swalEstilos);

// 2. Configurando o Template do Modal FireNet
const ModalPadrao = Swal.mixin({
    customClass: {
        popup: 'border-0 caixa-retangular',
        icon: 'icone-menor',
        title: 'titulo-elegante',
        htmlContainer: 'texto-suave',
        actions: 'botoes-espacados',
        
        // Mantendo exatamente os botões vazados que você escolheu!
        confirmButton: 'btn btn-outline-danger px-4 rounded-pill mx-1 fw-bold',
        cancelButton: 'btn btn-outline-secondary px-4 rounded-pill mx-1 fw-medium'
    },
    width: '480px', // Largura maior para garantir o formato retangular
    buttonsStyling: false,
    confirmButtonText: 'Sim',
    cancelButtonText: 'Cancelar',
    showCancelButton: true,
    background: '#ffffff',
    iconColor: '#d32f2f' // Vermelho da FireNet
});