# Wiseller — API

API do Wiseller, um CRM simples para vendedores acompanharem leads e os contatos indicados por eles ("referidos"). O front fica em outro repositório (`sellmore`, Angular). Sistema antigo e em produção: mude com cuidado e sempre com teste.

## Stack

- Laravel 7.17, PHP `^7.2.5` no `composer.json`.
- Autenticação por JWT (`tymon/jwt-auth`), guard `api`.
- MySQL 5.7.
- `intervention/image` para miniaturas de upload.
- Sem filas, sem agendamentos, sem Redis (cache e sessão em arquivo, fila `sync`).

**Atenção à versão do PHP**: produção roda PHP 8.0, que o Laravel 7 não suporta. O ambiente local roda 7.4. Rotas de `Route::resource` com `{id}` e requisições com parâmetro vazio na query string funcionam em 7.4 e dão erro 500 em 8.0. A atualização do framework está planejada; até lá, valide qualquer mudança nas duas versões.

## Como rodar

O ambiente fica na pasta acima deste repositório (`docker-compose.yml` com API, front, MySQL e Mailpit).

```bash
docker compose up -d                              # na pasta acima
docker compose exec api vendor/bin/phpunit        # testes
docker compose exec api php artisan migrate       # migrations no banco local
```

- API em `http://localhost:8000`, MySQL em `localhost:3306` (banco `wiseller`), Mailpit em `http://localhost:8025`.
- O `.env` é criado na primeira subida do container; `APP_KEY` e `JWT_SECRET` são gerados sozinhos.
- Para rodar a suíte em PHP 8.0, use uma imagem `php:8.0-cli` com `pdo_mysql` e `gd` na mesma rede do compose.

## Domínio

| Conceito | Tabela | Model |
|---|---|---|
| Vendedor (usuário do sistema) | `usuario` | `User` (autenticação) e `Funcionario` (mesma tabela) |
| Cliente / lead / referido | `customers` | `Customers` |
| Telefone do cliente | `customers_phone` | `Phone` |
| Compromisso da agenda | `calendar` | `Calendar` |
| Roteiro de venda do vendedor | `strategy` | `Strategy` |
| Registro de acesso | `usuario_log` | `Log` |

- **Lead e referido são a mesma tabela.** Um cliente sem `id_parent` é um lead (raiz); com `id_parent`, é um referido indicado por aquele cliente. Um referido que indica alguém também aparece como raiz na listagem.
- **Status do atendimento** (`customers.status`): `a` aberto, `ld` ligar depois, `pc` problemas com cartão, `n` não tem interesse, `c` comprou.
- **Arquivar** é `bo_ativo = 0`; nada é apagado. **Preferência** é `bo_preference`.
- Cada vendedor só enxerga os próprios clientes (`id_usuario`). Nem toda rota confere isso; ao mexer, confira.
- Colunas `bo_*` são booleanas; `id_*` são chaves estrangeiras.

## Estrutura

- `routes/api.php`: todas as rotas. Públicas no topo; o restante dentro do grupo com o middleware `apiJwt` (`app/Http/Middleware/apiProtectededRoute.php`), que responde 400 quando o token falta ou é inválido.
- `app/Http/Controllers/`: um controller por recurso. A regra de negócio está neles e nos models em `app/` (métodos estáticos).
- `app/Http/Helpers.php`: classe global `Helpers`, carregada pelo `composer.json` (`autoload.files`). Datas, telefones, CPF, identificação de navegador.
- `app/Library/`: integrações externas (Trello em `Jira.php`, CEP, CNPJ). Só o Trello é usado pelas rotas.
- `app/Console/BloqueioDeComandosDestrutivos.php`: impede comandos que apagam tabelas fora do banco de testes.
- Respostas seguem o formato `{"response": "mensagem"}` para avisos e `{"dados": ...}` para conteúdo, com status 400 para erro de regra.

## Banco e migrations

