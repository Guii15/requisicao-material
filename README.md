# Requisição de Material

Sistema interno para pedir, aprovar, separar e dar baixa em material do estoque, com assinatura eletrônica em cada etapa e PDF da requisição para imprimir.

Feito em Laravel 12 (PHP 8.4), Blade, Tailwind 4 e Alpine.js.

## Requisitos

- PHP 8.4 com as extensões `bcmath`, `dom`, `fileinfo`, `gd`, `intl`, `mbstring`, `pdo_sqlite` (ou `pdo_mysql`) e `zip`
- Composer 2
- Node 22 e npm (só para compilar o front)

## Instalação

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # se usar SQLite, que é o padrão do .env.example
php artisan migrate --seed       # cria as tabelas e carrega os feriados nacionais
npm ci
npm run build
```

Para desenvolver, `composer dev` sobe o servidor, a fila, os logs e o Vite juntos.

### Dados de demonstração

Em ambiente local (`APP_ENV=local`), o `php artisan migrate --seed` também cria setores, pessoas e requisições fictícias. Todos entram com a senha `demo1234`, por exemplo `demo.solicitante`, `demo.lider`, `demo.liderestoque`, `demo.estoque`, `demo.baixa` e `demo.admin`. A demonstração nunca roda em produção.

### Produção

No `.env` do servidor:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://endereco-do-sistema
SESSION_SECURE_COOKIE=true   # se o site roda em HTTPS
SESSION_ENCRYPT=true
```

Depois de cada atualização:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci && npm run build
php artisan optimize
```

Em produção, comandos destrutivos como `migrate:fresh` e `db:wipe` ficam bloqueados.

## Usuários

As pessoas, os setores e os aprovadores vêm da planilha de carga:

```bash
php artisan usuarios:importar caminho/planilha.xlsx --dry-run   # só mostra o que mudaria
php artisan usuarios:importar caminho/planilha.xlsx             # grava
```

- Aceita `.xlsx` (aba `USUARIOS`) ou `.csv` exportado do Excel.
- Colunas obrigatórias: `MATRICULA`, `NOME`, `LOGIN`, `SETOR`. Opcionais: `CARGO`, `PAPEL_SETOR` (`LIDER` ou `SUBLIDER`), `INCLUIR` (`SIM`/`NÃO`) e as marcações `ESTOQUE`, `LIDER_ESTOQUE`, `RESP_BAIXA` e `ADMIN` (`SIM`/`NÃO`).
- `--desativar-ausentes` desativa quem está ativo no sistema mas saiu da planilha.
- Se uma linha tiver erro, nada é gravado.
- Cada pessoa nova recebe uma senha provisória, listada no fim do comando para entregar a ela. O sistema pede a troca no primeiro acesso.

Quem aprova pedido de compra de funcionário (`aprova_compras`) é marcado direto no banco, porque a planilha ainda não tem essa coluna.

## Papéis

| Papel | O que faz |
|---|---|
| Qualquer pessoa ativa | Abre requisições e acompanha as próprias |
| Líder / sublíder do setor | Aprova ou reprova as requisições do setor, item por item |
| Estoque | Separa o que foi aprovado e confere a devolução do Teste |
| Líder do estoque | Libera o Uso e Consumo depois da separação e aprova os pedidos de setores sem aprovador |
| Responsável pela baixa | Dá baixa no Uso e Consumo e decide os pedidos de compra |
| Aprovador de compras | Aprova os pedidos de compra de funcionário, de qualquer setor |
| Admin | Vê tudo, cancela antes da entrega e dá baixa quando a própria responsável abriu o pedido |

Ninguém aprova o próprio pedido. Quem pode fazer o quê fica em `app/Policies/RequisicaoPolicy.php`; as telas só escondem os botões.

## Fluxo de cada tipo

- **Teste** (material emprestado para testar): aprovação do setor, depois separação e entrega na mesma tela, e por fim a devolução conferida item a item. O prazo máximo de devolução está na tabela `parametros` (`prazo_max_devolucao_dias`, em dias úteis).
- **Uso e Consumo**: aprovação do setor, separação, liberação do líder do estoque e baixa.
- **Pedido de compra de funcionário**: aprovação de quem aprova compras e decisão da responsável pela baixa, com a forma de pagamento e o print do pedido no WinThor.

Toda mudança de status passa por `app/Services/RequisicaoWorkflow.php`. Cada etapa grava uma assinatura com hash encadeado (`app/Services/AssinaturaService.php`), e qualquer alteração depois disso aparece como divergência na ficha da requisição.

## Testes

```bash
php artisan test
```

O GitHub roda os testes e o build do front em cada pull request e em cada push no `main` (`.github/workflows/testes.yml`).
