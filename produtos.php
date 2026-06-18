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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <!-- SEO Meta Tags básicas -->
  <title>Catálogo Completo de Eletrônicos e Ofertas – TechDeal</title>
  <meta name="description" content="Explore o catálogo completo de ofertas de tecnologia do TechDeal. Pesquise e compare preços de smartphones, notebooks, games, fones de ouvido e muito mais.">
  <meta name="keywords" content="TechDeal, catálogo de produtos, buscar ofertas, smartphones, notebooks, comparar preços, cupons">
  <meta name="robots" content="index, follow">
  
  <!-- Open Graph Meta Tags (Para compartilhamento otimizado em redes sociais) -->
  <meta property="og:title" content="Catálogo Completo de Eletrônicos – TechDeal">
  <meta property="og:description" content="Busque e filtre ofertas de eletrônicos das principais lojas parceiras com descontos exclusivos no TechDeal.">
  <meta property="og:image" content="og_banner.png">
  <meta property="og:type" content="website">
  <meta property="og:url" content="produtos.php">
  <meta property="og:site_name" content="TechDeal">
  
  <!-- Favicon para exibição correta na aba do navegador -->
  <link rel="icon" type="image/png" href="favicon.png">
  
  <link rel="stylesheet" href="css/style.css">
  
  <!-- Estilos customizados locais para a página de produtos (Filtros e Layout) -->
  <style>
    /* Linha de cabeçalho unindo o título e a barra de filtros */
    .produtos-header-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 2rem;
      margin-top: 2rem;
      flex-wrap: wrap;
      gap: 1rem;
    }
    
    /* Configurações tipográficas do título da página */
    .produtos-header-row h2 {
      font-size: 1.6rem;
      font-weight: 800;
      color: var(--text);
      letter-spacing: -0.5px;
      margin: 0;
    }
    
    /* Grid horizontal dos filtros rápidos */
    .produtos-quick-filters {
      display: flex;
      gap: 1rem;
      align-items: center;
      flex-wrap: wrap;
    }
    
    /* Estilização premium dos seletores (dropdowns) */
    .produtos-quick-filters select {
      padding: 0.6rem 2rem 0.6rem 0.8rem;
      border-radius: var(--radius-sm);
      border: 1.5px solid var(--border);
      font-size: 0.85rem;
      font-weight: 600;
      outline: none;
      background: var(--white);
      color: var(--text);
      cursor: pointer;
      transition: border-color 0.2s, box-shadow 0.2s;
      min-width: 160px;
      /* Ícone de seta customizado para um visual elegante e integrado */
      background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%234a5568' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 0.75rem center;
      background-size: 0.75rem;
      -webkit-appearance: none;
      -moz-appearance: none;
      appearance: none;
    }
    
    /* Efeito de foco nos seletores */
    .produtos-quick-filters select:focus {
      border-color: var(--brand);
      box-shadow: 0 0 0 4px rgba(0, 87, 255, 0.12);
    }
    
    /* Responsividade para telas menores */
    @media (max-width: 768px) {
      .produtos-header-row {
        flex-direction: column;
        align-items: flex-start;
      }
      .produtos-quick-filters {
        width: 100%;
      }
      .produtos-quick-filters select {
        flex: 1;
        min-width: 120px;
      }
    }
    
    /* Centraliza e dá espaçamento para a grade de produtos */
    .produtos-grid-wrap {
      max-width: 1200px;
      margin: 0 auto 4rem auto;
      padding: 0 1.5rem;
    }
    
    .text-center {
      text-align: center;
    }
    
    /* Botão simples estilo admin para limpar filtros */
    .btn-clear-filters {
      background: var(--brand);
      color: var(--white);
      padding: 0.65rem 1.25rem;
      border-radius: var(--radius-sm);
      text-decoration: none;
      font-weight: 600;
      font-size: 0.9rem;
      display: inline-block;
      margin-top: 1rem;
      transition: background 0.2s;
    }
    .btn-clear-filters:hover {
      background: var(--brand-dark);
    }
  </style>
