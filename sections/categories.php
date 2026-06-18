<?php
/*
  categories.php: Carrega dinamicamente a lista de categorias do banco de dados do Supabase
  e renderiza os cards de categorias rápidos na página inicial.
*/

// Prepara o contexto HTTP com os cabeçalhos de autenticação da API REST do Supabase
$opts = [
    "http" => [
        "method" => "GET",
        "header" => "apikey: " . SUPABASE_KEY . "\r\n" .
                    "Authorization: Bearer " . SUPABASE_KEY . "\r\n"
    ]
];
$context = stream_context_create($opts);

// URL do endpoint de categorias ordenadas por ID, incluindo os IDs dos produtos relacionados para sabermos quais têm produtos cadastrados
$url = SUPABASE_URL . "/rest/v1/categorias?select=*,produtos(id)&order=id.asc";

// Faz a requisição HTTP GET para obter o JSON de categorias (com cache local)
$response = fetch_supabase_with_cache($url, $context);
$categorias_raw = json_decode($response, true);

// Filtra a lista de categorias mantendo apenas aquelas que possuem pelo menos um produto associado
$categorias = [];
if (!empty($categorias_raw)) {
    $categorias = array_filter($categorias_raw, function($cat) {
        return !empty($cat['produtos']);
    });
}
?>
<!-- Seção Categorias: Grade de atalhos rápidos dinâmicos puxados diretamente do Supabase -->
<section class="section" id="categorias">
  <div class="section-header">
    <h2>Categorias</h2>
    <!-- Link para ver todas as categorias disponíveis 
    <a href="#">Ver todas →</a>-->
  </div>
  <div class="categories-grid">
    <?php 
      // Se houver categorias cadastradas, exibe cada uma delas como um card de atalho
      if (!empty($categorias)): 
        foreach ($categorias as $cat):
    ?>
      <!-- Card de categoria com link dinâmico para filtrar produtos por categoria -->
      <a class="cat-card" href="produtos.php?category=<?php echo $cat['id']; ?>">
        <!-- Emoji que atua como ícone da categoria -->
        <span class="cat-icon"><?php echo htmlspecialchars($cat['icone']); ?></span>
        <!-- Título da categoria -->
        <span><?php echo htmlspecialchars($cat['nome']); ?></span>
      </a>
    <?php 
        endforeach; 
      else:
    ?>
      <p class="text-muted">Nenhuma categoria encontrada.</p>
    <?php endif; ?>
  </div>
</section>
