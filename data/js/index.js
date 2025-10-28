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

let map;
let marker;
let geocoder;

// Explicitamente definir initMap no escopo global
window.initMap = function() {
    debugLog('1. Inicializando o mapa do Google Maps');
    const center = { lat: -22.8667, lng: -43.2833 };
    map = new google.maps.Map(document.getElementById("map"), {
        zoom: 14,
        center: center,
    });
    geocoder = new google.maps.Geocoder();
    debugLog('2. Mapa inicializado com sucesso', {
        center: center,
        zoom: 14
    });
};

function showLocation(address) {
    debugLog(`3. Função showLocation chamada com endereço: ${address}`);
    geocoder.geocode({ address: address }, (results, status) => {
        debugLog(`4. Resposta do geocoder - Status: ${status}`, {
            resultsLength: results ? results.length : 0
        });

        if (status === "OK") {
            const location = results[0].geometry.location;
            debugLog('5. Localização encontrada', {
                lat: location.lat(),
                lng: location.lng()
            });

            map.setCenter(location);
            map.setZoom(16);
            debugLog('6. Mapa centralizado e zoom ajustado', {
                newCenter: { lat: location.lat(), lng: location.lng() },
                newZoom: 16
            });

            if (marker) {
                debugLog('7. Removendo marcador existente');
                marker.setMap(null);
            }

            marker = new google.maps.Marker({
                position: location,
                map: map,
                title: address,
            });
            debugLog('8. Novo marcador adicionado ao mapa', {
                position: { lat: location.lat(), lng: location.lng() },
                title: address
            });

            const mapElement = document.getElementById("map");
            if (mapElement) {
                debugLog('9. Rolando a página para o elemento do mapa');
                mapElement.scrollIntoView({ behavior: "smooth", block: "start" });
            } else {
                debugError('9. ERRO: Elemento do mapa não encontrado');
            }
        } else {
            debugError(`5. ERRO: Falha ao encontrar a localização - Endereço: ${address}, Status: ${status}`);
            alert("Não foi possível encontrar a localização: " + address + ". Erro: " + status);
        }
    });
}

// Função para buscar rua digitada
window.searchStreet = function() {
    const searchInput = document.getElementById('searchStreet').value.trim();
    debugLog(`14. Função searchStreet chamada com entrada: ${searchInput}`);
    
    if (searchInput === '') {
        debugError('15. ERRO: Campo de busca vazio');
        alert('Por favor, digite o nome da rua para buscar.');
        return;
    }

    const address = `${searchInput}, Rio de Janeiro`;
    showLocation(address);
};

// Função para filtrar a lista de ruas com base na busca
function filterStreets() {
    const searchInput = document.getElementById('searchStreet').value.trim().toLowerCase();
    debugLog(`17. Função filterStreets chamada com entrada: ${searchInput}`);
    
    const streetItems = document.querySelectorAll('#coverageList li');
    streetItems.forEach(item => {
        const address = item.getAttribute('data-address').toLowerCase();
        if (searchInput === '' || address.includes(searchInput)) {
            item.style.display = 'list-item';
            debugLog(`18. Exibindo item: ${address}`);
        } else {
            item.style.display = 'none';
            debugLog(`18. Ocultando item: ${address}`);
        }
    });
}

// Adiciona eventos de busca e filtragem
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchStreet');
    if (searchInput) {
        searchInput.addEventListener('keypress', (event) => {
            if (event.key === 'Enter') {
                debugLog('16. Tecla Enter pressionada na caixa de busca');
                searchStreet();
            }
        });
        searchInput.addEventListener('input', () => {
            debugLog('19. Evento input disparado na caixa de busca');
            filterStreets();
        });
    }
});

// Função para mostrar a janela flutuante ao rolar
function handleScroll() {
    const signupPopup = document.getElementById('signupPopup');
    if (signupPopup && window.scrollY > 100) { // Mostra após rolar 100px
        signupPopup.classList.add('visible');
        debugLog('11. Janela flutuante "Assine já" exibida');
        window.removeEventListener('scroll', handleScroll); // Remove o listener após exibir
    }
}

// Função para fechar a janela flutuante
window.closeSignupPopup = function() {
    const signupPopup = document.getElementById('signupPopup');
    if (signupPopup) {
        signupPopup.style.display = 'none';
        debugLog('12. Janela flutuante "Assine já" fechada');
    }
};

// Adiciona o listener de rolagem quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', () => {
    debugLog('13. Adicionando listener de rolagem para a janela flutuante');
    window.addEventListener('scroll', handleScroll);
});

debugLog('10. Script index.js carregado com sucesso');