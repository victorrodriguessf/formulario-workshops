# IA.md · Registro do uso de Inteligência Artificial

Este arquivo registra o que foi pedido às ferramentas de IA, o que foi aceito, o que foi rejeitado ou corrigido, e por quê.

## Ferramentas usadas

| Ferramenta | Onde foi usada |
|---|---|
| Laravel Boost (MCP) | Guias do framework e documentação dentro do agente (ver `AGENTS.md`) |
| Claude (Anthropic) | Adequação do projeto aos requisitos da disciplina (nov/2026) |
| <!-- COMPLETAR: Codex, Cursor, Copilot etc., se usou na primeira versão --> | <!-- COMPLETAR --> |

## Como a IA foi usada

1. **Especificar antes de codar.** O plano inicial (`IMPLEMENTATION_PLAN_PONTO_A_PONTO.md`) foi escrito antes do código e serviu de contexto para o agente.
2. **Instruir o agente.** `AGENTS.md` diz como rodar o projeto, as convenções e o que o agente não pode fazer.
3. **Construir em partes pequenas**, com testes a cada etapa.
4. **Verificar de fora:** os testes automatizados, o GitHub Actions e o uso real da loja decidem se algo funciona, não a resposta da IA.

## Primeira versão (set–out/2026)

<!-- COMPLETAR com exemplos reais. Sugestões do que contar:
- Um trecho que a IA gerou e você aceitou (ex.: integração com a Orders API do Mercado Pago, validação de CPF).
- Um trecho que você recusou ou reescreveu, e por quê (ex.: algo que expunha dado pessoal, que não funcionava na Hostinger, que confirmava Pix sem consultar o banco).
- Um erro da IA que só apareceu em produção ou nos testes.
-->

## Adequação para a disciplina (23/11/2026)

### O que foi pedido

> "Usar o projeto da loja como case para o trabalho final, fazendo as alterações necessárias para cumprir os requisitos P1 a P9."

### O que foi aceito

| Pedido / proposta | Resultado | Como foi verificado |
|---|---|---|
| Cumprir P3 ("cada usuário só mexe nos próprios dados") | Oficinas ganharam dono (`user_id`). Policies retornam 403 para dados de outro organizador; lista e CSV de inscrições filtrados pelo dono. | `OrganizerOwnershipTest` (4 testes) |
| Mais regras de negócio, na tela e no servidor | Encerramento das inscrições até o início; limite de vagas não menor que as inscrições ativas; oficina com pagamento recebido não pode ser excluída. | `CourseBusinessRulesTest` |
| Resumo da consulta (P7) | Os totais passaram a seguir os filtros e ganharam valor arrecadado e vagas restantes. | Teste com dois organizadores e soma conferida à mão (R$ 230,00) |
| Demonstração sem dados reais | `DemoSeeder` com dois organizadores, quatro oficinas e inscrições fictícias, bloqueado em produção. | `DemoSeederTest` |
| Testes no GitHub Actions | Workflow `.github/workflows/tests.yml` com Pint e PHPUnit | Execução no GitHub |

### O que foi rejeitado ou corrigido

- **Suposição errada da IA sobre o projeto.** Antes de ler o código, a IA disse que provavelmente faltavam testes e escreveu um SPEC supondo "e-mail único por oficina". Ao abrir o repositório, havia 69 testes e a regra real é **CPF único por oficina**. O SPEC foi refeito a partir do código.
- **Botão desabilitado com dica só no `title`.** A primeira versão desabilitava o botão "Excluir" e explicava o motivo num `title`, que não aparece no celular (não existe passar o mouse). Foi trocado por um texto visível.
- **Arquivo alterado sem querer.** Ao instalar dependências, o `npm install` alterou o `package-lock.json`. A alteração não tinha relação com a tarefa e foi descartada antes do commit.
- **Testes dependendo do build do front.** A suíte falhava sem o `npm run build` (o build busca uma fonte externa). Em vez de exigir o build, os testes passaram a usar `withoutVite()`, já que verificam HTML e regras, não CSS.
- **Senha da demonstração.** A ideia de deixar uma senha fixa no seeder foi descartada (o projeto não pode ter segredo no código). A senha vem de `DEMO_ADMIN_PASSWORD` ou é gerada na hora.

### Riscos que conferi manualmente

- A migration atribui as oficinas antigas ao **primeiro** administrador. Em produção existe um só, então nada muda para a loja.
- O `DemoSeeder` apaga e recria as inscrições das oficinas de demonstração, por isso ele se recusa a rodar com `APP_ENV=production`, a não ser que `ALLOW_DEMO_SEED=true` seja definido de propósito.
