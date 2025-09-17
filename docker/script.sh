#!/bin/bash

# Configurar o VirtualHost para Laravel
echo "<VirtualHost *:80>
    ServerAlias consulta-jucerr-homolog.rr.sebrae.com.br
    DocumentRoot /var/www/html/public
    <Directory /var/www/html/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>" > /etc/apache2/sites-available/000-default.conf

a2enmod rewrite

# Configurar o VirtualHost para HTTPS
# echo "<VirtualHost *:443>
#     DocumentRoot /var/www/html/public
#     <Directory /var/www/html/public>
#         Options Indexes FollowSymLinks
#         AllowOverride All
#         Require all granted
#     </Directory>
#     ErrorLog \${APACHE_LOG_DIR}/error.log
#     CustomLog \${APACHE_LOG_DIR}/access.log combined

#     SSLEngine on
#     SSLCertificateFile /etc/ssl/certs/tls.crt
#     SSLCertificateKeyFile /etc/ssl/certs/tls.key
# </VirtualHost>" > /etc/apache2/sites-available/default-ssl.conf

# Habilitar o site SSL
# a2ensite default-ssl

# Habilitar modulos rewrite ssl
# a2enmod rewrite ssl