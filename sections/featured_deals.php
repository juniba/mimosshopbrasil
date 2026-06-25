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

// Comentário explicativo: Endpoints da API para trazer produtos com relacionamento da loja, ordenados pelo mais recente e limitados a 8 ofertas
$url = SUPABASE_URL . "/rest/v1/produtos?select=*,store(nome,css_class)&is_featured=eq.true&order=id.desc&limit=8";


// Executa a requisição GET para pegar os produtos em formato JSON (com cache local)
$response = fetch_supabase_with_cache($url, $context);
$produtos = json_decode($response, true);
?>
<section class="section" id="ofertas">
  <div class="section-header">
    <h2>Ofertas do dia <span class="tag-hot"><i data-lucide="flame" style="width: 14px; height: 14px; vertical-align: middle; stroke-width: 2.5; display: inline-block; margin-right: 2px;"></i> HOT</span></h2>
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
          // Renderiza o card do produto usando o template unificado
          include 'sections/product_card.php';
        endforeach; 
      else:
    ?>
      <!-- Mensagem amigável caso não existam ofertas ativas no banco -->
      <p class="text-muted">Nenhuma oferta encontrada no momento.</p>
    <?php endif; ?>
  </div>
</section>
