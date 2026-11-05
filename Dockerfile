FROM php:8.2-apache

# Installer les extensions système et PHP nécessaires pour Symfony (avec pdo_pgsql si tu es sur PostgreSQL, ou pdo_mysql)
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libicu-dev \
    libzip-dev \
    libpq-dev \
    && docker-php-ext-install intl pdo_mysql pdo_pgsql zip opcache

# Activer le module de réécriture d'Apache
RUN a2enmod rewrite

# Rediriger la racine web d'Apache vers le dossier public/ de Symfony
RUN sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf

# Autoriser le .htaccess d'Apache
RUN echo '<Directory /var/www/html/public>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/symfony.conf && \
a2enconf symfony

# Configurer Apache pour écouter sur le port 10000 (requis par Render)
RUN sed -i 's/80/10000/g' /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

# Définir le dossier de travail
WORKDIR /var/www/html

# Installer Composer depuis l'image officielle
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copier d'abord les fichiers de configuration de Composer pour optimiser le cache Docker
COPY composer.json composer.lock symfony.lock ./

# Installer les dépendances de production
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copier tout le reste du code source du projet dans le conteneur
COPY . .

# Terminer l'autoloader Composer et exécuter les migrations Doctrine automatiquement
RUN composer dump-autoload --optimize --classmap-authoritative

# Ajuster les permissions pour le serveur web
RUN chown -R www-data:www-data /var/www/html/var /var/www/html/public

# Lancer Apache en premier plan
CMD php bin/console doctrine:migrations:migrate --no-interaction && apache2-foreground