As migrations são a fonte única da estrutura do banco.

- `database/migrations/2020_01_01_000000_cria_estrutura_inicial.php` cria as tabelas a partir de `database/schema/estrutura-inicial.sql`, que é a estrutura exata de produção. Em banco que já tem as tabelas ela não faz nada.
- **Toda mudança de banco é uma migration nova.** Nunca altere a estrutura à mão nem edite a migration inicial.
- **Nunca use `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `migrate:rollback` ou `db:wipe`** fora do banco de testes. Eles são bloqueados em qualquer banco cujo nome não termine em `_test`; não contorne o bloqueio.
- Prefira migrations aditivas (coluna nova, tabela nova). Remover coluna ou tabela exige confirmação explícita de quem pediu a tarefa.
- No deploy, o servidor faz um backup completo do banco e só então roda `artisan migrate --force`.

## Testes

```bash
docker compose exec api vendor/bin/phpunit
docker compose exec api vendor/bin/phpunit --filter ClientesCadastro
```

- `tests/Feature/`: testes de API por HTTP, um arquivo por área. Base: `Tests\ApiTestCase`.
- `tests/Unit/`: funções puras (`Helpers`, bloqueio de comandos).
- Usam o MySQL `wiseller_test`, montado pelas migrations. Cada teste esvazia as tabelas. Há uma trava que recusa rodar em banco que não termine em `_test`.
- **Os testes usam só HTTP e SQL direto** (`tests/Support/CriaDados.php`), sem factories nem recursos de teste do framework, para continuarem válidos depois da atualização do Laravel. Mantenha assim.
- `tests/Support/ChamaApi.php` limpa a autenticação entre requisições do mesmo teste; use sempre `chamarApi()` e `enviarArquivoParaApi()` em vez de `$this->json()` direto.
- Nenhum teste envia e-mail (`Mail::fake()`, `MAIL_MAILER=array`) nem chama o Trello (`API_TRELLO_URL` aponta para um endereço que não responde no `phpunit.xml`).
- `RotasComDefeitoConhecidoTest` registra rotas que hoje dão erro 500. Ao consertar uma, tire-a de lá e escreva o teste do comportamento correto.
- Os testes descrevem o comportamento correto, que é o do PHP 7.4. Não ajuste um teste para aceitar um erro 500 que só existe em PHP 8.0.

## Deploy

`.github/workflows/deploy.yml`: todo push em `master` ou `main` publica em produção.

1. Monta o `vendor` com Composer.
2. Renomeia `server.php` para `index.php`, porque em produção a raiz do site é a pasta do projeto, não `public/`.
3. Envia por `rsync` com `--delete`. Ficam preservados no servidor: arquivos com ponto na raiz (`.env`, `.htaccess`), `storage/`, `img/`, `public/img/` e os `error_log`.
4. Pede ao servidor as migrations (backup antes).
5. Confere `GET /api/currentVersion`.

Consequências práticas:

- Variável nova no `.env` precisa ser criada à mão no servidor; o deploy não toca no `.env`.
- Uploads são gravados em `img/<pasta>` (relativo à raiz do projeto) e em `storage/app/public`. Se criar outro local de upload, proteja-o no workflow.
- A versão exibida ao usuário está fixa na rota `currentVersion` em `routes/api.php`; atualize-a ao publicar algo que o usuário perceba.

## Convenções

- Código e mensagens ao usuário em português; nomes de tabelas e colunas como já estão (mistura de português e inglês).
- Clean Code e SOLID: funções pequenas, nomes descritivos, sem números mágicos, retorno antecipado em vez de `else` e de condicionais aninhadas.
- Commits: mensagem começando com `[branch]`, um commit por mudança lógica, sem linhas de coautoria.
- Não rodar o Pint.
- Não acionar e-mail real nem o Trello a partir do ambiente local: confira o `.env` antes de testar esses fluxos manualmente.
