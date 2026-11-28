# Wiseller — API

API do Wiseller, um CRM simples para vendedores acompanharem leads e os contatos indicados por eles ("referidos"). O front fica em outro repositório (`sellmore`, Angular). Sistema antigo e em produção: mude com cuidado e sempre com teste.

## Stack

- Laravel 13, PHP 8.5 (o `composer.json` aceita 8.3 ou mais novo).
- Autenticação por JWT (`tymon/jwt-auth` 2), guard `api`.
- MySQL 5.7.
- `intervention/image` 4 para miniaturas de upload.
- Sem filas, sem agendamentos, sem Redis (cache e sessão em arquivo, fila `sync`).

O projeto foi atualizado do Laravel 7 em 2026-11. Os models continuam em `App\` (não em `App\Models`), e o `App\User` precisa manter esse nome: ele entra na assinatura dos tokens de login já emitidos.

## Como rodar

O ambiente fica na pasta acima deste repositório (`docker-compose.yml` com API, front, MySQL e Mailpit).

```bash
docker compose up -d                              # na pasta acima
docker compose exec api vendor/bin/phpunit        # testes
docker compose exec api php artisan migrate       # migrations no banco local
```

- API em `http://localhost:8000`, MySQL em `localhost:3306` (banco `wiseller`), Mailpit em `http://localhost:8025`.
- O `.env` é criado na primeira subida do container; `APP_KEY` e `JWT_SECRET` são gerados sozinhos.

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
- Cada vendedor só enxerga e altera os próprios dados (`id_usuario`). Toda rota que recebe um id busca o registro já filtrando pelo usuário logado (`Customers::doUsuario`); `SegurancaEntreContasTest` cobre isso. Rota nova precisa do mesmo cuidado e de um teste lá.
- `customers.status_alterado_em` guarda quando o status mudou pela última vez (`Customers::marcarMudancaDeStatus`); o painel de resultados conta atendimentos e vendas por essa data.
- Colunas `bo_*` são booleanas; `id_*` são chaves estrangeiras.

## Estrutura

- `bootstrap/app.php`: registro das rotas, do apelido `apiJwt` e do limite de 60 requisições por minuto da API. Não há mais `Kernel` nem `RouteServiceProvider`.
- `config/`: os arquivos aceitam também os nomes antigos de variável (`CACHE_DRIVER`, `FILESYSTEM_DRIVER`), porque o `.env` de produção não é tocado pelo deploy.
- `routes/api.php`: todas as rotas. Públicas no topo; o restante dentro do grupo com o middleware `apiJwt` (`app/Http/Middleware/apiProtectededRoute.php`), que responde 400 quando o token falta ou é inválido.
- `app/Http/Controllers/`: um controller por recurso. A regra de negócio está neles e nos models em `app/` (métodos estáticos).
- `app/Http/Helpers.php`: classe global `Helpers`, carregada pelo `composer.json` (`autoload.files`). Datas, telefones, CPF, identificação de navegador.
- `app/Library/`: integrações externas (Trello em `Jira.php`, CEP, CNPJ). Só o Trello é usado pelas rotas.
- `app/Resultados.php` (números do vendedor, `GET /api/resultados`) e `app/MetricasDeUso.php` (números da plataforma para o administrador, `GET /api/metricas-de-uso`).
- `app/LinkDeIndicacao.php` e `IndicacaoController`: link público pelo qual o cliente de um vendedor informa quem indica. `GET` e `POST /api/indicacao/{codigo}` não exigem login, têm limite próprio de requisições e nunca revelam se um telefone já está na carteira do vendedor. Quando entra contato novo, o vendedor recebe um e-mail (`IndicacoesRecebidasPeloLink`).
- **E-mails**: todos usam o layout de `resources/views/components/email/` (`<x-email.layout>`, `<x-email.titulo>`, `<x-email.paragrafo>`, `<x-email.botao>`, `<x-email.link>`, `<x-email.itens-com-visto>`), com as cores do site em `app/Mail/AparenciaDoEmail.php`. Os clientes de e-mail ignoram CSS, então o estilo vai direto em cada elemento. E-mail novo: monte com esses componentes.
- `app/CampanhaDeRetorno.php` e `php artisan campanha:volta-ao-wiseller`: convite em lotes para quem não acessa há 30 dias (`--lote`, `--limite-diario`, `--simular`, `--para=<e-mail>` para teste). Quem recebeu fica em `envio_de_campanha` e não recebe de novo. Endereço que o servidor recusa por não existir fica registrado com `motivo_da_recusa` e o lote segue; outras falhas (limite da caixa de saída, conexão) ou 5 recusas seguidas interrompem o lote, e o próximo retoma. Domínio que não recebe e-mail é pulado (`VerificadorDeDominioPorDns`).
- `app/Mail/NaoEnviaParaEnderecoDeTeste.php` cancela qualquer e-mail cujos destinatários estejam todos em domínio reservado para teste (`.test`, `.example`...), como as contas criadas pelos testes de ponta a ponta.
- `ContaController`: exportação dos clientes em CSV, preferência do lembrete e exclusão da conta (apaga tudo do vendedor, exige a senha).
- `app/Console/Commands/EnviaLembretesDasLigacoesDoDia.php`: `php artisan lembretes:ligacoes-do-dia` envia a cada vendedor as ligações agendadas para o dia. Roda por cron no servidor, uma vez por dia (`--simular` só informa quantos e-mails sairiam); o link de cancelamento do e-mail é assinado (`LembreteDeLigacoes`).
- `config/wiseller.php`: endereços da plataforma, da API e do site usados nos e-mails e nos links.
- A recuperação de senha usa link assinado com a chave da aplicação e a senha atual: vale por um dia e deixa de valer depois de usado.
- `app/Console/BloqueioDeComandosDestrutivos.php`: impede comandos que apagam tabelas fora do banco de testes.
- `GET /api/customers` aceita `page` e `perPage` (padrão 8, máximo 50) e, nesse caso, devolve também `total`; sem `page`, devolve todos os leads. O front sempre pagina.
- **Cuidado com consultas dentro de laços**: listagem, busca, "ligar depois", agenda e árvore de indicação buscam telefones e referidos em lote (`Phone::agrupadosPorCliente`, `Customers::getAll`). `ConsultasAoBancoTest` falha se o número de consultas voltar a crescer com a quantidade de clientes.
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
- Teste que simula data (`travelTo`) precisa fazer isso antes de criar o usuário autenticado, senão o token é recusado.
- Nenhum teste envia e-mail (`Mail::fake()`, `MAIL_MAILER=array`) nem chama o Trello (`API_TRELLO_URL` aponta para um endereço que não responde no `phpunit.xml`).
- PHPUnit 12: provedor de dados é método `public static` e é ligado ao teste pelo atributo `#[DataProvider('nome')]`, não por comentário.

