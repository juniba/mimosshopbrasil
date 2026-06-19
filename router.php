<?php
/*
  router.php: Roteador para o servidor embutido do PHP (php -S) local.
  Simula as regras do .htaccess para suportar URLs amigáveis (/blog/slug) no ambiente de desenvolvimento.
  Respeita a regra global de incluir comentários detalhados explicativos.
*/

$request_uri = $_SERVER["REQUEST_URI"];
// Extrai apenas o caminho da URL ignorando parâmetros de consulta (?param=val)
$path = parse_url($request_uri, PHP_URL_PATH);

// Se o arquivo ou imagem física de fato existir na pasta do projeto, serve o arquivo diretamente
if (file_exists(__DIR__ . $path) && !is_dir(__DIR__ . $path)) {
    return false;
}

// Regra para URLs de artigos do blog: /blog/qualquer-slug
if (preg_match('/^\/blog\/([a-zA-Z0-9-_]+)\/?$/', $path, $matches)) {
    // Passa o slug capturado para o parâmetro $_GET['slug']
    $_GET['slug'] = $matches[1];
    // Carrega o arquivo principal do blog que cuidará da exibição do artigo
    include __DIR__ . '/blog/index.php';
    exit;
}

// Regra para a listagem principal do blog: /blog ou /blog/
if (preg_match('/^\/blog\/?$/', $path)) {
    include __DIR__ . '/blog/index.php';
    exit;
}

// Caso contrário, deixa o servidor embutido do PHP lidar com a rota (ex: carregar index.php raiz, produtos.php, etc.)
return false;
