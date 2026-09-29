# Inscrições para oficinas do Armarinho Ponto a Ponto

Aplicação web para divulgar as oficinas presenciais do Armarinho Ponto a Ponto, receber inscrições e acompanhar pagamentos. Está em produção em **https://pontoapontonatal.com.br**.

O fluxo foi pensado para celular e para pessoas com pouca familiaridade digital. O participante não cria conta: escolhe a oficina, informa seus dados, confere tudo e paga por **Pix** ou reserva para pagar **em dinheiro** na loja. A equipe usa um painel com login para cadastrar oficinas, acompanhar inscrições e confirmar pagamentos em dinheiro.

## Funcionalidades

**Site público**
- Página inicial com banner próprio em duas versões: horizontal para computador e quadrada para celular, com botões para a lista de oficinas e para compras pelo WhatsApp.
- Catálogo das oficinas publicadas, com período de inscrição, pausa manual e limite de vagas.
- Formulário com validação de nome, WhatsApp, CPF e e-mail. Não é possível repetir o CPF na mesma oficina.
- Pagamento por Pix via Mercado Pago, com QR Code e código copia e cola. A página confere o pagamento sozinha e abre a confirmação assim que o banco aprova.
- Reserva para pagamento em dinheiro com prazo, que expira automaticamente.
- E-mails de reserva (dinheiro) e de confirmação da inscrição.

**Painel administrativo** (`/admin/login`, entrada com usuário e senha)
- Cadastro, edição e exclusão de oficinas, com foto, local, materiais, vagas e prazos.
- Lista de inscrições com busca, filtros e exportação em CSV compatível com o Excel.
- Confirmação manual de pagamentos **em dinheiro**. Pagamentos Pix são confirmados só pelo Mercado Pago.

## Tecnologias

- PHP **8.4+** e Laravel 13
- MySQL em produção, SQLite no desenvolvimento
- Blade, CSS e JavaScript sem frameworks, compilados com Vite
- PHPUnit

O fuso horário padrão é `America/Sao_Paulo`: prazos e horários cadastrados no painel valem no horário de Brasília.

## Instalação local

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm ci && npm run build
php artisan storage:link
php artisan admin:create
composer run dev
```

O `.env.example` já usa o gateway de pagamento simulado (`PAYMENT_GATEWAY=fake`). Com ele, a tela do Pix oferece um botão para simular a aprovação, que só aparece no ambiente local.

## Administradores

Crie cada acesso pelo terminal:

```bash
php artisan admin:create
```

- O comando pede nome, **usuário** e senha. A senha é digitada escondida e só é gravada criptografada.
- A senha precisa ter pelo menos 8 caracteres, com maiúscula, minúscula, número e símbolo.
- O login é limitado a 5 tentativas por minuto.
- Não existe administrador ou senha padrão no código.

## Pagamentos (Mercado Pago)

O Pix usa a **Orders API** do Mercado Pago. A comunicação acontece só no servidor, então a Public Key não é usada.

| Variável | Uso |
|---|---|
| `PAYMENT_GATEWAY` | `fake` (simulado) ou `mercadopago` |
| `MP_ENVIRONMENT` | `sandbox` ou `production`. Tem que casar com `APP_ENV`: credencial de produção é recusada fora de produção, e vice-versa |
| `MP_ACCESS_TOKEN` | Access Token da aba **Teste** ou **Credenciais produtivas** do painel. Nesta integração, os dois começam com `APP_USR-` |
| `MP_WEBHOOK_SECRET` | Assinatura secreta do webhook, usada para validar as notificações |
| `PIX_RECEIVER_NAME` | Nome exibido para quem paga, para conferência |

Nenhuma notificação é aceita sem conferência: antes de confirmar um pagamento, o sistema consulta a Order no Mercado Pago e confere valor e protocolo. A situação do Pix é atualizada por quatro caminhos:
- o webhook (`POST /webhook/mercadopago`, evento **Order (Mercado Pago)**);
- a verificação automática da página do Pix;
- o botão "Já paguei";
- o comando `webhook:process`, rodado pelo cron.

## E-mails

Em produção o envio usa o Gmail da loja por SMTP (`smtp.gmail.com`, porta 465, `MAIL_SCHEME=smtps`), com uma **senha de app** do Google. Se um envio falhar, o cron tenta de novo a cada minuto até conseguir.

## Tarefas agendadas

Dois comandos precisam rodar a cada minuto:

```bash
php artisan webhook:process            # confere Pix pendentes e reenvia e-mails que falharam
php artisan registrations:expire-cash  # expira reservas em dinheiro vencidas
```

- **Localmente** basta `php artisan schedule:work`.
- **Na Hostinger** o `schedule:run` não funciona, porque `proc_open` é bloqueado. Por isso o cron chama os dois comandos diretamente.

## Deploy

O passo a passo completo, com as particularidades da Hostinger, está em **[docs/deploy_hostinger.md](docs/deploy_hostinger.md)**:
- PHP 8.4;
- projeto fora do `public_html`;
- atalho de imagens criado com `ln -s`;
- aspas em senhas com `#` no `.env`;
- cron com os dois comandos;
- webhook do Mercado Pago;
- como publicar atualizações.

## Banner da página inicial

A arte é produzida no Canva e exportada em PNG de alta resolução:

| Arquivo | Uso |
|---|---|
| `public/images/hero-banner.webp` / `.jpg` | Versão horizontal, para telas a partir de 900px |
| `public/images/hero-banner-mobile.webp` / `.jpg` | Versão quadrada, para celular e tablet |

