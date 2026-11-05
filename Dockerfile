FROM wordpress:php8.3-apache

COPY gymtrack-plugin/gymtrack.php /usr/src/wordpress/wp-content/plugins/gymtrack/gymtrack.php
COPY gymtrack-plugin/assets/ /usr/src/wordpress/wp-content/plugins/gymtrack/assets/

EXPOSE 80
