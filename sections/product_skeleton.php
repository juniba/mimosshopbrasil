<?php
/*
  product_skeleton.php: Template parcial reutilizável para exibir skeletons de carregamento de produtos.
  Renderiza um número de cartões de skeleton de acordo com a variável $skeleton_count (padrão: 4).
  
  Comentário de regra: Este arquivo desenha os skeletons shimmer de carregamento de produtos.
*/
$count = isset($skeleton_count) ? intval($skeleton_count) : 4;
for ($i = 0; $i < $count; $i++):
?>
<div class="product-skeleton-card">
  <div class="product-skeleton-img product-skeleton"></div>
  <div class="product-skeleton-title product-skeleton"></div>
  <div class="product-skeleton-rating product-skeleton"></div>
  <div class="product-skeleton-price product-skeleton"></div>
  <div class="product-skeleton-btn product-skeleton"></div>
</div>
<?php endfor; ?>
