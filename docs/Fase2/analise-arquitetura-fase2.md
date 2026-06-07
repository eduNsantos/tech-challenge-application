# Análise de Arquitetura — Fase 2 Tech Challenge

**Projeto:** POS Tech — Sistema de Gestão de Ordens de Serviço  
**Data:** 2026-06-02  
**Fase:** 2 — Qualidade, Resiliência e Escalabilidade

---

## O que a Fase 2 exige

O desafio pede evolução em três eixos:

### 1. Evolução do código
- Refatorar para **Clean Architecture** ou **Arquitetura Hexagonal**
- Clean Code (nomes claros, coesão, simplicidade)
- Testes automatizados cobrindo os fluxos críticos

### 2. Novas / alteradas APIs

| Endpoint | Descrição |
|---|---|
| Abertura de OS | Receber cliente, veículo, serviços e peças em uma única chamada, retornando o ID único da OS |
| Consulta de status | Informar a situação atual (Recebida, Diagnóstico, Aguardando Aprovação, Execução, Finalizada, Entregue) |
| Aprovação de orçamento | Endpoint para notificações externas de aprovação ou recusa do cliente |
| Listagem de OS | Ordenação: `Em Execução > Aguardando Aprovação > Diagnóstico > Recebida`, mais antigas primeiro, sem finalizadas/entregues |
| Atualização de status | Via e-mail (notificação automática na mudança de status) |

### 3. Infraestrutura completa

- **Docker:** Dockerfile e docker-compose revisados
- **Kubernetes (K8s):** manifestos YAML — Deployments, Services, ConfigMaps, Secrets, HPA
- **Terraform (IaC):** provisionamento do cluster K8s + banco de dados
- **CI/CD pipeline:** build → testes → Docker push → deploy K8s + aplicação dos manifestos

---

## Estado atual do projeto (Fase 1)

O projeto já implementa uma arquitetura DDD com quatro camadas bem definidas:

```
app/
├── Domain/           # Entidades, Value Objects, interfaces (zero dependência de framework)
├── Application/      # UseCases + DTOs (83 arquivos)
├── Infrastructure/   # Eloquent Models + Repositories (21 arquivos)
└── Presentation/     # Controllers + Form Requests (34 arquivos)
```

**O que já está correto:**
- Inversão de dependência via interfaces no Domain
- Exception handling global — sem try/catch nos controllers
- UUIDs gerados nas entidades
- Eventos de domínio presentes
- 64 arquivos de teste (Unit + Feature)
- Docker Compose funcionando
- OpenAPI spec completa (2651 linhas)

---

## Arquitetura A — Clean Architecture (evolução do atual)

### Conceito

Clean Architecture organiza o sistema em camadas concêntricas onde as dependências sempre apontam para dentro: Presentation → Application → Domain. A regra de ouro é que o Domain nunca conhece nada das camadas externas.

O projeto atual já segue este modelo.

### Estrutura proposta (com melhorias)

```
app/
├── Domain/
│   └── {Domínio}/
│       ├── Entities/         # Regras de negócio puras
│       ├── ValueObjects/     # Validação imutável
│       └── Interfaces/       # Contratos de repositório (Output Ports)
│
├── Application/
│   └── {Domínio}/
│       ├── UseCases/         # Orquestração — um arquivo por operação
│       └── DTOs/             # Transporte de dados entre camadas
│
├── Infrastructure/
│   ├── Persistence/Eloquent/ # Models + Repositories Eloquent
│   └── Notifications/        # Adapter de e-mail e push
│
└── Presentation/
    └── Http/
        ├── Controllers/      # Recebe HTTP, chama UseCase, retorna JSON
        └── Requests/         # Validação de entrada
```

### Adaptações necessárias para a Fase 2

#### Código (ajustes pontuais)

| Item | Esforço | Detalhe |
|---|---|---|
| ~~Corrigir namespace `Customer/interfaces/`~~ | ✅ | Binding `ServiceOrderItem` corrigido de string literal para `::class` em `RepositoryServiceProvider` |
| Soft delete nas OS | Baixo | Coluna `deleted_at` — listagem exclui finalizadas/entregues logicamente |
| Listagem de OS com ordenação exigida | Baixo | Ajuste no repositório com `CASE WHEN` por status + `ORDER BY created_at ASC` |
| Endpoint de aprovação externo | Baixo | `ApproveServiceOrderByTokenUseCase` já existe — adequar payload e resposta |
| Notificação de status via e-mail | Médio | Implementar `EmailNotificationAdapter` em `Infrastructure/Notifications/` usando Laravel Mail |
| ~~Abertura de OS unificada~~ | ✅ | Já implementado. Adicionado `exists:vehicles,id` na request e check de existência do veículo no UseCase |
| Cobertura de testes nos fluxos críticos | Médio | Feature tests para listagem ordenada, aprovação e notificação por e-mail |
| Remover duplicidade Sanctum + JWT | Baixo | Remover `laravel/sanctum`, manter apenas `php-open-source-saver/jwt-auth` |

