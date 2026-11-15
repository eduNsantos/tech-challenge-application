#!/usr/bin/env bash
# Monta em build/lambda o pacote das Lambdas (consumers e publisher) da aplicação.
# O Terraform (tech-challenge-kubernetes/app-lambda) compacta esse diretório; no deploy,
# o script roda dentro da imagem da aplicação. Requer PHP 8.4 e Composer.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/build/lambda"

rm -rf "$OUT"
mkdir -p "$OUT"

for path in app bootstrap config database routes resources artisan composer.json composer.lock; do
  cp -R "$ROOT/$path" "$OUT/"
done

# Caches locais (config, rotas, pacotes de dev) não podem ir no pacote.
find "$OUT/bootstrap/cache" -type f -name '*.php' -delete

cd "$OUT"
composer install --no-dev --classmap-authoritative --no-interaction --prefer-dist --no-progress

echo "Pacote gerado em $OUT ($(du -sh "$OUT" | cut -f1))"
