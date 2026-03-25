# TechChallenge API

API de autenticação construída com Laravel 13, JWT e MySQL, com ambiente de desenvolvimento via Docker Compose e documentação OpenAPI.

## Stack

- PHP 8.4
- Laravel 13
- MySQL 8
- JWT para autenticação
- Docker Compose
- Swagger UI para visualização da documentação

## O que a API oferece

Atualmente o projeto expõe endpoints de autenticação em `/api/auth` para:

- registrar usuário
- realizar login
- obter usuário autenticado
- renovar token
- encerrar sessão

## Pré-requisitos

Antes de iniciar, tenha instalado na máquina:

- Docker
- Docker Compose

## Serviços disponíveis

Ao subir o ambiente, os serviços ficam disponíveis em:

- API Laravel: `http://localhost:8080`
- Swagger UI: `http://localhost:8082`
- MySQL: `localhost:3308`

## Configuração do ambiente

### 1. Copie o arquivo de ambiente

```bash
cp .env.example .env
```

### 2. Revise as variáveis do banco

O projeto já vem configurado para usar o serviço `db` do Docker Compose. No `.env`, valide ao menos estes campos:

```env
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=techchallenge
DB_USERNAME=techchallenge
DB_PASSWORD=
```

Se quiser evitar inconsistência no MySQL, mantenha `DB_PASSWORD` preenchido com o mesmo valor usado no container.

### 3. Suba os containers

```bash
docker compose up -d --build
```

### 4. Instale as dependências do PHP

```bash
docker compose run --rm app-php composer install
```

### 5. Gere a chave da aplicação

```bash
docker compose run --rm app-php php artisan key:generate
```

### 6. Rode as migrations

```bash
docker compose run --rm app-php php artisan migrate
```

## Como executar no dia a dia

Para iniciar o ambiente:

```bash
docker compose up -d
```

Para acompanhar logs:

```bash
docker compose logs -f app-php
```

Para parar os serviços:

```bash
docker compose down
```

## Documentação da API

O contrato da API está no arquivo `openapi.yaml`.

Para visualizar a documentação no navegador, suba o serviço e acesse:

```text
http://localhost:8082
```

## Endpoints principais

Base URL local:

```text
http://localhost:8080/api
```

Rotas disponíveis:

- `POST /auth/register`
- `POST /auth/login`
- `POST /auth/logout`
- `POST /auth/refresh`
- `GET /auth/me`

## Fluxo de autenticação

### Registrar usuário

```bash
curl -X POST http://localhost:8080/api/auth/register \
	-H "Content-Type: application/json" \
	-d '{
		"name": "João Silva",
		"email": "joao@example.com",
		"password": "senha12345",
		"password_confirmation": "senha12345"
	}'
```

### Realizar login

```bash
curl -X POST http://localhost:8080/api/auth/login \
	-H "Content-Type: application/json" \
	-d '{
		"email": "joao@example.com",
		"password": "senha12345"
	}'
```

Exemplo de resposta:

```json
{
	"access_token": "seu-token-jwt",
	"token_type": "bearer"
}
```

### Consultar usuário autenticado

```bash
curl http://localhost:8080/api/auth/me \
	-H "Authorization: Bearer seu-token-jwt"
```

### Renovar token

```bash
curl -X POST http://localhost:8080/api/auth/refresh \
	-H "Authorization: Bearer seu-token-jwt"
```

### Logout

```bash
curl -X POST http://localhost:8080/api/auth/logout \
	-H "Authorization: Bearer seu-token-jwt"
```

## Testes

Para executar os testes automatizados:

```bash
docker compose run --rm app-php php artisan test
```

## Observações

- O container `app-php` publica a aplicação na porta `8080` usando `php artisan serve`.
- O Swagger UI lê diretamente o arquivo `openapi.yaml` do projeto.
- O banco MySQL é exposto localmente na porta `3308`.