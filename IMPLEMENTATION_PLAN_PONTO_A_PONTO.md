# Plano de implementação — Inscrições em cursos do Armarinho Ponto a Ponto

**Data:** 26/09/2026  
**Destino:** implementação incremental pelo Codex  
**Situação:** planejamento; aplicação ainda não implementada  
**Objetivo imediato:** desenvolver e validar localmente. Publicação, contratação de serviços e cobranças reais ficam para uma etapa futura, explicitamente autorizada.

## 1. Objetivo e decisões consolidadas

Criar uma aplicação para divulgar cursos, receber inscrições pagas exclusivamente por Pix, confirmar pagamentos e administrar participantes. O público inclui pessoas idosas com diferentes níveis de familiaridade com celular e aplicativos bancários.

A aplicação deve reduzir erros, oferecer caminhos de recuperação e preservar os dados dos participantes. Nenhuma arquitetura garante ausência absoluta de falhas: os controles abaixo precisam ser implementados, testados e mantidos.

| Decisão | Definição |
| --- | --- |
| Backend e páginas | Laravel + Blade, em um único projeto |
| Estilização | CSS próprio, responsivo, organizado por componentes |
| Interatividade | JavaScript simples, como melhoria progressiva |
| Banco de dados | MySQL |
| Pagamentos | Mercado Pago; Pix com QR Code, Copia e Cola e webhook |
| Recebedor | Conta PF indicada pelo responsável; habilitação e dados reais a validar |
| Hospedagem futura | Hostinger Premium, contratação mensal; sem contratação nesta fase |
| Desenvolvimento | Local, com Docker Compose para PHP, servidor web e MySQL |
| Administração | Contas individuais de administrador, sem cadastro público |
| Alunos | Inscrição sem criar conta e sem senha |
| Planilha | XLSX com dados administrativos e lista de chamada |
| Fuso | America/Sao_Paulo na interface; persistência de instantes em UTC |

**Base técnica proposta:** Laravel 13 e PHP 8.3 ou superior compatível, após verificar ambiente e bibliotecas. A documentação consultada aponta PHP 8.3 como mínimo do Laravel 13. Fixar versões suportadas no projeto, registrar a decisão e não baixar para versões sem manutenção para acomodar a hospedagem. MySQL 8.x é a base local provisória; conferir a versão efetivamente fornecida pelo plano antes da homologação. [S1–S2]

**Não incluir no MVP:** React, API pública separada, microserviços, Redis, WebSockets, Kubernetes, construtor genérico de formulários, WhatsApp automático, marketplace, certificados de conclusão, recorrência, cartão ou boleto. Não exigir Node.js em produção. Ferramentas de teste podem usar Node localmente sem alterar essa arquitetura.

## 2. Escopo funcional e regras propostas

### 2.1 Página do curso

Preservar a sequência solicitada: **título → descrição → banner → formulário → pagamento**. Na descrição, apresentar data, horário, local, preço, conteúdo e materiais necessários, quando aplicáveis. Informações essenciais também devem existir como texto, mesmo quando estiverem no banner.

Campos fixos e obrigatórios:

| Campo | Tratamento |
| --- | --- |
| Nome completo | Aceitar acentos, apóstrofos e hífens; não impor uma quantidade arbitrária de sobrenomes |
| WhatsApp | Exibir `(XX) XXXXX-XXXX`; aceitar colagem com pontuação e `+55`, normalizando antes da validação |
| CPF | Aceitar com ou sem pontuação; validar dígitos verificadores e bloquear sequências repetidas |
| E-mail | Validar formato e limite; não obrigar a digitar duas vezes; usado pela integração e recuperação |

A máscara é uma ajuda, não um bloqueio ao preenchimento. CPF com dígitos válidos não comprova identidade; formato do telefone não comprova que o número possui WhatsApp. Não chamar APIs externas para validar identidade ou existência desses contatos no MVP.

O formulário deve indicar claramente que os dados são do **participante**, mesmo quando outra pessoa ajudar ou pagar. Não exigir que o titular da conta bancária pagadora seja o participante. O nome real do recebedor PF deve ser explicado antes do pagamento para evitar estranhamento no aplicativo do banco.

### 2.2 Regras de inscrição

Regras iniciais propostas, implementáveis sem interromper o desenvolvimento local:

- Uma inscrição por CPF por evento; o mesmo CPF pode participar de outros eventos.
- Uma inscrição pode possuir diversas tentativas históricas de pagamento, mas somente uma cobrança ativa ou de resultado desconhecido por vez.
- Somente o servidor determina preço, evento, janela de inscrição e situação do pagamento.
- Dados enviados criam inscrição pendente, nunca inscrição confirmada.
- Nome, contato e preço usados na cobrança ficam registrados como fotografia daquele momento. Alterações posteriores do evento não mudam cobranças anteriores.
- Inscrições são permitidas no intervalo `abertura <= agora < encerramento`, desde que o evento esteja publicado e não pausado/cancelado.
- Fechamento normal ou pausa impede novas cobranças, mas respeita cobranças já emitidas até sua expiração. Cancelamento do evento é uma ação diferente e exige tratamento dos pagamentos existentes.
- Prazo Pix inicial proposto: **60 minutos**, configurável apenas dentro dos limites da API. Calcular a expiração como o menor instante entre `agora + prazo configurado` e o encerramento das inscrições. A documentação consultada indica mínimo de 30 minutos; verificar novamente ao implementar. Se restar menos que o mínimo até o encerramento, não emitir nova cobrança. Mostrar “O prazo para novas inscrições terminou”. [S3]
- Uma nova tentativa exige cobrança anterior comprovadamente encerrada. Cronômetro do navegador nunca autoriza, sozinho, outra cobrança.
- Não oferecer curso gratuito pelo fluxo Pix: preço deve ser maior que zero. Cursos gratuitos seriam outra funcionalidade.
- Não disponibilizar botão administrativo “marcar como pago” no MVP. Permitir “Consultar pagamento”, que consulta o provedor.
- Não excluir eventos com histórico financeiro: arquivar. Correções em inscrições devem ser autorizadas e auditadas; CPF de inscrição paga não muda por edição simples.

