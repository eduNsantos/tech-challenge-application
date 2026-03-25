Rode instale o composer
```
docker compose run --rm app-php composer install
```

Copie o .env.example para .env e preencha os dados de banco de dados

Gere a API key
```
docker compose run --rm app-php php artisan key:generate
```

Rode as migrations
```
docker compose run --rm app-php php artisan migrate
```