<?php
/*
 * Login com os mesmos usuários do sistema de produção (coleção "usuarios").
 * O token é um JWT HS256 no mesmo formato do sistema de produção, guardado num cookie HttpOnly.
 */

final class Auth
{
    private const COOKIE = 'sac_token';
    private const VALIDADE = 7 * 86400;

    private static function segredo(): string
    {
        $s = env('JWT_SECRET');
        if ($s) return $s;
        if (env('MONGODB_URI')) {
            throw new RuntimeException('Defina a variável JWT_SECRET.');
        }
        return 'chave-local-de-desenvolvimento';
    }

    private static function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    private static function assinar(array $dados): string
    {
        $cab = self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $corpo = self::b64(json_encode($dados, JSON_UNESCAPED_UNICODE));
        $sig = self::b64(hash_hmac('sha256', "$cab.$corpo", self::segredo(), true));
        return "$cab.$corpo.$sig";
    }

    private static function verificar(string $token): ?array
    {
        $partes = explode('.', $token);
        if (count($partes) !== 3) return null;
        [$cab, $corpo, $sig] = $partes;
        $esperado = self::b64(hash_hmac('sha256', "$cab.$corpo", self::segredo(), true));
        if (!hash_equals($esperado, $sig)) return null;
        $dados = json_decode(base64_decode(strtr($corpo, '-_', '+/')), true);
        if (!is_array($dados) || ($dados['exp'] ?? 0) < time()) return null;
        return $dados;
    }

    // #[\SensitiveParameter]: a senha não aparece em mensagens de erro nem nos logs
    public static function entrar(string $email, #[\SensitiveParameter] string $senha): ?string
    {
        $u = db()->findOne('usuarios', ['email' => mb_strtolower(trim($email))]);
        if (!$u || empty($u['senhaHash'])) return null;

        // bcryptjs gera hashes "$2b$"; o PHP usa o prefixo equivalente "$2y$"
        $hash = preg_replace('/^\$2[ab]\$/', '\$2y\$', $u['senhaHash']);
        if (!password_verify($senha, $hash)) return null;

        $agora = time();
        $token = self::assinar([
            'id'    => $u['id'],
            'nome'  => $u['nome'] ?? $u['email'],
            'email' => $u['email'],
            'cargo' => $u['cargo'] ?? 'administrador',
            'iat'   => $agora,
            'exp'   => $agora + self::VALIDADE,
        ]);
        cookie(self::COOKIE, $token, self::VALIDADE);
        return $token;
    }

    public static function sair(): void
    {
        cookie(self::COOKIE, '', 0);
    }

    public static function usuario(): ?array
    {
        static $u = false;
        if ($u === false) {
            $t = $_COOKIE[self::COOKIE] ?? '';
            $u = $t ? self::verificar($t) : null;
        }
        return $u;
    }

    public static function admin(): bool
    {
        return (self::usuario()['cargo'] ?? '') === 'administrador';
    }

    /** Token anti-CSRF derivado do token de login */
    public static function csrf(): string
    {
        return hash_hmac('sha256', 'csrf|' . ($_COOKIE[self::COOKIE] ?? ''), self::segredo());
    }

    public static function conferirCsrf(): void
    {
        // formulários mandam no campo _csrf; chamadas em JSON (envio de anexos), no cabeçalho X-CSRF
        $enviado = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
        if (!hash_equals(self::csrf(), (string) $enviado)) {
            http_response_code(419);
            exit('Sessão expirada. Volte e tente novamente.');
        }
    }
}
