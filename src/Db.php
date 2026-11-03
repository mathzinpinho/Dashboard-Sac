<?php
/*
 * Acesso a dados.
 * - Em produção (Vercel): MongoDB, o mesmo banco do sistema de produção (MONGODB_URI / DB_NAME).
 * - Em desenvolvimento local sem MONGODB_URI: arquivos JSON na pasta data/.
 *
 * Filtros aceitos: igualdade ['campo' => valor] e faixa ['campo' => ['$gte' => a, '$lte' => b]].
 * Os documentos sempre voltam como array, com o _id convertido em 'id' (string).
 */

interface Store
{
    public function find(string $col, array $filtro = [], array $ordem = []): array;
    public function findOne(string $col, array $filtro): ?array;
    public function insert(string $col, array $doc): string;
    public function update(string $col, string $id, array $campos): void;
    public function delete(string $col, string $id): void;
    public function nextSeq(string $nome): int;
}

function db(): Store
{
    static $store = null;
    if ($store === null) {
        $uri = env('MONGODB_URI');
        $store = $uri
            ? new MongoStore($uri, env('DB_NAME', 'casadopadeiro'))
            : new JsonStore(dirname(__DIR__) . '/data');
    }
    return $store;
}

final class MongoStore implements Store
{
    private MongoDB\Driver\Manager $m;

    public function __construct(string $uri, private string $banco)
    {
        if (!extension_loaded('mongodb')) {
            throw new RuntimeException('Extensão mongodb do PHP não está habilitada.');
        }
        $this->m = new MongoDB\Driver\Manager($uri);
    }

    private function ns(string $col): string
    {
        return $this->banco . '.' . $col;
    }

    private function oid(string $id): MongoDB\BSON\ObjectId|string
    {
        return preg_match('/^[a-f0-9]{24}$/i', $id) ? new MongoDB\BSON\ObjectId($id) : $id;
    }

    private static function normalizar(array $doc): array
    {
        $doc['id'] = (string) $doc['_id'];
        unset($doc['_id']);
        return $doc;
    }

    public function find(string $col, array $filtro = [], array $ordem = []): array
    {
        $q = new MongoDB\Driver\Query($filtro, $ordem ? ['sort' => $ordem] : []);
        $cursor = $this->m->executeQuery($this->ns($col), $q);
        $cursor->setTypeMap(['root' => 'array', 'document' => 'array', 'array' => 'array']);
        return array_map([self::class, 'normalizar'], $cursor->toArray());
    }

    public function findOne(string $col, array $filtro): ?array
    {
        if (isset($filtro['id'])) {
            $filtro['_id'] = $this->oid($filtro['id']);
            unset($filtro['id']);
        }
        $q = new MongoDB\Driver\Query($filtro, ['limit' => 1]);
        $cursor = $this->m->executeQuery($this->ns($col), $q);
        $cursor->setTypeMap(['root' => 'array', 'document' => 'array', 'array' => 'array']);
        $docs = $cursor->toArray();
        return $docs ? self::normalizar($docs[0]) : null;
    }

    public function insert(string $col, array $doc): string
    {
        unset($doc['id']);
        $bulk = new MongoDB\Driver\BulkWrite();
        $id = $bulk->insert($doc);
        $this->m->executeBulkWrite($this->ns($col), $bulk);
        return (string) $id;
    }

    public function update(string $col, string $id, array $campos): void
    {
        unset($campos['id'], $campos['_id']);
        $bulk = new MongoDB\Driver\BulkWrite();
        $bulk->update(['_id' => $this->oid($id)], ['$set' => $campos]);
        $this->m->executeBulkWrite($this->ns($col), $bulk);
    }

    public function delete(string $col, string $id): void
    {
        $bulk = new MongoDB\Driver\BulkWrite();
        $bulk->delete(['_id' => $this->oid($id)], ['limit' => 1]);
        $this->m->executeBulkWrite($this->ns($col), $bulk);
    }

    public function nextSeq(string $nome): int
    {
        $cmd = new MongoDB\Driver\Command([
            'findAndModify' => 'sac_contadores',
            'query'  => ['_id' => $nome],
            'update' => ['$inc' => ['valor' => 1]],
            'new'    => true,
            'upsert' => true,
        ]);
        $res = $this->m->executeCommand($this->banco, $cmd)->toArray()[0];
        return (int) $res->value->valor;
    }
}

final class JsonStore implements Store
{
    public function __construct(private string $dir)
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    private function arquivo(string $col): string
    {
        return $this->dir . '/' . preg_replace('/[^a-z0-9_]/i', '', $col) . '.json';
    }

    private function ler(string $col): array
    {
        $f = $this->arquivo($col);
        return is_file($f) ? (json_decode(file_get_contents($f), true) ?: []) : [];
    }

    private function gravar(string $col, array $docs): void
    {
        // grava num arquivo temporário e troca: um erro nunca deixa o arquivo vazio
        $json = json_encode(array_values($docs), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $tmp = $this->arquivo($col) . '.tmp';
        file_put_contents($tmp, $json, LOCK_EX);
        rename($tmp, $this->arquivo($col));
    }

    private static function confere(array $doc, array $filtro): bool
    {
        foreach ($filtro as $campo => $cond) {
            $v = $doc[$campo] ?? null;
            if (is_array($cond)) {
                if (isset($cond['$gte']) && ($v === null || $v < $cond['$gte'])) return false;
                if (isset($cond['$lte']) && ($v === null || $v > $cond['$lte'])) return false;
            } elseif ($v !== $cond) {
                return false;
            }
        }
        return true;
    }

    public function find(string $col, array $filtro = [], array $ordem = []): array
    {
        $docs = array_values(array_filter($this->ler($col), fn($d) => self::confere($d, $filtro)));
        if ($ordem) {
            usort($docs, function ($a, $b) use ($ordem) {
                foreach ($ordem as $campo => $dir) {
                    $c = ($a[$campo] ?? '') <=> ($b[$campo] ?? '');
                    if ($c !== 0) return $c * $dir;
                }
                return 0;
            });
        }
        return $docs;
    }

    public function findOne(string $col, array $filtro): ?array
    {
        return $this->find($col, $filtro)[0] ?? null;
    }

    public function insert(string $col, array $doc): string
    {
        $docs = $this->ler($col);
        $doc['id'] = bin2hex(random_bytes(12));
        $docs[] = $doc;
        $this->gravar($col, $docs);
        return $doc['id'];
    }

    public function update(string $col, string $id, array $campos): void
    {
        $docs = $this->ler($col);
        foreach ($docs as &$d) {
            if ($d['id'] === $id) {
                $d = array_merge($d, $campos, ['id' => $id]);
            }
        }
        $this->gravar($col, $docs);
    }

    public function delete(string $col, string $id): void
    {
        $this->gravar($col, array_filter($this->ler($col), fn($d) => $d['id'] !== $id));
    }

    public function nextSeq(string $nome): int
    {
        $f = $this->dir . '/sac_contadores.json';
        $c = is_file($f) ? json_decode(file_get_contents($f), true) : [];
        $c[$nome] = ($c[$nome] ?? 0) + 1;
        file_put_contents($f, json_encode($c), LOCK_EX);
        return $c[$nome];
    }
}