## Deploy

`.github/workflows/deploy.yml`: todo push em `master` ou `main` publica em produção.

1. Monta o `vendor` com Composer, sem pacotes de desenvolvimento.
2. Envia por `rsync` com `--delete`. Ficam preservados no servidor: arquivos com ponto na raiz (`.env`, `.htaccess`), `storage/`, `img/`, `public/img/` e os `error_log`.
3. Pede ao servidor o fechamento do deploy: ele troca a versão do PHP do site se `deploy/versao-do-php` pedir outra, faz o backup do banco e roda as migrations.
4. Confere `GET /api/currentVersion`.

Em produção a raiz do site é a pasta do projeto, não `public/`; por isso existe o `index.php` na raiz, que só repassa para `public/index.php`.

Consequências práticas:

- Variável nova no `.env` precisa ser criada à mão no servidor; o deploy não toca no `.env`.
- Para mudar a versão do PHP de produção, altere `deploy/versao-do-php` (formato do cPanel, hoje `ea-php85`) junto com o `PHP_VERSION` do workflow e a imagem do Docker local.
- Uploads são gravados em `img/<pasta>` (relativo à raiz do projeto) e em `storage/app/public`. Se criar outro local de upload, proteja-o no workflow.
- A versão exibida ao usuário está fixa na rota `currentVersion` em `routes/api.php` (`version` e `resume`); o `link` vem de `config('wiseller.pagina_das_novidades')`, usado também no e-mail da campanha. Para avisar de uma versão nova: crie no Notion a página `Wiseller - Versão X` como primeiro item da lista em "Wiseller - Novidades" (ela já fica pública em `forested-file-aaa.notion.site`), troque o número e o resumo na rota e o endereço público dessa página em `config/wiseller.php`. O front mostra o aviso quando o número muda e o botão "Ver as novidades" abre o `link`.

## Convenções

- Código e mensagens ao usuário em português; nomes de tabelas e colunas como já estão (mistura de português e inglês).
- Clean Code e SOLID: funções pequenas, nomes descritivos, sem números mágicos, retorno antecipado em vez de `else` e de condicionais aninhadas.
- Commits: mensagem começando com `[branch]`, um commit por mudança lógica, sem linhas de coautoria.
- Não rodar o Pint.
- Não acionar e-mail real nem o Trello a partir do ambiente local: confira o `.env` antes de testar esses fluxos manualmente. Isso inclui o comando `lembretes:ligacoes-do-dia`.