**Limite de vagas:** não foi solicitado como requisito obrigatório. O MVP não deverá anunciar ou prometer controle de lotação. Se o responsável quiser esse limite, implementar a extensão da seção 11 antes de ativá-lo, nunca apenas um contador visual.

### 2.3 Administração

- Criar, editar, visualizar, duplicar e arquivar eventos.
- Alterar título, descrição, banner, texto alternativo, preço, local, data e horários.
- Configurar abertura e encerramento, publicar, pausar e cancelar, com mensagens claras sobre o efeito em cobranças existentes.
- Pré-visualizar o formulário antes de publicar.
- Duplicação cria um rascunho com novo identificador, sem copiar inscritos, pagamentos ou publicação automática.
- Listar inscrições com paginação, filtros por evento/situação/data e busca por nome; busca por CPF somente exata e protegida.
- Mostrar totais de confirmados, pendentes e exceções sem apresentar valores pendentes como receita recebida.
- Consultar o histórico de tentativas, sincronizar situação e identificar pagamentos que precisam de análise.
- Exportar XLSX e abrir lista de chamada em versão de impressão.
- Configurar nome público do organizador, nome esperado do recebedor e contato de ajuda. Nunca editar tokens do Mercado Pago por formulário comum.

## 3. Jornada e requisitos de UX para pessoas idosas

### 3.1 Jornada principal

1. **Conhecer o curso:** informações essenciais legíveis, preço visível e indicação “Pagamento por Pix”.
2. **Preencher os dados:** quatro campos, em uma coluna, com instruções próximas.
3. **Revisar:** resumo do participante, curso, preço e recebedor; botão “Corrigir meus dados” e ação “Gerar Pix de R$ XX,XX”. O resumo ainda não cria cobrança.
4. **Pagar:** QR Code e Copia e Cola; instruções para quem está no mesmo celular ou em outro aparelho.
5. **Confirmar:** mostrar confirmação somente após verificação pelo servidor, com protocolo, curso, local e data.

As etapas podem ser páginas Blade sequenciais com contexto persistido. Não fazer o aluno digitar os dados novamente ao voltar. O estado deve sobreviver ao retorno do aplicativo bancário no mesmo navegador, respeitando o prazo de retenção da sessão.

### 3.2 Especificação visual e de interação

Meta de acessibilidade: **WCAG 2.2 nível AA**, avaliada com testes automáticos e manuais. Aplicar os critérios de contraste, teclado, foco, identificação de erros, redimensionamento e mensagens de estado. Os valores ampliados abaixo são decisões de design para o projeto, não uma afirmação de que todos são mínimos normativos. [S6–S8]

- Texto principal com base de **18 px** em unidade relativa; entrelinha aproximada de 1,5; fontes familiares e sem adornos.
- Alvos de toque de pelo menos **48 × 48 CSS px**, com espaçamento que evite cliques acidentais.
- Contraste mínimo de 4,5:1 para texto comum, 3:1 para texto grande e componentes visuais aplicáveis.
- Coluna de leitura confortável; campos e ações principais ocupam a largura útil no celular.
- Layout funcional a 320 CSS px, texto ampliado a 200% e zoom/reflow a 400%, sem perda de ações ou rolagem horizontal no fluxo público.
- Rótulos sempre visíveis acima dos campos. Placeholder não substitui rótulo.
- “Nome completo (obrigatório)” em vez de depender exclusivamente de asterisco. Na interface, evitar blocos inteiros em maiúsculas.
- Foco claramente visível e nunca escondido por cabeçalho ou botão fixo. Ordem de tabulação natural, sem `tabindex` positivo.
- HTML semântico, `lang="pt-BR"`, título de página descritivo, um título principal e associação entre rótulo, ajuda e erro.
- Inputs adequados para telefone, e-mail e CPF; CPF como texto com teclado numérico, nunca como número, preservando zeros iniciais.
- Permitir colar, preencher automaticamente e usar gerenciador de senhas no administrador. Máscara não pode prender cursor ou impedir exclusão.
- Erros em texto, próximos ao campo e em resumo com links; foco no resumo após envio inválido. Não apagar outros campos.
- Não depender só de cor ou ícone; estados como “Aguardando pagamento” devem ser escritos.
- Evitar carrosséis, animações desnecessárias, pop-ups, contagem regressiva alarmista e CAPTCHA visual como primeira defesa.
- Uma ação principal por etapa; botões secundários claramente distintos. Sem botão “Limpar formulário”.
- Progresso visível durante envio. Travar repetição visual sem depender disso para impedir duplicidade no servidor.
- Respeitar redução de movimento. Não anunciar o cronômetro a cada segundo em leitor de tela; anunciar somente mudanças relevantes.
- Formulário funcional sem JavaScript para preenchimento e envio. No pagamento, oferecer atualização manual e seleção do código quando a cópia automática não funcionar.

### 3.3 Pix no mesmo celular

Mostrar instruções curtas e numeradas: copiar o código; abrir o banco; escolher Pix Copia e Cola; conferir valor e recebedor; pagar; voltar a esta página. O QR Code deve ser legível, sem logotipo sobreposto, com área branca ao redor e alternativa textual Copia e Cola.

O botão “Copiar código Pix” deve informar “Código copiado” somente quando a operação funcionar. Se o navegador negar acesso à área de transferência, oferecer seleção manual. Nunca copiar automaticamente sem ação da pessoa.

Oferecer “Já paguei — verificar pagamento”. Esse botão **consulta** o pagamento; não marca aprovação. Ao voltar do aplicativo bancário, atualizar o estado imediatamente. Expiração do código deve preservar os dados para que o aluno possa tentar novamente, quando permitido.

### 3.4 Mensagens previstas

| Situação | Texto de referência |
| --- | --- |
| CPF inválido | “Confira o CPF. Ele deve ter 11 números.” |
| Telefone incompleto | “Informe o DDD e o número do celular. Exemplo: (84) 99999-9999.” |
| Cobrança pendente | “Estamos aguardando seu pagamento. Sua inscrição será confirmada depois que o Pix for aprovado.” |
| Resultado desconhecido | “Estamos conferindo seu pagamento. Não faça outro Pix agora.” |
| Pagamento identificado | “Pagamento aprovado! Sua inscrição está confirmada.” |
| Código expirado | “Este código Pix expirou. Seus dados foram mantidos. Podemos verificar se há uma nova tentativa disponível.” |
| Falha ao consultar | “Não conseguimos atualizar agora. Sua inscrição foi mantida. Tente verificar novamente.” |
| Sessão de preenchimento expirada | “Por segurança, precisamos iniciar novamente. Se você já pagou, use a recuperação da inscrição ou fale com a loja.” |

