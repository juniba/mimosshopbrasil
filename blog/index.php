<?php
/*
  blog/index.php: Página pública principal do blog (localizada na pasta /blog).
  Exibe o feed de artigos com layout estruturado (Destaque, Categorias, Artigos Populares e Últimos Artigos) 
  ou abre uma postagem específica pelo slug.
  Respeita a regra global de comentários explicativos.
*/
// Carrega o arquivo de configuração localizado na pasta raiz de forma robusta usando caminhos absolutos (__DIR__)
require_once __DIR__ . '/../config.php';

// Captura o slug do artigo se fornecido via GET, ou tenta extrair diretamente da URL (suporte universal)
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : null;
if ($slug === null) {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $parts = explode('/', trim($path, '/'));
    // Se a URL terminar com /blog/algum-slug, extrai o slug correspondente
    if (count($parts) >= 2 && $parts[0] === 'blog') {
        $second_part = $parts[1];
        if (!empty($second_part) && $second_part !== 'index.php') {
            $slug = $second_part;
        }
    }
}

$artigo = null;
$artigos = [];
$featured_post = null;
$popular_posts = [];

// Prepara os cabeçalhos de autorização padrão para consumo da REST API do Supabase
$blog_opts = [
    "http" => [
        "method" => "GET",
        "header" => "apikey: " . SUPABASE_KEY . "\r\n" .
                    "Authorization: Bearer " . SUPABASE_KEY . "\r\n"
    ]
];
$blog_context = stream_context_create($blog_opts);

