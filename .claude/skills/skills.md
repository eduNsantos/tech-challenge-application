# Skills — projeto-pos

Guia de habilidades e procedimentos para o Claude Code neste projeto Laravel 13 + DDD.

> **REGRA OBRIGATORIA — IDIOMA:** Todo o codigo PHP DEVE ser escrito em **INGLES**:
> nomes de classes, metodos, propriedades, variaveis, constantes, DTOs, UseCases,
> nomes de arquivos, pastas de dominio e valores de enums.
> Excecoes permitidas: comentarios PHPDoc, mensagens de erro ao usuario e
> descricoes/summaries no `openapi.yaml`.
> Nomes de colunas no banco de dados tambem devem ser em ingles.

> **REGRA OBRIGATORIA — SWAGGER:** Toda vez que uma nova rota for registrada em `routes/api.php`,
> a documentacao `openapi.yaml` DEVE ser atualizada na mesma tarefa, sem excecao.
> Nao encerre o trabalho sem documentar o endpoint.

---

## add-domain

Adiciona um novo dominio completo seguindo a arquitetura DDD do projeto.

**Quando usar:** sempre que o usuario pedir para criar um novo dominio, entidade ou modulo de negocio.

**Passos (nesta ordem):**

1. **Domain/Entities** — criar `app/Domain/{Dominio}/Entities/{Dominio}.php`
   - Construtor com todos os campos + metodo estatico `create()` gerando UUID com `Str::uuid()->toString()`
   - Metodo `updateData()` para atualizacoes parciais (campos nulaveis)
   - Sem imports de Laravel/Eloquent (exceto `Illuminate\Support\Str` para UUID)

2. **Domain/ValueObjects** — criar um arquivo por conceito em `app/Domain/{Dominio}/ValueObjects/`
   - Imutavel (sem setters publicos)
   - Validacao no construtor; lancam `InvalidArgumentException` se invalido
   - Metodo `getValue(): string` para extrair o valor primitivo

3. **Domain/Interfaces** — criar `app/Domain/{Dominio}/Interfaces/{Dominio}RepositoryInterface.php`
   - Metodos: `save`, `findById`, `findAll`, `paginate`, `update`, `delete` (conforme necessario)
   - Tipagem com a Entidade, nao com Model Eloquent

4. **Infrastructure/Models** — criar `app/Infrastructure/Persistence/Eloquent/Models/{Dominio}Model.php`
   - Extende `Illuminate\Database\Eloquent\Model`
   - `$table`, `$primaryKey = 'id'`, `$keyType = 'string'`, `$incrementing = false`
   - `$fillable` com todos os campos

5. **Migration** — `php artisan make:migration create_{tabela}_table`
   - UUID como PK: `$table->uuid('id')->default(DB::raw('(UUID())'))->primary()`
   - `created_user_id` e `updated_user_id` como foreign keys para `users`
   - Movimentos/logs: apenas `created_at` (imutaveis)

6. **Infrastructure/Repositories** — criar `app/Infrastructure/Persistence/Eloquent/Repositories/{Dominio}RepositoryEloquent.php`
   - Implementa a interface do Domain
   - Metodo `toEntity(Model): Entidade` privado para conversao isolada
   - Nao expoe modelos Eloquent para fora da camada
   - Usa `Auth::id()` para `created_user_id` / `updated_user_id`

7. **Application/DTOs** — um DTO por operacao em `app/Application/{Dominio}/DTOs/`
   - `Create{Dominio}DTO`, `Update{Dominio}DTO`, `Show{Dominio}DTO`, `List{Dominio}DTO`
   - Somente campos necessarios para aquela operacao
   - Campos opcionais tipados como `?tipo`

8. **Application/UseCases** — um UseCase por operacao em `app/Application/{Dominio}/UseCases/`
   - Recebe interfaces de repositorio via construtor (nunca classes concretas)
   - Metodo `execute(DTO): mixed`
   - Sem acesso direto ao banco
   - Validacoes de negocio ficam aqui (unicidade, regras de exclusao, etc.)

9. **Presentation/Requests** — um Request por operacao em `app/Presentation/Http/Requests/`
   - Extende `Illuminate\Foundation\Http\FormRequest`
   - `authorize(): true`
   - Regras de validacao em `rules()`
   - Usar `prepareForValidation()` para injetar parametros de rota (ex: `id`)

10. **Presentation/Controller** — `app/Presentation/Http/Controllers/{Dominio}Controller.php`
    - Injeta UseCases no construtor (ou via parametro do metodo para resolucao automatica do Laravel)
    - Monta DTO a partir do Request validado
    - Retorna `response()->json()` com campos explicitamente mapeados (nunca serializa entidade diretamente)
    - Status codes: 200 (leitura/update), 201 (criacao), 204 (delete sem body)

