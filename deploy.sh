#!/bin/bash
# Roda no servidor, dentro da pasta do projeto, depois de enviar uma nova versão.
# O CSS/JS (public/build) é compilado antes do envio: a hospedagem não precisa de Node.
set -e
cd "$(dirname "$0")"

echo "1. Dependências do PHP..."
if command -v composer >/dev/null 2>&1; then
    composer install --no-dev --optimize-autoloader --no-interaction
else
    echo "   composer não encontrado; usando a pasta vendor enviada no pacote."
fi

echo "2. Migrações do banco..."
php artisan migrate --force

echo "3. Atalho das imagens das oficinas..."
# A Hostinger bloqueia symlink() no PHP, então o storage:link falha; o atalho é criado pelo shell.
if [ ! -L public/storage ]; then
    (cd public && ln -s ../storage/app/public storage)
fi

echo "4. Caches de produção..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "Deploy finalizado."
