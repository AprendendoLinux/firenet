<?php
// index.php
require_once 'config.php';

// Carrega configurações do sistema
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    error_log("Erro ao carregar configurações do sistema: " . $e->getMessage());
    $settings = [];
}

// Define a variável de depuração
$DEBUG_LOGS = false;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FireNet Telecom - Internet de Alta Velocidade</title>
    <link rel="stylesheet" href="/css/index.css?v=1">

    <!-- Meta Tags Gerais -->
    <meta name="description" content="A FireNet Telecom oferece internet de alta velocidade com planos de até 800 MEGA. Conexão estável e rápida para Pilares, Inhaúma, Engenho da Rainha e Tomás Coelho, RJ. Assine agora!">

    <!-- Meta Tags Open Graph para Redes Sociais -->
    <meta property="og:title" content="FireNet Telecom - Internet de Alta Velocidade">
    <meta property="og:description" content="Conexão rápida e estável com planos de até 800 MEGA. Cobertura em Pilares, Inhaúma, Engenho da Rainha e Tomás Coelho, RJ. Confira nossos planos e assine já!">
    <meta property="og:image" content="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/imagens/card.jpg'; ?>">
    <meta property="og:url" content="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/index.php'; ?>">
    <meta property="og:type" content="website">

    <!-- Meta Tags para Twitter (opcional, mas recomendado) -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="FireNet Telecom - Internet de Alta Velocidade">
    <meta name="twitter:description" content="Conexão rápida e estável com planos de até 800 MEGA. Cobertura em Pilares, Inhaúma, Engenho da Rainha e Tomás Coelho, RJ. Assine já!">
    <meta name="twitter:image" content="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/imagens/card.jpg'; ?>">
