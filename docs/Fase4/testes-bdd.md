# Testes BDD — fluxo completo da O.S. e SAGA de pagamento

Requisito da Fase 4 (*Testes e Qualidade*): **"pelo menos um fluxo completo testado com BDD"**.
Este documento descreve como o requisito foi atendido no `tech-challenge-application` e no
`tech-challenge-payment-service`, como rodar os testes e onde estão as evidências.

## O que é BDD

BDD (*Behavior-Driven Development*) descreve o comportamento do sistema em linguagem de negócio, num formato
que dev, PO e avaliador conseguem ler. Os cenários são escritos em **Gherkin** e cada frase é ligada a um
*step definition*, um trecho de código que executa a ação de verdade.

| Palavra-chave | Papel |
|---|---|
| `Funcionalidade` | O comportamento testado |
| `Contexto` | Pré-condições comuns a todos os cenários |
| `Cenário` / `Esquema do Cenário` + `Exemplos` | Um caso concreto, ou o mesmo caso com vários dados |
| `Dado` | Estado inicial |
| `Quando` | Ação |
| `Então` | Resultado esperado |
| `E` / `Mas` | Continuação do passo anterior |

A diferença para o teste unitário é o escopo: o unitário verifica uma classe isolada, enquanto o BDD verifica
um **fluxo de negócio de ponta a ponta**. Os testes unitários e de Feature continuam existindo; o BDD foi
**adicionado** a eles.

## Visão geral

