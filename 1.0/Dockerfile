# Usa a imagem base do PHP 8.2 FPM com Alpine Linux
FROM php:8.2-fpm-alpine

# Define o diretório de trabalho
WORKDIR /var/www/html
COPY data /var/www/html

# Instala dependências do sistema e extensões do PHP
RUN apk add --no-cache \
    # Dependências básicas
    nginx \
    # Para configuração de timezone
    tzdata \
    # Para extensões PHP
    oniguruma-dev \
    mariadb-connector-c-dev \
    # Para suporte a requisições HTTP (curl)
    curl \
    curl-dev \
    # Dependências para a extensão GD
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        curl \
        gd \
    && apk del .build-deps curl-dev \
    # Configura o timezone para America/Sao_Paulo
    && ln -sf /usr/share/zoneinfo/America/Sao_Paulo /etc/localtime \
    && echo "America/Sao_Paulo" > /etc/timezone \
    # Limpa o cache do apk para reduzir o tamanho da imagem
    && rm -rf /var/cache/apk/*

# Copia a configuração do Nginx
COPY nginx.conf /etc/nginx/nginx.conf

# Configura o PHP-FPM
RUN echo "error_log = /dev/stderr" >> /usr/local/etc/php/php.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/php.ini \
    && echo "display_errors = Off" >> /usr/local/etc/php/php.ini \
    && echo "display_startup_errors = Off" >> /usr/local/etc/php/php.ini \
    && echo "date.timezone = America/Sao_Paulo" >> /usr/local/etc/php/php.ini \
    && sed -i 's|;access.log = log/$pool.access.log|access.log = /dev/stdout|' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's|;error_log = log/php-fpm.log|error_log = /dev/stderr|' /usr/local/etc/php-fpm.d/www.conf

# Cria diretórios para logs e ajusta permissões amplas para simular "desbloqueio"
RUN mkdir -p /var/log/nginx \
    && ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html

# Expõe a porta 80 para o Nginx
EXPOSE 80

# Copia um script de entrada para iniciar Nginx e PHP-FPM
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Define o volume para os arquivos do projeto
VOLUME /var/www/html

# Inicia o contêiner com o script de entrada
ENTRYPOINT ["/entrypoint.sh"]