Disponibilizar ajuda por WhatsApp/telefone com o contato real configurado pelo responsável. Não preencher um número inventado nem inserir CPF, e-mail ou código Pix na mensagem do link de WhatsApp.

### 3.5 Validação com usuários

Executar rodada inicial com cerca de cinco pessoas idosas, com diferentes níveis de familiaridade digital, sem presumir incapacidade. Usar dados fictícios e pagamento simulado; não gravar dados bancários ou senhas.

Tarefas: localizar preço/local, preencher, corrigir um erro, copiar Pix, simular ida ao banco, retornar e reconhecer a confirmação; testar também código expirado e pagamento ainda não confirmado.

Registrar conclusão sem ajuda, pedidos de ajuda, erros e entendimento de “pendente” versus “confirmado”. Meta inicial: ao menos quatro de cinco concluírem sem orientação, ninguém acreditar que está confirmado antes da aprovação e nenhuma barreira crítica de acessibilidade. Resultado é evidência qualitativa para corrigir o fluxo, não certificação estatística. Repetir tarefas afetadas após correções.

## 4. Arquitetura e organização do código

Monólito modular Laravel. Controllers pequenos coordenam requisições; regras ficam em Actions/Services; Form Requests validam entradas; Policies autorizam acesso; Eloquent e migrations persistem os dados; Blade apresenta as páginas.

| Área | Responsabilidades |
| --- | --- |
| Eventos | Publicação, horários, conteúdo e arquivos |
| Inscrições | Normalização, unicidade, acesso e recuperação |
| Pagamentos | Cobranças, conciliação, estados e exceções |
| Administração | Autenticação, permissões, filtros e auditoria |
| Relatórios | XLSX e impressão |

Interface pequena `PaymentGateway` com adaptadores `Fake` e `MercadoPago`. Não criar abstrações genéricas para toda classe ou um sistema de múltiplos provedores. O fake existe para testar sem transações reais e deve ser bloqueado em produção.

Operações exemplificativas: `RegisterParticipant`, `CreatePixCharge`, `ReconcilePayment`, `HandlePaymentNotification`, `ExportAttendanceList`. Organizar por responsabilidade sem concentrar tudo em um “Service” único.

Princípios aplicados: KISS, separação de responsabilidades, DRY sem abstração prematura, YAGNI, menor privilégio, validação no servidor, falha segura e operações idempotentes. Documentar decisões importantes, especialmente as que afetam pagamentos e dados pessoais.

## 5. Modelo de dados mínimo

| Entidade | Campos/conceitos principais |
| --- | --- |
| `users` | Administradores, senha com hash, estado ativo, configuração de segundo fator |
| `events` | UUID/ULID, slug único, conteúdo, banner, alt, preço em centavos, datas, estado editorial e pausa |
| `registrations` | Evento, dados do participante, CPF cifrado, índice HMAC do CPF, estado operacional, preço contratado, protocolo opaco, versão do aviso de privacidade |
| `payment_attempts` | Inscrição, chave de idempotência, ambiente, IDs de order/transação, valor/moeda, estado local e bruto, expiração e datas do provedor |
| `webhook_inbox` | Identificador de deduplicação, recurso, datas de recebimento/processamento, tentativas, próximo retry e erro sanitizado |
| `audit_logs` | Ator, ação, entidade, identificador de correlação e alterações necessárias sem copiar dados pessoais completos |
| `registration_access_tokens` | Hash do token de recuperação, escopo, expiração, consumo e revogação |
| `settings` | Contato público, organizador e configurações operacionais sem segredos |

Requisitos de persistência:

- Dinheiro em centavos inteiros; enviar ao provedor no formato decimal exato exigido, sem usar ponto flutuante para cálculo financeiro.
- Chaves estrangeiras, índices para consultas, unicidade de `(event_id, cpf_lookup_hmac)`, de chave de idempotência e dos IDs do provedor por ambiente/conta.
- Não usar apenas hash simples de CPF: o espaço de valores permite tentativa sistemática. Usar HMAC com chave exclusiva mantida fora do banco e cifrar o valor original com recursos consolidados do framework.
- A cifragem de campos protege contra exposição isolada do banco, não contra comprometimento completo da aplicação. Documentar backup e rotação das chaves, inclusive recomposição do índice HMAC.
- Datas de criação/alteração e índices para pagamentos pendentes; não usar ID sequencial como autorização pública.
- Armazenar apenas os campos necessários da resposta do provedor. Payloads extensos e documentos pessoais não devem ir automaticamente para logs.
- MySQL real nos testes de concorrência, locks e unicidade; SQLite não deve ser a única evidência para esses comportamentos.

## 6. Pagamentos: integridade e recuperação

### 6.1 Contrato com Mercado Pago

Usar a **Checkout API via Orders API** como integração de referência. Confirmar, antes do adaptador real, sua disponibilidade para a conta PF e os campos exigidos. Não misturar exemplos de Payments, Checkout Pro e Orders no mesmo fluxo. Se houver impedimento, registrar uma decisão técnica de substituição sem mudar silenciosamente o contrato.

Verificar documentação vigente, endpoint, autenticação, idempotência, expiração, notificações, teste e cancelamento. O e-mail é obrigatório no contrato consultado. Access Token fica somente no backend. Registrar IDs de order e de transação separadamente. [S3–S5]

### 6.2 Estados internos

Estados financeiros pertencem a cada tentativa; estado operacional pertence à inscrição. Uma devolução não deve apagar o registro de que o pagamento existiu.

| Estado da tentativa | Comportamento |
| --- | --- |
| `creating` | Operação local registrada, chamada ao provedor ainda em curso |
| `unknown` | Timeout ou resposta inconclusiva; bloquear nova cobrança e reconciliar |
| `pending` | Cobrança criada e aguardando pagamento |
| `paid` | Pagamento confirmado pela consulta autenticada |
| `expired`, `cancelled`, `failed` | Encerramento comprovado; nova tentativa pode ser avaliada |
| `partially_refunded`, `refunded` | Devolução confirmada; revisão da situação da inscrição |
| `disputed` | Contestação informada pelo provedor; análise administrativa |