| Repositório | Ferramenta | Feature | Cenários | Comando |
|---|---|---|---|---|
| tech-challenge-application | [Behat 4](https://docs.behat.org) (PHP) | `tests/Bdd/features/saga_ordem_de_servico.feature` | 8 (114 passos) | `vendor/bin/behat` |
| tech-challenge-payment-service | [Cucumber.js 13](https://github.com/cucumber/cucumber-js) (Node) | `src/features/saga_pagamento.feature` | 10 (109 passos) | `npm run test:bdd` |

Os dois usam Gherkin em português (`# language: pt`) e rodam no CI de cada repositório.

```
                       ┌──────────────── BDD do application ────────────────┐
 Atendente/Mecânico ──▶ application ──(POST /orders)──▶ payment-service ──▶ Mercado Pago
                        │    ▲   ▲                         │
                        │    │   └── link / resultado ─────┘  ◀── BDD do payment-service (SAGA Step Functions)
                        │    └────── ServiceOrderExecutionFinished (SQS FIFO)
                        └─outbox──▶ services-ms (ServiceOrderCreated / StatusChanged)
```

- **application:** testa a O.S. inteira. Os outros microsserviços entram como simulações: o payment-service é
  o `FakePaymentGateway` e as respostas dele chegam pelos endpoints reais (`/api/payment/notification` e
  `/api/payment/callback/{token}`). O services-ms é simulado lendo a `outbox` e entregando mensagens à Lambda
  `ServiceOrderExecutionHandler`, como a fila FIFO faria.
- **payment-service:** testa a SAGA orquestrada. Os cenários executam a **definição real** da máquina de estados
  (`infra/state-machine/payment-saga.asl.json`, a mesma que o Terraform publica) com os handlers reais das
  Lambdas. DynamoDB, SQS, Step Functions, Secrets Manager, Mercado Pago e o application são fakes em memória.

Nenhum dos dois acessa a AWS ou o Mercado Pago. Os testes são determinísticos e rodam em menos de 1 segundo.

---

## tech-challenge-application (Behat)

### Estrutura

```
behat.php                                        # configuração (suite saga_ordem_de_servico)
tests/Bdd/
  features/saga_ordem_de_servico.feature         # cenários em Gherkin
  Context/ServiceOrderSagaContext.php            # step definitions
  Support/LaravelHarness.php                     # reaproveita o Tests\TestCase do PHPUnit dentro do Behat
  Support/RecordingNotificationService.php       # evita envio real de e-mail
```

O `LaravelHarness` estende o `Tests\TestCase` usado pelos testes de Feature. Assim, cada cenário sobe uma
aplicação nova com SQLite em memória (`RefreshDatabase`), o `FakePaymentGateway` e os helpers HTTP do Laravel
(`postJson`, `actingAs`, `assertDatabaseHas`). Os nomes dos arquivos não terminam em `Test.php`, então o PHPUnit
não os executa.

### Cenários

| Tag | Cenário | O que comprova |
|---|---|---|
| `@caminho-feliz` | Pagamento aprovado leva a O.S. da abertura até a entrega | `recebida → aguardando_aprovacao → em_execucao → finalizada → entregue`; cobrança de 600 centavos; notificações; baixa de estoque (10 → 8); eventos `ServiceOrderCreated` e `ServiceOrderStatusChanged` na outbox; `ExecutionStarted` não muda o status |
| `@compensacao` | Pagamento não concluído (`REJECTED` / `EXPIRED`) | Compensação: O.S. volta para `em_diagnostico`, estoque intacto, evento publicado; callback repetido → 422 |
| `@compensacao` | Novo orçamento após a recusa | Nova cobrança com outro `order_id`, que pode ser paga normalmente |
| `@rollback` | Falha no payment-service | 502 e **rollback**: nenhuma O.S. e nenhum evento gravados |
| `@rollback` | Estoque insuficiente | 422, nenhuma cobrança enviada, nenhuma O.S. gravada |
| `@idempotencia` | Mensagem duplicada do services-ms | Processada uma única vez (`processed_messages`), uma só notificação |
| `@idempotencia` | Fim de execução com sequência antiga | Ignorado (controle por `status_sequence`) |

### Como rodar

```bash
# Docker (PHP 8.4, igual ao CI)
make bdd

# ou direto, com PHP ≥ 8.3 e composer install feito
vendor/bin/behat
vendor/bin/behat --tags @compensacao      # só uma tag
vendor/bin/behat --format progress        # saída compacta
```

O Behat não lê o `phpunit.xml`. As variáveis de ambiente de teste (SQLite em memória, `APP_ENV=testing`) são
definidas em `LaravelHarness::configureEnvironment()`.

---

## tech-challenge-payment-service (Cucumber.js)

### Estrutura

```
infra/state-machine/payment-saga.asl.json        # definição da SAGA (lida pelo Terraform via templatefile)
src/cucumber.js                                  # configuração
src/features/
  saga_pagamento.feature                         # cenários em Gherkin
  step_definitions/saga.steps.js                 # step definitions
  support/asl-interpreter.js                     # executa a ASL: Task, Pass, Choice, Wait, Succeed, Fail, Retry, Catch
  support/aws-fakes.js                           # DynamoDB, SQS, SFN, Secrets Manager, Mercado Pago e application em memória
  support/world.js                               # estado de cada cenário + hooks
```

Para que o teste use exatamente a máquina de estados publicada, a ASL saiu do HCL inline (`jsonencode`) para
`infra/state-machine/payment-saga.asl.json`. O `step-functions.tf` usa
`jsonencode(jsondecode(templatefile(...)))`, que gera **a mesma string** da versão anterior: foi comparado com
`terraform console`, sem diff no plan.

### Cenários

| Tag | Cenário | O que comprova |
|---|---|---|
| `@caminho-feliz` | Pagamento aprovado | Estados `Registrar → IniciarTentativas → GerarLink → NotificarEAguardar → PagamentoAprovado`; DynamoDB `PAID`; application recebe o link e o resultado `PAID` |
| `@compensacao` | Pagamento recusado | Saga falha com `PaymentRejected`; DynamoDB `REJECTED`; callback `REJECTED`; nada na DLQ |
| `@compensacao` | Prazo esgotado | `Cancelar` marca `EXPIRED`, avisa o application, envia para a DLQ e termina em `SagaFalhou` |
| — | Pagamento pendente (PIX/boleto) | Saga continua aguardando, sem resultado para o application |
| `@falha` | Documento inválido | Falha no `Registrar`; Mercado Pago não é chamado; nada gravado |
| `@retry` | Instabilidade curta do Mercado Pago | `Retry` do `GerarLink`: 3 chamadas, sem espera diária |
| `@retry` | Mercado Pago fora no 1º dia | Nova tentativa diária (86400 s) gera o link: 5 chamadas, 1 espera |
| `@retry @falha` | Mercado Pago fora em todas as tentativas | 16 chamadas (4 ciclos × 4), 3 esperas, pedido na DLQ com `MercadoPagoError` |
| `@idempotencia` | Pedido duplicado | `ExecutionAlreadyExists`: só uma saga, nenhuma mensagem devolvida à fila |
| `@seguranca` | Webhook com assinatura inválida | 401, saga continua aguardando |

### Como rodar

```bash
cd src
npm ci
npm run test:bdd
npx cucumber-js --tags @retry           # só uma tag
npx cucumber-js --format pretty         # cada passo na tela
```

---

## Saga de ponta a ponta: quem testa o quê

| Etapa da O.S. | application (Behat) | payment-service (Cucumber) |
|---|---|---|
| Abrir O.S. e enviar orçamento | ✔ API real, outbox, notificação ao mecânico | — |
| Pedir cobrança | ✔ payload enviado (valor, `callback_url`, `notification_url`) | ✔ `POST /orders` → SQS → trigger → Step Functions |
| Gerar link no Mercado Pago | — | ✔ incluindo Retry e nova tentativa diária |
| Devolver o link ao application | ✔ `POST /api/payment/notification` | ✔ `notify-wait` chama `notification_url` |
| Pagamento aprovado | ✔ O.S. `em_execucao`, baixa de estoque | ✔ webhook assinado → `SendTaskSuccess` → `PAID` |
| Pagamento recusado/expirado (compensação) | ✔ O.S. volta a `em_diagnostico` | ✔ `PaymentRejected` / `Cancelar` → `EXPIRED` → DLQ |
| Execução no services-ms | ✔ eventos de outbox e Lambda de execução | — |
| Finalização e entrega | ✔ `finalizada` → `entregue` | — |

Os contratos usados nas simulações são os mesmos nos dois lados: `order_id`, `status`, `payment_link`, `PAID`,
`REJECTED` e `EXPIRED`.

## CI

| Repositório | Workflow | Passo |
|---|---|---|
| application | `.github/workflows/laravel-ci.yml` | Depois do PHPUnit, no pod do Kind: `vendor/bin/behat --format=progress` |
| payment-service | `.github/workflows/deploy.yml` (job **Test**) | `npm test` + `npm run test:bdd` (Node 24) |

## Evidências

Saída das execuções completas, geradas em 15/11/2026:

- [`evidencias/behat-application.txt`](evidencias/behat-application.txt): 8 cenários, 114 passos, todos passando
- [`evidencias/cucumber-payment-service.txt`](evidencias/cucumber-payment-service.txt): 10 cenários, 109 passos, todos passando

Também verificamos que os cenários **falham quando a regra quebra**:
- No application, mudar `rejectQuote()` para não voltar a O.S. ao diagnóstico derruba os 2 exemplos de compensação.
- No payment-service, reduzir o `MaxAttempts` do `GerarLink` ou desviar o timeout para fora do `Cancelar` derruba os cenários correspondentes.

## Adicionando um cenário

1. Escreva o cenário no `.feature` reaproveitando as frases existentes.
2. Rode o teste. Frases novas aparecem como *undefined*, e o Behat e o Cucumber sugerem o esqueleto do step.
3. Implemente o step no contexto (`ServiceOrderSagaContext.php` ou `saga.steps.js`), sempre pela mesma porta
   de entrada usada em produção: endpoint HTTP, Lambda ou ASL.
4. Confira que o cenário falha quando a regra é quebrada.
