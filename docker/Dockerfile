FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    postgresql-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    vips-tools \
    vips-heif

# gd s WebP a exif – náhledy fotek a otočení fotek z telefonu podle EXIF,
# zip – stažení všech fotek zakázky najednou
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_pgsql gd exif zip bcmath opcache

# vips-tools (vipsthumbnail) – náhledy i z obřích fotek a HEIC z iPhonu,
# zpracovává po částech, takže nepotřebuje celý obrázek v paměti jako GD.
# post_max_size musí pokrýt součet všech fotek v jednom odeslání (až 10 × 50 MB
# z telefonu). Při překročení PHP zahodí celé POST tělo a Laravel pak hlásí
# matoucí „CSRF token mismatch“ / 419.
RUN printf "upload_max_filesize=50M\npost_max_size=510M\nmax_file_uploads=20\nmemory_limit=256M\n" > /usr/local/etc/php/conf.d/uploads.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

USER www-data
