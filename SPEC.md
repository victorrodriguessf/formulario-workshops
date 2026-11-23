# SPEC.md · Inscrições em oficinas · Armarinho Ponto a Ponto

Produto no ar: https://pontoapontonatal.com.br
Disciplina: Tópicos Avançados de Programação (UNI-RN), projeto final da Unidade II.

## 1. Problema

O Armarinho Ponto a Ponto, loja de artigos de costura e artesanato em Natal/RN, promove oficinas presenciais (bordado, crochê, patchwork). As inscrições eram feitas pelo WhatsApp: a equipe anotava nomes nas conversas, conferia comprovantes de Pix um a um e controlava as vagas de cabeça.

Consequências:
- vagas vendidas além do limite, ou vagas sobrando sem ninguém perceber;
- pagamentos sem confirmação clara, conferidos manualmente;
- reservas "para pagar na loja" que nunca eram pagas e seguravam a vaga;
- nenhuma visão de quantos inscritos e quanto foi arrecadado por oficina.

**Frase do projeto:** "A loja hoje controla as inscrições das oficinas pelo WhatsApp e perde tempo e controle de vagas e pagamentos. Com o sistema de inscrições, o participante se inscreve e paga sozinho, e a loja acompanha vagas e pagamentos em tempo real."

## 2. Público

- **Participante:** cliente da loja, em geral mulher adulta, que usa principalmente o celular e tem pouca familiaridade digital. Não precisa criar conta.
- **Organizador:** pessoa da loja ou instrutora parceira responsável por uma ou mais oficinas. Entra no painel com usuário e senha próprios.

## 3. Escopo do MVP

Dentro:
- vitrine pública das oficinas publicadas;
- inscrição em etapas (dados, revisão, pagamento), pensada para celular;
- pagamento por Pix (Mercado Pago) com confirmação automática, ou reserva para pagar em dinheiro na loja;
- e-mails de reserva e de confirmação;
- painel com login: cadastro completo de oficinas e gestão de inscrições;
- consulta de inscrições com busca, filtros, resumo e exportação CSV.

Fora:
- conta do participante e histórico de compras;
- venda de materiais pelo site (continua pelo WhatsApp);
- cartão de crédito e parcelamento;
- nota fiscal.

## 4. Entidades

```
User (organizador) 1 ── N Event (oficina) 1 ── N Registration (inscrição) 1 ── N PaymentAttempt
```

- **User:** nome, usuário, senha (hash).
- **Event:** dono (`user_id`), título, descrição, local, materiais, imagem, preço, início e fim, abertura e encerramento das inscrições, prazo para pagamento em dinheiro, limite de vagas, situação (rascunho, publicada, cancelada), pausa.
- **Registration:** oficina, nome, WhatsApp, e-mail, CPF criptografado, hash do CPF, forma de pagamento, situação (aguardando pagamento, confirmada, expirada, cancelada, em análise), pagamento recebido, preço acordado, protocolo.
- **PaymentAttempt:** cobranças Pix de uma inscrição, com valor, situação e identificador no Mercado Pago.

## 5. Requisitos funcionais

| # | Requisito |
|---|---|
| RF01 | O visitante vê as oficinas publicadas, com preço, data, local e vagas. |
| RF02 | O visitante se inscreve com nome, WhatsApp, CPF e e-mail, revisa os dados e confirma. |
| RF03 | O visitante paga por Pix (QR Code e copia e cola) ou reserva para pagar em dinheiro até o prazo da oficina. |
| RF04 | O sistema confirma o Pix sozinho, consultando o Mercado Pago, e envia e-mail de confirmação. |
| RF05 | O organizador entra com usuário e senha. |
| RF06 | O organizador cria, lista, edita e exclui as próprias oficinas. |
| RF07 | O organizador lista, consulta, confirma pagamento em dinheiro e remove inscrições das próprias oficinas. |
| RF08 | O organizador consulta inscrições com busca, filtro por oficina e situação, e vê um resumo com totais e valor arrecadado. |
| RF09 | O organizador exporta a lista filtrada em CSV. |

