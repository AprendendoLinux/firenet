# Usa a imagem base do PHP 8.2 com Apache (baseada em Debian)
FROM php:8.2-apache

# Define o diretório de trabalho
WORKDIR /var/www/html

# Instala dependências do sistema e extensões do PHP
RUN apt-get update && apt-get install -y \
    # Dependências básicas
    bash \
    # Para extensões do PHP
    libzip-dev \
    # Para PDO MySQL
    libmariadb-dev \
    # Para mbstring (dependência oniguruma)
    libonig-dev \
    # Para configuração de timezone
    tzdata \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
    # Configura o timezone do sistema para America/Sao_Paulo
    && ln -sf /usr/share/zoneinfo/America/Sao_Paulo /etc/localtime \
    && echo "America/Sao_Paulo" > /etc/timezone \
    && dpkg-reconfigure -f noninteractive tzdata \
    # Limpa o cache do apt para reduzir o tamanho da imagem
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Habilita o módulo rewrite do Apache (caso precise de .htaccess)
RUN a2enmod rewrite

# Configura o Apache para enviar logs para stdout e stderr
RUN ln -sf /dev/stdout /var/log/apache2/access.log \
    && ln -sf /dev/stderr /var/log/apache2/error.log

# Configura o PHP para enviar logs de erro para stderr e define o timezone
RUN echo "error_log = /dev/stderr" >> /usr/local/etc/php/php.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/php.ini \
    && echo "display_errors = Off" >> /usr/local/etc/php/php.ini \
    && echo "display_startup_errors = Off" >> /usr/local/etc/php/php.ini \
    && echo "date.timezone = America/Sao_Paulo" >> /usr/local/etc/php/php.ini

# Copia a configuração do Apache para permitir acesso ao diretório /var/www/html
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's/\/var\/www\/html/\/var\/www\/html/' /etc/apache2/sites-available/000-default.conf

# Expõe a porta 80 para o Apache
EXPOSE 80

# Define o volume para os arquivos do projeto
VOLUME /var/www/html

# Inicia o Apache em modo foreground
CMD ["apache2-foreground"]
