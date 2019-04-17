# Russell Michell 2019 <russ@theruss.com> with a few nudges from:
# This Dockerfile is used to produce the image: dcentrica/silverstripe-twilio-part-1:0.0.1 
#
# To build & run
#
# #> docker build --tag=dcentrica/silverstripe-twilio-part-1:1.0.0 .
# #> docker run -it -p 8080:80 -v . dcentrica/silverstripe-twilio-part-1:1.0.0
# #> docker exec -it $( docker ps | awk 'NR == 2 { print $NR }' ) bash
# #> docker-compose up -d --build
#
# To push:
#
# #> docker push dcentrica/silverstripe-twilio-part-1:1.0.0
#
# This Dockerfile defines the image for the SilverStripe web-app. Mariadb is taken from the official MAraiDB image
# The entire "app" is built with docker-compose.

FROM php:7.1.26-apache-stretch
MAINTAINER Russell Michell <info@dcentrica.com>
ARG DOCROOT

VOLUME "$DOCROOT"
COPY . "$DOCROOT"
WORKDIR "$DOCROOT"
ADD .docker/apache/000-default.conf /etc/apache2/sites-available/

# Get base libs and packages
RUN DEBIAN_FRONTEND=noninteractive apt-get install -qqy && \
    DEBIAN_FRONTEND=noninteractive apt-get update -qqy && \
    DEBIAN_FRONTEND=noninteractive apt-get dist-upgrade -qqy && \
    DEBIAN_FRONTEND=noninteractive apt-get install -qqy sudo gnupg wget \
    vim zip unzip libgmp-dev \
    libcurl3-dev git zlib1g zlib1g-dev \
    libtidy-dev libicu-dev libxml2-dev libpng-dev \
    libjpeg-dev mariadb-server mariadb-client && \
    docker-php-ext-install zip mysqli intl gd gmp bcmath tidy && \
    pecl install msgpack && docker-php-ext-enable msgpack && \
    mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" && \
    sed -i 's#memory_limit = [0-9]+M#memory_limit = 512M#g' "$PHP_INI_DIR/php.ini" && \
    wget -O /usr/local/bin/composer https://getcomposer.org/composer.phar && chmod +x /usr/local/bin/composer && \
    mkdir -p $DOCROOT && \
    a2enmod rewrite && \
	echo "date.timezone = Pacific/Auckland" > "$PHP_INI_DIR/conf.d/timezone.ini" && \
	echo "date.timezone = Pacific/Auckland" > "$PHP_INI_DIR/conf.d/timezone.ini" && \
    a2ensite 000-default && \
    service apache2 start || service apache2 restart && \
    composer install --prefer-source --no-interaction

EXPOSE 80/tcp

# Setup env vars
ENV PATH="~/.composer/vendor/bin:./vendor/bin:${PATH}"
ENV LANG en_NZ.UTF-8
ENV EDITOR=/usr/bin/vim

# Docker needs Apache to run in the foreground
CMD ["apachectl", "-D", "FOREGROUND"]
