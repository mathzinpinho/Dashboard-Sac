# SAC · Casa do Padeiro

Sistema para registrar e acompanhar as reclamações de clientes (SAC), com dashboard.
Os campos seguem a aba **SAC** da planilha `planilha SAC.xlsx`, e o dashboard reproduz a aba **Dashboard Final**.

- **Linguagem:** PHP 8.3+ (sem frameworks e sem Composer)
- **Banco:** o mesmo MongoDB do sistema de produção (homolog), na coleção `sac_reclamacoes`
- **Login:** os mesmos usuários do sistema de produção (coleção `usuarios`). Só administradores podem excluir reclamações.
- **Usuários:** administradores cadastram usuários do SAC em `/usuarios`. Eles são gravados com cargo `operador` e área `sac`:
  entram no SAC e, no sistema de produção, não veem nenhum módulo. Se um administrador editar um desses usuários pela tela
  do sistema de produção, a área vira `todas` lá.

## Telas

| Página | O que faz |
|---|---|
| `/` | Dashboard: total, em aberto, finalizados, tempo médio de resolução, produtos, lotes, SACs por mês, rankings de reclamações, produtos, lotes, soluções, tipos e datas |
| `/reclamacoes` | Lista com busca, filtros, paginação e exportação CSV (abre no Excel) |
| `/reclamacoes/nova` | Registro de nova reclamação |
| `/reclamacoes/{id}` | Edição e finalização (o tempo de resolução é calculado sozinho) |
| `/importar` | Importa a planilha .xlsx ou .csv, com prévia. Linhas que já foram importadas são ignoradas |

**Valor gasto:** cada reclamação tem o campo *Valor gasto (R$)* (cupom, reembolso, reenvio...). O dashboard mostra o prejuízo total,
por mês, por reclamação e por produto (SAC com vários produtos divide o valor igualmente entre eles). A importação reconhece as colunas
"Valor", "Valor gasto", "Prejuízo" ou "Custo".

**Apagar todas as reclamações:** só administradores, em `/reclamacoes/apagar-tudo` (link no fim da lista). Exige digitar `APAGAR TODAS`,
a própria senha e marcar a confirmação, e ainda pergunta mais uma vez antes de apagar. Apaga também os anexos no Blob. Fica registrado
em `sac_auditoria` quem apagou, quando e quantas eram. A numeração dos SACs continua de onde parou.

**Fotos e vídeos:** cada reclamação aceita anexos (até 100 MB por arquivo; fotos grandes são reduzidas para 2000px antes do envio).
Os arquivos vão direto do navegador para o **Vercel Blob** (store `sac-anexos`, público com nomes aleatórios, região São Paulo),
autorizados por um token temporário gerado em `/anexos/token`. Anexos removidos de uma reclamação, ou de uma reclamação excluída,
são apagados do Blob. A biblioteca de envio fica em `assets/js/vendor/` (gerada do pacote oficial `@vercel/blob`).

`/saude` responde se o banco e os anexos estão funcionando (só sim/não).

As listas de opções (tipos, sugestões de descrição e solução, dias para alerta) ficam em [config/opcoes.php](config/opcoes.php).

## Publicação

O site está em **https://sac-casadopadeiro.vercel.app** (projeto `sac-casadopadeiro` na Vercel), ligado a este repositório:
**todo push no branch `main` publica automaticamente.** O [vercel.json](vercel.json) configura o runtime PHP (`vercel-php`) e as rotas.

Variáveis em **Project Settings → Environment Variables** (Production):

- `MONGODB_URI`: usuário próprio do SAC no mesmo cluster do sistema de produção. A senha deve ter só letras e números; caracteres como `@` e `#` quebram a URI.
- `JWT_SECRET`: chave própria do SAC (não precisa ser a do sistema de produção)
- `DB_NAME`: opcional; o padrão é `casadopadeiro`, o mesmo banco do sistema de produção

## Rodar no computador

```bash
php -S localhost:8090 router.php
```

Sem `MONGODB_URI` no arquivo `.env`, os dados ficam em arquivos na pasta `data/`, só para testes. Para criar um usuário de teste:

```bash
php scripts/criar-usuario.php email@teste.com senha "Nome"
```

Para usar o banco de verdade localmente, copie `.env.example` para `.env`, preencha as variáveis e habilite a extensão `mongodb` no PHP.

## Estrutura

```
api/index.php     rotas (ponto de entrada na Vercel)
src/              banco (Db), login (Auth), regras do SAC (Sac), importação/exportação (Planilha)
views/            telas em PHP
assets/           CSS, JS e logo
config/opcoes.php listas de opções
```