## 6. Regras de negócio

Cada regra é conferida na tela (HTML, JavaScript ou ocultando a ação) e no servidor (validação ou consulta com trava no banco).

| # | Regra | Tela | Servidor / banco |
|---|---|---|---|
| RN01 | **Vagas:** não há inscrição em oficina lotada. Canceladas e expiradas não ocupam vaga. | Formulário some e aparece o aviso de vagas esgotadas | Contagem com `lockForUpdate` na criação |
| RN02 | **CPF único por oficina:** o mesmo CPF não se inscreve duas vezes na mesma oficina. | Mensagem no campo CPF | Índice único `(event_id, cpf_lookup_hmac)` |
| RN03 | **Período de inscrição:** só dentro da janela de abertura e encerramento, sem pausa. | Formulário indisponível com o motivo | `registrationPeriodIsOpen()` conferido de novo ao gravar |
| RN04 | **Dinheiro com prazo:** pagamento em dinheiro só até o prazo; reserva não paga expira e libera a vaga. | Opção some após o prazo | Validação + comando `registrations:expire-cash` |
| RN05 | **Dono da oficina:** cada organizador só vê e altera as próprias oficinas e as inscrições delas. | Listas filtradas | Policies retornam 403 |
| RN06 | **Encerramento antes do início:** as inscrições devem encerrar até o início da oficina. | Campo com `max` e mensagem | Validação no controller |
| RN07 | **Vagas não abaixo do ocupado:** o limite não pode ser menor que as inscrições ativas. | Campo com `min` e mensagem | Validação no controller |
| RN08 | **Exclusão segura:** oficina com pagamento recebido não pode ser excluída; deve ser cancelada ou pausada. | Botão "Excluir" substituído por aviso | Controller recusa |
| RN09 | **Preço válido:** preço maior que zero; o preço da inscrição fica congelado mesmo se a oficina mudar de preço. | Campo obrigatório | Validação + `agreed_price_cents` |
| RN10 | **Pix só pelo banco:** o organizador não marca Pix como pago; só o Mercado Pago confirma. | Botão só existe para dinheiro | Validação no controller |

## 7. Critérios de aceitação

| # | Dado / quando | Então |
|---|---|---|
| CA01 | Oficina com todas as vagas ocupadas | O formulário não aparece e, se o envio for forçado, a inscrição é recusada. |
| CA02 | Segunda inscrição com o mesmo CPF na mesma oficina | "Já existe uma inscrição para este CPF neste curso." |
| CA03 | Organizadora B abre a URL de edição de oficina da organizadora A | Resposta 403. |
| CA04 | Organizadora B abre a lista ou o CSV de inscrições | Não vê inscrições das oficinas da organizadora A. |
| CA05 | Cadastro com encerramento das inscrições depois do início | "As inscrições devem encerrar até o início da oficina." |
| CA06 | Edição de limite para 2 numa oficina com 3 inscrições ativas | "O limite não pode ser menor que as 3 inscrições ativas desta oficina." |
| CA07 | Exclusão de oficina com pagamento recebido | "Esta oficina tem inscrições pagas. Desative-a (cancele ou pause) em vez de excluir." |
| CA08 | Filtro por oficina na tela de inscrições | Totais, valor arrecadado (só pagamentos recebidos) e vagas restantes refletem a oficina escolhida. |
| CA09 | Fluxo de inscrição em tela de 360 px | Todas as etapas funcionam sem rolagem horizontal. |

Todos os critérios de CA01 a CA08 estão cobertos por testes em `tests/Feature`.

## 8. Requisitos não funcionais

- Publicado com HTTPS em hospedagem compartilhada (Hostinger), MySQL hospedado.
- Nenhum segredo no repositório: credenciais só no `.env`, fora do Git.
- CPF criptografado; duplicidade checada por hash.
- Demonstração em banco separado com dados fictícios (`DemoSeeder`) e Mercado Pago em modo de teste ou gateway simulado.
- Testes automatizados rodando no GitHub Actions a cada push.
