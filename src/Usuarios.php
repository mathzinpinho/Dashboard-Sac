<?php
/*
 * Usuários do SAC, na coleção "usuarios" (a mesma do sistema de produção).
 *
 * - Criados pelo SAC: cargo "operador" e área "sac". Entram no SAC e, no sistema de produção,
 *   não veem nenhum módulo. Podem ser editados por completo aqui (dados, tipo, senha, exclusão).
 * - Vindos do sistema de produção: aqui só dá para ajustar quais telas do SAC eles veem.
 *   Senha, cargo e exclusão continuam sendo feitos pelo sistema de produção.
 *
 * Campos próprios do SAC (o sistema de produção ignora):
 * - telasSac: telas liberadas (ver TELAS). Sem o campo, todas as telas ficam liberadas.
 * - cargoSac: "administrador" ou "operador" dentro do SAC, para não mexer no "cargo" da produção.
 *
 * Atenção: se um administrador editar um usuário do SAC pela tela do sistema de produção, a área "sac"
 * vira "todas" lá (o sistema de produção não conhece essa área).
 */

final class Usuarios
{
    public const AREA = 'sac';
    public const SENHA_MIN = 8;

    /** tela => [rótulo, descrição] */
    public const TELAS = [
        'dashboard'   => ['Dashboard', 'Indicadores, gráficos e exportação'],
        'reclamacoes' => ['Reclamações', 'Lista, ver, editar e exportar'],
        'nova'        => ['Nova reclamação', 'Registrar reclamações'],
        'importar'    => ['Importar planilha', 'Trazer o histórico de uma planilha'],
    ];

    public static function listarTodos(): array
    {
        $lista = db()->find('usuarios');
        usort($lista, fn($a, $b) => strcoll(mb_strtolower($a['nome'] ?? $a['email'] ?? ''), mb_strtolower($b['nome'] ?? $b['email'] ?? '')));
        return $lista;
    }

    public static function buscar(string $id): ?array
    {
        return $id === '' ? null : db()->findOne('usuarios', ['id' => $id]);
    }

    /** Criado pelo SAC (editável por completo aqui) */
    public static function ehDoSac(array $u): bool
    {
        return ($u['area'] ?? '') === self::AREA;
    }

    public static function ehAdmin(array $u): bool
    {
        // sem cargo = administrador, como já era no login (usuários antigos do sistema de produção)
        return ($u['cargo'] ?? 'administrador') === 'administrador' || ($u['cargoSac'] ?? '') === 'administrador';
    }

    /** Telas que o usuário pode abrir (administrador: todas) */
    public static function telas(array $u): array
    {
        if (self::ehAdmin($u) || !isset($u['telasSac']) || !is_array($u['telasSac'])) return array_keys(self::TELAS);
        return array_values(array_intersect(array_keys(self::TELAS), $u['telasSac']));
    }

    /** Telas marcadas no cadastro (mesmo para administrador, para não perder a escolha se ele deixar de ser admin) */
    public static function telasMarcadas(array $u): array
    {
        if (!isset($u['telasSac']) || !is_array($u['telasSac'])) return array_keys(self::TELAS);
        return array_values(array_intersect(array_keys(self::TELAS), $u['telasSac']));
    }

    private static function hash(#[\SensitiveParameter] string $senha): string
    {
        // o sistema de produção (bcryptjs) grava "$2b$"; é o mesmo algoritmo do "$2y$" do PHP
        return preg_replace('/^\$2y\$/', '\$2b\$', password_hash($senha, PASSWORD_BCRYPT, ['cost' => 10]));
    }

    private static function validarSenha(array $in, array &$erros, bool $obrigatoria): ?string
    {
        $senha = (string) ($in['senha'] ?? '');
        if ($senha === '' && !$obrigatoria) return null;
        if (mb_strlen($senha) < self::SENHA_MIN) $erros['senha'] = 'A senha precisa ter pelo menos ' . self::SENHA_MIN . ' caracteres.';
        elseif ($senha !== (string) ($in['senha2'] ?? '')) $erros['senha2'] = 'As senhas não conferem.';
        return $senha;
    }

    /** Nome e e-mail validados; $id = usuário que está sendo editado (para o e-mail único) */
    private static function validarDados(array $in, array &$erros, ?string $id = null): array
    {
        $d = [
            'nome'  => trim((string) ($in['nome'] ?? '')),
            'email' => mb_strtolower(trim((string) ($in['email'] ?? ''))),
        ];
        if (mb_strlen($d['nome']) < 2) $erros['nome'] = 'Informe o nome.';
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            $erros['email'] = 'E-mail inválido.';
        } else {
            $outro = db()->findOne('usuarios', ['email' => $d['email']]);
            if ($outro && $outro['id'] !== $id) $erros['email'] = 'Já existe um usuário com este e-mail.';
        }
        return $d;
    }

