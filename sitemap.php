<?php
// sitemap.php – gera um sitemap XML dinâmico para o site MCD Market Prime
// Comentário: Consulta os arquivos PHP relevantes e cria URLs completas.

header('Content-Type: application/xml; charset=UTF-8');

$baseUrl = 'https://www.mcdmarketprime.com';

// Lista de caminhos estáticos conhecidos
$staticPaths = [
    '/',
    '/produtos.php',
    '/blog/',
    '/sobre.php',
    '/contato.php',
];

// Opcional: incluir URLs de produtos (exemplo fictício)
for ($i = 1; $i <= 20; $i++) {
    $staticPaths[] = "/produto.php?id={$i}";
}

$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset/>');
$xml->addAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

foreach ($staticPaths as $path) {
    $url = $xml->addChild('url');
    $url->addChild('loc', $baseUrl . $path);
    $url->addChild('changefreq', 'daily');
    $url->addChild('priority', '0.8');
}

// Cache por 24h
header('Cache-Control: public, max-age=86400');

echo $xml->asXML();
?>