Inscrição: `awaiting_payment`, `confirmed`, `expired`, `cancelled` ou `review_required`. A última representa exceção operacional, sem alterar artificialmente o estado financeiro.

Mapear status e detalhes com testes. Na documentação consultada, `processed/accredited` indica crédito da order, enquanto `action_required/waiting_payment` e `action_required/waiting_transfer` indicam pendência. Não considerar qualquer `processed` como aprovação integral: existem detalhes como devolução parcial. Status desconhecido deve gerar análise, nunca confirmação automática. [S3, S9]

Na projeção para inscrição: pagamento integral válido confirma; devolução integral cancela a inscrição operacionalmente; devolução parcial e contestação exigem análise até existir regra específica. Se outra tentativa continuar paga, não cancelar cegamente ao receber devolução de uma tentativa duplicada: recalcular a situação considerando todo o histórico.

### 6.3 Criação sem duplicidade

1. Validar inscrição, janela e preço no servidor; verificar acesso à sessão do participante.
2. Em transação curta, bloquear a inscrição e criar ou reaproveitar tentativa ativa. Registrar chave de idempotência e referência externa antes da chamada HTTP.
3. Encerrar a transação do banco antes de acessar a rede. Nunca manter lock durante espera no Mercado Pago.
4. Criar a cobrança com a mesma chave para retries da mesma operação e exatamente o mesmo payload. Guardar uma impressão digital do payload para detectar divergências.
5. Persistir a resposta em nova transação, respeitando a possibilidade de o webhook já ter chegado.
6. Em timeout, manter `unknown`; consultar/repetir idempotentemente de acordo com a API. Não criar nova chave só porque o usuário atualizou a página.

O bloqueio do botão não substitui índices, locks e idempotência. Uma falha depois de o provedor criar a cobrança deve ser recuperável sem gerar uma segunda cobrança.

### 6.4 Webhook e conciliação

- Endpoint HTTPS público apenas para o provedor; sem login de aluno/admin. Excluir de CSRF somente essa rota, não toda a aplicação.
- Verificar assinatura conforme o contrato vigente, inclusive canonicalização dos campos, usando validador oficial compatível ou implementação testada com vetores oficiais. Não interpretar `x-signature` como se contivesse o segredo.
- Validar estrutura e tamanho. Deduplicar por identificador de notificação quando disponível, mantendo também idempotência na aplicação do estado.
- Persistir a notificação válida em inbox durável antes de confirmar recebimento HTTP. Se falhar a persistência, responder erro para permitir reenvio.
- Consultar o recurso na API autenticada e conferir conta/ambiente, vínculo, referência, moeda, valor esperado e crédito efetivo. Não confiar no status do body recebido ou no navegador.
- Construir URLs de API a partir de host e caminhos fixos; nunca acessar uma URL arbitrária recebida na notificação. Registrar e testar proteção contra replay conforme a política de timestamp do provedor, sem descartar retries legítimos.
- Normalmente processar a consulta de forma síncrona com timeout curto, dentro do orçamento de resposta. Falhas transitórias ficam na inbox para retry. Responder sucesso por recebimento durável não significa pagamento aprovado.
- Não depender de execução PHP “depois da resposta” nem de worker permanente na hospedagem compartilhada.
- Criar comando Artisan idempotente para processar inbox pendente e reconciliar cobranças incertas. Agendamento desejado a cada cinco minutos, ajustável ao mínimo permitido pela hospedagem; documentar esse limite e alertar sobre atrasos.
- Para consulta manual do aluno e retorno do banco, permitir reconciliação controlada com cooldown por tentativa. Polling comum lê o banco, sem chamar a API em cada requisição.
- Aplicar lock por tentativa durante atualização, tolerar eventos repetidos ou fora de ordem e não rebaixar pagamento confirmado com evento antigo. Usar consulta atual e datas do provedor, preservando eventos legítimos de reembolso.
- Recurso desconhecido deve ir para análise sanitizada; não criar inscrição arbitrária a partir de uma notificação.
- Usar retries limitados, backoff e `Retry-After` quando fornecido; nunca loop infinito ou repetição cega de operação financeira.

### 6.5 Exceções e expiração

- Pagamento ocorrido dentro do prazo, com notificação atrasada: confirmar normalmente após consulta, mesmo se o formulário já fechou.
- Pagamento confirmado em evento cancelado, divergência de valor, duas tentativas pagas ou pagamento inesperado após prazo: preservar o recebimento e marcar `review_required`.
- Nunca confirmar duas inscrições nem liberar duas vagas para dois pagamentos da mesma inscrição.
- Reembolso no MVP é operado no painel do Mercado Pago pelo responsável; a aplicação deve reconciliar o resultado. Não adicionar estorno automático sem regra de negócio e implementação específica.
- Antes de declarar falha/expiração financeira, consultar situação quando houver dúvida. O vencimento visual não prova ausência de pagamento.
- Polling sugerido: a cada 5–10 segundos enquanto a página estiver visível, reduzindo frequência após espera prolongada; pausar em segundo plano e retomar ao voltar. Permitir verificação manual.

## 7. Segurança e privacidade

Os controles desta seção são requisitos propostos do projeto. Usar a orientação OWASP para Laravel como referência de revisão, sem presumir que instalar o framework resolve autorização, configuração ou exposição de dados. [S10]

### 7.1 Administração e aplicação