Os botões desenhados na arte recebem áreas clicáveis invisíveis. As posições estão em `resources/css/app.css` (`.hero-hotspot-courses` e `.hero-hotspot-whatsapp`), em porcentagem da imagem. **Se a arte mudar de layout, essas porcentagens precisam ser medidas de novo.**

Evite exportar do Canva em SVG: elementos com textura viram imagens embutidas e o arquivo passa de 25 MB.

## Segurança e privacidade

O sistema guarda dados pessoais: nome, WhatsApp, e-mail e CPF. O CPF fica criptografado, e a checagem de duplicidade usa um hash que não revela o número.

Regras que o projeto segue:
- Nunca versionar `.env`, bancos, logs, backups ou credenciais. O `.gitignore` já cobre esses arquivos.
- Em produção:
  - `APP_DEBUG=false`;
  - `SESSION_ENCRYPT=true`;
  - `SESSION_SECURE_COOKIE=true`;
  - HTTPS obrigatório.
- Usar credenciais separadas para teste e produção, e trocar imediatamente qualquer senha ou token que tenha sido exposto.
- Antes de publicar, rodar `composer audit` e `npm audit`.

## Testes e verificações

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
npm run build
composer audit
npm audit
```

A suíte não faz nenhuma cobrança real: a integração com o Mercado Pago é simulada nos testes.

## Estrutura principal

- `app/Http/Controllers`: fluxo público, painel e webhook.
- `app/Services/Payment`: contrato de pagamento, adaptador do Mercado Pago e gateway simulado.
- `app/Console/Commands`: criação de administradores, conciliação de pagamentos e expiração de reservas.
- `app/Mail` e `resources/views/mail`: e-mails de reserva e de confirmação.
- `resources/views`: páginas públicas, painel e componentes.
- `routes/web.php`: rotas públicas, administrativas e do Mercado Pago.
- `docs/`: guia de deploy, decisões técnicas e histórico do projeto.
- `tests/`: testes do fluxo público, painel, pagamentos, webhook e e-mails.

## Licença, direitos autorais e uso da marca

### Código-fonte: licença MIT

O código deste projeto é distribuído sob a **[licença MIT](LICENSE)**. Você pode copiar, estudar, alterar, redistribuir e usar o código, inclusive em outros projetos e para fins comerciais, **desde que mantenha os créditos**:
- conserve o aviso de copyright e o texto da licença em todas as cópias ou partes relevantes;
- ao publicar uma versão derivada, cite a origem, por exemplo: "Baseado no sistema de inscrições do Armarinho Ponto a Ponto, desenvolvido por Victor Rodrigues da Silva Fonseca".

O software é fornecido "como está", sem garantias. Quem o reutiliza é responsável pela própria instalação, configuração e operação.

### Marca, logotipos e imagens: todos os direitos reservados

A licença MIT **não** se aplica à identidade visual do Armarinho Ponto a Ponto. Os itens abaixo são protegidos pela Lei de Direitos Autorais ([Lei nº 9.610/1998](https://www.planalto.gov.br/ccivil_03/leis/l9610.htm)) e, no caso da marca, pela Lei da Propriedade Industrial ([Lei nº 9.279/1996](https://www.planalto.gov.br/ccivil_03/leis/l9279.htm)):

- o nome "Armarinho Ponto a Ponto" e o slogan "Aonde as ideias criam formas!";
- os logotipos (`public/images/logo.png`, `logo-fundobranco.png`, `logo-horizontal.png`);
- as artes dos banners (`public/images/hero-banner*`) e as padronagens (`public/images/lateral-horizontal*.png`);
- os textos e demais elementos visuais originais do site.

Reproduzir, modificar ou usar esses itens, comercialmente ou não, depende de **autorização prévia e por escrito**, respeitadas as exceções previstas em lei, como a citação com indicação da fonte (art. 46 da Lei nº 9.610/1998). **Quem replicar o projeto deve trocar a marca e as imagens pelas suas próprias.**

As fotos e artes das oficinas enviadas pelo painel ficam em `storage/app/public`, que não é versionado. Os direitos dessas imagens pertencem aos seus autores e parceiros.

### Componentes de terceiros

Cada componente de terceiros segue a sua própria licença:
- **Laravel e as demais dependências PHP e JavaScript:** licenças próprias, a maioria MIT. Veja em `vendor/` e `node_modules/` depois da instalação.
- **Fonte [Instrument Sans](https://github.com/Instrument/instrument-sans)** (em `public/fonts`): distribuída sob a [SIL Open Font License 1.1](https://openfontlicense.org), que permite uso e redistribuição mantendo o aviso de copyright e a licença da fonte.
- **Mercado Pago, Pix e WhatsApp:** marcas dos seus respectivos titulares. O uso aqui é só de integração e referência.

### Proteção de dados pessoais (LGPD)

O sistema coleta nome, WhatsApp, e-mail e CPF de participantes, o que está sujeito à Lei Geral de Proteção de Dados ([Lei nº 13.709/2018](https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm)). Quem colocar uma cópia deste sistema no ar passa a ser o **controlador** desses dados e é responsável por:
- informar a finalidade da coleta;
- restringir o acesso ao painel e aos backups;
- guardar os dados só pelo tempo necessário;
- atender pedidos de acesso, correção e exclusão feitos pelos titulares.

## Pendências conhecidas

- **Sessão expirada:** se a sessão do navegador expirar, o participante não consegue voltar à tela do Pix e precisa pedir ajuda à loja.
- **Exportação:** a lista de inscrições sai em CSV, não em XLSX, e ainda não existe lista de chamada para impressão.
- **Painel:** não permite duplicar ou arquivar oficinas.
