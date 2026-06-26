<?php
// seo.php – funções auxiliares para geração de tags SEO
// Comentário: Centraliza a lógica de criação de meta tags dinâmicas para facilitar manutenção.

/**
 * Gera o título da página com base em título customizado ou fallback.
 * @param string $customTitle Título específico da página.
 * @return string
 */
function seo_title(string $customTitle = ''): string {
    $base = 'MCD Market Prime';
    return $customTitle ? "$customTitle – $base" : $base;
}

/**
 * Gera a meta description com texto opcional.
 */
function seo_description(string $customDesc = ''): string {
    $default = 'Compre os melhores produtos de beleza e moda no MCD Market Prime. Qualidade e entrega rápida.';
    return $customDesc ?: $default;
}

/**
 * Gera as tags Open Graph básicas.
 */
function seo_open_graph(array $options = []): string {
    $defaults = [
        'title' => seo_title(),
        'description' => seo_description(),
        'image' => '/img/og_banner.png',
        'url' => $_SERVER['REQUEST_URI'] ?? '/',
        'type' => 'website',
    ];
    $data = array_merge($defaults, $options);
    $tags = [];
    foreach ($data as $key => $value) {
        $tags[] = "<meta property=\"og:$key\" content=\"" . htmlspecialchars($value, ENT_QUOTES) . "\">";
    }
    return implode("\n", $tags);
}

/**
 * Gera JSON‑LD estruturado para o WebSite.
 */
function seo_json_ld_site(): string {
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'url' => 'https://www.mcdmarketprime.com/',
        'name' => 'MCD Market Prime',
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => 'https://www.mcdmarketprime.com/produtos.php?search={search_term_string}',
            'query-input' => 'required name=search_term_string'
        ]
    ];
    return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>';
}
?>
