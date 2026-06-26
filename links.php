<?php
/*
  links.php: Página pública para exibir as encomendas de links finalizadas pelo administrador.
  Consulta produtos no Supabase onde is_encomenda = true e exibe os cards usando
  a mesma estrutura e identidade visual do catálogo principal (produtos.php).
*/
require_once 'config.php';

// Contexto HTTP de autenticação REST do Supabase
$opts = [
    "http" => [
        "method" => "GET",
        "header" => "apikey: " . SUPABASE_KEY . "\r\n" .
                    "Authorization: Bearer " . SUPABASE_KEY . "\r\n"
    ]
];
$context = stream_context_create($opts);

// 1. Busca Lojas e Categorias para preencher os seletores do formulário de filtros (com cache local)
$url_lojas = SUPABASE_URL . "/rest/v1/store?select=*&order=id.asc";
$response_lojas = fetch_supabase_with_cache($url_lojas, $context);
$lojas = json_decode($response_lojas, true);

$url_categorias = SUPABASE_URL . "/rest/v1/categorias?select=*&order=id.asc";
$response_categorias = fetch_supabase_with_cache($url_categorias, $context);
$categorias = json_decode($response_categorias, true);

// 2. Captura os parâmetros de filtro passados via URL (GET)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$store_filter = isset($_GET['store']) ? intval($_GET['store']) : 0;
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;
$sort_filter = isset($_GET['sort']) ? trim($_GET['sort']) : 'default';

// 3. Monta a URL da API do Supabase filtrando especificamente onde is_encomenda = true
$url_produtos = SUPABASE_URL . "/rest/v1/produtos?select=*,store(nome,css_class)&is_encomenda=eq.true";

if ($search !== '') {
    // Busca no array de categorias carregadas quais nomes contêm a palavra pesquisada (busca insensível a maiúsculas/minúsculas)
    $matching_category_ids = [];
    if (!empty($categorias)) {
        foreach ($categorias as $c) {
            if (stripos($c['nome'], $search) !== false) {
                $matching_category_ids[] = intval($c['id']);
            }
        }
    }
    
    // Se encontrar IDs de categorias correspondentes, monta a cláusula OR unindo busca por título ou ID de categoria
    if (!empty($matching_category_ids)) {
        $or_parts = ["titulo.ilike.*" . urlencode($search) . "*"];
        foreach ($matching_category_ids as $cat_id) {
            $or_parts[] = "categoria_id.eq." . $cat_id;
        }
        $url_produtos .= "&or=(" . implode(",", $or_parts) . ")";
    } else {
        // Caso contrário, filtra somente pelo termo no título do produto
        $url_produtos .= "&titulo=ilike.*" . urlencode($search) . "*";
    }
}
if ($store_filter > 0) {
    $url_produtos .= "&store_id=eq." . $store_filter;
}
if ($category_filter > 0) {
    $url_produtos .= "&categoria_id=eq." . $category_filter;
}

// Configuração de Ordenação na consulta SQL
if ($sort_filter === 'price_asc') {
    $url_produtos .= "&order=preco_novo.asc";
} elseif ($sort_filter === 'price_desc') {
    $url_produtos .= "&order=preco_novo.desc";
} else {
    // Default ordenado pelo ID decrescente para mostrar encomendas recentes primeiro
    $url_produtos .= "&order=id.desc";
}

// 4. Executa a busca de produtos filtrados no Supabase (com cache local)
$response_produtos = fetch_supabase_with_cache($url_produtos, $context);
$produtos = $response_produtos !== false ? json_decode($response_produtos, true) : [];