</head>
<body>
  <?php 
    // Carrega o cabeçalho dinâmico baseado no status de login
    include 'header.php'; 
  ?>

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
          <!-- Limpa o termo de busca mas mantém as seleções de loja, categoria e ordenação nos parâmetros GET -->
          <a href="produtos.php?store=<?php echo $store_filter > 0 ? $store_filter : ''; ?>&category=<?php echo $category_filter > 0 ? $category_filter : ''; ?>&sort=<?php echo $sort_filter; ?>" 
             style="text-decoration: none; color: var(--brand); font-weight: 700; font-size: 1.1rem; margin-left: 0.25rem; display: inline-block; transform: translateY(-1px); cursor: pointer;" 
             title="Limpar pesquisa">×</a>
        </span>
      </div>
    <?php endif; ?>
    
    <div class="products-grid" id="products-grid" style="margin-top: 0;">
      <?php 
        // Se houver produtos retornados do Supabase, renderiza os cards
        if (!empty($produtos)): 
          foreach ($produtos as $p):
            // Trata os descontos de forma visual
            $desconto_html = "";
            if (!empty($p['desconto'])) {
                $desconto_html = '<span class="badge-discount">' . htmlspecialchars($p['desconto']) . '</span>';
            }
            
            // Trata as lojas associadas
            $store_name = !empty($p['store']['nome']) ? htmlspecialchars($p['store']['nome']) : 'Parceiro';
            $store_css = !empty($p['store']['css_class']) ? htmlspecialchars($p['store']['css_class']) . '-sm' : 'badge-amazon-sm';
            
            // Formata os preços no formato brasileiro (R$ X.XXX,XX)
            $preco_novo_fmt = "R$ " . number_format($p['preco_novo'], 2, ',', '.');
            $preco_antigo_html = "";
            if (!empty($p['preco_antigo'])) {
                $preco_antigo_fmt = "R$ " . number_format($p['preco_antigo'], 2, ',', '.');
                $preco_antigo_html = '<div class="price-old">' . $preco_antigo_fmt . '</div>';
            }

            // Mapeia cliques de afiliados baseados no produto (conforme featured_deals.php)
            $click_slug = $p['slug_comparativo'] ?? '';
            if (empty($click_slug)) {
                if ($p['id'] == 3) $click_slug = 'apple-watch-s9';
                elseif ($p['id'] == 4) $click_slug = 'dell-inspiron';
                elseif ($p['id'] == 5) $click_slug = 'dualsense';
                elseif ($p['id'] == 6) $click_slug = 'sony-zve10';
                elseif ($p['id'] == 7) $click_slug = 'samsung-tv-55';
                else $click_slug = 'produto-' . $p['id'];
            }

            // Mapeia nota decimal das estrelas para corresponder ao design original
            $nota_estrelas = '5.0';
            if ($p['id'] == 1) $nota_estrelas = '4.8';
            elseif ($p['id'] == 2) $nota_estrelas = '4.6';
            elseif ($p['id'] == 3) $nota_estrelas = '4.9';
            elseif ($p['id'] == 4) $nota_estrelas = '4.5';
            elseif ($p['id'] == 5) $nota_estrelas = '4.8';
            elseif ($p['id'] == 6) $nota_estrelas = '4.7';
            elseif ($p['id'] == 7) $nota_estrelas = '4.7';
            elseif ($p['id'] == 8) $nota_estrelas = '4.9';
      ?>
        <!-- Card do produto com estrutura exatamente idêntica às ofertas do dia do index -->
        <div class="product-card" data-store="<?php echo htmlspecialchars($p['loja']); ?>">
          <div class="product-img">
            <!-- Imagem e badges do produto -->
            <img src="<?php echo htmlspecialchars($p['imagem_url']); ?>" alt="<?php echo htmlspecialchars($p['titulo']); ?>" class="product-img-real">
            <?php echo $desconto_html; ?>
            <span class="badge-store <?php echo $store_css; ?>"><?php echo $store_name; ?></span>
          </div>
          <div class="product-body">
            <p class="product-title"><?php echo htmlspecialchars($p['titulo']); ?></p>
            <div class="product-rating">
              <span class="stars"><?php echo htmlspecialchars($p['estrelas'] ?? '★★★★★'); ?></span>
              <span><?php echo $nota_estrelas; ?> (<?php echo number_format($p['avaliacoes'], 0, ',', '.'); ?> avaliações)</span>
            </div>
            <div class="product-price">
              <?php echo $preco_antigo_html; ?>
              <div class="price-new"><?php echo $preco_novo_fmt; ?></div>
            </div>
            <!-- Botão de compra afiliado com evento trackClick -->
            <a href="<?php echo htmlspecialchars($p['link_afiliado']); ?>" class="btn-buy btn-<?php echo htmlspecialchars($p['loja']); ?>" onclick="trackClick('<?php echo htmlspecialchars($p['loja']); ?>', '<?php echo htmlspecialchars($click_slug); ?>')">Ver na <?php echo $store_name; ?> →</a>
          </div>
        </div>
      <?php 
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
  </div>

  <?php 
    // Carrega o rodapé dinâmico do site (com categorias do Supabase)
    include 'footer.php'; 
  ?>
  <script src="js/main.js"></script>
</body>
</html>
