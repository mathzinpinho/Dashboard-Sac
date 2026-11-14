# SAC · Casa do Padeiro

Sistema para registrar e acompanhar as reclamações de clientes (SAC), com dashboard.
Os campos seguem a aba **SAC** da planilha `planilha SAC.xlsx`, e o dashboard reproduz a aba **Dashboard Final**.

- **Linguagem:** PHP 8.3+ (sem frameworks e sem Composer)
- **Banco:** o mesmo MongoDB do sistema de produção (homolog), na coleção `sac_reclamacoes`
- **Login:** os mesmos usuários do sistema de produção (coleção `usuarios`). Só administradores podem excluir reclamações.
- **Usuários:** `/usuarios` (só administradores) lista todos os usuários da coleção, do SAC e do sistema de produção.
  Em **Editar**, o administrador liga e desliga, com chaves, as telas que cada usuário vê (Dashboard, Reclamações,
  Nova reclamação, Importar planilha). As mudanças valem no próximo clique do usuário, sem sair e entrar.
  - Usuários criados pelo SAC (cargo `operador`, área `sac`) podem ter nome, e-mail, tipo e senha alterados e ser excluídos.
    No sistema de produção eles não veem nenhum módulo. Se um administrador editar um desses usuários pela tela do sistema
    de produção, a área vira `todas` lá.
  - Usuários do sistema de produção: aqui se ajustam o tipo (só dentro do SAC) e as telas. Nome, e-mail e senha continuam
    sendo alterados por lá. Um administrador da produção pode virar Operador no SAC e continua administrador lá.
    "Excluir usuário" só tira o acesso ao SAC (`removidoSac`): ele não entra mais aqui, o login da produção continua, e dá
    para desfazer em "Restaurar acesso" (filtro "Removidos" na lista).
  - Campos próprios do SAC na coleção `usuarios` (o sistema de produção ignora): `telasSac` (telas liberadas; sem o campo,
    todas) e `cargoSac` (`administrador` ou `operador` dentro do SAC; quando existe, vale mais que o `cargo` da produção,
    que nunca é alterado).
  - Administradores (`cargoSac` = `administrador`, ou, sem `cargoSac`, `cargo` = `administrador`) veem todas as telas. Ninguém pode se rebaixar nem se excluir.

## Telas

| Página | O que faz |
|---|---|
| `/` | Dashboard: total, em aberto, finalizados, tempo médio de resolução, produtos, lotes, SACs por mês, rankings de reclamações, produtos, lotes, soluções, tipos e datas |
| `/reclamacoes` | Lista com busca, filtros, paginação e exportação em Excel (.xlsx) detalhada, com aba de resumo |
| `/reclamacoes/nova` | Registro de nova reclamação |
| `/reclamacoes/{id}` | Edição e finalização (o tempo de resolução é calculado sozinho) |
| `/importar` | Importa a planilha .xlsx ou .csv, com prévia. Linhas que já foram importadas são ignoradas |
| `/usuarios` | Lista de usuários; `/usuarios/{id}` edita tipo, telas liberadas, senha e exclusão (só administradores) |

**Valor gasto:** cada reclamação tem o campo *Valor gasto (R$)* (cupom, reembolso, reenvio...). O dashboard mostra o custo total,
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
