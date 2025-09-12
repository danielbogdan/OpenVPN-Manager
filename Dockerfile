# Dockerfile
FROM php:8.2-apache

ENV DEBIAN_FRONTEND=noninteractive

# Pachete utile + extensii PHP
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates curl unzip git iproute2 iputils-ping gnupg \
    libzip-dev \
    && docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

RUN printf "ServerName localhost\n" > /etc/apache2/conf-available/servername.conf \
 && a2enconf servername

# Docker CLI din repo oficial Docker (nu docker.io)
RUN apt-get update && apt-get install -y --no-install-recommends \
      ca-certificates curl gnupg \
 && install -m 0755 -d /etc/apt/keyrings \
 && curl -fsSL https://download.docker.com/linux/debian/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg \
 && chmod a+r /etc/apt/keyrings/docker.gpg \
 && echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/debian \
    $(. /etc/os-release && echo $VERSION_CODENAME) stable" > /etc/apt/sources.list.d/docker.list \
 && apt-get update \
 && apt-get install -y --no-install-recommends docker-ce-cli \
 && rm -rf /var/lib/apt/lists/*


# Setează DocumentRoot către /var/www/html/public și permite .htaccess acolo
RUN sed -ri 's!DocumentRoot /var/www/html!DocumentRoot /var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
 && printf "\n<Directory /var/www/html/public>\n\tAllowOverride All\n\tRequire all granted\n</Directory>\n" >> /etc/apache2/apache2.conf \
 # expune /actions din afara DocumentRoot, ca să poți apela /actions/*.php din formulare
 && printf "\nAlias /actions /var/www/html/actions\n<Directory /var/www/html/actions>\n\tAllowOverride None\n\tRequire all granted\n</Directory>\n" >> /etc/apache2/apache2.conf


# Copiem codul (va fi suprascris de bind-mount în docker-compose în dev)
COPY . /var/www/html

# Composer installation and dependency management
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Entry-point: adaugă www-data în grupul socketului Docker și pornește Apache
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