if ($slug !== null) {
    // Busca o artigo específico pelo slug único na tabela 'artigos' (usando cache local de 60 segundos)
    $blog_url = SUPABASE_URL . "/rest/v1/artigos?select=*&slug=eq." . rawurlencode($slug) . "&publicado=eq.true";
    $blog_response = fetch_supabase_with_cache($blog_url, $blog_context, 60);
    $result = json_decode($blog_response, true);
    if (!empty($result)) {
        $artigo = $result[0];
    }
} else {
    // Busca todos os artigos publicados ordenados pela data de criação decrescente (usando cache local de 120 segundos)
    $blog_url = SUPABASE_URL . "/rest/v1/artigos?select=*&publicado=eq.true&order=created_at.desc";
    $blog_response = fetch_supabase_with_cache($blog_url, $blog_context, 120);
    $raw_artigos = json_decode($blog_response, true) ?: [];
    
    // Separa os artigos por seções (Destaque, Populares e Últimos Artigos)
    foreach ($raw_artigos as $art) {
        if ($art['is_featured'] === true && $featured_post === null) {
            // Define o artigo de destaque principal
            $featured_post = $art;
        } elseif ($art['is_popular'] === true) {
            // Adiciona à lista de artigos populares
            $popular_posts[] = $art;
        } else {
            // Adiciona à lista geral de últimos artigos
            $artigos[] = $art;
        }
    }
    
    // Fallback: Caso nenhum artigo esteja marcado como destaque, define o mais recente como destaque
    if ($featured_post === null && !empty($artigos)) {
        $featured_post = array_shift($artigos);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <!-- Define a URL base para que os caminhos relativos (CSS, JS, links) resolvam a partir da raiz do site -->
  <base href="/">
  
  <?php if ($artigo !== null): ?>
    <!-- SEO Meta Tags para o Artigo de Blog específico -->
    <title><?php echo htmlspecialchars($artigo['titulo']); ?> – Mimos Shop Brasil</title>
    <meta name="description" content="<?php echo htmlspecialchars($artigo['resumo'] ?? ''); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($artigo['titulo']); ?> – Mimos Shop Brasil">
    <meta property="og:description" content="<?php echo htmlspecialchars($artigo['resumo'] ?? ''); ?>">
    <?php if (!empty($artigo['imagem_url'])): ?>
      <meta property="og:image" content="<?php echo htmlspecialchars($artigo['imagem_url']); ?>">
    <?php endif; ?>
  <?php else: ?>
    <!-- SEO Meta Tags para a Listagem Geral do Blog -->
    <title>Blog Mimos Shop Brasil – Dicas de Ofertas e Eletrônicos</title>
    <meta name="description" content="Leia as melhores dicas, comparativos e guias de compra de celulares, eletrônicos e utilidades no blog do Mimos Shop Brasil.">
    <meta property="og:title" content="Blog Mimos Shop Brasil – Dicas de Ofertas e Eletrônicos">
    <meta property="og:description" content="Leia as melhores dicas, comparativos e guias de compra de celulares, eletrônicos e utilidades no blog do Mimos Shop Brasil.">
  <?php endif; ?>
  
  <link rel="icon" type="image/png" href="favicon.png">
  <link rel="stylesheet" href="css/style.css">
  
  <style>
    /* Estilos dedicados para a página do blog */
    .blog-container {
      max-width: 1200px;
      margin: 2rem auto;
      padding: 0 1.5rem;
      min-height: calc(100vh - 450px);
    }
    
    /* Layout de Grade de duas colunas (Principal e Barra Lateral) */
    .blog-layout {
      display: grid;
      grid-template-columns: 2.5fr 1fr;
      gap: 2.5rem;
    }
    @media (max-width: 992px) {
      .blog-layout {
        grid-template-columns: 1fr;
      }
    }
    
    /* Artigo em Destaque */
    .featured-box {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      margin-bottom: 3rem;
      box-shadow: var(--shadow);
      display: flex;
      flex-direction: column;
      text-decoration: none;
      color: var(--text);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .featured-box:hover {
      transform: translateY(-3px);
      box-shadow: var(--shadow-hover);
      border-color: var(--brand);
    }
    .featured-img-wrap {
      width: 100%;
      aspect-ratio: 21/9;
      background: #F1F5F9;
      overflow: hidden;
    }
    .featured-img-wrap img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .featured-content {
      padding: 2rem;
    }
    .featured-badge {
      display: inline-block;
      background: #EF4444;
      color: white;
      font-size: 0.72rem;
      font-weight: 800;
      padding: 0.25rem 0.75rem;
      border-radius: 99px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 1rem;
    }
    .featured-title {
      font-size: 1.8rem;
      font-weight: 800;
      line-height: 1.3;
      margin-bottom: 0.75rem;
      letter-spacing: -0.5px;
    }
    .featured-desc {
      font-size: 1rem;
      color: var(--text-muted);
      line-height: 1.6;
      margin-bottom: 1.25rem;
    }
    .featured-link {
      font-size: 0.9rem;
      font-weight: 700;
      color: var(--brand);
    }
    
    /* Seção de Últimos Artigos */
    .section-title {
      font-size: 1.4rem;
      font-weight: 800;
      color: var(--text);
      border-bottom: 2px solid var(--brand);
      padding-bottom: 0.5rem;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    /* Grade de cards do blog */
    .blog-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 1.5rem;
    }
    .blog-card {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      display: flex;
      flex-direction: column;
      transition: all 0.2s ease-in-out;
      text-decoration: none;
      color: var(--text);
    }
    .blog-card:hover {
      transform: translateY(-4px);
      box-shadow: var(--shadow-hover);
      border-color: var(--brand);
    }
    .blog-card-img {
      aspect-ratio: 16/10;
      background: #F1F5F9;
      overflow: hidden;
    }
    .blog-card-img img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .blog-card-body {
      padding: 1.25rem;
      flex: 1;
      display: flex;
      flex-direction: column;
    }
    .blog-card-date {
      font-size: 0.75rem;
      color: var(--text-muted);
      margin-bottom: 0.5rem;
      font-weight: 600;
    }
    .blog-card-title {
      font-size: 1.1rem;
      font-weight: 700;
      line-height: 1.4;
      margin-bottom: 0.5rem;
      color: var(--text);
    }
    .blog-card-excerpt {
      font-size: 0.85rem;
      color: var(--text-muted);
      line-height: 1.5;
      margin-bottom: 1rem;
      display: -webkit-box;
      -webkit-line-clamp: 3;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    .blog-card-link {
      margin-top: auto;
      font-size: 0.82rem;
      font-weight: 700;
      color: var(--brand);
    }

    /* Barra Lateral (Widgets) */
    .blog-sidebar {
      display: flex;
      flex-direction: column;
      gap: 2.5rem;
    }
    
    .widget {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 1.5rem;
      box-shadow: var(--shadow);
    }
    .widget-title {
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--text);
      margin-bottom: 1.25rem;
      border-bottom: 1px solid var(--border);
      padding-bottom: 0.5rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    /* Lista de Categorias */
    .widget-categories {
      display: flex;
      flex-direction: column;
      gap: 0.50rem;
    }
    .widget-categories a {
      display: flex;
      align-items: center;
      justify-content: space-between;
      color: var(--text);
      text-decoration: none;
      font-size: 0.9rem;
      font-weight: 600;
      padding: 0.5rem 0.75rem;
      border-radius: var(--radius-sm);
      transition: background 0.15s, color 0.15s;
    }
    .widget-categories a:hover {
      background: #F1F5F9;
      color: var(--brand);
    }
    .widget-categories a span {
      background: #E2E8F0;
      font-size: 0.75rem;
      padding: 0.15rem 0.5rem;
      border-radius: 99px;
      color: var(--text-muted);
    }
    
    /* Lista de Artigos Populares */
    .widget-popular {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }
    .widget-popular-item {
      display: flex;
      gap: 0.75rem;
      text-decoration: none;
      color: var(--text);
    }
    .widget-popular-item:hover .popular-title {
      color: var(--brand);
    }
    .widget-popular-img {
      width: 64px;
      height: 64px;
      border-radius: 6px;
      overflow: hidden;
      flex-shrink: 0;
      background: #F1F5F9;
    }
    .widget-popular-img img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .popular-title {
      font-size: 0.88rem;
      font-weight: 700;
      line-height: 1.35;
      color: var(--text);
      transition: color 0.15s;
    }
    .popular-date {
      font-size: 0.72rem;
      color: var(--text-muted);
      margin-top: 0.25rem;
      display: block;
    }
    
    /* Estilos do Artigo Completo */
    .post-wrap {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 2.5rem;
      box-shadow: var(--shadow);
    }
    @media (max-width: 768px) {
      .post-wrap {
        padding: 1.5rem;
      }
    }
    .post-back {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      color: var(--text-muted);
      text-decoration: none;
      font-weight: 600;
      font-size: 0.88rem;
      margin-bottom: 1.5rem;
      transition: color 0.15s;
    }
    .post-back:hover {
      color: var(--brand);
    }
    .post-date {
      font-size: 0.82rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--text-muted);
      margin-bottom: 0.75rem;
    }
    .post-title {
      font-size: 2.4rem;
      font-weight: 800;
      line-height: 1.25;
      margin-bottom: 1.5rem;
      color: var(--text);
      letter-spacing: -0.8px;
    }
    .post-banner {
      width: 100%;
      max-height: 480px;
      border-radius: var(--radius-sm);
      overflow: hidden;
      margin-bottom: 2rem;
      background: #F1F5F9;
    }
    .post-banner img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .post-content {
      font-size: 1.05rem;
      line-height: 1.8;
      color: #334155;
    }
    .post-content p { margin-bottom: 1.5rem; }
    .post-content h2, .post-content h3 {
      color: var(--text);
      margin-top: 2rem;
      margin-bottom: 1rem;
      font-weight: 700;
    }
    
    .post-not-found {
      text-align: center;
      padding: 5rem 2rem;
    }
  </style>
</head>
<body>
  <?php 
    // Inclui o cabeçalho global localizado na pasta raiz usando caminho absoluto
    include __DIR__ . '/../header.php'; 
  ?>
  
  <div class="blog-container">
    <?php if ($slug !== null): ?>
      <!-- SEÇÃO: LEITURA DE ARTIGO INDIVIDUAL -->
      <?php if ($artigo !== null): ?>
        <article class="post-wrap">
          <!-- Link para voltar ao feed de notícias do blog -->
          <a href="blog/" class="post-back">← Voltar para o Blog</a>
          
          <!-- Metadados da data de criação -->
          <div class="post-date">Publicado em <?php echo date('d \d\e F \d\e Y', strtotime($artigo['created_at'])); ?></div>
          
          <!-- Título principal do artigo -->
          <h1 class="post-title"><?php echo htmlspecialchars($artigo['titulo']); ?></h1>
          
          <!-- Banner ilustrativo do artigo se disponível -->
          <?php if (!empty($artigo['imagem_url'])): ?>
            <div class="post-banner">
              <img src="<?php echo htmlspecialchars($artigo['imagem_url']); ?>" alt="<?php echo htmlspecialchars($artigo['titulo']); ?>">
            </div>
          <?php endif; ?>
          
          <!-- Conteúdo HTML do artigo carregado do banco de dados -->
          <div class="post-content">
            <?php 
              // Exibe o conteúdo permitindo HTML básico cadastrado pelo administrador
              echo $artigo['conteudo']; 
            ?>
          </div>
        </article>
      <?php else: ?>
        <!-- Artigo não localizado -->
        <div class="post-not-found">
          <h2>Artigo não encontrado</h2>
          <p>O artigo que você está procurando não existe ou foi removido.</p>
          <a href="blog/" class="btn-buy btn-amazon" style="display:inline-block; width:auto; margin-top:1.5rem; padding:0.75rem 2rem;">Ir para o Blog</a>
        </div>
      <?php endif; ?>
      
    <?php else: ?>
      <!-- SEÇÃO: LISTAGEM DE ARTIGOS (FEED DO BLOG ESTRUTURADO) -->
      <div class="blog-layout">
        
        <!-- Coluna Principal (Destaque e Últimos Artigos) -->
        <main class="blog-main">
          
          <!-- Artigo em Destaque Principal -->
          <?php if ($featured_post !== null): ?>
            <h2 class="section-title">⭐ Destaque</h2>
            <a href="blog/<?php echo urlencode($featured_post['slug']); ?>" class="featured-box">
              <div class="featured-img-wrap">
                <?php if (!empty($featured_post['imagem_url'])): ?>
                  <img src="<?php echo htmlspecialchars($featured_post['imagem_url']); ?>" alt="<?php echo htmlspecialchars($featured_post['titulo']); ?>">
                <?php endif; ?>
              </div>
              <div class="featured-content">
                <span class="featured-badge">Artigo em Destaque</span>
                <h3 class="featured-title"><?php echo htmlspecialchars($featured_post['titulo']); ?></h3>
                <p class="featured-desc"><?php echo htmlspecialchars($featured_post['resumo'] ?? ''); ?></p>
                <span class="featured-link">Ler artigo completo →</span>
              </div>
            </a>
          <?php endif; ?>
          
          <!-- Seção de Últimos Artigos (Feed) -->
          <h2 class="section-title">⏰ Últimos Artigos</h2>
          <?php if (!empty($artigos)): ?>
            <div class="blog-grid">
              <?php foreach ($artigos as $art): ?>
                <a href="blog/<?php echo urlencode($art['slug']); ?>" class="blog-card">
                  <div class="blog-card-img">
                    <?php if (!empty($art['imagem_url'])): ?>
                      <img src="<?php echo htmlspecialchars($art['imagem_url']); ?>" alt="<?php echo htmlspecialchars($art['titulo']); ?>">
                    <?php else: ?>
                      <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:#E2E8F0; font-size:2rem;">📝</div>
                    <?php endif; ?>
                  </div>
                  <div class="blog-card-body">
                    <span class="blog-card-date"><?php echo date('d/m/Y', strtotime($art['created_at'])); ?></span>
                    <h3 class="blog-card-title"><?php echo htmlspecialchars($art['titulo']); ?></h3>
                    <p class="blog-card-excerpt"><?php echo htmlspecialchars($art['resumo'] ?? ''); ?></p>
                    <span class="blog-card-link">Ler mais →</span>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-muted">Nenhum outro artigo publicado no momento.</p>
          <?php endif; ?>
          
        </main>
        
        <!-- Coluna Lateral (Widget de Categorias e Artigos Populares) -->
        <aside class="blog-sidebar">
          
          <!-- Widget de Categorias -->
          <div class="widget">
            <h3 class="widget-title">Categorias</h3>
            <div class="widget-categories">
              <!-- As categorias direcionam o usuário para a página de produtos catálogo correspondente -->
              <a href="produtos.php?category=1">Tecnologia <span>→</span></a>
              <a href="produtos.php?category=2">Casa <span>→</span></a>
              <a href="produtos.php?category=3">Ferramentas <span>→</span></a>
              <a href="produtos.php?category=4">Beleza <span>→</span></a>
              <a href="produtos.php?category=5">Esporte <span>→</span></a>
            </div>
          </div>
          
          <!-- Widget de Artigos Populares -->
          <?php if (!empty($popular_posts)): ?>
            <div class="widget">
              <h3 class="widget-title">Mais Populares</h3>
              <div class="widget-popular">
                <?php foreach ($popular_posts as $pop): ?>
                  <a href="blog/<?php echo urlencode($pop['slug']); ?>" class="widget-popular-item">
                    <div class="widget-popular-img">
                      <?php if (!empty($pop['imagem_url'])): ?>
                        <img src="<?php echo htmlspecialchars($pop['imagem_url']); ?>" alt="">
                      <?php else: ?>
                        <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:#E2E8F0; font-size:1.2rem;">📝</div>
                      <?php endif; ?>
                    </div>
                    <div>
                      <h4 class="popular-title"><?php echo htmlspecialchars($pop['titulo']); ?></h4>
                      <span class="popular-date"><?php echo date('d/m/Y', strtotime($pop['created_at'])); ?></span>
                    </div>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
          
        </aside>
        
      </div>
    <?php endif; ?>
  </div>
  
  <?php 
    // Inclui o rodapé global localizado na pasta raiz usando caminho absoluto
    include __DIR__ . '/../footer.php'; 
  ?>
  <script src="js/main.js"></script>
</body>
</html>
