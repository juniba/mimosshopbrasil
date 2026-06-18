<?php
/*
  featured_deals.php: Carrega dinamicamente os produtos marcados como destaque (is_featured = true)
  do banco de dados do Supabase e renderiza os cards de produtos.
  Inclui a funcionalidade de rastreamento de cliques e os descontos correspondentes.
*/

// Prepara o contexto HTTP com as chaves de autenticação do Supabase
$opts = [
    "http" => [
        "method" => "GET",
        "header" => "apikey: " . SUPABASE_KEY . "\r\n" .
                    "Authorization: Bearer " . SUPABASE_KEY . "\r\n"
    ]
];
$context = stream_context_create($opts);

// Endpoints da API para trazer produtos com relacionamento da loja
$url = SUPABASE_URL . "/rest/v1/produtos?select=*,store(nome,css_class)&is_featured=eq.true&order=id.asc";

// Executa a requisição GET para pegar os produtos em formato JSON (com cache local)
$response = fetch_supabase_with_cache($url, $context);
$produtos = json_decode($response, true);
?>
<section class="section" id="ofertas">
  <div class="section-header">
    <h2>Ofertas do dia <span class="tag-hot">🔥 HOT</span></h2>
    <!-- Link para visualizar todos os produtos cadastrados, abrindo a página de produtos sem filtros predefinidos -->
    <a href="produtos.php">Ver todas →</a>
  </div>
  
  <!-- Filtros de produtos por loja parceira ou ordenação por desconto -->
  <div class="filters">
    <button class="filter-btn active" onclick="filterProducts('all', this)">Todos</button>
    <button class="filter-btn" onclick="filterProducts('amazon', this)">Amazon</button>
    <button class="filter-btn" onclick="filterProducts('ml', this)">Mercado Livre</button>
    <button class="filter-btn" onclick="filterProducts('discount', this)">Maior desconto</button>
  </div>
  
  <!-- Grade de produtos em exibição -->
  <div class="products-grid" id="products-grid">
    <?php 
      // Se houver produtos retornados, faz a iteração para gerar os cards dinamicamente
      if (!empty($produtos)): 
        foreach ($produtos as $p):
          // Trata os descontos de forma visual
          $desconto_html = "";
          if (!empty($p['desconto'])) {
              $desconto_html = '<span class="badge-discount">' . htmlspecialchars($p['desconto']) . '</span>';
          }
          
          // Trata os dados e classes de estilização da loja parceira
          $store_name = !empty($p['store']['nome']) ? htmlspecialchars($p['store']['nome']) : 'Parceiro';
          $store_css = !empty($p['store']['css_class']) ? htmlspecialchars($p['store']['css_class']) . '-sm' : 'badge-amazon-sm';
          
          // Formata os valores monetários no padrão local brasileiro (R$ X.XXX,XX)
          $preco_novo_fmt = "R$ " . number_format($p['preco_novo'], 2, ',', '.');
          $preco_antigo_html = "";
          if (!empty($p['preco_antigo'])) {
              $preco_antigo_fmt = "R$ " . number_format($p['preco_antigo'], 2, ',', '.');
              $preco_antigo_html = '<div class="price-old">' . $preco_antigo_fmt . '</div>';
          }

          // Mapeamento de cliques amigáveis baseados no produto
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
      <!-- Card do produto com atributos de filtragem dinâmica -->
      <div class="product-card" data-store="<?php echo htmlspecialchars($p['loja']); ?>">
        <div class="product-img">
          <!-- Imagem principal do produto e tags visuais por cima -->
          <img src="<?php echo htmlspecialchars($p['imagem_url']); ?>" alt="<?php echo htmlspecialchars($p['titulo']); ?>" class="product-img-real">
          <?php echo $desconto_html; ?>
          <span class="badge-store <?php echo $store_css; ?>"><?php echo $store_name; ?></span>
        </div>
        <div class="product-body">
          <!-- Detalhes textuais, avaliação e preços do produto -->
          <p class="product-title"><?php echo htmlspecialchars($p['titulo']); ?></p>
          <div class="product-rating">
            <span class="stars"><?php echo htmlspecialchars($p['estrelas'] ?? '★★★★★'); ?></span>
            <span><?php echo $nota_estrelas; ?> (<?php echo number_format($p['avaliacoes'], 0, ',', '.'); ?> avaliações)</span>
          </div>
          <div class="product-price">
            <?php echo $preco_antigo_html; ?>
            <div class="price-new"><?php echo $preco_novo_fmt; ?></div>
          </div>
          <!-- Botão de redirecionamento e track de cliques de afiliação -->
          <a href="<?php echo htmlspecialchars($p['link_afiliado']); ?>" class="btn-buy btn-<?php echo htmlspecialchars($p['loja']); ?>" onclick="trackClick('<?php echo htmlspecialchars($p['loja']); ?>', '<?php echo htmlspecialchars($click_slug); ?>')">Ver na <?php echo $store_name; ?> →</a>
        </div>
      </div>
    <?php 
        endforeach; 
      else:
    ?>
      <!-- Mensagem amigável caso não existam ofertas ativas no banco -->
      <p class="text-muted">Nenhuma oferta encontrada no momento.</p>
    <?php endif; ?>
  </div>
</section>
