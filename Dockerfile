FROM php:8.3-apache

ARG BUILD_VERSION=1839

LABEL org.opencontainers.image.title="MyBB Forum"
LABEL org.opencontainers.image.description="MyBB 1.8.39 forum, Railway-ready single container"
LABEL org.opencontainers.image.version="1.8.39"
LABEL org.opencontainers.image.source="https://github.com/nicalevras/mybb"

# Install PHP extensions MyBB requires
RUN set -ex; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libwebp-dev \
        libzip-dev \
        libcurl4-openssl-dev \
        libonig-dev \
        libxml2-dev \
        unzip \
        curl \
    ; \
    rm -rf /var/lib/apt/lists/*; \
    \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-install -j "$(nproc)" \
        gd \
        mysqli \
        pdo_mysql \
        opcache \
        curl \
        zip \
        mbstring \
        xml \
        simplexml \
    ;

# OPcache settings
RUN { \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=8'; \
        echo 'opcache.max_accelerated_files=4000'; \
        echo 'opcache.revalidate_freq=2'; \
        echo 'opcache.fast_shutdown=1'; \
        echo 'opcache.enable_cli=1'; \
    } > /usr/local/etc/php/conf.d/opcache-recommended.ini

# PHP settings for MyBB
RUN { \
        echo 'file_uploads=On'; \
        echo 'upload_max_filesize=10M'; \
        echo 'post_max_size=10M'; \
        echo 'max_execution_time=30'; \
        echo 'memory_limit=256M'; \
        echo 'display_errors=Off'; \
        echo 'log_errors=On'; \
        echo 'error_log=/dev/stderr'; \
    } > /usr/local/etc/php/conf.d/mybb-recommended.ini

# Download and extract MyBB to /usr/src/mybb
ENV MYBB_VERSION=${BUILD_VERSION}

RUN set -ex; \
    curl -o mybb.tar.gz -fSL "https://github.com/mybb/mybb/archive/refs/tags/mybb_${MYBB_VERSION}.tar.gz"; \
    tar -xzf mybb.tar.gz -C /usr/src/; \
    mv "/usr/src/mybb-mybb_${MYBB_VERSION}" /usr/src/mybb; \
    rm mybb.tar.gz; \
    ls -la /usr/src/mybb/index.php;

# Fix MPM conflict — forcefully remove event/worker, keep only prefork for mod_php
RUN rm -f /etc/apache2/mods-enabled/mpm_event.* \
 && rm -f /etc/apache2/mods-enabled/mpm_worker.* \
 && a2enmod mpm_prefork rewrite

# Apache config for MyBB
RUN echo '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/mybb.conf \
    && a2enconf mybb

# Copy File Manager plugin into MyBB source
COPY file-manager-plugin/Upload/ /usr/src/mybb/

# Copy entrypoint
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

WORKDIR /var/www/html

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
