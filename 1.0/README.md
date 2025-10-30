# Firenet Alpine

Bem-vindo ao **Firenet Alpine**, um contêiner Docker otimizado que fornece um ambiente leve e eficiente para aplicações PHP. Este repositório utiliza **Alpine Linux**, **Nginx**, e **PHP 8.2 FPM**, oferecendo suporte a autenticação, gerenciamento de sessões, integração com banco de dados MySQL, envio de e-mails, e requisições HTTP para APIs externas.

## Sobre o Contêiner

Este contêiner foi projetado para suportar aplicações PHP com as seguintes funcionalidades:

- **Autenticação segura**: Suporte a login com validação via reCAPTCHA e autenticação de dois fatores (2FA).
- **Gerenciamento de sessões**: Manipulação de sessões PHP para controle de usuários logados.
- **Conexão com MySQL**: Integração com banco de dados MySQL via PDO para operações de leitura e escrita.
- **Envio de e-mails**: Suporte a envio de e-mails usando bibliotecas como PHPMailer.
- **Integração com APIs**: Requisições HTTP para APIs externas, como Telegram, usando `curl` ou `file_get_contents`.
- **URLs amigáveis**: Reescritas de URL configuradas no Nginx, compatíveis com padrões comuns de `.htaccess`.

A imagem é construída com Alpine Linux, resultando em um tamanho reduzido (\~50-70 MB) e alta performance.

## Pré-requisitos

- Docker instalado.
- Arquivos PHP da sua aplicação (a serem montados no contêiner).
- Conexão com um banco de dados MySQL (se necessário).
- Chaves de API para serviços externos, como reCAPTCHA ou Telegram (se aplicável).

## Como Usar

Siga os passos abaixo para executar o contêiner localmente:

1. **Clone o repositório**:

   ```bash
   git clone https://github.com/AprendendoLinux/firenet-alpine.git
   cd firenet-alpine
   ```

2. **Prepare os arquivos PHP**:

   - Coloque os arquivos da sua aplicação (ex.: `index.php`, `config.php`) no diretório `src/`.
   - Configure as credenciais do banco de dados e APIs no seu arquivo de configuração.

3. **Construa a imagem Docker**:

   ```bash
   docker build -t firenet-alpine .
   ```

4. **Execute o contêiner**:

   ```bash
   docker run -d -p 8080:80 -v $(pwd)/src:/var/www/html firenet-alpine
   ```

5. **Acesse a aplicação**:

   - Abra seu navegador em `http://localhost:8080`.
   - Teste as funcionalidades da sua aplicação PHP.

## Estrutura do Repositório

```
firenet-alpine/
├── Dockerfile          # Configuração da imagem Docker
├── nginx.conf          # Configuração do Nginx
├── entrypoint.sh       # Script para iniciar Nginx e PHP-FPM
├── src/                # Diretório para os arquivos PHP da aplicação
└── README.md           # Este arquivo
```

## Configurações Principais

- **Imagem Base**: `php:8.2-fpm-alpine`.
- **Servidor Web**: Nginx, configurado para processar arquivos PHP e suportar reescritas de URL.
- **PHP**: Versão 8.2 com extensões `pdo_mysql`, `mbstring`, e `curl`.
- **Timezone**: Configurado para `America/Sao_Paulo`.
- **Logs**: Redirecionados para `stdout` e `stderr` para monitoramento.
- **Permissões**: Diretório `/var/www/html` com permissões ajustadas para acesso seguro.

## Personalização

- **Reescritas de URL**: Edite `nginx.conf` para adicionar regras específicas de redirecionamento.
- **Extensões PHP**: Modifique o `Dockerfile` para incluir extensões adicionais (ex.: `gd`, `zip`).
- **Configurações**: Ajuste as credenciais de banco de dados e APIs nos arquivos PHP da sua aplicação.

## Contribuindo

Contribuições são bem-vindas! Siga os passos abaixo:

1. Faça um fork do repositório.

2. Crie uma branch para sua feature ou correção:

   ```bash
   git checkout -b minha-feature
   ```

3. Commit suas alterações:

   ```bash
   git commit -m "Adiciona minha feature"
   ```

4. Envie para o repositório remoto:

   ```bash
   git push origin minha-feature
   ```

5. Abra um Pull Request no GitHub.

## Licença

Este projeto é licenciado sob a MIT License.

## Autor

- **AprendendoLinux** - GitHub

## Agradecimentos

- À comunidade open-source por ferramentas como Docker, Alpine Linux, Nginx, e PHP.

---

⭐ Se este projeto foi útil, deixe uma estrela no repositório! 🚀

