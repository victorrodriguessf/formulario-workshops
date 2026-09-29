# Decisões Técnicas

## 1. Stack e Versões
- **Linguagem**: PHP 8.3+ (Atualmente usando a versão 8.5+ disponível localmente para compatibilidade futura).
- **Framework**: Laravel na sua versão mais atual compatível com os requisitos (v13+).
- **Banco de Dados**: MySQL 8.x.
- **Estilização**: CSS Vanilla (Puro), responsivo e com componentes reutilizáveis, sem Tailwind.
- **Interatividade**: Vanilla JavaScript focado em melhoria progressiva.

## 2. Bibliotecas Propostas
- **Pagamentos**: A aplicação possui um gateway `fake` para desenvolvimento e integração nativa com a Orders API do Mercado Pago para Pix.
- **Testes**: PHPUnit.
- **Relatórios**: Biblioteca mantida e recomendada (ex: `phpoffice/phpspreadsheet`) será validada para XLSX.

## 3. Compatibilidade de Hospedagem (Hostinger Premium)
A aplicação está sendo desenvolvida com arquitetura clássica que não necessita de WebSockets, SSR via Node.js ou daemons do tipo RabbitMQ/Redis em produção. Utilizaremos o sistema de CRON nativo de hospedagens web com o agendador do Laravel, além de cache baseado em arquivos ou banco de dados.

## 4. Proteção de Desenvolvimento
- O `.env.example` usa `PAYMENT_GATEWAY=fake` e `MP_ENVIRONMENT=sandbox` como padrões seguros.
- A homologação local pode usar credenciais de teste. Credenciais de produção são recusadas fora de `APP_ENV=production`, e credenciais de sandbox são recusadas em produção.

## 5. Pendências do Negócio (Seção 12)
Defaults adotados para o desenvolvimento local:
- **Limite de vagas**: O MVP não possuirá travas rígidas de vagas no checkout; inscrições são ilimitadas enquanto o evento estiver aberto.
- **Prazo Pix**: 60 minutos como prazo operacional seguro.
- **Cancelamentos e Devoluções**: Tratamento operacional e manual; a aplicação deve refletir o status de reconciliação.
- **Proteção de Dados**: Texto simples indicando por que os dados são processados. Não haverá consentimento embutido para marketing no mesmo fluxo (finalidade distinta).
- **Lista de Chamada**: Organização alfabética pt-BR na exportação de presença.

## 6. Contrato Pix

O adaptador usa a **Orders API** (`/v1/orders`) para criar e consultar o Pix. O ID da Order é armazenado em `payment_attempts.order_id`. A confirmação local só ocorre depois de validar o estado, o valor e a referência externa consultados diretamente no Mercado Pago. As notificações de produção devem usar o tópico **Order (Mercado Pago)**.
