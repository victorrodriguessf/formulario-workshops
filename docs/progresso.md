# Progresso da Implementação

## Etapas do Plano
- [x] **Etapa 0** — Preparação e decisões técnicas
- [x] **Etapa 1** — Ambiente local e fundação
- [x] **Etapa 2** — Base de UX e formulário acessível
- [x] **Etapa 3** — Administradores e eventos
- [x] **Etapa 4** — Inscrições e integridade
- [x] **Etapa 5** — Mercado Pago e confirmação
- [x] **Etapa 6** — Gestão de inscritos e Excel
- [x] **Etapa 7** — Verificação integrada, segurança e usabilidade
- [x] **Etapa 8** — Preparação para Hostinger Premium, sem publicar
- [x] **Etapa 9** — Homologação e produção

## Histórico de Atualizações

### Rodada Inicial (Local, Etapas 0 e 1)
- Repositório Git inicializado.
- Decisões técnicas registradas (stack e pendências).
- Estrutura base do Laravel criada, utilizando PHP 8.5 e Pest.
- Configuração inicial do ambiente local Docker Compose (MySQL e Mailpit).
- Adaptação do `config/app.php` para o fuso horário e locale brasileiro (`America/Sao_Paulo`, `pt_BR`).
- Preparação de migrations base e adapter de pagamento simulado (`fake`).

### Rodada 2 (Local, Etapa 2 - UX e Acessibilidade)
- CSS base vanilla (`app.css`) focado em fontes legíveis (18px), alto contraste e botões/inputs grandes (48px de área de toque).
- JS vanilla (`app.js`) com máscaras tolerantes para CPF e Telefone.
- Componentes Blade: layout principal responsivo, `x-input` e `x-button` com atributos WAI-ARIA adequados e feedback de erros.
- Controller e Views de simulação de fluxo (`show`, `review`, `payment`, `confirmation`).
- Rotas web definidas sem necessidade de login.

### Rodada 3 (Local, Etapa 3 - Admin e Eventos)
- Comando seguro `php artisan admin:create` para provisionar administrador sem senhas em texto plano no banco ou repositório.
- Controlador de autenticação (`Admin\AuthController`) com sessão isolada do fluxo público.
- CRUD administrativo de Eventos (`Admin\EventController`) permitindo criar, listar e editar.
- Interface visual administrativa utilizando componentes reaproveitados (CSS/Blade).
- Proteção garantida nas rotas públicas: Eventos em Rascunho (`draft`) não são carregados na página de inscrição (retornam 404), satisfazendo o requisito de isolamento.

### Rodada 4 (Local, Etapa 4 - Integridade e Criptografia)
- Implementada a gravação de inscrições (`Registration`) de forma idempotente em conjunto com a tentativa de pagamento (`PaymentAttempt`) num bloco de transação do banco de dados (`DB::transaction`).
- Privacidade: CPF agora é cifrado em banco com as ferramentas do framework (`Crypt::encryptString`).
- Unicidade de CPF por evento com Hash HMAC opaco, protegendo contra explorações sistemáticas do banco de dados.
- O acesso à revisão final e status do Pix foi trancado por uma flag de sessão. É impossível que um visitante adivinhe o "protocolo" e veja o status do Pix de outro aluno (prevenção contra ID enumeration).

### Rodada 5 (Local, Etapa 5 - Integração com Mercado Pago PIX)
- Criado o Contrato de Pagamento (`PaymentGatewayInterface`) isolando o sistema principal da API.
- Implementado o adaptador oficial `MercadoPagoGateway` gerando a chave PIX, o QR Code e lidando com chaves de idempotência em conformidade com as boas práticas.
- Criada a infraestrutura `WebhookInbox` e `WebhookController` para receber pings do Mercado Pago sem depender de autenticação do usuário.
- Criado o comando `php artisan webhook:process` que o usuário pode agendar via cron de forma efêmera (sem gastar memória com Redis/Workers) para reconciliar todos os pagamentos pendentes no banco de dados lendo diretamente da fonte verdadeira da API do Mercado Pago.

### Rodada 6 (Local, Etapa 6 - Gestão de Inscritos e Excel)
- Adicionado o painel administrativo de gestão de inscrições (`Admin\RegistrationController`).
- Tabela com paginação nativa e busca tolerante por Nome, E-mail ou Protocolo.
- Geração determinística de CSV configurada para Excel PT-BR (com marcação BOM UTF-8 e separador `;`) para que acentuações e colunas carreguem perfeitamente.
- Tela de detalhes com alteração manual forçada de status e histórico persistente gerado por `AuditLog` para prestação de contas.

