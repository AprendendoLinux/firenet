# Sistema de Gerenciamento de Cadastros e Instalação

![FireNet Telecom](https://firenettelecom.com.br/static/logo.png)

[![GitHub Repo](https://img.shields.io/badge/GitHub-Repo-blue?logo=github)](https://github.com/AprendendoLinux/firenet)
[![Licença](https://img.shields.io/badge/Licença-MIT-green)](LICENSE)
[![Versão](https://img.shields.io/badge/Versão-2.0-orange)](https://github.com/AprendendoLinux/firenet/releases)
[![Python](https://img.shields.io/badge/Python-3.11-blue?logo=python)](https://www.python.org/)
[![Flask](https://img.shields.io/badge/Flask-3.0.3-lightgrey?logo=flask)](https://flask.palletsprojects.com/)
[![Docker](https://img.shields.io/badge/Docker-Support-blue?logo=docker)](https://www.docker.com/)

## Introdução

Bem-vindo ao repositório **FireNet Telecom**! Este projeto é uma aplicação web completa desenvolvida em Python com o framework Flask, projetada para gerenciar cadastros de clientes de uma provedora de internet chamada **FireNet Telecom**. O sistema permite o registro de novos clientes, gerenciamento de instalações, envio de notificações por email e Telegram, reset de senhas, verificação de cobertura de endereços, geração de relatórios, páginas públicas de marketing com detalhes sobre vantagens e muito mais.

O objetivo principal desse projeto é fornecer uma solução robusta e escalável para automação de processos administrativos para a **FireNet Telecom**, com foco em usabilidade, segurança e integração com serviços externos como Google Maps, reCAPTCHA e bots de Telegram. O frontend é construído com templates HTML renderizados via Jinja2, Bootstrap para responsividade e JavaScript para interações dinâmicas, como validações em tempo real, modais, buscas AJAX, wizards de cadastro e mapas interativos. Inclui templates profissionais para emails, garantindo comunicações consistentes e branded.

Este repositório é mantido por [**Henrique Fagundes**](https://henrique.tec.br) e está hospedado aqui no [**Github**](https://github.com/AprendendoLinux/firenet). Se você é um desenvolvedor interessado em Flask, Docker ou automação de workflows administrativos, este projeto pode servir como um excelente exemplo prático. O projeto é projetado exclusivamente para execução em Docker, garantindo consistência entre ambientes de desenvolvimento e produção.

### Motivação do Projeto

Este projeto foi inspirado em cenários reais de provedores de internet locais no Brasil, onde o gerenciamento manual de cadastros leva a erros, atrasos e perda de eficiência. Este sistema resolve problemas como:
- Validação automática de CPF e endereços via CEP.
- Notificações em tempo real para administradores via email e Telegram, com templates customizados para boas-vindas, resets de senha, atualizações de instalação e alertas de novos cadastros.
- Agendamento e rastreamento de instalações com status como "Programada", "Concluída" ou "Não Realizada".
- Geração de relatórios impressíveis para instalações concluídas.
- Páginas públicas otimizadas para SEO com meta tags Open Graph e Twitter Cards, incluindo detalhes sobre vantagens como baixa latência, IP fixo, descontos exclusivos e mais.
- Formulários interativos com reCAPTCHA, máscaras de input e verificações AJAX.
- Integração com banco de dados MySQL para persistência de dados.
- Segurança com hashing de senhas e proteção contra spam via reCAPTCHA.
- Gerenciamento de usuários administrativos e áreas de cobertura.
- **Retenção Inteligente de Clientes:** Identificação de duplicidade de CPF no cadastro com engatilho automático para resgate da venda via atendimento no Telegram e WhatsApp.
- **Atendimento Virtual Dinâmico:** Um widget pré-atendente no site que gerencia filas de suporte, verifica viabilidade técnica (ViaCEP + banco de dados local) e informa o status do pedido antes do cliente ser repassado ao atendimento humano.
- **Gestão Dinâmica de Expediente:** Controle absoluto dos horários de atendimento da equipe, com bloqueios automáticos no site aos finais de semana e feriados configurados.
- **Alertas e Comunicados Globais:** Módulo anti-conflito para emitir avisos emergenciais e comunicados institucionais na interface do cliente.

O projeto foi desenvolvido com ênfase em boas práticas de programação, como uso de variáveis de ambiente via Docker Compose para configurações sensíveis, tratamento de erros robusto, modularidade para facilitar expansões futuras e um frontend intuitivo com modais para edições, buscas filtradas e elementos reutilizáveis como headers e footers.

### Recursos Principais

- **Cadastro de Clientes**: Formulário wizard em múltiplos passos com validação em tempo real (ex: verificação de CPF duplicado via AJAX, máscaras para CPF/telefone/data).
- **Gerenciamento de Usuários**: Login, reset de senha, criação, edição e exclusão de contas administrativas, incluindo integração com Telegram ID.
- **Notificações**: Envio de emails de boas-vindas, atualizações de instalação, resets de senha e alertas para admins, usando templates HTML profissionais com branding. Integração com Telegram para notificações instantâneas em Markdown.
- **Verificação de Cobertura**: Consulta a endereços pré-cadastrados para verificar disponibilidade de serviço, com gerenciamento administrativo de áreas de cobertura e mapa interativo via Google Maps.
- **Relatórios e Agendamentos**: Geração de relatórios com identificadores únicos (NNNN), gerenciamento de status de instalação e impressão de fichas de clientes e relatórios.
- **Páginas Públicas**: Site marketing com seções de planos, vantagens (detalhadas em páginas individuais), cobertura, contato e cadastro, otimizado para SEO e compartilhamento social.
- **Integrações Externas**:
  - Google Maps API para mapas interativos e geocoding.
  - reCAPTCHA para proteção contra bots em formulários.
  - SMTP para envio de emails (compatível com Gmail, Outlook, etc.).
  - Telegram Bot para notificações assíncronas.
  - WhatsApp link fixo para suporte rápido.
- **Segurança**: Hashing de senhas com Werkzeug, sanitização de inputs para prevenir SQL Injection e XSS, sessões permanentes com Flask.
- **Frontend Avançado**: Páginas administrativas com tabelas responsivas, modais para edições, buscas dinâmicas por nome/CPF/username/email, máscaras de input (ex: CPF, data) e atualizações AJAX sem recarregar a página. Páginas públicas com carrosséis, cards de vantagens e formulários validados.
- **Deploy com Docker**: Fácil implantação usando Docker Compose, incluindo container para o app e banco de dados MySQL. Não há suporte para execução local sem Docker.
- **Atendente Virtual (WhatsApp Widget)**: Modal dinâmico multi-step integrado ao frontend para triagem de suporte, consulta de planos, verificação de viabilidade (ViaCEP + banco local), rastreio de status de instalação e redirecionamento inteligente.
- **Gestão Inteligente de Horários**: Sistema automatizado configurável pelo painel admin que verifica os dias da semana e feriados, alterando dinamicamente a disponibilidade de suporte e mensagens de "Loja Fechada" no site.
- **Sistema de Retenção de Clientes**: Fluxo automatizado que identifica tentativas de cadastro com CPFs duplicados, bloqueia a duplicidade no banco e gera um chamado imediato via Telegram para a equipe recuperar a venda com apenas um clique.
- **Gestão de Avisos Globais**: Painel para criação de alertas temporários para clientes. Possui um "rolo compressor" lógico em SQL/Python que detecta e desativa automaticamente avisos conflitantes no mesmo período temporal.

Este sistema é escalável e pode ser adaptado para outros contextos, como e-commerces ou CRMs simples, com um foco em interfaces amigáveis para administradores e usuários finais.

## Tecnologias Utilizadas

O projeto utiliza uma stack moderna e leve, focada em performance e facilidade de manutenção:

- **Backend**: Python 3.11 com Flask 3.0.3 como framework web.
- **Banco de Dados**: MySQL (via PyMySQL 1.1.1) para armazenamento relacional, com tabelas como `cadastros`, `usuarios`, `relatorios`, `cobertura`, `avisos` e `configuracoes`.
- **Segurança**: Werkzeug 3.0.4 para hashing de senhas e gerenciamento de sessões.
- **Notificações**:
  - Flask-Mail 0.10.0 para envio de emails, com templates HTML renderizados via Jinja2.
  - python-telegram-bot 20.7 para integração com Telegram.
- **Outras Bibliotecas**:
  - Requests 2.32.3 para chamadas HTTP (ex: verificação de reCAPTCHA).
  - Cryptography 43.0.3 para operações criptográficas.
  - Asyncio e Threading para tarefas assíncronas (ex: envio de mensagens no Telegram sem travar a thread principal).
  - UUID para geração de tokens únicos.
- **Frontend**: HTML5, CSS3 e JavaScript vanilla (com Bootstrap 5.3.3 para layout responsivo, Font Awesome para ícones, SweetAlert para modais customizados e Jinja2 para templates). Inclui scripts para máscaras de input matemáticas, validações client-side, AJAX (Fetch API) para edições e buscas dinâmicas, wizards multi-step e integração com Google Maps.
- **Containerização**: Docker com Dockerfile e docker-compose.yml para ambientes de desenvolvimento e produção. O app roda em um container Python e o DB em MySQL latest.
- **Outros**: reCAPTCHA para anti-spam, Google Maps API para mapas, CSS customizado para páginas de impressão e SEO com meta tags.

Todas as dependências estão listadas no `requirements.txt` para build da imagem Docker.

## Estrutura do Repositório

A estrutura de diretórios é organizada para separar lógica de negócios, templates, assets estáticos e configurações Docker:

```
firenet/
├── app.py                  # Arquivo principal da aplicação Flask com rotas, lógica de negócios, API e integrações.
├── sql.py                  # Módulo para gerenciamento de banco de dados (conexões, inicializações de tabelas, migrações).
├── requirements.txt        # Dependências Python para build Docker.
├── Dockerfile              # Configuração para build da imagem Docker do app.
├── docker-compose.yml      # Configuração para orquestração de containers (app + DB MySQL).
├── templates/              # Diretório de templates HTML renderizados via Jinja2:
│   ├── index.html          # Página inicial pública com visão geral da empresa, seções de planos, vantagens, cobertura, etc.
│   ├── cadastro.html       # Formulário de cadastro de clientes em wizard multi-step com validações.
│   ├── whatsapp_widget.html# Componente de Atendente Virtual interativo com fluxos modais de triagem.
│   ├── login.html          # Página de login administrativo.
│   ├── forgot_password.html # Página para solicitação de reset de senha.
│   ├── reset_password.html # Página para redefinição de senha via token.
│   ├── contato.html        # Formulário de contato público com reCAPTCHA.
│   ├── sucesso.html        # Página de sucesso pós-cadastro ou contato.
│   ├── cards_vantagens.html # Include para cards de vantagens (reutilizado em index.html).
│   ├── emails/             # Subdiretório para templates de emails:
│   │   ├── contato.html    # Template para email de contato recebido via site.
│   │   ├── boas_vindas.html # Template para email de boas-vindas ao novo cliente.
│   │   ├── reset_senha.html # Template para email de reset de senha com link.
│   │   ├── atualizacao_instalacao.html # Template para email de atualização de status de instalação.
│   │   └── novo_cadastro_admin.html # Template para email de alerta de novo cadastro para admins.
│   ├── vantagens/          # Páginas estáticas de vantagens detalhadas:
│   │   ├── dns_proprio.html # Detalhes sobre DNS próprio otimizado.
│   │   ├── conexao_estavel.html # Detalhes sobre conexão estável via fibra óptica.
│   │   ├── descontos_exclusivos.html # Detalhes sobre descontos por pagamento em dia.
│   │   ├── infraestrutura_premium.html # Detalhes sobre infraestrutura de alta qualidade.
│   │   ├── instalacao_rapida.html # Detalhes sobre instalação em até 72 horas.
│   │   ├── upload_proporcional.html # Detalhes sobre upload proporcional ao download.
│   │   ├── ipv6_ipv4_fixo.html # Detalhes sobre IPv6 e IPv4 fixo sem CGNAT.
│   │   ├── whatsapp_rapido.html # Detalhes sobre suporte rápido via WhatsApp.
│   │   └── baixa_latencia.html # Detalhes sobre baixa latência para games e TI.
│   ├── admin/              # Subdiretório para templates administrativos:
│   │   ├── menu.html       # Menu de navegação administrativo (incluído em outras páginas admin).
│   │   ├── clientes.html   # Lista de clientes com buscas, edições modais e agendamentos.
│   │   ├── avisos.html     # Painel para gerenciamento do sistema de alertas e comunicados globais.
│   │   ├── relatorios.html # Página de relatórios com geração, listagem e impressão.
│   │   ├── usuarios.html   # Gerenciamento de usuários com criação, edição e exclusão.
│   │   ├── cobertura.html  # Gerenciamento de áreas de cobertura com criação, edição e exclusão.
│   │   ├── imprimir_cliente.html   # Template para impressão de ficha de cliente.
│   │   └── imprimir_relatorio.html # Template para impressão de relatórios de instalações.
│   └── footer.html         # Rodapé comum (incluído em páginas públicas e admin).
├── static/                 # Diretório para arquivos estáticos (CSS, JS, imagens):
│   ├── css/                # Estilos CSS (ex: style.css, imprimir-cliente.css).
│   ├── js/                 # Scripts JavaScript (ex: validações, máscaras, AJAX).
│   └── img/                # Imagens e logos (ex: logo.png, whatsapp.webp, card_firenet.jpg).
├── LICENSE                 # Arquivo de licença (MIT recomendado).
└── README.md               # Este documento de documentação extensiva.

```

Nota: Os templates utilizam includes Jinja para reutilizar componentes como menu, footer e cards de vantagens, promovendo DRY (Don't Repeat Yourself). Templates de email são renderizados via Flask-Mail com variáveis Jinja para personalização dinâmica. Se o repositório real tiver mais arquivos, atualize esta seção.

## Detalhes dos Templates Frontend

Abaixo, uma descrição detalhada e extensa de cada template frontend principal, incluindo sua funcionalidade, componentes chave e integrações com o backend. Esses templates são renderizados dinamicamente via Flask e Jinja2, com dados passados do backend. Divididos em públicos (marketing/usuário), administrativos e emails.

### Templates Públicos

#### templates/whatsapp_widget.html (NOVO)
Modal interativo de pré-atendimento (Atendente Virtual) que flutua em todas as páginas públicas.
- **Estrutura Principal**: Divisões em múltiplos "steps" invisíveis que simulam um aplicativo. O cliente escolhe consultar planos (carrossel dinâmico), consultar viabilidade por CEP, rastrear o status do agendamento (via CPF) ou ser redirecionado para departamentos específicos (Suporte, Financeiro, Vendas).
- **JavaScript Integrado**: Validação matemática e formatação automática de CPF; Fetch API acionando rotas do backend (`/api/check_status`, `/api/consultar_cpf`); verificação unificada de ViaCEP combinada com a base local para detectar cobertura no endereço.
- **Integrações**: Lê variáveis Jinja `{{ whatsapp_ativo }}` para determinar em milissegundos se o expediente está aberto ou fechado, travando ou liberando a transferência do usuário para o link direto do `wa.me`.

#### templates/contato.html

Página pública para formulário de contato, permitindo que visitantes enviem mensagens para setores específicos (Suporte, Vendas, Financeiro).

- **Estrutura Principal**:
  - Header reutilizável com logo, menu desktop/mobile (links para seções do index via âncoras).
  - Seção principal com título "Contato" e formulário em grid responsivo: Campos para nome, email, setor (select), mensagem.
  - Integração com reCAPTCHA via `<div class="g-recaptcha" data-sitekey="{{ recaptcha_site_key }}"></div>`.
  - Botão "Enviar" que submete para rota `/enviar_contato` (POST).
  - Botão flutuante WhatsApp com link personalizado.
  - Inclui footer (`{% include 'footer.html' %}`).

- **JavaScript Integrado**:
  - Carregamento assíncrono do reCAPTCHA via script Google.
  - Bootstrap bundle para interações (ex: collapse menu mobile).

- **Integrações**:
  - Rotas Flask: `/contato` (GET/POST), `/enviar_contato` (POST para processamento e envio de email).
  - Dependências: Bootstrap, Font Awesome, Google Fonts (Montserrat).

#### templates/index.html

Página inicial pública, servindo como landing page de marketing com seções scrolláveis para planos, vantagens, cobertura, central do cliente e cadastro.

- **Estrutura Principal**:
  - Header reutilizável idêntico a outros públicos.
  - Hero section com call-to-action para cadastro.
  - Seções com IDs para âncoras: #planos (cards de planos com preços), #vantagens (include de cards_vantagens.html), #cobertura (formulário de verificação com mapa Google Maps), #central (links para boleto, nota fiscal), #cadastro (call-to-action).
  - Meta tags para SEO, Open Graph e Twitter Cards, com imagem `card_firenet.jpg`.
  - Botão flutuante WhatsApp.
  - Inclui footer.

- **JavaScript Integrado**:
  - Integração com Google Maps: `initMap()` para mapa interativo, autocomplete de endereços, verificação de cobertura via AJAX (`/check_coverage` POST).
  - SweetAlert para feedbacks de cobertura (sucesso com redirecionamento, erro).
  - CSS inline para scroll-margin-top em âncoras (evitar overlap com header sticky).

- **Integrações**:
  - Rotas: `/` (GET), `/check_coverage` (POST).
  - APIs: Google Maps com chave `{{ google_maps_api_key }}`.

#### templates/cards_vantagens.html

Template include para cards de vantagens, reutilizado em index.html e páginas de vantagens para listar benefícios do provedor.

- **Estrutura Principal**:
  - Container com título "Vantagens".
  - Grid responsivo de cards: Cada card com ícone Font Awesome, título, descrição curta e link "Saiba mais" para páginas de vantagens específicas (ex: /vantagens/ipv6-ipv4-fixo).
  - 8 vantagens: IPv6 & IPv4 Fixo, Baixa Latência, DNS Próprio, Suporte 24h, Sem Fidelidade, Instalação Rápida, WhatsApp Rápido, Infraestrutura Premium.
  - Botão flutuante WhatsApp (repetido para consistência).

- **Integrações**:
  - Usado via `{% include 'cards_vantagens.html' %}` em index.html e páginas de vantagens.
  - Rotas: Links para /vantagens/<slug>.

#### templates/footer.html

Footer reutilizável incluído em todas as páginas públicas e algumas admin.

- **Estrutura Principal**:
  - Background dark com texto branco: Copyright contendo informações de autoria e direitos.

- **Integrações**:
  - Simples include Jinja.

#### templates/forgot_password.html

Página pública para solicitação de reset de senha administrativa.

- **Estrutura Principal**:
  - Header reutilizável.
  - Card centralizado com form: Campo email, botão "Enviar Link de Reset".
  - Mensagens flash para feedbacks.
  - Link "Voltar ao Login".

- **Integrações**:
  - Rota: `/forgot_password` (GET/POST).
  - Envia email com token de reset.

#### templates/reset_password.html

Página pública para redefinição de senha via token.

- **Estrutura Principal**:
  - Header reutilizável.
  - Card com form: Campos nova senha e confirmação, botão "Atualizar Senha".
  - Mensagens flash.

- **Integrações**:
  - Rota: `/reset_password/<token>` (GET/POST).
  - Valida token e atualiza senha no DB.

#### templates/sucesso.html

Página de sucesso pós-cadastro ou contato, com redirecionamento ao início.

- **Estrutura Principal**:
  - Header reutilizável.
  - Seção central com mensagem "Cadastro Realizado com Sucesso!" e botão "Voltar ao Início".
  - Botão WhatsApp e footer.

- **Integrações**:
  - Rota: Redirecionada após POST bem-sucedido em /cadastro.

#### templates/login.html

Página pública para login administrativo.

- **Estrutura Principal**:
  - Header reutilizável.
  - Card com form: Campos username/email, senha, botão "Entrar".
  - Link "Esqueci minha senha".
  - Mensagens flash.
  - Inclui footer.

- **Integrações**:
  - Rota: `/login` (GET/POST).
  - Redireciona para /admin/clientes se sucesso.

#### templates/cadastro.html

Página pública para cadastro de clientes em formato wizard multi-step.

- **Estrutura Principal**:
  - Header reutilizável.
  - Stepper visual com dots e linhas para passos: Dados Pessoais, Endereço, Plano, LGPD.
  - Forms validados por passo: Campos com máscaras (CPF, RG, data nascimento, telefone, CEP), selects para planos/vencimentos, checkboxes para WhatsApp/LGPD.
  - Botões de navegação (próximo/voltar) e submit final.
  - Verificação AJAX de CPF duplicado, que bloqueia o usuário e abre a janela do plano de "Retenção" caso ele já seja cliente.

- **JavaScript Integrado**:
  - Funções para máscaras (CPF, RG, data, telefone, CEP, senha rede).
  - Validação por passo (obrigatoriedade, formatos, senhas iguais, LGPD aceita).
  - AJAX para check CPF (`/check_cpf` POST).
  - Navegação entre passos com classes active/done.
  - Busca CEP via ViaCEP API para auto-preenchimento.

- **Integrações**:
  - Rota: `/cadastro` (GET/POST).
  - Submete dados completos, envia emails/Telegram.

#### Templates de Vantagens (em templates/vantagens/)

Essas páginas estáticas detalham cada vantagem listada nos cards, acessíveis via rotas como /vantagens/<slug>. Todas seguem estrutura similar: Header reutilizável, seção principal com título e descrição explicativa, include de cards_vantagens.html para cross-promoção, e footer. Conteúdo focado em explicações acessíveis, benefícios e exemplos reais, otimizado para SEO com meta tags.

##### templates/vantagens/dns_proprio.html

- **Estrutura Principal**:
  - Título "DNS Próprio".
  - Descrição: Explica DNS como catálogo de telefones, benefícios de DNS próprio (velocidade, privacidade, bloqueio de sites maliciosos).
  - Include de cards_vantagens.
  - JavaScript: Bootstrap bundle.

- **Integrações**:
  - Rota: `/vantagens/dns-proprio` (GET).

##### templates/vantagens/conexao_estavel.html

- **Estrutura Principal**:
  - Título "Conexão Estável".
  - Descrição: Compara a estabilidade com ponte forte; destaca fibra óptica para streaming/downloads sem interrupções.
  - Include de cards_vantagens.

- **Integrações**:
  - Rota: `/vantagens/conexao-estavel` (GET).

##### templates/vantagens/descontos_exclusivos.html

- **Estrutura Principal**:
  - Título "Descontos Exclusivos".
  - Descrição: Explica descontos por pagamento em dia (ex: R$104,90 para R$84,90), incentivos e economia.
  - Include de cards_vantagens.

- **Integrações**:
  - Rota: `/vantagens/descontos-exclusivos` (GET).

##### templates/vantagens/infraestrutura_premium.html

- **Estrutura Principal**:
  - Título "Infraestrutura Premium".
  - Descrição: Define infraestrutura como esqueleto da internet; enfatiza cabos resistentes e servidores potentes para performance total.
  - Include de cards_vantagens.

- **Integrações**:
  - Rota: `/vantagens/infraestrutura-premium` (GET).

##### templates/vantagens/instalacao_rapida.html

- **Estrutura Principal**:
  - Título "Instalação Rápida".
  - Descrição: Garantia de instalação em 72 horas úteis; processo simples sem custos extras.
  - Include de cards_vantagens.

- **Integrações**:
  - Rota: `/vantagens/instalacao-rapida` (GET).

##### templates/vantagens/upload_proporcional.html

- **Estrutura Principal**:
  - Título "Upload Proporcional".
  - Descrição: Diferença entre download/upload; benefícios de upload equilibrado para envios rápidos e videochamadas.
  - Include de cards_vantagens.

- **Integrações**:
  - Rota: `/vantagens/upload-proporcional` (GET).

##### templates/vantagens/ipv6_ipv4_fixo.html

- **Estrutura Principal**:
  - Título "IPv6 & IPv4 Fixo".
  - Descrição: Explica IPs como endereços; vantagens de IP fixo sem CGNAT para acessos remotos e segurança.
  - Include de cards_vantagens.

- **Integrações**:
  - Rota: `/vantagens/ipv6-ipv4-fixo` (GET).

##### templates/vantagens/whatsapp_rapido.html

- **Estrutura Principal**:
  - Título "WhatsApp Rápido".
  - Descrição: Suporte personalizado via WhatsApp para resoluções rápidas sem esperas.
  - Include de cards_vantagens.

- **Integrações**:
  - Rota: `/vantagens/whatsapp-rapido` (GET).

##### templates/vantagens/baixa_latencia.html

- **Estrutura Principal**:
  - Título "Baixa Latência".
  - Descrição: Compara latência com atraso em jogo; benefícios para gamers e profissionais com rotas diretas.
  - Include de cards_vantagens.

- **Integrações**:
  - Rota: `/vantagens/baixa-latencia` (GET).

### Templates Administrativos

#### templates/admin/avisos.html (NOVO)
Painel de controle para criação e disparo de alertas globais no site.
- **Estrutura Principal**: Formulários para definir título, mensagem, tipo de alerta (warning, info, danger) e datas de início/fim da validade.
- **JavaScript Integrado**: Comunicação AJAX para salvar e alternar (toggle) o status de ativo/inativo do aviso na interface.
- **Integrações**: Chama a função do Rolo Compressor do backend (`desativar_conflitos()`) para garantir que os períodos estipulados de avisos não entrem em colisão na tela do usuário.

#### templates/admin/relatorios.html

Este template gerencia a página de relatórios administrativos. É uma interface para gerar novos relatórios baseados em instalações concluídas, listar relatórios antigos com paginação e imprimir via nova aba.

- **Estrutura Principal**:
  - Inclui o menu administrativo (`{% include 'admin/menu.html' %}`).
  - Seção principal com título "Relatórios" e mensagens flash para feedbacks (ex: sucesso/erro).
  - Formulário para gerar relatório: Input para data de vencimento com máscara (dd/mm/aaaa) e sugestão automática via `suggested_vencimento`.
  - Tabela responsiva listando relatórios com colunas: Identificador, Data de Vencimento, Clientes, Data de Criação, Ações (imprimir).
  - Paginação com botões anterior/próximo e indicador de página.
  - Modal para alertar sobre instalações insuficientes (fixo em 3 instalações concluídas necessárias).

- **JavaScript Integrado**:
  - Máscara para data de vencimento usando regex.
  - Validação antes de submit: Verifica se há pelo menos 3 instalações concluídas; exibe modal ou alert se insuficiente.
  - Abre nova aba para impressão após geração via sessão (`session['novo_relatorio']`).

- **Integrações**:
  - Rotas Flask: `/admin/gerar_relatorio` (POST), `/admin/relatorios` (GET com paginação), `/admin/imprimir_relatorio/<identificador>` (GET).
  - Dependências: Bootstrap para modais e tooltips, Font Awesome para ícones.

#### templates/admin/menu.html

Template reutilizável para o menu de navegação administrativo, incluído em todas as páginas admin.

- **Estrutura Principal**:
  - Header sticky com logo e links de navegação: Clientes, Usuários, Relatórios, Área de Cobertura, Avisos, Logout.
  - Versão desktop: Links horizontais.
  - Versão mobile: Botão hambúrguer para collapse vertical.

- **Integrações**:
  - Usa `url_for` para rotas dinâmicas (ex: `url_for('clientes')`).
  - Bootstrap para responsividade e collapse.

#### templates/admin/imprimir_cliente.html

Template otimizado para impressão de ficha individual de cliente, formatado para A4.

- **Estrutura Principal**:
  - Div `.a4-page` com seções: Dados Pessoais, Endereço, Dados do Plano, Instalação, Outros.
  - Cabeçalho com logo e título.
  - Campos preenchidos via variáveis Jinja.
  - Rodapé com copyright.
  - Botão "Imprimir Ficha" (oculto na impressão via `.no-print`).

- **CSS Integrado**:
  - Estilos para layout A4, com media queries para print (sem margens extras, fundo branco).

- **Integrações**:
  - Rota Flask: `/admin/imprimir_cliente/<cliente_id>` (GET).
  - Dados passados do backend: `cliente`, `created_at`.

#### templates/admin/clientes.html

Página principal administrativa para listagem e gerenciamento de clientes, com buscas, edições e agendamentos.

- **Estrutura Principal**:
  - Inclui menu admin.
  - Título "Lista de Clientes" com mensagens flash.
  - Formulários de busca por Nome e CPF (com ícone de lupa).
  - Tabela responsiva com colunas: ID, Nome, CPF, Telefone, Plano, Status Instalação, Ações (editar, agendar, imprimir).
  - Modais para Editar Cliente e Agendar Instalação, com forms detalhados e validações.
  - Modal para relatório pendente se status mudar para "Concluída" sem relatório.

- **JavaScript Integrado**:
  - Carregamento de dados via AJAX para modais (fetch `/admin/get_cliente/<id>`).
  - Máscaras para CPF, telefone, data (nascimento/instalação) e CEP.
  - Busca dinâmica client-side por Nome/CPF.
  - Submit AJAX para edições e agendamentos, com atualização dinâmica da tabela e reload para consistência.
  - Inicialização de tooltips Bootstrap.

- **Integrações**:
  - Rotas: `/admin/update_cliente/<id>` (POST), `/admin/update_agendamento/<id>` (POST), `/admin/imprimir_cliente/<id>` (GET).
  - Enums para status e turnos via selects.

#### templates/admin/imprimir_relatorio.html

Template para impressão de relatórios de instalações, formatado para A4 com estilo profissional.

- **Estrutura Principal**:
  - Div `.a4-page` com cabeçalho (logo, título), seção de dados do relatório (número, data, tabela de clientes) e rodapé.
  - Tabela com colunas: Nome Completo, CPF, Endereço, Data de Conclusão.
  - Texto formal solicitando baixa de boleto.
  - Botão "Imprimir Relatório" (oculto na impressão).

- **CSS Integrado**:
  - Estilos para A4, tabelas zebradas, media queries para print.

- **Integrações**:
  - Rota: `/admin/imprimir_relatorio/<identificador>` (GET).
  - Dados: `identificador`, `data_criacao`, `quantidade` (fixo 3), `clientes`, `data_vencimento`.

#### templates/admin/usuarios.html

Página para gerenciamento de usuários administrativos.

- **Estrutura Principal**:
  - Inclui menu admin.
  - Título "Lista de Usuários" com botão "Novo Usuário".
  - Inputs de busca por Username e Email.
  - Tabela com colunas: ID, Username, Email, Telegram ID, Ações (editar, apagar).
  - Modal para Editar/Novo Usuário com fields para username, senha (opcional em edição), email e Telegram ID.

- **JavaScript Integrado**:
  - Carregamento AJAX de dados para modal (fetch `/admin/get_usuario/<id>`).
  - Busca dinâmica por Username/Email.
  - Submit AJAX para create/update, com validações (senhas iguais, min 6 chars).
  - Confirmação para apagar, com POST AJAX e reload.

- **Integrações**:
  - Rotas: `/admin/create_usuario` (POST), `/admin/update_usuario/<id>` (POST), `/admin/delete_usuario/<id>` (POST).

#### templates/admin/cobertura.html

Página para gerenciamento de áreas de cobertura.

- **Estrutura Principal**:
  - Inclui menu admin.
  - Título "Área de Cobertura" com botão "Novo Endereço".
  - Formulários de busca por Nome Logradouro e Bairro (com lupa).
  - Tabela com colunas: ID, Tipo Logradouro, Nome Logradouro, Bairro, Cidade, Estado, País, Ações.
  - Modal para Editar/Novo Endereço com fields correspondentes.

- **JavaScript Integrado**:
  - Carregamento AJAX de dados para modal (fetch `/admin/get_cobertura/<id>`).
  - Submit AJAX para create/update.
  - Confirmação para apagar com POST AJAX e reload.

- **Integrações**:
  - Rotas: `/admin/create_cobertura` (POST), `/admin/update_cobertura/<id>` (POST), `/admin/delete_cobertura/<id>` (POST).

### Templates de Emails (em templates/emails/)

Esses templates são usados pelo Flask-Mail para gerar emails HTML profissionais, com estilos inline para compatibilidade com clientes de email. Incluem header com logo, conteúdo personalizado via Jinja e footer com copyright. Usam variáveis como `{{ base_url }}` para links absolutos e `{{ current_year }}` para ano dinâmico.

#### templates/emails/contato.html

Template simples para email de notificação de contato recebido via site, enviado para admins.

- **Estrutura Principal**:
  - Cabeçalho com saudação.
  - Lista UL com detalhes: Nome, E-mail, Setor, Mensagem.
  - Rodapé com assinatura e copyright.

- **Integrações**:
  - Renderizado em `enviar_contato()` com variáveis `nome`, `email`, `setor`, `mensagem`, `current_year`.
  - Enviado via Flask-Mail para setores específicos.

#### templates/emails/boas_vindas.html

Template de boas-vindas para novos clientes pós-cadastro.

- **Estrutura Principal**:
  - Tabela responsiva para layout email-safe.
  - Header com logo.
  - Conteúdo: Saudação personalizada, mensagem de agradecimento, call-to-action button "Acessar Site".
  - Footer vermelho com copyright.
  - Estilos inline: Font Montserrat, cores branding, responsivo via media queries.

- **Integrações**:
  - Renderizado em função de cadastro com `nome`, `base_url`, `current_year`.
  - Enviado ao email do cliente.

#### templates/emails/reset_senha.html

Template para email de reset de senha, com link temporário.

- **Estrutura Principal**:
  - Tabela responsiva.
  - Header com logo.
  - Conteúdo: Saudação, instrução para clicar no button "Redefinir Senha", aviso se não solicitado.
  - Footer com copyright.
  - Estilos inline similares aos outros emails.

- **Integrações**:
  - Renderizado em `forgot_password()` com `reset_url`, `base_url`, `current_year`.
  - Enviado ao email do usuário.

#### templates/emails/atualizacao_instalacao.html

Template para notificar clientes sobre atualizações no status de instalação.

- **Estrutura Principal**:
  - Tabela responsiva.
  - Header com logo.
  - Conteúdo: Saudação, detalhes: Data, Turno, Status (com cor condicional via inline CSS: amarelo para Programada, verde para Concluída, vermelho para Não Realizada), Observações.
  - Button "Acessar Site".
  - Footer.
  - Estilos inline, incluindo cores condicionais para status.

- **Integrações**:
  - Renderizado em `enviar_atualizacao_instalacao()` com `nome`, `data_instalacao`, `turno_instalacao`, `status_instalacao`, `observacoes`, `base_url`, `current_year`.
  - Enviado ao email do cliente após atualização admin.

#### templates/emails/novo_cadastro_admin.html

Template detalhado para alertar admins sobre novo cadastro de cliente.

- **Estrutura Principal**:
  - Tabela responsiva.
  - Header com logo.
  - Conteúdo: Título "Novo Cadastro", tabela com todos os dados do cliente.
  - Footer.
  - Estilos inline para tabela (zebrada, borders).

- **Integrações**:
  - Renderizado em função de cadastro com todas as variáveis do form (ex: `nome`, `cpf`, etc.), `base_url`, `current_year`.
  - Enviado para admins configurados.

Esses templates de email garantem comunicações profissionais, responsivas e seguras, com links absolutos via `base_url` para ambientes de produção.

Esses templates garantem uma experiência frontend rica, com responsividade mobile via Bootstrap e interações dinâmicas sem full page reloads.

## Instalação e Configuração

Este projeto é projetado exclusivamente para execução em Docker. Não há suporte para instalação local sem Docker, pois todas as configurações são gerenciadas via `docker-compose.yml`. Isso garante portabilidade e isolamento.

### Pré-requisitos

- Docker e Docker Compose instalados (versão recente recomendada).
- Chaves API: Google Maps, reCAPTCHA, Telegram Bot Token, credenciais SMTP (configure no `docker-compose.yml`).
- Senhas seguras para DB e app (não use valores fake em produção).

### Instalação com Docker

1. Clone o repositório:

```
git clone https://github.com/AprendendoLinux/firenet.git
cd firenet/

```

2. Edite o `docker-compose.yml` com valores reais para as variáveis de ambiente (substitua os "fake_" placeholders):
- `DB_PASSWORD`: Senha segura para MySQL.
- `Maps_API_KEY`: Chave válida.
- `SECRET_KEY`: Chave Flask única.
- `TELEGRAM_BOT_TOKEN`: Token do bot.
- `MAIL_SERVER`, `MAIL_PORT`, etc.: Configurações SMTP.
- `APP_BASE_URL`: URL base (ex: https://seu-dominio.com/).
- `RECAPTCHA_SITE_KEY` e `RECAPTCHA_SECRET_KEY`: Chaves Google.
- `MYSQL_ROOT_PASSWORD`: Senha root MySQL.
- Volumes persistentes: `/srv/database` para dados MySQL.

3. Build e rode os containers:

```
docker-compose up -d --build

```
- O app roda em `http://localhost:8080/` (mapeado de porta 5000 interna).
- O DB MySQL roda em `localhost:3306` (acesso via root com senha definida).
- Aguarde inicialização: O app espera o DB estar pronto via `wait_for_db()`.

4. Acesse o app: Abra `http://localhost:8080/` no navegador. Login inicial: admin/admin (mude imediatamente).

5. Para parar ou reiniciar:

```
docker-compose down
docker-compose up -d

```

6. Logs e depuração: `docker-compose logs -f firenet` para app, `docker-compose logs -f database` para DB.

Em produção: Use HTTPS via reverse proxy (ex: Nginx), volumes persistentes e secrets Docker para senhas. Monitore com tools como Portainer.

## Uso e Endpoints (API)

A aplicação expõe várias rotas HTTP e endpoints da API interna para as lógicas de retenção e validação do Javascript:

- **APIs Internas e Background**:
- `/api/solicitar_retorno` (POST): Rota chamada quando o CPF duplicado é detectado; dispara via Telegram e e-mail os dados do cliente, engatilhando a recuperação da venda.
- `/api/consultar_cpf` e `/api/check_status` (POST): Rotas essenciais do Atendente Virtual, responsáveis por devolver status de instalação e validar dados do usuário em JSON.
- `/admin/save_schedule` e `/admin/toggle_whatsapp` (POST): API restrita para salvar dinamicamente as tabelas de horários comerciais e feriados, refletindo globalmente na renderização dos widgets de atendimento do site.

- **Públicas Tradicionais**:
- / (GET): Index com seções marketing.
- /contato (GET/POST): Formulário contato.
- /cadastro (GET/POST): Wizard cadastro.
- /check_cpf (POST): Verifica CPF (JSON).
- /check_coverage (POST): Verifica cobertura (JSON).
- /vantagens/<vantagem> (GET): Detalhes vantagens.
- /sucesso (GET): Página sucesso.
- /forgot_password (GET/POST): Solicita reset.
- /reset_password/<token> (GET/POST): Reseta senha.
- /enviar_contato (POST): Processa contato.
- /login (GET/POST): Login.
- /logout (GET): Logout.

- **Admin Rotas**:
- /admin/clientes (GET): Lista clientes com buscas.
- /admin/update_cliente/<id> (POST): Edita cliente.
- /admin/update_agendamento/<id> (POST): Atualiza instalação.
- /admin/imprimir_cliente/<id> (GET): Imprime ficha.
- /admin/relatorios (GET): Lista relatórios com paginação.
- /admin/gerar_relatorio (POST): Gera novo.
- /admin/imprimir_relatorio/<identificador> (GET): Imprime relatório.
- /admin/usuarios (GET): Lista usuários.
- /admin/avisos (GET/POST): Cria e edita comunicados de colisão controlada.
- /admin/create_usuario (POST): Cria usuário.
- /admin/update_usuario/<id> (POST): Edita usuário.
- /admin/delete_usuario/<id> (POST): Apaga usuário.
- /admin/cobertura (GET): Lista coberturas com buscas.
- /admin/create_cobertura (POST): Cria cobertura.
- /admin/update_cobertura/<id> (POST): Edita cobertura.
- /admin/delete_cobertura/<id> (POST): Apaga cobertura.

## Configurações Avançadas

- **Banco de Dados**: Inicializado via `init_db()` com locks para identificadores. Enums para status.
- **Emails e Telegram**: Templates em /templates/emails/. Async para Telegram. Configure SMTP em docker-compose.
- **Segurança**: HTTPS em prod, CSRF se expandir.
- **Customizações**: Edite templates para branding, adicione rotas em app.py.

## Contribuição

Contribuições bem-vindas! Fork, branch, commit, push e PR. Siga código de conduta.

### Issues

Relate em [Issues](https://github.com/AprendendoLinux/firenet/issues).

## Licença

[MIT License](LICENSE).

## Contato

- Autor: [**Henrique Fagundes**](https://henrique.tec.br)
- Email: henrique@henrique.tec.br
- Suporte: Issues no GitHub.

Agradecimentos à comunidade open-source!

Última atualização: Fevereiro 2026.