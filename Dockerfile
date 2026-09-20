# École Abou el Anouar — image Apache + PHP + PostgreSQL
FROM php:8.3-apache

# Dépendance + extension PDO PostgreSQL
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Autoriser .htaccess (et masquer la navigation des dossiers)
RUN sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf /etc/apache2/sites-available/*.conf

WORKDIR /var/www/html
COPY . /var/www/html

# Répertoires de session / upload
RUN mkdir -p /var/www/html/espace/config/.cache \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80

# Initialisation idempotente de la base (crée les tables si absentes,
# seeds uniquement si la base est vide) puis démarrage d'Apache.
CMD ["sh", "-c", "php /var/www/html/espace/config/init_db.php >/dev/null 2>&1; apache2-foreground"]