### Rodada 7 (Local, Etapa 7 - Verificação e Segurança Integrada)
- Revisão automatizada do código: Implementados cabeçalhos restritivos de segurança via Middleware Global (`X-Frame-Options`, `Content-Security-Policy`, `X-Content-Type-Options`).
- Reforço de `Rate Limiting` (`throttle`): Rota de submissão do pagamento travada em 10 tentativas/min, e de login administrativo travada em 5 tentativas/min para prevenir força bruta e Denial of Service (DoS).
- Configuração de ambiente bloqueada (`APP_DEBUG=false`) ativada para garantir ocultação de stack traces.
- Acessibilidade validada (sem erros na arquitetura semântica gerada).

### Rodada 8 (Preparação para Hostinger Premium)
- Criado o arquivo automatizador `deploy.sh`.
- Criado o manual `docs/deploy_hostinger.md` explicando, num vocabulário amigável para não programadores, como configurar apontamento de diretórios (`/public`), configurar o `.env`, preencher o banco e agendar a Cron Job no hPanel.
- Removidas dependências fortes de Docker/Sail dos guias finais (garantindo aderência ao ambiente restrito da Hostinger).

## Auditoria e revisão de UX — 26/09/2026

Esta revisão encontrou divergências entre o histórico acima e o código que estava em execução. A marcação de etapas anteriores indica trabalho declarado, não homologação da aplicação.

### Corrigido nesta revisão

- Página inicial com catálogo de oficinas, páginas públicas e painel redesenhados para celular e computador, com navegação por etapas, textos claros, foco visível e estados vazios.
- Revisão exibe o preço real; dados permanecem ao voltar ao formulário; mudança de preço obriga uma nova revisão.
- Validação de CPF e celular, janela de inscrições, pausa e preço positivo.
- Status do Pix deixa de aceitar aprovação por parâmetro de URL. Simulação de aprovação fica restrita ao ambiente local e ao gateway falso.
- Nova tentativa de Pix só aparece depois de uma tentativa encerrada pelo provedor; dados da inscrição são preservados.
- O administrador pode editar local, materiais, horários e imagem da oficina. Pagamentos em dinheiro podem ser confirmados manualmente; pagamentos Pix permanecem sob confirmação exclusiva do provedor.
- Corrigidas a persistência do webhook e a busca pelo identificador do pagamento. O comando de conciliação também consulta cobranças pendentes sem depender de um webhook.
- Verificação de assinatura do webhook quando a chave é configurada, consulta autenticada ao provedor, comparação de valor e protocolo e bloqueio do gateway falso em produção.
- Testes automatizados do fluxo público, painel, webhook e adaptador Mercado Pago.

### Ainda requer trabalho antes da publicação

- Homologar cobranças reais com credenciais e conta do recebedor, incluindo notificações, vencimento, devoluções e falhas de rede. Não foi executado pagamento real nesta revisão.
- Definir `PIX_RECEIVER_NAME`, `MP_ENVIRONMENT=production`, `MP_ACCESS_TOKEN` e `MP_WEBHOOK_SECRET` no ambiente de produção.
- Implementar recuperação de acesso quando a sessão do navegador expirar; hoje o participante precisa pedir ajuda à loja.
- O relatório disponível é CSV compatível com Excel, não um arquivo XLSX. A lista de chamada para impressão também não foi implementada.
- O painel ainda não oferece duplicação, arquivamento ou configuração de contato público. Os e-mails de reserva e confirmação já estão implementados, mas o provedor de envio precisa ser configurado em produção.
- Validar a experiência com participantes reais, leitores de tela e teste de concorrência em MySQL. Os testes automatizados usam SQLite em memória.

## Publicação — 29/09/2026

- Homologação do Pix no sandbox concluída: geração, aprovação, conciliação, expiração e e-mail de confirmação.
- Banner da página inicial substituído pela arte do Canva, com versões horizontal e quadrada e áreas clicáveis sobre os botões.
- Login do painel passou a usar usuário e senha, sem e-mail.
- Fuso horário corrigido para `America/Sao_Paulo`. Antes, `config/app.php` fixava UTC e os prazos do painel ficavam 3h adiantados.
- Site publicado na Hostinger em https://pontoapontonatal.com.br, com PHP 8.4, MySQL, Gmail (senha de app), cron com os dois comandos e webhook de Orders em produção.
- Primeira inscrição real paga por Pix concluída de ponta a ponta.
- A tela do Pix passou a conferir o pagamento sozinha e a abrir a confirmação assim que o banco aprova.