11. **RepositoryServiceProvider** — adicionar binding em `app/Providers/RepositoryServiceProvider.php`:
    ```php
    $this->app->bind({Dominio}RepositoryInterface::class, {Dominio}RepositoryEloquent::class);
    ```

12. **Rotas** — registrar em `routes/api.php` dentro do grupo `auth:api`
    ```php
    Route::group(['middleware' => 'auth:api', 'prefix' => '{dominio}'], function () {
        Route::get('/', [{Dominio}Controller::class, 'list']);
        Route::post('/', [{Dominio}Controller::class, 'store']);
        Route::get('/{id}', [{Dominio}Controller::class, 'show']);
        Route::put('/{id}', [{Dominio}Controller::class, 'update']);
        Route::delete('/{id}', [{Dominio}Controller::class, 'destroy']);
    });
    ```

13. **[OBRIGATORIO] openapi.yaml** — documentar TODOS os novos endpoints antes de encerrar:
    - Nova tag em `tags:` com nome e descricao do dominio
    - Novo path para cada rota (GET, POST, PUT, DELETE)
    - `requestBody` com schema e example para rotas com body
    - `responses` cobrindo 200/201, 400/422 (validacao), 401, 404
    - Novos schemas em `components/schemas` para request e response
    - Referenciar schemas existentes (`ErroValidacao`, `NaoAutenticado`) onde aplicavel
    - Verificar no Swagger UI (`http://localhost:8082`) se renderiza corretamente

---

## add-usecase

Adiciona um novo UseCase a um dominio existente.

**Quando usar:** nova operacao de negocio em dominio ja existente.

**Checklist:**
- [ ] Criar DTO em `app/Application/{Dominio}/DTOs/{Acao}{Dominio}DTO.php`
- [ ] Criar UseCase em `app/Application/{Dominio}/UseCases/{Acao}{Dominio}UseCase.php`
- [ ] Adicionar metodo na interface `{Dominio}RepositoryInterface.php` (se necessario)
- [ ] Implementar metodo no repositorio Eloquent
- [ ] Criar Request em `app/Presentation/Http/Requests/{Acao}{Dominio}Request.php`
- [ ] Adicionar metodo no Controller
- [ ] Registrar rota em `routes/api.php`
- [ ] **[OBRIGATORIO]** Documentar o novo endpoint em `openapi.yaml`

---

## add-value-object

Adiciona um Value Object a um dominio existente.

**Quando usar:** quando um campo precisa de validacao especifica de dominio (ex: CPF, placa, CEP, codigo de produto).

**Padrao:**
```php
namespace App\Domain\{Dominio}\ValueObjects;

use InvalidArgumentException;

class {NomeConceito}
{
    private string $value;

    public function __construct(string $value)
    {
        if (!$this->isValid($value)) {
            throw new InvalidArgumentException("Valor invalido para {NomeConceito}: {$value}");
        }
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    private function isValid(string $value): bool
    {
        // logica de validacao
    }
}
```

---

## run-tests

Executa a suite de testes do projeto.

```bash
# Todos os testes
composer run test

# Apenas testes unitarios
php artisan test --testsuite=Unit

# Apenas testes de feature
php artisan test --testsuite=Feature

# Teste especifico
php artisan test tests/Unit/Domain/Peca/Entities/PecaTest.php

# Com coverage (requer Xdebug)
php artisan test --coverage
```

**Estrutura esperada de testes:**
- `tests/Unit/Domain/{Dominio}/Entities/` — testa entidades isoladamente (sem banco, sem HTTP)
- `tests/Unit/Domain/{Dominio}/ValueObjects/` — testa validacoes e casos invalidos dos value objects
- `tests/Unit/Application/{Dominio}/UseCases/` — testa casos de uso com repositorio mockado (Mockery)
- `tests/Feature/{Dominio}/` — testa endpoints HTTP completos com banco real

---

## docker-ops

Operacoes comuns com Docker no projeto.

```bash
# Subir ambiente
docker compose up -d

# Parar ambiente
docker compose down

# Ver logs
docker compose logs -f app-php

# Entrar no container
docker compose exec app-php bash

# Rodar artisan dentro do container
docker compose exec app-php php artisan migrate

# Recriar containers (sem perder dados)
docker compose down && docker compose up -d --build

# Recriar containers E apagar dados (MySQL volume)
docker compose down -v && docker compose up -d
```

> **ATENCAO:** `docker compose down -v` apaga o volume do MySQL (todos os dados).
> Usar apenas quando necessario recriar o banco do zero (ex: senha errada no .env).

**Servicos:**
| Servico     | Porta local | Descricao                  |
|-------------|-------------|----------------------------|
| app-php     | 8080        | Laravel API                |
| db          | 3306        | MySQL 8.0                  |
| swagger-ui  | 8082        | Documentacao OpenAPI       |