- Login por sessão Laravel; hashes de senha pelo framework, sem criptografia própria.
- Sem registro público de administradores. Provisionamento inicial por comando seguro, sem senha fixa em seed ou commit.
- Exigir segundo fator para administradores em produção, usando biblioteca mantida compatível, com códigos de recuperação protegidos e procedimento documentado.
- Rate limiting no login e recuperação, sem bloqueio permanente que permita negação de serviço contra um administrador.
- Regenerar sessão no login, invalidar no logout e revogar ao desativar usuário. Cookies `HttpOnly`, `Secure` em HTTPS e `SameSite` adequado; testar retorno do banco e autenticação administrativa.
- Autorização em toda ação administrativa, incluindo exportações, banners privados e consulta financeira; ocultar botão não é autorização.
- Blade escapando conteúdo; descrição em texto simples com quebras de linha no MVP. Não aceitar HTML arbitrário.
- Form Requests, listas explícitas de campos graváveis e consultas parametrizadas. Ordenação/filtros administrativos por allowlist.
- CSRF nas mutações de navegador. GET nunca cria cobrança, altera inscrição ou executa comando administrativo.
- Limites de requisição e tamanho para inscrição, upload, consulta e recuperação. Limites não devem impedir toda uma turma atrás do mesmo IP; combinar sessão, recurso e IP com testes.
- HTTPS em produção, debug desativado, erros públicos sem stack trace e headers de proteção coerentes, incluindo CSP testada e proibição de enquadramento indevido.
- Tokens, senhas e `.env` fora do Git e da pasta pública; separar credenciais de teste e produção. Não registrar cabeçalhos de autenticação.

### 7.2 Acesso do aluno e recuperação

- A inscrição fica acessível à sessão que a criou; saber CPF, e-mail, protocolo ou ID não deve permitir ler dados privados.
- Protocolo é referência de atendimento, não senha de acesso.
- Recuperação por e-mail com resposta genérica, link de uso único, expiração curta e rate limit. Consumir token, estabelecer sessão restrita e redirecionar para URL limpa.
- Armazenar somente hash de tokens aleatórios com alta entropia. Evitar vazamento por logs, referer, analytics e recursos de terceiros.
- Após recuperar, exibir somente dados mínimos e mascarados; alterações sensíveis exigem conferência administrativa.
- Não salvar CPF, e-mail, Pix ou dados pessoais em `localStorage`. Sessões/drafts no servidor devem ter expiração e limpeza.
- No ambiente local, e-mails ficam em caixa de teste. Antes de produção, configurar e testar SMTP transacional. Se indisponível, mostrar atendimento humano; nunca revelar inscrição a partir de CPF/e-mail informado.
- Duplicidade em outra sessão não revela nome, situação ou dados anteriores. Oferecer recuperação de forma neutra.

### 7.3 Upload, Excel e backups

- Banner: aceitar JPEG/PNG/WebP, validar conteúdo real, limite inicial de 5 MB e dimensões máximas; rejeitar SVG e executáveis. Reprocessar imagem, remover metadados e usar nome aleatório.
- A pasta pública deve permitir somente arquivos destinados à publicação. Upload nunca pode executar PHP; backups, planilhas e documentos não ficam acessíveis por URL previsível.
- XLSX autenticado, com valores de usuário gravados como texto explícito para impedir fórmulas injetadas (`=`, `+`, `-`, `@` e controles). CPF e telefone como texto para preservar zeros.
- Exportação não fica armazenada publicamente; se temporária, controlar acesso, expiração e limpeza. Headers para evitar cache de respostas pessoais.
- Backup automático de banco e banners, cifrado e com acesso restrito; guardar cópia fora da mesma conta de hospedagem. Guardar chaves necessárias à restauração separadamente e com acesso controlado.
- Meta proposta: perda máxima de 24 horas de dados em desastre e recuperação em até um dia útil. Validar se o serviço escolhido atende; ajustar frequência durante períodos de inscrições ativas.
- Testar restauração real em ambiente isolado. Um backup “concluído” sem teste de recuperação não encerra a tarefa.

### 7.4 Tratamento de dados pessoais

Manter inventário de dados, finalidade, acesso, fornecedores, retenção e descarte. O guia da ANPD para agentes de pequeno porte é referência de organização das medidas; este plano não define sozinho obrigações jurídicas ou prazos legais. [S11]

- Explicar de forma simples por que nome, CPF, telefone e e-mail são coletados, quem recebe os dados e como pedir correção/exclusão quando aplicável.
- Não condicionar inscrição a marketing, nem criar caixa obrigatória de “consentimento LGPD” como solução genérica. Definir a base legal e o texto com o responsável antes de produção.
- Não incluir analytics publicitário, gravação de sessão ou compartilhamento de contatos para marketing no MVP.
- CPF mascarado nas listagens; acesso integral apenas quando necessário e autorizado. Lista de chamada sem CPF, telefone ou e-mail por padrão.
- Definir retenção por categoria antes de produção. Como proposta operacional: apagar drafts não enviados rapidamente; revisar pendências abandonadas após 30 dias; retenção de pagamentos e comprovantes depende da finalidade e obrigações aplicáveis.
- Descarte deve tratar cópias, logs e backups de acordo com seu ciclo; não prometer remoção imediata de todas as cópias.
- Ter procedimento de incidente: conter acesso, preservar evidências necessárias, avaliar impacto, corrigir, comunicar responsáveis e verificar deveres legais aplicáveis.

## 8. Plano de execução por etapas

Executar na ordem abaixo. Cada etapa deve produzir resultado executável localmente, atualizar o progresso e passar seus critérios antes de avançar. A equipe não precisa contratar hospedagem para iniciar.

### Etapa 0 — Preparação e decisões técnicas

**Tarefas**

- Inspecionar repositório e instruções existentes antes de modificar; se for novo, criar estrutura mínima Git sem segredos.
- Registrar stack, versão PHP/Laravel, MySQL, bibliotecas propostas e compatibilidade de hospedagem em decisão técnica.
- Criar README, `.env.example`, plano de progresso e instruções locais.
- Definir fake de pagamento por padrão e proteção explícita contra usar credenciais de produção em desenvolvimento.
- Registrar pontos pendentes do negócio da seção 12 com defaults propostos, sem inventar dados do armarinho.

**Aceite:** plano de versões documentado, escopo claro, ambiente de produção fora de uso e nenhuma alteração em serviço externo.

### Etapa 1 — Ambiente local e fundação

**Tarefas**

- Docker Compose com PHP, servidor web e MySQL, volumes persistentes e portas vinculadas somente ao localhost.
- Imagens e dependências fixadas; Composer lock versionado; dependências de desenvolvimento fora da futura publicação.
- Configurar Blade, CSS, JS simples, conexão, UTC e apresentação pt-BR.
- Criar migrations, factories com dados fictícios, validações comuns e tratamento de erros.
- Configurar PHPUnit ou Pest, formatação e análise estática compatível; não instalar duas ferramentas equivalentes para a mesma finalidade.
- Configurar caixa de e-mail local e adaptador fake, sem enviar mensagens reais.

