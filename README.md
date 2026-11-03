# SAC · Casa do Padeiro

Sistema para registrar e acompanhar as reclamações de clientes (SAC), com dashboard.
Os campos seguem a aba **SAC** da planilha `planilha SAC.xlsx`, e o dashboard reproduz a aba **Dashboard Final**.

- **Linguagem:** PHP 8.3+ (sem frameworks e sem Composer)
- **Banco:** o mesmo MongoDB do sistema de produção (homolog), na coleção `sac_reclamacoes`
- **Login:** os mesmos usuários do sistema de produção (coleção `usuarios`). Só administradores podem excluir reclamações.

## Telas

| Página | O que faz |
|---|---|
| `/` | Dashboard: total, em aberto, finalizados, tempo médio de resolução, produtos, lotes, SACs por mês, rankings de reclamações, produtos, lotes, soluções, tipos e datas |
| `/reclamacoes` | Lista com busca, filtros, paginação e exportação CSV (abre no Excel) |
| `/reclamacoes/nova` | Registro de nova reclamação |
| `/reclamacoes/{id}` | Edição e finalização (o tempo de resolução é calculado sozinho) |
| `/importar` | Importa a planilha .xlsx ou .csv, com prévia. Linhas que já foram importadas são ignoradas |

As listas de opções (tipos, sugestões de descrição e solução, dias para alerta) ficam em [config/opcoes.php](config/opcoes.php).

## Publicar na Vercel

1. Suba esta pasta para um repositório no GitHub e importe o repositório na Vercel (ou rode `vercel` nesta pasta).
2. Em **Project Settings → Environment Variables**, cadastre as variáveis abaixo com os **mesmos valores** do projeto do sistema de produção (homolog):
   - `MONGODB_URI`
   - `DB_NAME` (se não estiver definida, usa `casadopadeiro`, igual ao sistema de produção)
   - `JWT_SECRET`
3. Faça o deploy. O [vercel.json](vercel.json) já configura o runtime PHP (`vercel-php`) e as rotas.
4. Entre com o seu usuário do sistema de produção e importe a planilha em **Importar**.

> Se o MongoDB Atlas tiver restrição de IP, ele já precisa liberar a Vercel, como faz para o sistema de produção.

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