</head>
<body>
    <header>
        <a href="/"><a href='/'><img src="imagens/firenet.png" alt="Logotipo FireNet Telecom"></a></a>
    </header>

    <nav>
        <a href="/">Início</a>
        <a href="#planos">Planos</a>
        <a href="#vantagens">Vantagens</a>
        <a href="#cobertura" onclick="focusSearch()">Cobertura</a>
        <a href="#central-cliente">Central do Cliente</a>
        <a href="#cadastro">Cadastro</a>
        <a href="#contato">Contato</a>
        <a href="login.php">Área do Consultor</a>
    </nav>

    <div class="container">
        <div class="section" id="inicio">
            <h2>Internet de Alta Velocidade com a FireNet Telecom</h2>
            <div class="intro-card">
                <p>
                    A FireNet Telecom oferece a melhor experiência de internet para você, seja para navegar, jogar ou trabalhar. Com velocidades de até 800 MEGA, garantimos conexão estável e rápida para atender às suas necessidades. Assine agora e faça parte da revolução digital!
                </p>
            </div>
        </div>

        <div class="section" id="planos">
            <h2>Nossos Planos</h2>
            <p>
                Escolha o plano que melhor se adapta às suas necessidades. Todos os valores incluem descontos para pagamento até o vencimento!
            </p>
            <div class="plans">
                <div class="plan-card" onclick="window.location.href='#cadastro'">
                    <h3>800 MEGA</h3>
                    <p>R$ 164,90*</p>
                    <p class="full-price">Valor Integral: R$ 184,90</p>
                    <p class="discount-note">*Com desconto até o vencimento</p>
                </div>

                <div class="plan-card" onclick="window.location.href='#cadastro'">
                    <h3>700 MEGA</h3>
                    <p>R$ 144,90*</p>
                    <p class="full-price">Valor Integral: R$ 164,90</p>
                    <p class="discount-note">*Com desconto até o vencimento</p>
               </div>
                <div class="plan-card" onclick="window.location.href='#cadastro'">
                    <h3>600 MEGA</h3>
                    <p>R$ 104,90*</p>
                    <p class="full-price">Valor Integral: R$ 124,90</p>
                    <p class="discount-note">*Com desconto até o vencimento</p>
                </div>
                <div class="plan-card" onclick="window.location.href='#cadastro'">
                    <h3>500 MEGA</h3>
                    <p>R$ 84,90*</p>
                    <p class="full-price">Valor Integral: R$ 104,90</p>
                    <p class="discount-note">*Com desconto até o vencimento</p>
                </div>
            </div>
            <p class="installation-info">
                <strong>Taxa de Instalação:</strong> A instalação tem uma taxa única de <strong>R$ 120,00</strong> para os bairros Pilares, Inhaúma, Engenho da Rainha e Tomás Coelho. Esse custo cobre os materiais necessários para a ativação do serviço, incluindo o equipamento ONT (que fica na casa do cliente), a fibra óptica e os conectores. O pagamento deve ser feito diretamente ao técnico no momento da finalização, aceitando <strong>dinheiro, Pix, cartão de débito ou crédito</strong>.
            </p>
            <p class="no-fidelity-message">
                Todos os nossos planos são livres de fidelidade. Aqui, você tem total liberdade para permanecer conosco apenas enquanto estiver satisfeito com o serviço.
            </p>
        </div>

        <div class="section" id="vantagens">
            <h2>Vantagens da FireNet Telecom</h2>
            <p>
                Escolher a FireNet Telecom significa optar por qualidade, velocidade e confiabilidade. Confira os benefícios que oferecemos:
            </p>
            <div class="advantages">
                <div class="advantage-card" onclick="window.location.href='#cadastro'">
                    <p>Suporte a IPv6 e IPv4 fixo fora do CGNAT (por uma pequena taxa de aluguel).</p>
                </div>
                <div class="advantage-card" onclick="window.location.href='#cadastro'">
                    <p>Baixa latência e ping baixo para destinos nacionais, ideal para gamers e profissionais de TI.</p>
                </div>
                <div class="advantage-card" onclick="window.location.href='#cadastro'">
                    <p>DNS próprio na própria rede, agilizando a resolução de nomes e melhorando a abertura de sites.</p>
                </div>
                <div class="advantage-card" onclick="window.location.href='#cadastro'">
                    <p>Taxa de Upload proporcional a taxa de Download.</p>
                </div>
                <div class="advantage-card" onclick="window.location.href='#cadastro'">
                    <p>Conexão estável, perfeita para streaming e downloads.</p>
                </div>
                <div class="advantage-card" onclick="window.location.href='#cadastro'">
                    <p>Planos com descontos exclusivos até o vencimento, economizando no seu bolso.</p>
                </div>
                <div class="advantage-card" onclick="window.location.href='#cadastro'">
                    <p>Atendimento personalizado via WhatsApp, com suporte rápido e eficiente.</p>
                </div>
                <div class="advantage-card" onclick="window.location.href='#cadastro'">
                    <p>Infraestrutura de ponta para garantir a melhor performance em qualquer situação.</p>
                </div>
            </div>
        </div>

        <div class="section" id="cobertura">
            <h2>Área de Cobertura</h2>
            <p>
                Digite o nome da rua no campo abaixo ou clique em uma das ruas listadas para verificar a cobertura da FireNet Telecom em Pilares, Inhaúma, Engenho da Rainha e Tomás Coelho.
            </p>
            <div class="search-box">
                <input type="text" id="searchStreet" placeholder="Digite o nome da rua">
                <button onclick="searchStreet()">Buscar</button>
            </div>
            <div id="map" class="coverage-map"></div>
            <h3>Ruas com Cobertura</h3>
            <ul class="coverage-list" id="coverageList">
                <li data-address="Avenida João Ribeiro (Rua), Pilares, Rio de Janeiro"><a onclick="showLocation('Avenida João Ribeiro (Rua), Pilares, Rio de Janeiro')">Avenida João Ribeiro (Rua)</a></li>
                <li data-address="Avenida João Ribeiro (Vila 805), Pilares, Rio de Janeiro"><a onclick="showLocation('Avenida João Ribeiro (Vila 805), Pilares, Rio de Janeiro')">Avenida João Ribeiro (Vila 805)</a></li>
                <li data-address="Avenida Pastor Martin Luther King, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Avenida Pastor Martin Luther King, Inhaúma, Rio de Janeiro')">Avenida Pastor Martin Luther King e Automóvel Club</a></li>
                <li data-address="Condomínio José dos Reis, 2100, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Condomínio José dos Reis, 2100, Inhaúma, Rio de Janeiro')">Condomínio José dos Reis, 2100</a></li>
                <li data-address="Estrada Adhemar Bebiano, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Estrada Adhemar Bebiano, Inhaúma, Rio de Janeiro')">Estrada Adhemar Bebiano (Pista)</a></li>
                <li data-address="Estrada Velha, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Estrada Velha, Inhaúma, Rio de Janeiro')">Estrada Velha</a></li>
                <li data-address="Favelinha Beirando Rio, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Favelinha Beirando Rio, Inhaúma, Rio de Janeiro')">Favelinha Beirando Rio</a></li>
                <li data-address="Praça 24 de Outubro, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Praça 24 de Outubro, Inhaúma, Rio de Janeiro')">Praça 24 de Outubro</a></li>
                <li data-address="Praça Emboara, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Praça Emboara, Inhaúma, Rio de Janeiro')">Praça Emboara</a></li>
                <li data-address="Praça Major Aderbal Costa, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Praça Major Aderbal Costa, Inhaúma, Rio de Janeiro')">Praça Major Aderbal Costa</a></li>
                <li data-address="Rua Afonso de Albuquerque, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Afonso de Albuquerque, Inhaúma, Rio de Janeiro')">Rua Afonso de Albuquerque</a></li>
                <li data-address="Rua Albano Fragoso, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Albano Fragoso, Inhaúma, Rio de Janeiro')">Rua Albano Fragoso</a></li>
                <li data-address="Rua Alvares da Rocha, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Alvares da Rocha, Inhaúma, Rio de Janeiro')">Rua Alvares da Rocha</a></li>
                <li data-address="Rua Álvaro Carneiro, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Álvaro Carneiro, Inhaúma, Rio de Janeiro')">Rua Álvaro Carneiro</a></li>
                <li data-address="Rua Álvaro de Miranda, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Álvaro de Miranda, Inhaúma, Rio de Janeiro')">Rua Álvaro de Miranda</a></li>
                <li data-address="Rua Apinage, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Apinage, Inhaúma, Rio de Janeiro')">Rua Apinage</a></li>
                <li data-address="Rua Aratuipe, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Aratuipe, Inhaúma, Rio de Janeiro')">Rua Aratuipe</a></li>
                <li data-address="Rua Augusto e Souza, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Augusto e Souza, Inhaúma, Rio de Janeiro')">Rua Augusto e Souza</a></li>
                <li data-address="Rua Barata de Almeida TD Escadão, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Barata de Almeida TD Escadão, Inhaúma, Rio de Janeiro')">Rua Barata de Almeida TD Escadão</a></li>
                <li data-address="Rua Bento do Amaral, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Bento do Amaral, Inhaúma, Rio de Janeiro')">Rua Bento do Amaral</a></li>
                <li data-address="Rua Bororo, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Bororo, Inhaúma, Rio de Janeiro')">Rua Bororo</a></li>
                <li data-address="Rua C, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua C, Inhaúma, Rio de Janeiro')">Rua C</a></li>
                <li data-address="Rua Caciquiara, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Caciquiara, Inhaúma, Rio de Janeiro')">Rua Caciquiara</a></li>
                <li data-address="Rua Caminho do Mateus, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Caminho do Mateus, Inhaúma, Rio de Janeiro')">Rua Caminho do Mateus</a></li>
                <li data-address="Rua Carlos Gonçalves Penna, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Carlos Gonçalves Penna, Inhaúma, Rio de Janeiro')">Rua Carlos Gonçalves Penna e Condomínio</a></li>
                <li data-address="Rua Carmem Cinira (Pista), Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Carmem Cinira (Pista), Inhaúma, Rio de Janeiro')">Rua Carmem Cinira (Pista)</a></li>
                <li data-address="Rua Carmen Cinira, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Carmen Cinira, Inhaúma, Rio de Janeiro')">Rua Carmen Cinira</a></li>
                <li data-address="Rua Cesar do Rego Monteiro F, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Cesar do Rego Monteiro F, Inhaúma, Rio de Janeiro')">Rua Cesar do Rego Monteiro F</a></li>
                <li data-address="Rua Cherente, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Cherente, Inhaúma, Rio de Janeiro')">Rua Cherente</a></li>
                <li data-address="Rua Cincinate Lopes, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Cincinate Lopes, Inhaúma, Rio de Janeiro')">Rua Cincinate Lopes (até 345)</a></li>
                <li data-address="Rua Contiguiba, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Contiguiba, Inhaúma, Rio de Janeiro')">Rua Contiguiba</a></li>
                <li data-address="Rua Correia de Almeida, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Correia de Almeida, Inhaúma, Rio de Janeiro')">Rua Correia de Almeida</a></li>
                <li data-address="Rua D, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua D, Inhaúma, Rio de Janeiro')">Rua D</a></li>
                <li data-address="Rua Edgar Severiano Lima, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Edgar Severiano Lima, Inhaúma, Rio de Janeiro')">Rua Edgar Severiano Lima</a></li>
                <li data-address="Rua Edmundo, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Edmundo, Inhaúma, Rio de Janeiro')">Rua Edmundo</a></li>
                <li data-address="Rua Edmundo Pereira, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Edmundo Pereira, Inhaúma, Rio de Janeiro')">Rua Edmundo Pereira</a></li>
                <li data-address="Rua Engenho do Mato, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Engenho do Mato, Inhaúma, Rio de Janeiro')">Rua Engenho do Mato</a></li>
                <li data-address="Rua Espedicionario, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Espedicionario, Inhaúma, Rio de Janeiro')">Rua Espedicionario</a></li>
                <li data-address="Rua Ferreira Demenezes, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Ferreira Demenezes, Inhaúma, Rio de Janeiro')">Rua Ferreira Demenezes e Vila (TV10)</a></li>
                <li data-address="Rua Fontoura Xavier, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Fontoura Xavier, Inhaúma, Rio de Janeiro')">Rua Fontoura Xavier</a></li>
                <li data-address="Rua Francisco Siqueira, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Francisco Siqueira, Inhaúma, Rio de Janeiro')">Rua Francisco Siqueira</a></li>
                <li data-address="Rua Frederico Santoni, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Frederico Santoni, Inhaúma, Rio de Janeiro')">Rua Frederico Santoni</a></li>
                <li data-address="Rua Frei Barauna, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Frei Barauna, Inhaúma, Rio de Janeiro')">Rua Frei Barauna</a></li>
                <li data-address="Rua Guarabu, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Guarabu, Inhaúma, Rio de Janeiro')">Rua Guarabu</a></li>
                <li data-address="Rua Heleodora, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Heleodora, Inhaúma, Rio de Janeiro')">Rua Heleodora</a></li>
                <li data-address="Rua Ibate, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Ibate, Inhaúma, Rio de Janeiro')">Rua Ibate</a></li>
                <li data-address="Rua Ibiapaba, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Ibiapaba, Inhaúma, Rio de Janeiro')">Rua Ibiapaba e R. Fernando Portugal</a></li>
                <li data-address="Rua Indaiaçu Leite, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Indaiaçu Leite, Inhaúma, Rio de Janeiro')">Rua Indaiaçu Leite</a></li>
                <li data-address="Rua Itaparica, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Itaparica, Inhaúma, Rio de Janeiro')">Rua Itaparica</a></li>
                <li data-address="Rua Ivan de Oliveira Lima, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Ivan de Oliveira Lima, Inhaúma, Rio de Janeiro')">Rua Ivan de Oliveira Lima</a></li>
                <li data-address="Rua Izaque de Oliveira, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Izaque de Oliveira, Inhaúma, Rio de Janeiro')">Rua Izaque de Oliveira</a></li>
                <li data-address="Rua Jeronimo de Albuquerque, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Jeronimo de Albuquerque, Inhaúma, Rio de Janeiro')">Rua Jeronimo de Albuquerque</a></li>
                <li data-address="Rua João do Amaral, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua João do Amaral, Inhaúma, Rio de Janeiro')">Rua João do Amaral</a></li>
                <li data-address="Rua João Lisboa, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua João Lisboa, Inhaúma, Rio de Janeiro')">Rua João Lisboa</a></li>
                <li data-address="Rua José dos Reis, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua José dos Reis, Inhaúma, Rio de Janeiro')">Rua José dos Reis</a></li>
                <li data-address="Rua José Faivre, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua José Faivre, Inhaúma, Rio de Janeiro')">Rua José Faivre</a></li>
                <li data-address="Rua Jose Mirales, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Jose Mirales, Inhaúma, Rio de Janeiro')">Rua Jose Mirales</a></li>
                <li data-address="Rua Julia Corteines, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Julia Corteines, Inhaúma, Rio de Janeiro')">Rua Julia Corteines</a></li>
                <li data-address="Rua Luis de Simoni, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Luis de Simoni, Inhaúma, Rio de Janeiro')">Rua Luis de Simoni</a></li>
                <li data-address="Rua Luiz de Castro, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Luiz de Castro, Inhaúma, Rio de Janeiro')">Rua Luiz de Castro</a></li>
                <li data-address="Rua Magessi, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Magessi, Inhaúma, Rio de Janeiro')">Rua Magessi</a></li>
                <li data-address="Rua Maria Deolinda, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Maria Deolinda, Inhaúma, Rio de Janeiro')">Rua Maria Deolinda</a></li>
                <li data-address="Rua Mario Ferreira, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Mario Ferreira, Inhaúma, Rio de Janeiro')">Rua Mario Ferreira</a></li>
                <li data-address="Rua Mateus Silva, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Mateus Silva, Inhaúma, Rio de Janeiro')">Rua Mateus Silva</a></li>
                <li data-address="Rua Mathias da Cunha, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Mathias da Cunha, Inhaúma, Rio de Janeiro')">Rua Mathias da Cunha</a></li>
                <li data-address="Rua Nunes Viana, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Nunes Viana, Inhaúma, Rio de Janeiro')">Rua Nunes Viana</a></li>
                <li data-address="Rua Padre José Beltrão, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Padre José Beltrão, Inhaúma, Rio de Janeiro')">Rua Padre José Beltrão</a></li>
                <li data-address="Rua Pereira Pinto, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Pereira Pinto, Inhaúma, Rio de Janeiro')">Rua Pereira Pinto</a></li>
                <li data-address="Rua Pinheiro Amado, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Pinheiro Amado, Inhaúma, Rio de Janeiro')">Rua Pinheiro Amado</a></li>
                <li data-address="Rua Preta, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Preta, Inhaúma, Rio de Janeiro')">Rua Preta</a></li>
                <li data-address="Rua Raimundo Cela, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Raimundo Cela, Inhaúma, Rio de Janeiro')">Rua Raimundo Cela</a></li>
                <li data-address="Rua Rual Penna Firme, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Rual Penna Firme, Inhaúma, Rio de Janeiro')">Rua Rual Penna Firme</a></li>
                <li data-address="Rua Santa Rita Dura, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Santa Rita Dura, Inhaúma, Rio de Janeiro')">Rua Santa Rita Dura</a></li>
                <li data-address="Rua Silva Vale (Vila), Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Silva Vale (Vila), Inhaúma, Rio de Janeiro')">Rua Silva Vale (Vila)</a></li>
                <li data-address="Rua Soares Meireles, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Soares Meireles, Inhaúma, Rio de Janeiro')">Rua Soares Meireles</a></li>
                <li data-address="Rua Souza Freitas, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Souza Freitas, Inhaúma, Rio de Janeiro')">Rua Souza Freitas</a></li>
                <li data-address="Rua Theofilo Dias, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Theofilo Dias, Inhaúma, Rio de Janeiro')">Rua Theofilo Dias</a></li>
                <li data-address="Rua Vaz da Costa, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Rua Vaz da Costa, Inhaúma, Rio de Janeiro')">Rua Vaz da Costa</a></li>
                <li data-address="Travessa Buquira, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Travessa Buquira, Inhaúma, Rio de Janeiro')">Travessa Buquira</a></li>
                <li data-address="Travessa Eduardo das Neves, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Travessa Eduardo das Neves, Inhaúma, Rio de Janeiro')">Travessa Eduardo das Neves</a></li>
                <li data-address="Travessa Francisco Mateus, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Travessa Francisco Mateus, Inhaúma, Rio de Janeiro')">Travessa Francisco Mateus</a></li>
                <li data-address="Travessa Marques da Cruz, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Travessa Marques da Cruz, Inhaúma, Rio de Janeiro')">Travessa Marques da Cruz</a></li>
                <li data-address="Travessa Nunes Leal, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Travessa Nunes Leal, Inhaúma, Rio de Janeiro')">Travessa Nunes Leal</a></li>
                <li data-address="Travessa Rio Faleiro, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Travessa Rio Faleiro, Inhaúma, Rio de Janeiro')">Travessa Rio Faleiro</a></li>
                <li data-address="Travessa Vaz da Costa, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Travessa Vaz da Costa, Inhaúma, Rio de Janeiro')">Travessa Vaz da Costa</a></li>
                <li data-address="Travessa Xavier da Veiga, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Travessa Xavier da Veiga, Inhaúma, Rio de Janeiro')">Travessa Xavier da Veiga</a></li>
                <li data-address="Vila Rosa, Inhaúma, Rio de Janeiro"><a onclick="showLocation('Vila Rosa, Inhaúma, Rio de Janeiro')">Vila Rosa</a></li>
            </ul>
        </div>

        <div class="section" id="cadastro">
            <h2>Faça Seu Cadastro</h2>
            <p>
                Clique no botão abaixo para preencher o formulário e se tornar um cliente FireNet Telecom, aproveitando a melhor internet da região!
            </p>
            <div class="cadastro-portal">
                <a href="cadastro.html">Cadastre-se</a>
            </div>
        </div>

        <div class="section" id="central-cliente">
            <h2>Central do Cliente</h2>
            <p>
                Acesse a Central do Cliente para gerenciar sua conta com facilidade. Emita a 2ª via de boletos, consulte e baixe seu contrato, abra chamados de suporte e muito mais. Tudo em um só lugar para sua comodidade!
            </p>
            <div class="client-portal">
                <a href="https://sistema.alfaprovedor.com.br/central_assinante_web/login" target="_blank">Central do Cliente</a>
            </div>
        </div>

        <div class="section contact-section" id="contato">
            <h2>Entre em Contato</h2>
            <p>Fale conosco pelo WhatsApp para tirar dúvidas ou contratar um plano:</p>
            <a href="https://wa.me/5521997123987" target="_blank">(21) 99712-3987</a>
        </div>
    </div>

    <!-- Botão flutuante para voltar ao topo -->
    <button class="scroll-top-btn" onclick="scrollToTop()">Topo</button>

    <!-- Janela flutuante de "Assine já" -->
    <?php
    $signupPopupMessage = $settings['signup_popup_message'] ?? '';
    if (!empty($signupPopupMessage)) {
        echo '<div class="signup-popup" id="signupPopup">';
        echo '    <button class="close-btn" onclick="closeSignupPopup()">×</button>';
        echo '    <h3>' . htmlspecialchars($signupPopupMessage) . '</h3>';
        echo '    <a href="cadastro.html">Cadastre-se</a>';
        echo '</div>';
    } else {
        error_log("Janela flutuante de 'Assine já' não exibida: mensagem não definida no banco de dados.");
    }
    ?>
    <footer>
        <p>© 2025 FireNet Telecom - Todos os direitos reservados.</p>
    </footer>

    <!-- Definir a variável DEBUG_LOGS -->
    <script>
        window.DEBUG_LOGS = <?php echo json_encode($DEBUG_LOGS); ?>;
    </script>

    <!-- Carregar o script index.js antes da API do Google Maps -->
    <script src="/js/index.js?v=1"></script>

    <!-- Google Maps API -->
    <?php
    $googleMapsEnabled = isset($settings['google_maps_enabled']) ? $settings['google_maps_enabled'] == '1' : true;
    $googleMapsApiKey = $settings['google_maps_api_key'] ?? '';

    if ($googleMapsEnabled && !empty($googleMapsApiKey)) {
        echo '<script async src="https://maps.googleapis.com/maps/api/js?key=' . htmlspecialchars($googleMapsApiKey) . '&callback=initMap"></script>';
    } else {
        error_log("Google Maps API não carregada: API desabilitada ou chave ausente.");
    }
    ?>

    <!-- Script para o botão de voltar ao topo -->
    <script>
        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    </script>

    <!-- Script para a janela flutuante "Assine já" com controle por sessão -->
    <script>
        function handleScroll() {
            const signupPopup = document.getElementById('signupPopup');
            const hasSeenPopup = localStorage.getItem('firenet_popup_seen');

            if (signupPopup && window.scrollY > 100 && !hasSeenPopup) {
                signupPopup.classList.add('visible');
                debugLog('11. Janela flutuante "Assine já" exibida');
                localStorage.setItem('firenet_popup_seen', 'true'); // Marca como visto
                window.removeEventListener('scroll', handleScroll);
            }
        }

        window.closeSignupPopup = function() {
            const signupPopup = document.getElementById('signupPopup');
            if (signupPopup) {
                signupPopup.style.display = 'none';
                debugLog('12. Janela flutuante "Assine já" fechada');
                localStorage.setItem('firenet_popup_seen', 'true'); // Marca como visto ao fechar
            }
        };

        // Adiciona o listener de rolagem quando o DOM carregar
        document.addEventListener('DOMContentLoaded', () => {
            debugLog('13. Adicionando listener de rolagem para a janela flutuante');
            window.addEventListener('scroll', handleScroll);
        });
    </script>
</body>
</html>
