FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libmemcached-dev \
        zlib1g-dev \
        libzip-dev \
        unzip \
        cron \
    && pecl install memcached \
    && docker-php-ext-enable memcached \
    && docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY docker/apache.conf /etc/apache2/conf-available/news-aggregator.conf
RUN a2enconf news-aggregator

COPY docker/crontab /etc/cron.d/news-fetch
RUN chmod 0644 /etc/cron.d/news-fetch \
    && crontab /etc/cron.d/news-fetch

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