#### Infraestrutura (nova — sem impacto no código da aplicação)

```
/k8s/
├── deployment.yaml          # Pod da aplicação PHP
├── service.yaml             # Exposição de porta
├── configmap.yaml           # Variáveis não sensíveis (APP_ENV, DB_HOST)
├── secret.yaml              # Variáveis sensíveis (DB_PASSWORD, JWT_SECRET)
└── hpa.yaml                 # Horizontal Pod Autoscaler (CPU/memória)

/infra/
├── main.tf                  # Cluster Kubernetes (local: kind / cloud: EKS ou GKE)
├── database.tf              # MySQL gerenciado (RDS ou Cloud SQL)
├── variables.tf
└── outputs.tf

.github/workflows/
└── ci-cd.yml                # Build → Testes → Docker push → kubectl apply
```

### Pontos fortes

- Esforço de refatoração mínimo — base sólida já existe e funciona
- Time concentra energia na infraestrutura, que é o maior peso da Fase 2
- Risco de regressão muito baixo
- Estrutura familiar para toda a equipe
- Nenhum teste existente precisa ser reescrito

### Pontos fracos

- A estrutura DDD vive dentro do namespace `App\` do Laravel, o que não é o mais "puro" em Clean Architecture estrita, mas atende plenamente os requisitos do desafio

---

## Arquitetura B — Arquitetura Hexagonal (Ports & Adapters)

### Conceito

A Arquitetura Hexagonal (Alistair Cockburn, 2005) organiza o sistema em torno de um **núcleo de domínio** isolado, conectado ao mundo externo por meio de **Ports** (interfaces) e **Adapters** (implementações). O núcleo não sabe nada sobre HTTP, banco de dados ou e-mail — ele só conhece ports.

```
           ┌─────────────────────────────────┐
           │           ADAPTERS              │
           │  Inbound          Outbound      │
           │  (HTTP, CLI)      (DB, Email)   │
           │        │               ▲        │
           │        ▼               │        │
           │  ┌─────────────────────────┐    │
           │  │    NÚCLEO DE DOMÍNIO    │    │
           │  │                         │    │
           │  │  Input Ports            │    │
           │  │  (interfaces UseCase)   │    │
           │  │                         │    │
           │  │  Domain (Entities/VOs)  │    │
           │  │                         │    │
           │  │  Output Ports           │    │
           │  │  (interfaces Repos)     │    │
           │  └─────────────────────────┘    │
           └─────────────────────────────────┘
```

### Estrutura proposta

```
app/
├── Core/                              # Núcleo — zero dependências externas
│   ├── Domain/
│   │   └── {Domínio}/
│   │       ├── Entities/
│   │       └── ValueObjects/
│   └── Application/
│       └── {Domínio}/
│           ├── Ports/
│           │   ├── Input/             # Interfaces dos UseCases (Input Ports)
│           │   └── Output/            # Interfaces dos repositórios (Output Ports)
│           ├── UseCases/
│           └── DTOs/
│
└── Adapters/
    ├── Inbound/                       # Driving adapters
    │   └── Http/
    │       ├── Controllers/
    │       └── Requests/
    └── Outbound/                      # Driven adapters
        ├── Persistence/
        │   ├── Models/
        │   └── Repositories/
        └── Notifications/
            ├── Email/
            └── Push/