// 5. Ordenação complementar em PHP (por Desconto, caso selecionado)
if ($sort_filter === 'discount' && !empty($produtos)) {
    usort($produtos, function($a, $b) {
        $da = intval(str_replace(['-', '%'], '', $a['desconto'] ?? '0'));
        $db = intval(str_replace(['-', '%'], '', $b['desconto'] ?? '0'));
        return $db <=> $da; // Ordena de forma decrescente (do maior desconto para o menor)
    });
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <!-- SEO Meta Tags atualizadas para MCD Market Prime com URLs absolutas -->
  <title>Encomendas de Links com Desconto – MCD Market Prime</title>
  <meta name="description" content="Confira os links de desconto personalizados do MCD Market Prime. Encomendas de afiliados com cupons e preços especiais garantidos para Amazon e Mercado Livre.">
  <meta name="keywords" content="MCD Market Prime, encomendas, links com desconto, ofertas de afiliados, cupons de desconto">
  <meta name="robots" content="index, follow">
  <!-- URL canônica absoluta para evitar duplicidade de conteúdo no Google -->
  <link rel="canonical" href="https://mcdmarketprime.com/links.php">
  
  <!-- Open Graph Meta Tags com URLs absolutas para compartilhamento otimizado em redes sociais -->
  <meta property="og:title" content="Encomendas de Links com Desconto – MCD Market Prime">
  <meta property="og:description" content="Confira as encomendas e links com descontos exclusivos criados a pedido dos clientes no MCD Market Prime.">
  <meta property="og:image" content="https://mcdmarketprime.com/og_banner.png">
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://mcdmarketprime.com/links.php">
  <meta property="og:site_name" content="MCD Market Prime">
  
  <!-- Favicon para exibição correta na aba do navegador -->
  <!-- Definição do ícone do site para branding e identificação visual -->
  <link rel="icon" type="image/png" href="img/favicon.png">
  
  <!-- Preconnect e fontes do Google Fonts carregadas via head para otimização de renderização -->
  <!-- Comentário de regra: Este bloco carrega a fonte Inter de forma performática -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  
  <!-- Preload de recursos críticos de imagem para melhorar o LCP/CLS -->
  <link rel="preload" href="img/logo_mcdmarketprime.png" as="image">

  <!-- Folhas de estilo modulares contendo variáveis, layouts de componentes e os estilos específicos para filtros e catálogos -->
  <link rel="stylesheet" href="css/base.min.css">
  <link rel="stylesheet" href="css/components.min.css">
  
  <?php 
    // Comentário de regra: Inclui as tags globais de monitoramento do Google Analytics
    include 'sections/analytics.php'; 
  ?>
</head>
<body>
  <?php 
    // Carrega o cabeçalho dinâmico baseado no status de login
    include 'header.php'; 
  ?>

  <!-- Comentário de regra: Breadcrumbs de navegação hierárquica para melhorar a usabilidade e SEO -->
  <div class="produtos-grid-wrap" style="margin-bottom: 0; padding-top: 1rem;">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
      <a href="index.php">Home</a>
      <span class="separator">/</span>
      <span class="current">Links</span>
    </nav>
  </div>


  <!-- Container da Grade de Produtos de Encomenda -->
  <div class="produtos-grid-wrap">
    
    <!-- Linha de cabeçalho unindo o título e os filtros rápidos na mesma linha -->
    <div class="produtos-header-row">
      <div>
        <h2>Encomendas de Links</h2>
        <p style="color: var(--text-light); font-size: 0.9rem; margin-top: 0.25rem;">Links personalizados gerados a pedido de nossos clientes.</p>
      </div>
      
      <!-- Formulário de filtros rápidos com submissão automática via JavaScript (onchange) -->
      <form action="links.php" method="GET" class="produtos-quick-filters">
        <?php if (!empty($search)): ?>
          <!-- Preserva o termo de busca ativo nas filtragens subsequentes -->
          <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
        <?php endif; ?>
        
        <!-- Dropdown dinâmico de Lojas Parceiras -->
        <select name="store" onchange="this.form.submit()">
          <option value="">Todas as Lojas</option>
          <?php if (!empty($lojas)): ?>
            <?php foreach ($lojas as $l): ?>
              <option value="<?php echo $l['id']; ?>" <?php echo $store_filter === intval($l['id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($l['nome']); ?>
              </option>
            <?php endforeach; ?>
          <?php endif; ?>
        </select>
        
        <!-- Dropdown dinâmico de Categorias -->
        <select name="category" onchange="this.form.submit()">
          <option value="">Todas as Categorias</option>
          <?php if (!empty($categorias)): ?>
            <?php foreach ($categorias as $c): ?>
              <option value="<?php echo $c['id']; ?>" <?php echo $category_filter === intval($c['id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($c['nome']); ?>
              </option>
            <?php endforeach; ?>
          <?php endif; ?>
        </select>
        
        <!-- Dropdown de Ordenação de Produtos -->
        <select name="sort" onchange="this.form.submit()">
          <option value="default" <?php echo $sort_filter === 'default' ? 'selected' : ''; ?>>Mais Recentes</option>
          <option value="price_asc" <?php echo $sort_filter === 'price_asc' ? 'selected' : ''; ?>>Menor Preço</option>
          <option value="price_desc" <?php echo $sort_filter === 'price_desc' ? 'selected' : ''; ?>>Maior Preço</option>
          <option value="discount" <?php echo $sort_filter === 'discount' ? 'selected' : ''; ?>>Maior Desconto</option>
        </select>
      </form>
    </div>
    
    <!-- Exibe um badge indicando que há um filtro de pesquisa ativo, permitindo limpá-lo de forma independente -->
    <?php if (!empty($search)): ?>
      <div style="margin-top: -1rem; margin-bottom: 2rem; display: flex; align-items: center; gap: 0.5rem;">
        <span style="font-size: 0.85rem; background: var(--bg); border: 1.5px solid var(--border); padding: 0.35rem 0.75rem; border-radius: 99px; font-weight: 600; color: var(--text-light); display: flex; align-items: center; gap: 0.4rem;">
          Busca ativa: "<strong><?php echo htmlspecialchars($search); ?></strong>"
          <!-- Limpa o termo de busca mas mantém as seleções de loja, categoria e ordenação nos parâmetros GET -->
          <a href="links.php?store=<?php echo $store_filter > 0 ? $store_filter : ''; ?>&category=<?php echo $category_filter > 0 ? $category_filter : ''; ?>&sort=<?php echo $sort_filter; ?>" 
             style="text-decoration: none; color: var(--brand); font-weight: 700; font-size: 1.1rem; margin-left: 0.25rem; display: inline-block; transform: translateY(-1px); cursor: pointer;" 
             title="Limpar pesquisa">×</a>
        </span>
      </div>
    <?php endif; ?>
    
    <div class="products-grid" id="products-grid" style="margin-top: 0;">
      <?php 
        // Se houver produtos de encomenda retornados do Supabase, renderiza os cards
        if (!empty($produtos)): 
          foreach ($produtos as $p):
            // Renderiza o card do produto usando o template unificado
            include 'sections/product_card.php';
          endforeach; 
        else:
      ?>
        <!-- Mensagem de fallback amigável caso nenhuma encomenda seja encontrada -->
        <div class="text-center" style="grid-column: 1 / -1; padding: 4rem 1.5rem;">
          <p class="text-muted" style="font-size: 1.1rem; font-weight: 600;">Nenhuma encomenda disponível com os filtros selecionados.</p>
          <a href="links.php" class="btn-clear-filters">Limpar Filtros</a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php 
    // Carrega o rodapé dinâmico do site
    include 'footer.php'; 
  ?>
  <script src="js/main.min.js"></script>
</body>
</html>
