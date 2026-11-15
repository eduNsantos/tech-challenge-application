# language: pt
Funcionalidade: Saga da ordem de serviço entre os microsserviços
  Como oficina mecânica
  Quero que a O.S. avance entre application, payment-service e services-ms
  Para que orçamento, pagamento e execução fiquem consistentes, com compensação quando algo falha

  O application coordena a O.S. por eventos (coreografia):
  - pede a cobrança ao payment-service e recebe o link e o resultado por callback;
  - publica ServiceOrderCreated/ServiceOrderStatusChanged para o services-ms (outbox);
  - recebe ServiceOrderExecutionFinished do services-ms (fila FIFO service-order-execution).
  Os serviços externos são simulados: o FakePaymentGateway registra os pedidos de cobrança
  e as respostas do payment-service e do services-ms são enviadas como chegariam de verdade.

  Contexto:
    Dado que a oficina tem um atendente e um mecânico cadastrados
    E o cliente "Cliente BDD" com CPF "52998224725"
    E o veículo de placa "BDD1A23"
    E o serviço "Troca de óleo" custando R$ 5,00
    E a peça "Filtro de óleo" custando R$ 0,50 com 10 unidades em estoque
    E a integração com o services-ms está habilitada

  @caminho-feliz
  Cenário: Pagamento aprovado leva a O.S. da abertura até a entrega
    Quando o atendente abre uma O.S. com 1 "Troca de óleo" e 2 "Filtro de óleo"
    Então a O.S. deve estar com status "recebida"
    E o mecânico deve ser notificado com o assunto "Nova ordem de serviço aberta"
    E o services-ms deve receber o evento "ServiceOrderCreated"
    Quando o mecânico envia o orçamento para aprovação
    Então a O.S. deve estar com status "aguardando_aprovacao"
    E o payment-service deve receber uma cobrança de 600 centavos
    Quando o payment-service devolve o link de pagamento
    Então o cliente deve ser notificado com o assunto "Orçamento da sua ordem de serviço disponível"
    Quando o payment-service informa o resultado "PAID" do pagamento
    Então a O.S. deve estar com status "em_execucao"
    E o estoque da peça "Filtro de óleo" deve ser 8
    E o services-ms deve receber o evento "ServiceOrderStatusChanged" com status "em_execucao"
    Quando o services-ms informa o início da execução
    Então a O.S. deve estar com status "em_execucao"
    Quando o services-ms informa o fim da execução
    Então a O.S. deve estar com status "finalizada"
    E o cliente deve ser notificado com o assunto "Sua ordem de serviço foi concluída"
    E o services-ms deve receber o evento "ServiceOrderStatusChanged" com status "finalizada"
    Quando o atendente registra a entrega do veículo
    Então a O.S. deve estar com status "entregue"

  @compensacao
  Esquema do Cenário: Pagamento não concluído compensa a aprovação e devolve a O.S. ao diagnóstico
    Dado uma O.S. aguardando pagamento com 1 "Troca de óleo" e 2 "Filtro de óleo"
    Quando o payment-service informa o resultado "<resultado>" do pagamento
    Então a O.S. deve estar com status "em_diagnostico"
    E o estoque da peça "Filtro de óleo" deve ser 10
    E nenhuma baixa de estoque deve ter sido registrada
    E o services-ms deve receber o evento "ServiceOrderStatusChanged" com status "em_diagnostico"
    Quando o payment-service reenvia o mesmo resultado "<resultado>"
    Então a requisição deve falhar com status 422
    E a O.S. deve estar com status "em_diagnostico"

    Exemplos:
      | resultado |
      | REJECTED  |
      | EXPIRED   |

  @compensacao
  Cenário: Novo orçamento após a recusa abre uma nova cobrança e pode ser pago
    Dado uma O.S. aguardando pagamento com 1 "Troca de óleo" e 2 "Filtro de óleo"
    E o payment-service informa o resultado "REJECTED" do pagamento
    Quando o mecânico envia o orçamento para aprovação
    Então o payment-service deve ter recebido 2 cobranças com identificadores diferentes
    Quando o payment-service devolve o link de pagamento
    E o payment-service informa o resultado "PAID" do pagamento
    Então a O.S. deve estar com status "em_execucao"
    E o estoque da peça "Filtro de óleo" deve ser 8

  @rollback
  Cenário: Falha no payment-service desfaz a abertura da O.S.
    Dado que o payment-service está indisponível
    Quando o atendente abre uma O.S. com 1 "Troca de óleo" e 2 "Filtro de óleo" já enviando o orçamento
    Então a requisição deve falhar com status 502
    E nenhuma ordem de serviço deve ter sido gravada
    E nenhum evento deve ter sido publicado para o services-ms

  @rollback
  Cenário: Estoque insuficiente impede gerar a cobrança
    Quando o atendente abre uma O.S. com 1 "Troca de óleo" e 20 "Filtro de óleo" já enviando o orçamento
    Então a requisição deve falhar com status 422
    E a resposta deve conter a mensagem "Estoque insuficiente"
    E nenhuma cobrança deve ter sido enviada ao payment-service
    E nenhuma ordem de serviço deve ter sido gravada

  @idempotencia
  Cenário: Mensagem duplicada do services-ms é processada uma única vez
    Dado uma O.S. em execução com 1 "Troca de óleo" e 2 "Filtro de óleo"
    Quando o services-ms informa o fim da execução
    E o services-ms reenvia a mesma mensagem de fim da execução
    Então a O.S. deve estar com status "finalizada"
    E o cliente deve ter recebido 1 notificação com o assunto "Sua ordem de serviço foi concluída"

  @idempotencia
  Cenário: Fim de execução calculado sobre um status antigo é ignorado
    Dado uma O.S. em execução com 1 "Troca de óleo" e 2 "Filtro de óleo"
    E o atendente volta a O.S. para "em_diagnostico"
    Quando o services-ms informa o fim da execução com a sequência anterior
    Então a O.S. deve estar com status "em_diagnostico"