**Aceite:** um novo checkout sobe seguindo README; migrations funcionam em banco vazio; reiniciar containers preserva dados; testes básicos passam; credenciais não aparecem no Git.

### Etapa 2 — Base de UX e formulário acessível

**Tarefas**

- Implementar componentes Blade reutilizáveis: campos, ajuda, erros, botões, avisos e resumo.
- Criar página de curso fictício, formulário, revisão, pagamento simulado e confirmação simulada claramente identificada como teste.
- Implementar máscaras tolerantes, mensagens e navegação por teclado.
- Implementar estados fechado, ainda não aberto, pendente, expirado e erro de conexão.
- Fazer primeira rodada de avaliação visual e por teclado antes de concluir o backend.

**Aceite:** fluxo completo em celular, sem campos apagados em erro; botões grandes; foco correto; sem dependência de imagem para informação essencial; contraste e zoom verificados.

### Etapa 3 — Administradores e eventos

**Tarefas**

- Implementar login, permissões, segundo fator e recuperação administrativa com componentes mantidos.
- CRUD de eventos, upload seguro, prévia, duplicação, publicação, pausa e arquivamento.
- Validar abertura anterior ao encerramento, datas coerentes e preço positivo.
- Registrar auditoria de mudanças e aviso de impacto ao alterar evento com inscritos.
- Configurar cadastro dos dados públicos do organizador sem credenciais financeiras.

**Aceite:** visitante não acessa nenhuma função/admin/exportação; evento rascunho não aparece publicamente; upload inválido é rejeitado; edição de preço não altera valores já contratados.

### Etapa 4 — Inscrições e integridade

**Tarefas**

- Implementar normalização/validação server-side, review e criação idempotente.
- Criar sessão de acesso, recuperação por e-mail e proteção contra enumeração.
- Aplicar regra CPF/evento no banco, fotografia de preço e validação temporal no servidor.
- Implementar máquina de estados, locks curtos e exceções operacionais.
- Integrar o fake determinístico para aprovação, pendência, falha, atraso e timeout.

**Aceite:** envios concorrentes não duplicam inscrição; manipulação do preço falha; mesma pessoa pode participar de outro evento; outro navegador não lê dados por conhecer CPF/ID.

### Etapa 5 — Mercado Pago e confirmação

**Tarefas**

- Confirmar contrato Orders e habilitação da conta; usar credenciais de teste fornecidas de forma segura.
- Implementar adaptador, idempotência persistida, consulta, expiração, cancelamento quando suportado e mapeamento de estados.
- Implementar webhook autenticado, inbox, reconciliação, retries e auditoria.
- Integrar QR Code/Copia e Cola, retorno ao navegador e botão de verificação.
- Implementar comando Artisan para reconciliação e processamento de pendências sem worker permanente.
- Documentar testes realmente suportados pelo provedor. A documentação Pix consultada descreve teste por requisição com valores predefinidos, não uma transferência bancária real de sandbox. Não prometer cenários que o ambiente não reproduz. [S5]
- Se necessário, usar túnel temporário apenas para testar webhook: fake/dados fictícios, debug público desativado e administrador inacessível externamente. Isso não autoriza publicação de produção.

**Aceite:** testes de contrato e matriz financeira passam; timeout não duplica; webhook forjado não confirma; uma notificação perdida é recuperada; nenhum teste move dinheiro real.

### Etapa 6 — Gestão de inscritos e Excel

**Tarefas**

- Listagem paginada, filtros, busca, detalhes, histórico e consulta ao provedor.
- Gerar XLSX com biblioteca PHP mantida e compatível, como PhpSpreadsheet, após conferir requisitos.
- Aba `Inscrições`: curso, protocolo, nome, WhatsApp, e-mail, CPF conforme permissão, data, situação, valor e data de pagamento.
- Aba `Lista de chamada`: somente confirmados, ordem alfabética pt-BR, número, nome, presença e assinatura.
- Cabeçalho com evento/data/local; congelamento de cabeçalho e filtros na aba administrativa.
- Impressão A4, repetição de cabeçalho e largura de uma página, permitindo múltiplas páginas na altura. Não reduzir toda a turma até ficar ilegível.
- Tela HTML de impressão como alternativa ao Excel.

**Aceite:** arquivo abre no Excel/LibreOffice, mantém zeros, não executa fórmulas de entradas maliciosas; lista não inclui pendentes nem dados pessoais desnecessários; impressão legível em mais de uma página.

### Etapa 7 — Verificação integrada, segurança e usabilidade

**Tarefas**

- Executar matriz da seção 9 em MySQL e no navegador.
- Auditoria de dependências e revisão das rotas sensíveis, uploads, sessões e exports.
- Rodada de uso com idosos e correção dos impedimentos encontrados.
- Teste de carga inicial proposto: 20 sessões concorrentes em navegação/inscrição, com provedor fake, mais pico de notificações repetidas. Aumentar somente se houver demanda real ou risco identificado.
- Meta local indicativa: p95 menor que 2 segundos nas páginas sem API externa sob esse cenário, sem inconsistência de dados. Isso não é garantia de capacidade na hospedagem.
- Testar falha do banco, falha do provedor, retomada de sessão, expiração e restauração de backup.

**Aceite:** nenhum bloqueador de pagamento, privacidade ou acesso; nenhuma barreira crítica de UX; limitações restantes documentadas e classificadas.

### Etapa 8 — Preparação para Hostinger Premium, sem publicar

**Tarefas**

- Criar guia de deploy com verificação de PHP/extensões, MySQL, Composer/SSH, diretório público, cron, limites de memória e SMTP.
- Verificar requisitos de extensões tanto do framework quanto da biblioteca XLSX; comparar PHP CLI e PHP web.
- Documentar geração e guarda da chave da aplicação, segredos, HTTPS, permissões mínimas e configuração de cache.
- Preparar publicação por release, migrations compatíveis, smoke test e rollback. Sem comandos destrutivos como `migrate:fresh` em produção.
- Criar runbook para atraso de pagamento, credencial revogada, erro de upload, backup e recuperação.
- Não exigir Docker, Redis, `npm install` ou processo permanente na hospedagem.

