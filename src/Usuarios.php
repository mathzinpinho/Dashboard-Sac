<?php
/*
 * Cadastro de usuários do SAC na coleção "usuarios", a mesma do sistema de produção.
 *
 * O usuário criado aqui tem cargo "operador" e área "sac": entra no SAC, e no sistema de produção
 * não vê nenhum módulo (nenhum usa a área "sac"). Administradores continuam sendo criados
 * pelo sistema de produção.
 * Atenção: se um administrador editar este usuário pela tela do sistema de produção, a área "sac"
 * vira "todas" lá (o sistema de produção não conhece essa área).
 */

final class Usuarios
{
    public const AREA = 'sac';
    public const SENHA_MIN = 8;

    public static function listarSac(): array
    {
        $lista = db()->find('usuarios', ['area' => self::AREA]);
        usort($lista, fn($a, $b) => strcmp(self::dataIso($b['criadoEm'] ?? ''), self::dataIso($a['criadoEm'] ?? '')));
        return $lista;
    }

    /** Retorna [dados, erros] */
    public static function criar(array $in, array $criador): array
    {
        $d = [
            'nome'  => trim((string) ($in['nome'] ?? '')),
            'email' => mb_strtolower(trim((string) ($in['email'] ?? ''))),
        ];
        $senha = (string) ($in['senha'] ?? '');
        $erros = [];

        if (mb_strlen($d['nome']) < 2) $erros['nome'] = 'Informe o nome.';
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $erros['email'] = 'E-mail inválido.';
        elseif (db()->findOne('usuarios', ['email' => $d['email']])) $erros['email'] = 'Já existe um usuário com este e-mail.';
        if (mb_strlen($senha) < self::SENHA_MIN) $erros['senha'] = 'A senha precisa ter pelo menos ' . self::SENHA_MIN . ' caracteres.';
        elseif ($senha !== (string) ($in['senha2'] ?? '')) $erros['senha2'] = 'As senhas não conferem.';
        if ($erros) return [$d, $erros];

        try {
            db()->insert('usuarios', $d + [
                // o sistema de produção (bcryptjs) grava "$2b$"; é o mesmo algoritmo do "$2y$" do PHP
                'senhaHash'  => preg_replace('/^\$2y\$/', '\$2b\$', password_hash($senha, PASSWORD_BCRYPT, ['cost' => 10])),
                'cargo'      => 'operador',
                'area'       => self::AREA,
                'areas'      => [self::AREA],
                'criadoEm'   => self::agora(),
                'criadoPor'  => $criador['nome'],
            ]);
        } catch (MongoDB\Driver\Exception\BulkWriteException) {
            // índice único de e-mail do sistema de produção (cadastro simultâneo)
            return [$d, ['email' => 'Já existe um usuário com este e-mail.']];
        }
        return [$d, []];
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
