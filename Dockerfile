FROM wordpress:php8.3-apache

# GymTrack se incorpora como plugin versionado en el proyecto.
# WordPress y Apache se proporcionan mediante la imagen oficial.
COPY gymtrack-plugin/gymtrack.php /usr/src/wordpress/wp-content/plugins/gymtrack/gymtrack.php

EXPOSE 80
