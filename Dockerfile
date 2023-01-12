FROM php:7.4-alpine3.13

RUN mkdir /app

WORKDIR /app

# Install Git, NPM & needed libraries
RUN apk update \
    && apk add bash git nodejs npm gettext subversion mysql mysql-client zip \
    && rm -f /var/cache/apk/*

RUN docker-php-ext-install mysqli

#  Install Composer
RUN EXPECTED_CHECKSUM=$(curl -s https://composer.github.io/installer.sig) \
    && curl https://getcomposer.org/installer -o composer-setup.php \
    && ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")" \
    && if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then >&2 echo 'ERROR: Invalid installer checksum'; rm composer-setup.php; exit 1; fi \
    && php composer-setup.php --quiet \
    && php -r "unlink('composer-setup.php');" \
    && mv composer.phar /usr/local/bin/composer

RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && \
    chmod +x wp-cli.phar && \
    mv wp-cli.phar /usr/local/bin/wp

RUN chmod +x -R ./