```

### Diferença prática em relação ao projeto atual

| Camada atual (Clean/DDD) | Equivalente Hexagonal |
|---|---|
| `app/Domain/{X}/Interfaces/` | `app/Core/Application/{X}/Ports/Output/` |
| `app/Application/{X}/UseCases/` | `app/Core/Application/{X}/UseCases/` + Input Ports explícitos |
| `app/Presentation/Http/` | `app/Adapters/Inbound/Http/` |
| `app/Infrastructure/Persistence/` | `app/Adapters/Outbound/Persistence/` |
| `app/Infrastructure/Notifications/` | `app/Adapters/Outbound/Notifications/` |

A diferença **funcional** é zero. A diferença é de organização e nomenclatura.

### Adaptações necessárias para a Fase 2

#### Código (refatoração estrutural significativa)

| Item | Esforço | Detalhe |
|---|---|---|
| Reorganizar toda a estrutura de pastas | Alto | Mover 170+ arquivos para nova hierarquia |
| Criar Input Ports (interfaces de UseCase) | Médio | Cada UseCase precisa de uma interface correspondente |
| Separar Output Ports explicitamente | Médio | Mover interfaces de repositório para `Application/Ports/Output/` |
| Atualizar todos os namespaces PHP | Alto | 170+ arquivos com `namespace` a alterar |
| Atualizar `RepositoryServiceProvider.php` | Médio | Referenciar todos os novos namespaces |
| Atualizar todos os `use` statements | Alto | Em todos os UseCases, Controllers, Requests e testes |
| Reescrever / atualizar todos os testes existentes | Alto | Namespaces dos testes também mudam |
| Demais adaptações funcionais da Fase 2 | Igual ao Clean | Mesmo esforço listado na Opção A |

#### Infraestrutura

Idêntica à Opção A — a escolha de arquitetura de código não afeta K8s, Terraform ou CI/CD.

### Pontos fortes

- Ports & Adapters explícitos tornam a substituição de adapters mais óbvia (ex: trocar Eloquent por Doctrine)
- Nomenclatura alinhada com a literatura canônica da Hexagonal
- Demonstra o conceito de forma mais explícita ao avaliador

### Pontos fracos

- Refatoração massiva com alto risco de regressão em 170+ arquivos
- Nenhum ganho funcional em relação ao que já existe
- Consome o tempo que deveria ir para K8s, Terraform e CI/CD
- A diferença em relação ao atual é de "etiqueta", não de princípio arquitetural

---

## Comparativo direto

| Critério | Clean Architecture (atual) | Hexagonal |
|---|---|---|
| **Atende os requisitos da Fase 2** | Sim | Sim |
| **Separação de camadas** | Sim | Sim |
| **Inversão de dependência** | Sim | Sim |
| **Esforço de refatoração** | Baixo | Alto |
| **Risco de regressão** | Baixo | Alto |
| **Testes existentes preservados** | Sim | Reescrita necessária |
| **Tempo disponível para infraestrutura** | Máximo | Reduzido |
| **Familiaridade da equipe** | Alta | Requer curva de aprendizado |
| **Diferencial técnico ao avaliador** | Já demonstrado | Mais explícito na nomenclatura |

---

## Conclusão e Recomendação

**Recomendação: manter e evoluir a Clean Architecture atual.**

### Justificativa

O projeto já implementa corretamente todos os princípios que a Fase 2 exige: separação de camadas, inversão de dependência, domínio isolado de framework, repositórios abstraídos por interfaces e exception handling centralizado. Migrar para Hexagonal significaria renomear pastas e alterar 170+ namespaces sem ganhar nenhuma capacidade funcional nova.

O peso real da Fase 2 está na **infraestrutura** — K8s, Terraform e CI/CD precisam ser construídos do zero e representam o maior volume de trabalho. Desperdiçar esse tempo com refatoração estrutural que não agrega valor funcional seria um erro estratégico.

### Plano de execução sugerido

```
Semana 1 — Ajustes de código
  ├── [x] Corrigir namespaces inconsistentes
  ├── [x] Endpoint de abertura de OS unificado
  ├── [x] Listagem de OS com ordenação correta + soft delete
  ├── Notificação por e-mail na mudança de status
  └── Testes dos fluxos críticos

Semana 2 — Kubernetes
  ├── deployment.yaml + service.yaml
  ├── configmap.yaml + secret.yaml
  └── hpa.yaml (CPU/memória)

Semana 3 — Terraform + CI/CD
  ├── Scripts IaC para cluster K8s e banco
  └── Pipeline GitHub Actions completa

Semana 4 — Documentação e entrega
  ├── README.md atualizado com diagrama de arquitetura
  ├── Vídeo demonstrativo (até 15 minutos)
  └── PDF de entrega para o portal
```

### Diagrama da arquitetura final

```
                        ┌─────────────────────────────────┐
   GitHub Actions       │         Kubernetes Cluster       │
   ─────────────        │                                  │
   push → main          │  ┌──────────┐   ┌────────────┐  │
       │                │  │  PHP App │   │   MySQL    │  │
       ▼                │  │  Pod(s)  │◄──│   Pod      │  │
   1. Run tests         │  │  :8080   │   │   :3306    │  │
   2. Build Docker      │  └────┬─────┘   └────────────┘  │
   3. Push registry     │       │HPA (CPU)                 │
   4. kubectl apply     │  ┌────▼─────┐                   │
                        │  │ Service  │◄── Ingress         │
                        │  │ (LB)     │                    │
                        │  └──────────┘                   │
                        └─────────────────────────────────┘
                                    ▲
                              Terraform IaC
                        (provisionamento do cluster)
```

A Clean Architecture já entregue é evidência suficiente de domínio do conceito. O avaliador verá separação de camadas, inversão de dependência e testabilidade — sem precisar de uma renomeação estrutural que não agrega valor ao produto.