    private static function telasDoFormulario(array $in): array
    {
        $marcadas = is_array($in['telas'] ?? null) ? $in['telas'] : [];
        return array_values(array_intersect(array_keys(self::TELAS), $marcadas));
    }

    /** Retorna [dados, erros] */
    public static function criar(array $in, array $criador): array
    {
        $erros = [];
        $d = self::validarDados($in, $erros);
        $senha = self::validarSenha($in, $erros, true);
        if ($erros) return [$d, $erros];

        try {
            db()->insert('usuarios', $d + [
                'senhaHash' => self::hash($senha),
                'cargo'     => 'operador',
                'cargoSac'  => 'operador',
                'area'      => self::AREA,
                'areas'     => [self::AREA],
                'telasSac'  => array_keys(self::TELAS),
                'criadoEm'  => self::agora(),
                'criadoPor' => $criador['nome'],
            ]);
        } catch (MongoDB\Driver\Exception\BulkWriteException) {
            // índice único de e-mail do sistema de produção (cadastro simultâneo)
            return [$d, ['email' => 'Já existe um usuário com este e-mail.']];
        }
        return [$d, []];
    }

    /**
     * Salva a edição. Usuário do sistema de produção: só as telas.
     * Retorna [usuário com as alterações, erros].
     */
    public static function salvar(array $u, array $in, array $editor): array
    {
        $erros = [];
        $campos = ['telasSac' => self::telasDoFormulario($in)];

        if (self::ehDoSac($u)) {
            $campos += self::validarDados($in, $erros, $u['id']);
            $admin = ($in['tipo'] ?? '') === 'administrador';
            if (!$admin && $u['id'] === $editor['id'] && self::ehAdmin($u)) {
                $erros['tipo'] = 'Você não pode tirar o seu próprio acesso de administrador.';
            }
            $campos['cargoSac'] = $admin ? 'administrador' : 'operador';
            $senha = self::validarSenha($in, $erros, false);
            if ($senha !== null && !$erros) $campos['senhaHash'] = self::hash($senha);
        }

        $novo = array_merge($u, $campos);
        unset($novo['senhaHash']);
        if ($erros) return [$novo, $erros];

        $campos['alteradoEm'] = self::agora();
        $campos['alteradoPor'] = $editor['nome'];
        try {
            db()->update('usuarios', $u['id'], $campos);
        } catch (MongoDB\Driver\Exception\BulkWriteException) {
            return [$novo, ['email' => 'Já existe um usuário com este e-mail.']];
        }
        return [$novo, []];
    }

    /** Exclui um usuário criado pelo SAC. Retorna a mensagem de erro, ou null se excluiu. */
    public static function excluir(array $u, array $editor): ?string
    {
        if (!self::ehDoSac($u)) return 'Este usuário é do sistema de produção e só pode ser excluído por lá.';
        if ($u['id'] === $editor['id']) return 'Você não pode excluir o seu próprio cadastro.';
        db()->delete('usuarios', $u['id']);
        return null;
    }

    /** No MongoDB a data vai como Date, igual ao sistema de produção */
    private static function agora(): mixed
    {
        return env('MONGODB_URI') ? new MongoDB\BSON\UTCDateTime() : date('c');
    }

    public static function dataIso(mixed $v): string
    {
        if ($v instanceof MongoDB\BSON\UTCDateTime) {
            return $v->toDateTime()->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('c');
        }
        return is_string($v) ? $v : '';
    }
}
