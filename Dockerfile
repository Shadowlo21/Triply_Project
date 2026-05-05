FROM php:8.3-cli

RUN docker-php-ext-install pdo pdo_sqlite

WORKDIR /app
COPY . /app

RUN mkdir -p /app/public/uploads && chmod -R 0775 /app/database /app/public/uploads

EXPOSE 10000

CMD ["php", "-S", "0.0.0.0:10000", "-d", "upload_max_filesize=20M", "-d", "post_max_size=25M", "-d", "memory_limit=128M", "index.php"]