**Aceite:** checklist de compatibilidade revisado, release local reproduzível e instruções de reversão testadas em cópia do ambiente; nenhum site publicado.

### Etapa 9 — Homologação e produção futuras

**Dependência:** autorização do responsável para publicar e configurar ambiente real. Esta etapa faz parte do plano, mas não é uma ação autorizada agora.

- Provisionar domínio/hospedagem e ambiente de homologação com dados sintéticos, sem indexação e com acesso restrito compatível com o webhook.
- Executar smoke tests no plano real, incluindo cron, sessão, XLSX, e-mail e integração de teste.
- Revisar dados do recebedor PF, aviso de privacidade, retenção e regras de cancelamento.
- Configurar conta/segredos de produção separadamente, segundo fator dos administradores, backups e monitoramento.
- Realizar uma transação real de baixo valor apenas com autorização expressa; conferir recebimento e fluxo operacional de devolução.
- Abrir inscrições após checklist; monitorar pendências e erros no primeiro evento.
- Em falha, pausar novas cobranças, manter conciliação e atendimento, restaurar release anterior compatível; nunca reverter banco financeiro indiscriminadamente.

**Aceite:** pagamento real autorizado conferido, canais de suporte funcionando, restauração comprovada e responsável consegue operar o evento e imprimir a chamada.

## 9. Matriz mínima de testes e evidências

Testes devem verificar comportamentos e riscos reais, não repetir linha a linha a implementação. Cada cenário deve registrar resultado, ambiente e evidência sem dados reais de alunos.

| ID | Cenário | Resultado esperado |
| --- | --- | --- |
| T01 | Nome com acentos/apóstrofo, CPF começando em zero, telefone colado com +55 | Normalização correta, sem mutilar dados |
| T02 | Campos vazios/inválidos | Erro legível e dados válidos preservados |
| T03 | Envio sem JavaScript | Inscrição e revisão funcionam |
| T04 | Evento antes da abertura, no limite e após encerramento | Janela respeitada no servidor |
| T05 | Pausa enquanto formulário já está aberto | Nova cobrança bloqueada, mensagem clara |
| T06 | Preço adulterado no navegador | Servidor ignora valor informado |
| T07 | Duplo clique e POSTs concorrentes | Uma inscrição e uma operação financeira |
| T08 | CPF igual no mesmo evento e em evento diferente | Unicidade somente no evento |
| T09 | Timeout após criação no provedor | Estado incerto recuperado com mesma operação |
| T10 | Falha ao gravar resposta de cobrança | Reconciliação recupera vínculo sem duplicar |
| T11 | Webhook chega antes da resposta de criação | Convergência para estado correto |
| T12 | Assinatura ausente/inválida ou conteúdo adulterado | Sem confirmação e sem vazamento |
| T13 | Webhook válido duplicado/fora de ordem | Sem efeito financeiro duplicado ou regressão |
| T14 | API responde 429/5xx ou rede cai | Retry limitado e visível, sem nova cobrança |
| T15 | Webhook perdido e cron executado | Estado conciliado pela API |
| T16 | Valor, moeda, referência ou ambiente divergente | Análise manual, não confirmação |
| T17 | Pagamento no prazo, notificação após encerramento | Confirmação após verificação |
| T18 | Cronômetro acaba enquanto confirmação chega | Não criar segunda cobrança |
| T19 | Tentativa anterior encerrada e novo Pix solicitado | Nova chave apenas para nova operação válida |
| T20 | Duas tentativas pagas | Uma inscrição, duplicidade financeira sinalizada |
| T21 | Evento cancelado com pagamento posterior | Recebimento preservado, análise/reembolso operacional |
| T22 | Devolução parcial/integral ou contestação | Histórico preservado e estado atualizado |
| T23 | Pessoa paga em nome de outro participante | Não rejeitar apenas pela diferença de titular |
| T24 | IDs/CPF/protocolo de outra pessoa | Nenhum acesso não autorizado |
| T25 | Recuperação válida, inválida, expirada, repetida | Respostas neutras e token de uso único |
| T26 | Acesso direto a admin e exportação sem sessão | Negado |
| T27 | Logout, desativação e segundo fator | Sessões e permissões corretas |
| T28 | CSRF, XSS, SQL injection e mass assignment | Mutações/entradas maliciosas neutralizadas |
| T29 | Upload executável, MIME falso, SVG, imagem enorme | Rejeição segura |
| T30 | Nome começando por fórmula em exportação | Texto literal no XLSX |
| T31 | Retorno do banco, recarga, voltar e falha de conexão | Contexto preservado sem cobrança duplicada |
| T32 | Teclado, leitor de tela, zoom e tela pequena | Fluxo completo e mensagens compreensíveis |
| T33 | Área de transferência bloqueada | Alternativa manual disponível |
| T34 | Lista impressa com turma em várias páginas | Cabeçalho repetido, nomes e assinatura legíveis |
| T35 | Backup restaurado em ambiente isolado | Banco, banners e dados cifrados recuperáveis |
| T36 | `.env`, logs, arquivos internos e banco pela internet | Inacessíveis |
| T37 | Bot de inscrições e turma compartilhando IP | Abuso limitado sem bloqueio indiscriminado |
| T38 | Mudança de preço após cobrança pendente | Cobrança conserva preço original |
| T39 | Falha no SMTP e na consulta do pagamento | Nenhuma aprovação falsa; ajuda e retry disponíveis |
| T40 | Fake/credenciais de teste ativados em produção | Inicialização/configuração rejeitada |

## 10. Observabilidade e operação

- Identificador de correlação por inscrição/tentativa, sem CPF no log.
- Painel de exceções com pendências antigas, tentativas incertas e falhas de reconciliação.
- Registrar última execução bem-sucedida do cron e alertar se ultrapassar o intervalo esperado com margem definida.
- Medir falhas de criação, latência de confirmação, retries, erros de autenticação e exportação, sem analytics individual de alunos.
- Logs estruturados com retenção e rotação; capturar códigos técnicos sanitizados para diagnóstico.
- Alerta técnico para o responsável quando houver erro persistente, backup falhando ou ausência prolongada de conciliação; definir destino antes de produção.
- Rotina mensal de atualização de dependências e revisão de suporte; atualizar correções críticas conforme avaliação de risco.
- Antes de cada evento, conferir horários, preço, banner, suporte e exportação. Após o evento, aplicar política de retenção e arquivamento.

