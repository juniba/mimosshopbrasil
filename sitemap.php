<?php
/*
  sitemap.xml (dinâmico via PHP): Gera um mapa do site em formato XML
  para facilitar a indexação pelo Google e outros mecanismos de busca.
  Inclui páginas estáticas, produtos e artigos do blog carregados do Supabase.
*/
require_once 'config.php';

// Define o cabeçalho de resposta como XML
header('Content-Type: application/xml; charset=utf-8');

// URL base do site (configurável para ambiente de produção)
$base_url = 'https://mcdmarketprime.com';

// Prepara o contexto HTTP para consultas ao Supabase
$sitemap_opts = [
    "http" => [
        "method" => "GET",
        "header" => "apikey: " . SUPABASE_KEY . "\r\n" .
                    "Authorization: Bearer " . SUPABASE_KEY . "\r\n"
    ]
];
$sitemap_context = stream_context_create($sitemap_opts);

// Busca os slugs dos artigos publicados do blog para inclusão no sitemap
$artigos_url = SUPABASE_URL . "/rest/v1/artigos?select=slug,created_at&publicado=eq.true&order=created_at.desc";
$artigos_response = fetch_supabase_with_cache($artigos_url, $sitemap_context, 600);
$artigos = json_decode($artigos_response, true) ?: [];

// Busca os IDs dos produtos para inclusão no sitemap (página de catálogo com filtros)
$categorias_url = SUPABASE_URL . "/rest/v1/categorias?select=id&order=id.asc";
$categorias_response = fetch_supabase_with_cache($categorias_url, $sitemap_context, 600);
$categorias = json_decode($categorias_response, true) ?: [];

// Data atual formatada para o padrão W3C (ISO 8601)
$today = date('Y-m-d');

// Início do documento XML do sitemap
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <!-- Páginas estáticas principais do site -->
  <url>
    <loc><?php echo $base_url; ?>/</loc>
    <lastmod><?php echo $today; ?></lastmod>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>
  <url>
    <loc><?php echo $base_url; ?>/produtos.php</loc>
    <lastmod><?php echo $today; ?></lastmod>
    <changefreq>daily</changefreq>
    <priority>0.9</priority>
  </url>
  <url>
    <loc><?php echo $base_url; ?>/blog/</loc>
    <lastmod><?php echo $today; ?></lastmod>
    <changefreq>daily</changefreq>
    <priority>0.8</priority>
  </url>
  <url>
    <loc><?php echo $base_url; ?>/links.php</loc>
    <lastmod><?php echo $today; ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.6</priority>
  </url>
  
  <!-- URLs dinâmicas dos artigos do blog -->
  <?php foreach ($artigos as $art): ?>
  <url>
    <loc><?php echo $base_url; ?>/blog/<?php echo htmlspecialchars($art['slug']); ?></loc>
    <lastmod><?php echo date('Y-m-d', strtotime($art['created_at'])); ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.7</priority>
  </url>
  <?php endforeach; ?>
  
  <!-- URLs de categorias filtradas no catálogo -->
  <?php foreach ($categorias as $cat): ?>
  <url>
    <loc><?php echo $base_url; ?>/produtos.php?category=<?php echo $cat['id']; ?></loc>
    <lastmod><?php echo $today; ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.5</priority>
  </url>
  <?php endforeach; ?>
</urlset>
