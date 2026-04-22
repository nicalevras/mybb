FROM php:8.3-apache

ARG BUILD_VERSION=1839
ARG BUILD_SHA512SUM=a13f45cc465100726d324a59f1683c5a116d4aad8ae3d26d3b6d709d8d97b6db14a135c08a0064d3194ed363891efa6cdcb0f4b58111d78a718fa2f6bd936bd7

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

# Download and extract MyBB
ENV MYBB_VERSION=${BUILD_VERSION}
ENV MYBB_SHA512=${BUILD_SHA512SUM}

RUN set -ex; \
    curl -o mybb.tar.gz -fSL "https://github.com/mybb/mybb/archive/refs/tags/mybb_${MYBB_VERSION}.tar.gz"; \
    echo "${MYBB_SHA512}  mybb.tar.gz" | sha512sum -c -; \
    tar -xzf mybb.tar.gz -C /usr/src/; \
    rm mybb.tar.gz;

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Apache config for MyBB
RUN echo '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/mybb.conf \
    && a2enconf mybb

# Copy entrypoint
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Data volume for persistent storage
VOLUME /var/www/html

WORKDIR /var/www/html

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