## 11. Extensão opcional: limite de vagas

Implementar somente se o responsável confirmar a necessidade. Criar reserva temporária associada à tentativa, contabilização transacional e teste de concorrência para a última vaga.

A reserva deve permanecer enquanto houver cobrança pagável ou resultado incerto. Não liberar vaga só pelo relógio sem tratar a situação financeira. Antes de emitir substituta, garantir que a anterior não permanece pagável; se o provedor não permitir garantir, bloquear substituição até reconciliação.

Se ocorrer pagamento tardio após liberação e não houver vaga, não confirmar além da capacidade: manter recebimento, sinalizar análise e executar a política de devolução. Uma regra de lotação incompleta impede a ativação desse recurso.

## 12. Pendências que não bloqueiam o desenvolvimento local

| Decisão | Proposta para implementação | Necessário antes de produção |
| --- | --- | --- |
| Limite de vagas | Sem controle de lotação no MVP | Confirmar necessidade por curso |
| Prazo Pix | 60 minutos dentro da janela e limites da API | Validar operação e acessibilidade |
| Cancelamento e devolução | Tratamento administrativo pelo Mercado Pago | Política pública e responsável definidos |
| Marca/banner | Conteúdo fictício identificado | Receber identidade visual e materiais finais |
| Ajuda | Componente configurável sem número inventado | Telefone/WhatsApp oficial |
| Nome do recebedor | Placeholder apenas em teste | Nome real esperado no aplicativo bancário |
| Dados e retenção | Controles técnicos preparados | Aprovar finalidade, base legal, prazos e aviso |
| E-mail transacional | Caixa local | SMTP e domínio autenticado |
| Hospedagem | Docker local | Confirmar versões/extensões, cron, memória e pasta pública no Premium |
| Conta PF Mercado Pago | Fake e teste | Habilitação, credenciais e condições comerciais |

Mudanças nessas decisões devem ser documentadas antes de modificar fluxos já concluídos. Não assumir que uma preferência por hospedagem equivale a compra ou aprovação de custos.

## 13. Instruções de execução para o Codex

1. Ler este plano e as instruções reais do repositório. Não gerar toda a aplicação em uma única alteração.
2. Iniciar pelas etapas 0 e 1, mantendo as demais como tarefas futuras. Avançar incrementalmente após verificar os critérios, sem pedir confirmação para cada escolha técnica rotineira.
3. Implementar apenas o escopo acordado. Questões que mudam cobrança, lotação, privacidade ou cancelamento devem ser registradas e confirmadas antes de produção; usar os defaults explícitos nos testes locais.
4. Preferir recursos nativos do Laravel e bibliotecas mantidas. Não escrever criptografia própria, autenticação improvisada ou abstrações sem necessidade.
5. Nunca inventar endpoints, campos, tarifas, regras de teste ou compatibilidade de hospedagem. Conferir documentação da API escolhida quando necessário.
6. Manter migrations, fixtures sintéticas, testes de comportamento e README atualizados. Não usar dados reais de participantes nos testes.
7. Não declarar “pago” por clique, imagem de comprovante, query string, webhook sem verificação ou retorno do navegador.
8. Não instalar serviços adicionais, trocar stack, integrar WhatsApp ou publicar sem que isso faça parte do escopo autorizado.
9. Ao terminar cada etapa, registrar o que foi feito, testes executados, limitações e próximo passo em `docs/progresso.md`.
10. Entregar o projeto executável localmente e um guia de publicação. Não contratar hospedagem, publicar, enviar mensagens reais ou gerar cobrança real nesta fase.

### Critério geral de conclusão local

- Formulário e administração funcionam com dados fictícios.
- Fluxos críticos financeiros e de acesso passam na matriz de testes.
- Integração real de teste foi verificada quando credenciais estiverem disponíveis; se não estiverem, declarar claramente “adaptador pendente de homologação”, sem apresentar fake como integração concluída.
- UX foi verificada em celular, teclado, leitor de tela e com participantes idosos; pendências ficam explicitamente registradas.
- XLSX e chamada são legíveis e seguros.
- Dependências, segredos, backup e restauração foram revisados.
- Publicação está documentada e continua não executada.

## 14. Fontes técnicas consultadas

Consulta em 26/09/2026. Revalidar requisitos externos na implementação; decisões próprias e metas de produto neste documento não são garantias dos fornecedores.

- **[S1]** Laravel — [Deployment](https://laravel.com/docs/deployment).
- **[S2]** Laravel — [Release Notes e suporte de versões](https://laravel.com/docs/releases).
- **[S3]** Mercado Pago — [Pix via Orders API](https://www.mercadopago.com.br/developers/pt/docs/checkout-api-orders/payment-integration/pix).
- **[S4]** Mercado Pago — [Notificações de orders](https://www.mercadopago.com.br/developers/pt/docs/checkout-api-orders/notifications).
- **[S5]** Mercado Pago — [Teste de integração Pix](https://www.mercadopago.com.br/developers/pt/docs/checkout-api-orders/integration-test/pix).
- **[S6]** W3C — [Referência rápida WCAG 2.2](https://www.w3.org/WAI/WCAG22/quickref/).
- **[S7]** W3C WAI — [Older Users and Web Accessibility](https://www.w3.org/WAI/older-users/).
- **[S8]** W3C WAI — [Forms Tutorial](https://www.w3.org/WAI/tutorials/forms/).
- **[S9]** Mercado Pago — [Status da order](https://www.mercadopago.com.br/developers/pt/docs/checkout-api-orders/payment-management/status/order-status).
- **[S10]** OWASP — [Laravel Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Laravel_Cheat_Sheet.html).
- **[S11]** ANPD — [Guia de segurança da informação para agentes de pequeno porte](https://www.gov.br/anpd/pt-br/centrais-de-conteudo/materiais-educativos-e-publicacoes/guia-orientativo-sobre-seguranca-da-informacao-para-agentes-de-tratamento-de-pequeno-porte).
- **[S12]** Hostinger — [Acesso SSH nos planos](https://www.hostinger.com/support/1583245-how-to-connect-to-a-hosting-plan-via-ssh-in-hostinger/).