**Configuracao do banco (.env):**
- `DB_PASSWORD` nao pode ser vazio — MySQL 8.0 rejeita autenticacao sem senha para usuarios nao-root
- Se alterar `DB_PASSWORD`, recriar o volume: `docker compose down -v && docker compose up -d`

---

## format-code

Formata o codigo PHP usando Laravel Pint.

```bash
# Formatar tudo
./vendor/bin/pint

# Verificar sem alterar (dry-run)
./vendor/bin/pint --test

# Formatar arquivo especifico
./vendor/bin/pint app/Domain/Peca/Entities/Peca.php
```

---

## check-ddd

Valida se uma implementacao respeita a arquitetura DDD do projeto.

**Violacoes comuns a verificar:**

- [ ] Domain importa `Illuminate\*` (exceto `Illuminate\Support\Str` para UUID) ou qualquer outro namespace de framework
- [ ] Application acessa `Model::create()`, `DB::*` ou qualquer Eloquent diretamente
- [ ] Controller acessa repositorio diretamente (sem UseCase)
- [ ] Controller serializa entidade diretamente em `response()->json($entidade)` — sempre mapear campos explicitamente
- [ ] Infrastructure retorna objetos Eloquent para camadas superiores (deve converter com `toEntity()`)
- [ ] UseCase depende de classe concreta em vez de interface
- [ ] UUID gerado fora da entidade (ex: no controller, migration ou repositorio)
- [ ] Nova interface nao registrada em `RepositoryServiceProvider`
- [ ] Nova rota registrada sem documentacao correspondente em `openapi.yaml`
- [ ] Try-catch em UseCase ou Controller — o handler global em `bootstrap/app.php` ja trata `DomainException` e `InvalidArgumentException` como 422; nunca capturar essas excecoes nas camadas de aplicacao ou apresentacao

---

## api-docs

Atualiza a documentacao OpenAPI.

**Arquivo:** `openapi.yaml` na raiz do projeto
**Visualizacao:** `http://localhost:8082`

**Esta skill e OBRIGATORIA sempre que uma nova rota for adicionada.**

### Regra YAML — uso do caractere `:`

Em YAML, `:` seguido de espaco (`: `) e interpretado como separador de chave-valor.
**Nunca** use `:` dentro de um valor de texto sem protege-lo. Regras:

| Situacao | Correto | Errado |
|---|---|---|
| Texto simples sem `:` | `description: Texto livre` | — |
| Texto com `:` no conteudo | `description: "Exemplo: valor"` | `description: Exemplo: valor` |
| Texto longo com `:` | bloco `|-` com indentacao | sem aspas |
| Chave de objeto | `reason: Purchase NF 123` | — |

**Boas praticas para o `openapi.yaml`:**
- Use aspas duplas `"..."` em qualquer valor `example:`, `description:` ou `message:` que contenha `:`
- Prefira blocos `|-` para descricoes longas que possam ter `:` no meio
- Nunca use `:` como efeito decorativo/visual em valores de texto (ex: `ex:`, `obs:`, `nota:`)
- Apos editar, reiniciar o swagger-ui: `docker compose restart swagger-ui`

**Ao adicionar novo endpoint, verificar cada item:**
1. Nova tag em `tags:` (se for dominio novo)
2. Novo `path` com todos os metodos HTTP da rota
3. `operationId` unico no formato `{dominio}-{acao}` (ex: `peca-create`, `estoque-entrada`)
4. `security: [{bearerAuth: []}]` em todas as rotas protegidas
5. `parameters` para path params (`{id}`) e query params (`page`, `perPage`, filtros)
6. `requestBody` com `schema.$ref` + `example` para POST e PUT
7. `responses` cobrindo obrigatoriamente: sucesso (200/201), validacao (422), nao autenticado (401), nao encontrado (404 quando aplicavel)
8. Schemas em `components/schemas` para cada request e response body
9. Reutilizar `$ref: '#/components/responses/NaoAutenticado'` e `$ref: '#/components/schemas/ErroValidacao'`
10. Testar no Swagger UI (`http://localhost:8082`) antes de considerar concluido

**Exemplo de path completo:**
```yaml
/peca/{id}:
  get:
    tags: [Pecas e Insumos]
    summary: Obter detalhes da peca
    operationId: peca-show
    security:
      - bearerAuth: []
    parameters:
      - name: id
        in: path
        required: true
        schema:
          type: string
          format: uuid
    responses:
      '200':
        description: Detalhes retornados com sucesso
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/PecaDetalhe'
      '401':
        $ref: '#/components/responses/NaoAutenticado'
      '404':
        description: Peca nao encontrada
```
