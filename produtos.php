<?php
/*
  produtos.php: Página pública de catálogo de produtos com buscas e filtros dinâmicos.
  Permite filtrar por palavra-chave, loja parceira, categoria e ordenar por preço ou desconto.
  Os dados de produtos, lojas e categorias são carregados em tempo real do Supabase.
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
$price_range = isset($_GET['price_range']) ? intval($_GET['price_range']) : 0;

// 3. Monta a URL da API do Supabase para produtos de acordo com os filtros
$url_produtos = SUPABASE_URL . "/rest/v1/produtos?select=*,store(nome,css_class)";

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
// Comentário de regra: Mapeamento de faixas de preço para filtragem na consulta ao banco de dados Supabase
if ($price_range === 1) {
    $url_produtos .= "&preco_novo=lte.50";
} elseif ($price_range === 2) {
    $url_produtos .= "&preco_novo=gte.50&preco_novo=lte.100";
} elseif ($price_range === 3) {
    $url_produtos .= "&preco_novo=gte.100&preco_novo=lte.200";
} elseif ($price_range === 4) {
    $url_produtos .= "&preco_novo=gte.200&preco_novo=lte.500";
} elseif ($price_range === 5) {
    $url_produtos .= "&preco_novo=gte.500";
}

// Configuração de Ordenação na consulta SQL
if ($sort_filter === 'price_asc') {
    $url_produtos .= "&order=preco_novo.asc";
} elseif ($sort_filter === 'price_desc') {
    $url_produtos .= "&order=preco_novo.desc";
} else {
    // Default ordenado pelo ID
    $url_produtos .= "&order=id.asc";
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

// Comentário de regra: Lógica de paginação em PHP para evitar inchaço do DOM (DOM bloating) e lentidão de carregamento
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$items_per_page = 12; // Exibe no máximo 12 produtos por página
$total_items = count($produtos);
$total_pages = ceil($total_items / $items_per_page);
$current_page = min($current_page, max(1, $total_pages));
$offset = ($current_page - 1) * $items_per_page;
$paginated_produtos = array_slice($produtos, $offset, $items_per_page);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <!-- SEO Meta Tags básicas -->
  <!-- SEO Meta Tags atualizadas para MCD Market Prime -->
  <title>Catálogo Completo de Ofertas – MCD Market Prime</title>
  <meta name="description" content="Explore o catálogo completo de ofertas do MCD Market Prime. Pesquise e compare preços de eletrônicos, utilidades e presentes das melhores lojas.">
  <meta name="keywords" content="MCD Market Prime, catálogo de produtos, buscar ofertas, eletrônicos, utilidades, comparar preços, cupons">
  <meta name="robots" content="index, follow">
  <link rel="canonical" href="https://mcdmarketprime.com/produtos.php">
  
  <!-- Open Graph Meta Tags com URLs absolutas para compartilhamento otimizado em redes sociais -->
  <meta property="og:title" content="Catálogo Completo de Ofertas – MCD Market Prime">
  <meta property="og:description" content="Busque e filtre ofertas das principais lojas parceiras com descontos exclusivos no MCD Market Prime.">
  <meta property="og:image" content="https://mcdmarketprime.com/og_banner.png">
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://mcdmarketprime.com/produtos.php">
  <meta property="og:site_name" content="MCD Market Prime">
  
  <!-- Favicon para exibição correta na aba do navegador -->
  <link rel="icon" type="image/png" href="favicon.png">
  
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
      <span class="current">Produtos</span>
    </nav>
  </div>


  <!-- Container da Grade de Produtos -->
  <div class="produtos-grid-wrap">
    
    <!-- Linha de cabeçalho unindo o título e os filtros rápidos na mesma linha -->
    <div class="produtos-header-row">
      <h2>Catálogo de Ofertas</h2>
      
      <!-- Formulário de filtros rápidos com submissão automática via JavaScript (onchange) -->
      <form action="produtos.php" method="GET" class="produtos-quick-filters">
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
        
        <!-- Dropdown de Faixa de Preço -->
        <select name="price_range" onchange="this.form.submit()">
          <option value="0" <?php echo $price_range === 0 ? 'selected' : ''; ?>>Qualquer Preço</option>
          <option value="1" <?php echo $price_range === 1 ? 'selected' : ''; ?>>Até R$ 50</option>
          <option value="2" <?php echo $price_range === 2 ? 'selected' : ''; ?>>R$ 50 a R$ 100</option>
          <option value="3" <?php echo $price_range === 3 ? 'selected' : ''; ?>>R$ 100 a R$ 200</option>
          <option value="4" <?php echo $price_range === 4 ? 'selected' : ''; ?>>R$ 200 a R$ 500</option>
          <option value="5" <?php echo $price_range === 5 ? 'selected' : ''; ?>>Acima de R$ 500</option>
        </select>
        
        <!-- Dropdown de Ordenação de Produtos -->
        <select name="sort" onchange="this.form.submit()">
          <option value="default" <?php echo $sort_filter === 'default' ? 'selected' : ''; ?>>Relevância</option>
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
          <!-- Limpa o termo de busca mas mantém as seleções de loja, categoria, ordenação e faixa de preço nos parâmetros GET -->
          <a href="produtos.php?store=<?php echo $store_filter > 0 ? $store_filter : ''; ?>&category=<?php echo $category_filter > 0 ? $category_filter : ''; ?>&sort=<?php echo $sort_filter; ?>&price_range=<?php echo $price_range > 0 ? $price_range : ''; ?>" 
             style="text-decoration: none; color: var(--brand); font-weight: 700; font-size: 1.1rem; margin-left: 0.25rem; display: inline-block; transform: translateY(-1px); cursor: pointer;" 
             title="Limpar pesquisa">×</a>
        </span>
      </div>
    <?php endif; ?>
    
    <div class="products-grid" id="products-grid" style="margin-top: 0;">
      <?php 
        // Comentário de regra: Renderiza os cards correspondentes apenas à página selecionada
        if (!empty($paginated_produtos)): 
          foreach ($paginated_produtos as $p):
            // Renderiza o card do produto usando o template unificado
            include 'sections/product_card.php';
          endforeach; 
        else:
      ?>
        <!-- Mensagem de fallback amigável caso nenhum produto seja encontrado nos filtros -->
        <div class="text-center" style="grid-column: 1 / -1; padding: 4rem 1.5rem;">
          <p class="text-muted" style="font-size: 1.1rem; font-weight: 600;">Nenhum produto encontrado com os filtros selecionados.</p>
          <a href="produtos.php" class="btn-clear-filters">Limpar Filtros</a>
        </div>
      <?php endif; ?>
    </div>

    <?php 
      // Comentário de regra: Bloco de paginação dinâmica e responsiva mantendo os parâmetros de filtro ativos na URL
      if ($total_pages > 1): 
    ?>
      <div class="pagination-container">
        <?php 
          // Função auxiliar para gerar URLs mantendo outros filtros ativos
          function get_page_url($page_num, $search, $store_filter, $category_filter, $sort_filter, $price_range) {
              $params = [];
              if (!empty($search)) $params['search'] = $search;
              if ($store_filter > 0) $params['store'] = $store_filter;
              if ($category_filter > 0) $params['category'] = $category_filter;
              if ($sort_filter !== 'default') $params['sort'] = $sort_filter;
              if ($price_range > 0) $params['price_range'] = $price_range;
              $params['page'] = $page_num;
              return 'produtos.php?' . http_build_query($params);
          }
        ?>
        
        <?php if ($current_page > 1): ?>
          <a href="<?php echo get_page_url($current_page - 1, $search, $store_filter, $category_filter, $sort_filter, $price_range); ?>" class="pagination-btn pagination-nav">
            <i data-lucide="chevron-left" style="width: 16px; height: 16px;"></i> Anterior
          </a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
          <?php if ($i === $current_page): ?>
            <span class="pagination-btn active"><?php echo $i; ?></span>
          <?php else: ?>
            <a href="<?php echo get_page_url($i, $search, $store_filter, $category_filter, $sort_filter, $price_range); ?>" class="pagination-btn"><?php echo $i; ?></a>
          <?php endif; ?>
        <?php endfor; ?>

        <?php if ($current_page < $total_pages): ?>
          <a href="<?php echo get_page_url($current_page + 1, $search, $store_filter, $category_filter, $sort_filter, $price_range); ?>" class="pagination-btn pagination-nav">
            Próxima <i data-lucide="chevron-right" style="width: 16px; height: 16px;"></i>
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php 
    // Carrega o rodapé dinâmico do site (com categorias do Supabase)
    include 'footer.php'; 
  ?>
  <script src="js/main.min.js"></script>
</body>
</html>
