# 📦 Imagem Docker com PHP 8.2 + Apache

Esta imagem Docker é baseada no PHP 8.2 com Apache, construída sobre o Debian, e foi otimizada para aplicações web modernas com suporte a MySQL via PDO, `mbstring` e configuração de timezone para o Brasil (America/Sao_Paulo).

Ideal para rodar aplicações PHP que requerem Apache e extensões comuns como `pdo_mysql` e `mbstring`.

---

## 🔧 Recursos inclusos

- ✅ PHP 8.2
- ✅ Apache 2.4
- ✅ Suporte a PDO MySQL (`pdo_mysql`)
- ✅ Suporte a `mbstring`
- ✅ Timezone configurado para `America/Sao_Paulo`
- ✅ Módulo `mod_rewrite` habilitado
- ✅ Logs do Apache e PHP redirecionados para `stdout` e `stderr`
- ✅ Volume persistente em `/var/www/html`
- ✅ Pronto para uso com `.htaccess`

---

## 📂 Estrutura do Dockerfile

O Dockerfile realiza as seguintes ações:

1. Usa a imagem base oficial `php:8.2-apache`.
2. Instala dependências do sistema e extensões PHP necessárias.
3. Configura o timezone para `America/Sao_Paulo`.
4. Habilita o módulo `rewrite` do Apache.
5. Redireciona os logs do Apache e PHP para os canais padrão (`stdout`, `stderr`).
6. Permite sobrescrever configurações via `.htaccess`.
7. Define o diretório de trabalho como `/var/www/html`.

---

## 🚀 Como usar

### Clonando o repositório

```bash
git clone https://github.com/AprendendoLinux/firenet.git
cd firenet
```

### Construindo a imagem

```bash
docker build -t firenet .
```

### Executando o contêiner

```bash
docker run -d \
  -p 8080:80 \
  -v $(pwd)/src:/var/www/html \
  --name firenet \
  firenet
```

### Acessando no navegador

Abra no navegador: [http://localhost:8080](http://localhost:8080)

---

## 🐳 Docker Compose (opcional)

Você também pode usar o seguinte `docker-compose.yml`:

```yaml
version: '3.8'

services:
  web:
    build: .
    ports:
      - "8080:80"
    volumes:
      - ./src:/var/www/html
    container_name: firenet
```

Execute com:

```bash
docker-compose up -d
```

---

## 🛠️ Personalizações adicionais

- **Diretório de projeto:** basta montar seu projeto PHP em `/var/www/html`.
- **Timezone:** altere a linha correspondente no Dockerfile para outro fuso horário, se desejar.
- **Log de erros PHP:** redirecionado para `stderr`. Pode ser customizado no `php.ini`.

---

## 📎 Volume persistente

O contêiner define:

```dockerfile
VOLUME /var/www/html
```

Isso permite que o diretório de projeto PHP seja persistido e facilmente mapeado com o host.

---

## 📜 Licença

Este projeto é de código aberto e está disponível sob a licença [MIT](LICENSE).

---

## 🙋‍♂️ Autor

**Luiz Henrique Marques Fagundes**  
Analista de Infraestrutura GNU/Linux  
🔗 [aprendendolinux.com](https://www.aprendendolinux.com)  
📧 contato [@] aprendendolinux [.] com
