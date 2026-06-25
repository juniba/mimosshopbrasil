<?php
/*
  product_card.php: Partial reutilizável para renderizar um card de produto.
  Espera que a variável $p contenha o array de dados do produto.
  
  Comentário de regra: Este arquivo centraliza a lógica de mapeamento e estilização dos produtos.
*/

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

// Mapeia cliques de afiliados baseados no produto
$click_slug = $p['slug_comparativo'] ?? '';
if (empty($click_slug)) {
    if (!empty($p['is_encomenda']) && $p['is_encomenda'] === true) {
        $click_slug = 'encomenda-' . $p['id'];
    } else {
        if ($p['id'] == 3) $click_slug = 'apple-watch-s9';
        elseif ($p['id'] == 4) $click_slug = 'dell-inspiron';
        elseif ($p['id'] == 5) $click_slug = 'dualsense';
        elseif ($p['id'] == 6) $click_slug = 'sony-zve10';
        elseif ($p['id'] == 7) $click_slug = 'samsung-tv-55';
        else $click_slug = 'produto-' . $p['id'];
    }
}

// Mapeia nota decimal das estrelas para corresponder ao design original
$nota_estrelas = '5.0';
if (empty($p['is_encomenda'])) {
    if ($p['id'] == 1) $nota_estrelas = '4.8';
    elseif ($p['id'] == 2) $nota_estrelas = '4.6';
    elseif ($p['id'] == 3) $nota_estrelas = '4.9';
    elseif ($p['id'] == 4) $nota_estrelas = '4.5';
    elseif ($p['id'] == 5) $nota_estrelas = '4.8';
    elseif ($p['id'] == 6) $nota_estrelas = '4.7';
    elseif ($p['id'] == 7) $nota_estrelas = '4.7';
    elseif ($p['id'] == 8) $nota_estrelas = '4.9';
}
?>
<!-- Card do produto com estrutura unificada -->
<div class="product-card" data-store="<?php echo htmlspecialchars($p['loja']); ?>">
  <div class="product-img">
    <!-- Imagem e badges do produto -->
    <img src="<?php echo htmlspecialchars($p['imagem_url']); ?>" alt="<?php echo htmlspecialchars($p['titulo']); ?>" class="product-img-real" loading="lazy">
    <?php echo $desconto_html; ?>
    <span class="badge-store <?php echo $store_css; ?>"><?php echo $store_name; ?></span>
    
    <!-- Comentário de regra: Botão de adicionar aos favoritos localmente usando localStorage -->
    <button class="btn-wishlist" data-id="<?php echo intval($p['id']); ?>" aria-label="Favoritar produto" onclick="toggleFavorite(<?php echo intval($p['id']); ?>, event)">
      <svg class="heart-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
      </svg>
    </button>
  </div>
  <div class="product-body">
    <p class="product-title"><?php echo htmlspecialchars($p['titulo']); ?></p>
    <div class="product-rating">
      <span class="stars"><?php echo htmlspecialchars($p['estrelas'] ?? '★★★★★'); ?></span>
      <span><?php echo $nota_estrelas; ?> (<?php echo number_format($p['avaliacoes'] ?? 0, 0, ',', '.'); ?> avaliações)</span>
    </div>
    <div class="product-price">
      <?php echo $preco_antigo_html; ?>
      <div class="price-new"><?php echo $preco_novo_fmt; ?></div>
    </div>
    <!-- Botão redirecionando para a nova página de pré-venda individual do produto -->
    <a href="<?php echo $prefix; ?>produto.php?id=<?php echo intval($p['id']); ?>" class="btn-buy btn-detalhes">Ver Detalhes →</a>
  </div>
</div>

