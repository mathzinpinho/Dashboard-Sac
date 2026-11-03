<?php
// Só para rodar no computador:  php -S localhost:8000 router.php
// (na Vercel quem faz este papel é o vercel.json)
$caminho = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($caminho, '/assets/') && is_file(__DIR__ . $caminho)) {
    return false;
}
require __DIR__ . '/api/index.php';
