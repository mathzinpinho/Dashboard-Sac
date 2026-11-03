<?php
/*
 * Fotos e vídeos das reclamações, guardados no Vercel Blob (store "sac-anexos").
 *
 * O arquivo vai direto do navegador para o Blob (a Vercel limita o corpo de uma requisição
 * ao PHP a ~4,5 MB, menos que um vídeo de celular). O PHP só:
 *  1. gera um token de envio temporário, restrito a imagens/vídeos e ao tamanho máximo;
 *  2. guarda na reclamação a lista de anexos enviados;
 *  3. apaga do Blob os anexos removidos.
 * Formato do token igual ao do pacote oficial @vercel/blob (generateClientTokenFromReadWriteToken).
 */

final class Anexos
{
    public const TAMANHO_MAX = 100 * 1024 * 1024;   // 100 MB por arquivo
    public const TIPOS = ['image/*', 'video/*'];
    private const API = 'https://vercel.com/api/blob';
    private const API_VERSAO = '13';

    private static function chave(): ?string
    {
        $t = env('BLOB_READ_WRITE_TOKEN');
        return ($t && str_starts_with($t, 'vercel_blob_rw_')) ? $t : null;
    }

    public static function ativo(): bool
    {
        return self::chave() !== null;
    }

    /** vercel_blob_rw_<storeId>_<segredo> */
    private static function storeId(): string
    {
        return explode('_', (string) self::chave())[3] ?? '';
    }

    /** Resposta ao evento "blob.generate-client-token" enviado pelo upload() do navegador */
    public static function tokenEnvio(string $pathname): string
    {
        $chave = self::chave() ?? throw new RuntimeException('Armazenamento de anexos não configurado.');
        if (!preg_match('#^sac/[A-Za-z0-9/_.\-]+$#', $pathname) || str_contains($pathname, '..')) {
            throw new DomainException('Nome de arquivo inválido.');
        }
        $payload = base64_encode(json_encode([
            'allowedContentTypes' => self::TIPOS,
            'maximumSizeInBytes'  => self::TAMANHO_MAX,
            'addRandomSuffix'     => true,
            'pathname'            => $pathname,
            'validUntil'          => (int) round(microtime(true) * 1000) + 3600 * 1000,
        ], JSON_UNESCAPED_SLASHES));
        $assinatura = hash_hmac('sha256', $payload, $chave);
        return 'vercel_blob_client_' . self::storeId() . '_' . base64_encode("$assinatura.$payload");
    }

    /**
     * Confere a lista de anexos vinda do formulário: só aceita arquivos deste store, na pasta sac/.
     * Mantém só os campos conhecidos.
     */
    public static function limpar(string $json): array
    {
        $lista = json_decode($json, true);
        if (!is_array($lista)) return [];
        $prefixo = 'https://' . strtolower(self::storeId()) . '.public.blob.vercel-storage.com/sac/';
        $ok = [];
        foreach ($lista as $a) {
            $url = (string) ($a['url'] ?? '');
            if (!self::ativo() || !str_starts_with(strtolower($url), $prefixo)) continue;
            $tipo = (string) ($a['tipo'] ?? '');
            $ok[$url] = [
                'url'        => $url,
                'nome'       => mb_substr((string) ($a['nome'] ?? basename($url)), 0, 120),
                'tipo'       => preg_match('#^(image|video)/[\w.+-]+$#', $tipo) ? $tipo : 'application/octet-stream',
                'tamanho'    => (int) ($a['tamanho'] ?? 0),
                'enviado_por' => mb_substr((string) ($a['enviado_por'] ?? ''), 0, 80),
                'enviado_em' => (string) ($a['enviado_em'] ?? ''),
            ];
        }
        return array_values($ok);
    }

    /** Apaga arquivos do Blob. Falhas só vão para o log: a reclamação já foi salva. */
    public static function excluir(array $urls): void
    {
        $urls = array_values(array_filter($urls));
        if (!$urls || !self::ativo()) return;
        // a API apaga no máximo um lote por chamada
        if (count($urls) > 100) {
            foreach (array_chunk($urls, 100) as $lote) self::excluir($lote);
            return;
        }
        $ctx = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => implode("\r\n", [
                'authorization: Bearer ' . self::chave(),
                'x-api-version: ' . self::API_VERSAO,
                'content-type: application/json',
            ]),
            'content'       => json_encode(['urls' => $urls], JSON_UNESCAPED_SLASHES),
            'ignore_errors' => true,
            'timeout'       => 15,
        ]]);
        $resp = @file_get_contents(self::API . '/delete', false, $ctx);
        $status = isset($http_response_header[0]) ? $http_response_header[0] : 'sem resposta';
        if ($resp === false || !str_contains($status, ' 200')) {
            error_log("Falha ao apagar anexos do Blob ($status): " . substr((string) $resp, 0, 200));
        }
    }

    public static function ehVideo(array $a): bool
    {
        return str_starts_with($a['tipo'] ?? '', 'video/');
    }

    public static function tamanho(int $bytes): string
    {
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
        return max(1, round($bytes / 1024)) . ' KB';
    }
}
