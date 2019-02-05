# Russell Michell 2019 <russ@theruss.com> with a few nudges from:
# https://github.com/sminnee/docker-silverstripe-lamp
# https://blog.codeship.com/using-docker-compose-for-php-development/
# https://hub.docker.com/_/php/
#
# TODO: Use docker-compose to servicify:
# - MariaDB
# - Bitcoin Core
# - LND

FROM php:7.1.26-apache-stretch
MAINTAINER Russell Michell <russ@theruss.com>

# Get base libs and packages
RUN DEBIAN_FRONTEND=noninteractive apt-get update -qqy
RUN DEBIAN_FRONTEND=noninteractive apt-get dist-upgrade -qqy
RUN DEBIAN_FRONTEND=noninteractive apt-get install -qqy sudo wget vim libgmp-dev libcurl3-dev git zlib1g zlib1g-dev libicu-dev libpng-dev libjpeg-dev

# Get and enable middleware and extensions for PHP and MariaDB
RUN DEBIAN_FRONTEND=noninteractive apt-get install -qqy mariadb-client mariadb-server
RUN docker-php-ext-install zip mysqli intl gd curl mbstring gmp bcmath
RUN pecl install msgpack && \
    docker-php-ext-enable msgpack

# Configure PHP for a DEV environment
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
RUN sed -i 's#memory_limit = [0-9]+M#memory_limit = 512M#g' "$PHP_INI_DIR/php.ini"

# Get Composer
RUN wget -O /usr/local/bin/composer https://getcomposer.org/composer.phar && \
	chmod +x /usr/local/bin/composer

# SilverStripe Apache Configuration
RUN a2enmod rewrite && \
	rm -r /var/www/html && \
	echo "date.timezone = Pacific/Auckland" > "$PHP_INI_DIR/conf.d/timezone.ini" && \
	echo "date.timezone = Pacific/Auckland" > "$PHP_INI_DIR/conf.d/timezone.ini"

## Commands and ports	
EXPOSE 80
VOLUME /var/www
COPY . /var/www/
WORKDIR /var/www/

# Run apache in foreground mode, because Docker needs a foreground
ADD apache.conf /etc/apache2/sites-available/000-default.conf
ADD apache.fg /usr/local/bin/apache-foreground
RUN chmod +x /usr/local/bin/apache-foreground
CMD ["/usr/local/bin/apache-foreground"]

RUN composer install --prefer-source --no-interaction
ENV PATH="~/.composer/vendor/bin:./vendor/bin:${PATH}"
ENV LANG en_US.UTF-8

# Frontend build tools:
# nvm
# TODO

