# Deploy na Hostinger (hospedagem compartilhada com hPanel)

Processo usado na primeira publicação, em 29/09/2026. Nos exemplos, troque `USUARIO` pelo usuário da hospedagem (formato `u123456789`) e `DOMINIO` pelo domínio do site.

## Particularidades da Hostinger

- **PHP 8.4 ou superior.** As dependências (Symfony 8) não rodam no 8.3. Escolha a versão em Sites → Gerenciar → Avançado → Configuração do PHP.
- **`exec`, `symlink` e `proc_open` são bloqueados.** Consequências:
  - `php artisan storage:link` falha, então o atalho das imagens é criado com `ln -s`.
  - `php artisan schedule:run` não funciona, então o cron chama os comandos diretamente.
- **Não há Node.** O CSS e o JS são compilados no computador antes do envio.
- **O projeto fica fora do `public_html`.** O `public_html` vira um atalho para a pasta `public` do projeto, e assim o `.env` e o código nunca ficam acessíveis pela web.

## 1. Gerar o pacote no computador

Faça numa cópia separada, para não trocar o `vendor` de desenvolvimento:

```bash
rsync -a --exclude='.git' --exclude='node_modules' --exclude='vendor' --exclude='.env' \
  --exclude='database/database.sqlite' --exclude='public/storage' --exclude='public/hot' \
  --exclude='bootstrap/cache/*.php' --exclude='storage/logs/*.log' \
  --exclude='storage/framework/sessions/*' --exclude='storage/framework/views/*' \
  --exclude='storage/framework/cache/data/*' --exclude='storage/app/public/events' \
  ./ /tmp/formulario-workshops/
cd /tmp/formulario-workshops
composer install --no-dev --optimize-autoloader --no-interaction --no-scripts
npm ci && npm run build && rm -rf node_modules
zip -rqX ../formulario-workshops-deploy.zip .
```

Confira que o `.zip` **não** contém `.env` nem `database.sqlite`.

## 2. Preparar o hPanel

1. **PHP 8.4** (ver acima).
2. **Banco MySQL:** em Bancos de Dados → Gerenciamento, crie o banco e o usuário. A Hostinger adiciona o prefixo `USUARIO_` aos nomes.
3. **SSH:** ative em Avançado → Acesso SSH.

## 3. Enviar o projeto

1. No Gerenciador de Arquivos, dentro de `domains/DOMINIO/`, no mesmo nível do `public_html` e **não dentro dele**, envie o `.zip` e extraia. Depois renomeie a pasta para `formulario-workshops`.
2. No SSH, aponte o domínio para a pasta `public` (a página padrão da Hostinger fica guardada):

```bash
cd ~/domains/DOMINIO
mv public_html public_html_padrao
ln -s formulario-workshops/public public_html
```

## 4. Configurar o `.env`

```bash
cd ~/domains/DOMINIO/formulario-workshops
nano .env        # cole o modelo abaixo e preencha
chmod 600 .env
```

```ini
APP_NAME="Armarinho Ponto a Ponto"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://DOMINIO
APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=pt_BR
APP_TIMEZONE=America/Sao_Paulo
LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=USUARIO_nomedobanco
DB_USERNAME=USUARIO_nomedousuario
DB_PASSWORD='senha-entre-aspas-simples'

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.gmail.com
MAIL_PORT=465
MAIL_USERNAME=email-da-loja@gmail.com
MAIL_PASSWORD=senhadeappdogoogle
MAIL_FROM_ADDRESS=email-da-loja@gmail.com
MAIL_FROM_NAME="${APP_NAME}"

PAYMENT_GATEWAY=mercadopago
MP_ENVIRONMENT=production
PIX_RECEIVER_NAME="Nome exibido para quem paga"
MP_ACCESS_TOKEN=token-da-aba-credenciais-produtivas
MP_WEBHOOK_SECRET=
```

Cuidados com o `.env`:
- **Senhas com `#`** ficam entre aspas simples. Sem as aspas, tudo depois do `#` é ignorado e o banco recusa o acesso.
- **Gmail:** use uma **senha de app** (Conta Google → Segurança → Senhas de app, com a verificação em duas etapas ativada), sem os espaços. A senha normal da conta é recusada.
- **Depois de qualquer mudança no `.env`**, rode `php artisan config:cache`.

## 5. Instalar

```bash
cd ~/domains/DOMINIO/formulario-workshops
php artisan key:generate --force
php artisan migrate --force
(cd public && ln -s ../storage/app/public storage)
php artisan admin:create          # uma vez para cada administrador
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Os administradores entram com **usuário** e senha. A senha precisa ter 8+ caracteres, com maiúscula, minúscula, número e símbolo.

## 6. Tarefas agendadas (cron)

Em Avançado → Tarefas Cron, crie duas tarefas do tipo **Personalizado**, com a opção "Uma vez por minuto" (`* * * * *`):

```bash
/usr/bin/php /home/USUARIO/domains/DOMINIO/formulario-workshops/artisan webhook:process >/dev/null 2>&1
/usr/bin/php /home/USUARIO/domains/DOMINIO/formulario-workshops/artisan registrations:expire-cash >/dev/null 2>&1
```

- A primeira confere os Pix pendentes no Mercado Pago e reenvia e-mails que falharam.
- A segunda expira as reservas em dinheiro vencidas.

Confira com `crontab -l`.

## 7. Webhook do Mercado Pago

1. No painel de desenvolvedor, abra a aplicação → **Webhooks** → modo **Produção**.
2. Preencha a URL `https://DOMINIO/webhook/mercadopago` e escolha o evento **Order (Mercado Pago)**.
3. Copie a assinatura secreta para `MP_WEBHOOK_SECRET` e rode `php artisan config:cache`.

## 8. Verificações

- `https://DOMINIO/.env` deve responder **403**.
- Envio de e-mail:
  ```bash
  php artisan tinker --execute='Illuminate\Support\Facades\Mail::raw("Teste", fn ($m) => $m->to(config("mail.from.address"))->subject("Teste do site"));'
  ```
- Faça uma inscrição real com uma oficina de valor baixo e pague o Pix. A tela deve mudar sozinha para "inscrição confirmada" e o e-mail de confirmação deve chegar.

## Publicar atualizações

- **Poucos arquivos alterados:**
  1. No computador, rode `npm run build` e zipe só os arquivos alterados com a pasta `public/build`, mantendo os caminhos.
  2. Envie o `.zip` para dentro de `formulario-workshops` e extraia pelo SSH (`unzip -o`); o Gerenciador de Arquivos criaria uma pasta nova.
  3. Rode `php artisan route:cache && php artisan view:cache && php artisan config:cache`.
- **Versão completa:** gere o pacote do passo 1, extraia por cima da pasta do projeto (o `.env` e `storage/app/public` são preservados) e rode `./deploy.sh`.
