<?php
/*
  blog/index.php: Página pública principal do blog do Mimos Shop Brasil (REDESIGN MODERNO).
  Exibe o feed de artigos com layout premium (Hero Destaque com Gradiente, Cards Animados,
  Sidebar com Widgets Dinâmicos, Paginação Server-Side) ou abre uma postagem específica
  com breadcrumbs, barra de progresso de leitura, compartilhamento social e artigos relacionados.
*/
// Carrega o arquivo de configuração localizado na pasta raiz
require_once __DIR__ . '/../config.php';

// Captura o slug do artigo se fornecido via GET, ou tenta extrair da URL amigável
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

// Variáveis para armazenar os artigos do blog separados por seção
$artigo = null;
$artigos = [];
$featured_post = null;
$popular_posts = [];
$related_posts = [];

// Configuração de paginação server-side (6 artigos por página)
$per_page = 6;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Prepara os cabeçalhos de autorização para consumo da API REST do Supabase
$blog_opts = [
    "http" => [
        "method" => "GET",
        "header" => "apikey: " . SUPABASE_KEY . "\r\n" .
                    "Authorization: Bearer " . SUPABASE_KEY . "\r\n"
    ]
];
$blog_context = stream_context_create($blog_opts);

// Busca as categorias dinâmicas do Supabase para a sidebar
$cats_url = SUPABASE_URL . "/rest/v1/categorias?select=*&order=id.asc";
$cats_response = fetch_supabase_with_cache($cats_url, $blog_context, 300);
$blog_categorias = json_decode($cats_response, true) ?: [];

if ($slug !== null) {
    // Busca o artigo específico pelo slug único na tabela 'artigos'
    $blog_url = SUPABASE_URL . "/rest/v1/artigos?select=*&slug=eq." . rawurlencode($slug) . "&publicado=eq.true";
    $blog_response = fetch_supabase_with_cache($blog_url, $blog_context, 60);
    $result = json_decode($blog_response, true);
    if (!empty($result)) {
        $artigo = $result[0];
        
        // Busca artigos relacionados (os mais recentes excluindo o atual) para exibir no final
        $related_url = SUPABASE_URL . "/rest/v1/artigos?select=id,titulo,slug,resumo,imagem_url,created_at&publicado=eq.true&slug=neq." . rawurlencode($slug) . "&order=created_at.desc&limit=3";
        $related_response = fetch_supabase_with_cache($related_url, $blog_context, 120);
        $related_posts = json_decode($related_response, true) ?: [];
    }
} else {
    // Busca todos os artigos publicados ordenados pela data de criação decrescente
    $blog_url = SUPABASE_URL . "/rest/v1/artigos?select=*&publicado=eq.true&order=created_at.desc";
    $blog_response = fetch_supabase_with_cache($blog_url, $blog_context, 120);
    $raw_artigos = json_decode($blog_response, true) ?: [];
    
    // Separa os artigos por seções (Destaque, Populares e Últimos Artigos)
    foreach ($raw_artigos as $art) {
        if ($art['is_featured'] === true && $featured_post === null) {
            $featured_post = $art;
        } elseif ($art['is_popular'] === true) {
            $popular_posts[] = $art;
        } else {
            $artigos[] = $art;
        }
    }
    
    // Fallback: se nenhum artigo estiver marcado como destaque, usa o mais recente
    if ($featured_post === null && !empty($artigos)) {
        $featured_post = array_shift($artigos);
    }
}

/**
 * Calcula o tempo estimado de leitura de um texto em minutos.
 * Baseado na velocidade média de leitura em português (200 palavras/minuto).
 */
function calc_reading_time($text) {
    $word_count = str_word_count(strip_tags($text));
    $minutes = max(1, ceil($word_count / 200));
    return $minutes;
}

// Calcula a paginação dos artigos da listagem geral
$total_artigos = count($artigos);
$total_pages = max(1, ceil($total_artigos / $per_page));
$current_page = min($current_page, $total_pages);
$offset = ($current_page - 1) * $per_page;
$artigos_paginated = array_slice($artigos, $offset, $per_page);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <!-- Define a URL base para que os caminhos relativos resolvam a partir da raiz do site -->
  <base href="/">
  
  <?php if ($artigo !== null): ?>
    <!-- SEO Meta Tags para o Artigo de Blog específico -->
    <title><?php echo htmlspecialchars($artigo['titulo']); ?> – Mimos Shop Brasil</title>
    <meta name="description" content="<?php echo htmlspecialchars($artigo['resumo'] ?? ''); ?>">
    <meta name="author" content="Mimos Shop Brasil">
    <link rel="canonical" href="https://mimosshopbrasil.com/blog/<?php echo htmlspecialchars($artigo['slug']); ?>">
    <!-- Open Graph para compartilhamento otimizado do artigo -->
    <meta property="og:title" content="<?php echo htmlspecialchars($artigo['titulo']); ?> – Mimos Shop Brasil">
    <meta property="og:description" content="<?php echo htmlspecialchars($artigo['resumo'] ?? ''); ?>">
    <meta property="og:type" content="article">
    <meta property="og:url" content="https://mimosshopbrasil.com/blog/<?php echo htmlspecialchars($artigo['slug']); ?>">
    <?php if (!empty($artigo['imagem_url'])): ?>
      <meta property="og:image" content="<?php echo htmlspecialchars($artigo['imagem_url']); ?>">
    <?php endif; ?>
    <meta property="og:site_name" content="Mimos Shop Brasil">
    
    <!-- Schema.org Article JSON-LD para SEO estruturado no Google -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Article",
      "headline": "<?php echo htmlspecialchars($artigo['titulo']); ?>",
      "description": "<?php echo htmlspecialchars($artigo['resumo'] ?? ''); ?>",
      <?php if (!empty($artigo['imagem_url'])): ?>
      "image": "<?php echo htmlspecialchars($artigo['imagem_url']); ?>",
      <?php endif; ?>
      "datePublished": "<?php echo $artigo['created_at']; ?>",
      "author": {
        "@type": "Organization",
        "name": "Mimos Shop Brasil"
      },
      "publisher": {
        "@type": "Organization",
        "name": "Mimos Shop Brasil",
        "logo": {
          "@type": "ImageObject",
          "url": "https://mimosshopbrasil.com/img/logo.png"
        }
      }
    }
    </script>
  <?php else: ?>
    <!-- SEO Meta Tags para a Listagem Geral do Blog -->
    <title>Blog – Mimos Shop Brasil | Dicas de Ofertas e Eletrônicos</title>
    <meta name="description" content="Leia as melhores dicas, comparativos e guias de compra de celulares, eletrônicos e utilidades no blog do Mimos Shop Brasil.">
    <link rel="canonical" href="https://mimosshopbrasil.com/blog/">
    <meta property="og:title" content="Blog – Mimos Shop Brasil | Dicas de Ofertas e Eletrônicos">
    <meta property="og:description" content="Leia as melhores dicas, comparativos e guias de compra de celulares, eletrônicos e utilidades no blog do Mimos Shop Brasil.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://mimosshopbrasil.com/blog/">
    <meta property="og:site_name" content="Mimos Shop Brasil">
  <?php endif; ?>
  
  <link rel="icon" type="image/png" href="favicon.png">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <?php 
    // Inclui o cabeçalho global com navegação dinâmica
    include __DIR__ . '/../header.php'; 
  ?>
  
  <?php if ($slug !== null && $artigo !== null): ?>
    <!-- Barra de progresso de leitura animada (avança conforme o scroll) -->
    <div class="reading-progress-bar" id="reading-progress"></div>
  <?php endif; ?>
  
  <div class="blog-container">
    <?php if ($slug !== null): ?>
      <!-- ================================================ -->
      <!-- SEÇÃO: LEITURA DE ARTIGO INDIVIDUAL (REDESIGN)   -->
      <!-- ================================================ -->
      <?php if ($artigo !== null): ?>
        
        <!-- Breadcrumbs de navegação hierárquica para SEO e usabilidade -->
        <nav class="blog-breadcrumbs" aria-label="Breadcrumb">
          <a href="index.php">Home</a>
          <span class="separator">›</span>
          <a href="blog/">Blog</a>
          <span class="separator">›</span>
          <!-- Usamos uma alternativa segura de corte de string para compatibilidade caso a extensão mbstring não esteja ativa -->
          <span class="current"><?php 
            $title_raw = $artigo['titulo'];
            echo htmlspecialchars(strlen($title_raw) > 50 ? substr($title_raw, 0, 47) . '...' : $title_raw); 
          ?></span>
        </nav>
        
        <article class="blog-post-wrap">
          <!-- Metadados da data de publicação e tempo estimado de leitura -->
          <div class="blog-post-date">
            📅 Publicado em <?php echo date('d \d\e F \d\e Y', strtotime($artigo['created_at'])); ?>
            <span class="blog-post-reading-badge">
              ⏱ <?php echo calc_reading_time($artigo['conteudo'] ?? ''); ?> min de leitura
            </span>
          </div>
          
          <!-- Título principal do artigo (H1 único da página) -->
          <h1 class="blog-post-title"><?php echo htmlspecialchars($artigo['titulo']); ?></h1>
          
          <!-- Banner ilustrativo do artigo com lazy loading -->
          <?php if (!empty($artigo['imagem_url'])): ?>
            <div class="blog-post-banner">
              <img src="<?php echo htmlspecialchars($artigo['imagem_url']); ?>" alt="<?php echo htmlspecialchars($artigo['titulo']); ?>" loading="lazy">
            </div>
          <?php endif; ?>
          
          <!-- Conteúdo HTML do artigo com sanitização de segurança -->
          <div class="blog-post-content">
            <?php 
              // Permite apenas tags HTML seguras para prevenir XSS
              $allowed_tags = '<p><br><h1><h2><h3><h4><h5><h6><strong><em><b><i><u><a><ul><ol><li><img><blockquote><table><tr><td><th><thead><tbody><div><span><hr><pre><code>';
              echo strip_tags($artigo['conteudo'], $allowed_tags); 
            ?>
          </div>
          
          <!-- Barra de compartilhamento social com botões estilizados -->
          <div class="blog-share">
            <span class="blog-share-label">Compartilhar:</span>
            <?php 
              // Monta as URLs de compartilhamento para cada rede social
              $share_url = 'https://mimosshopbrasil.com/blog/' . urlencode($artigo['slug']);
              $share_title = urlencode($artigo['titulo']); 
            ?>
            <a href="https://api.whatsapp.com/send?text=<?php echo $share_title . '%20' . urlencode($share_url); ?>" target="_blank" rel="noopener" class="blog-share-btn whatsapp" title="Compartilhar no WhatsApp">
              📱 WhatsApp
            </a>
            <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode($share_url); ?>&text=<?php echo $share_title; ?>" target="_blank" rel="noopener" class="blog-share-btn twitter" title="Compartilhar no Twitter">
              🐦 Twitter
            </a>
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($share_url); ?>" target="_blank" rel="noopener" class="blog-share-btn facebook" title="Compartilhar no Facebook">
              📘 Facebook
            </a>
            <button class="blog-share-btn copy" onclick="navigator.clipboard.writeText('<?php echo $share_url; ?>').then(()=>{this.textContent='✓ Copiado!'; setTimeout(()=>{this.textContent='🔗 Copiar Link'},2000)})" title="Copiar link do artigo">
              🔗 Copiar Link
            </button>
          </div>
          
          <!-- Seção de artigos relacionados para manter o usuário no site -->
          <?php if (!empty($related_posts)): ?>
            <div class="blog-related">
              <h3 class="blog-related-title">📖 Leia também</h3>
              <div class="blog-related-grid">
                <?php foreach ($related_posts as $rel): ?>
                  <a href="blog/<?php echo urlencode($rel['slug']); ?>" class="blog-card">
                    <div class="blog-card-img">
                      <?php if (!empty($rel['imagem_url'])): ?>
                        <img src="<?php echo htmlspecialchars($rel['imagem_url']); ?>" alt="<?php echo htmlspecialchars($rel['titulo']); ?>" loading="lazy">
                      <?php else: ?>
                        <div class="img-placeholder">📝</div>
                      <?php endif; ?>
                    </div>
                    <div class="blog-card-body">
                      <span class="blog-card-date"><?php echo date('d/m/Y', strtotime($rel['created_at'])); ?></span>
                      <h4 class="blog-card-title"><?php echo htmlspecialchars($rel['titulo']); ?></h4>
                      <span class="blog-card-link">Ler artigo →</span>
                    </div>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
          
        </article>
      <?php else: ?>
        <!-- Artigo não localizado: mensagem amigável com link de retorno -->
        <div class="blog-not-found">
          <h2>Artigo não encontrado</h2>
          <p>O artigo que você está procurando não existe ou foi removido.</p>
          <a href="blog/" class="btn btn-white" style="background: var(--brand); color: white; display: inline-flex;">Ir para o Blog</a>
        </div>
      <?php endif; ?>
      
    <?php else: ?>
      <!-- ================================================ -->
      <!-- SEÇÃO: LISTAGEM DE ARTIGOS — FEED DO BLOG        -->
      <!-- ================================================ -->
      <div class="blog-layout">
        
        <!-- Coluna Principal (Destaque + Grid de Artigos + Paginação) -->
        <main class="blog-main">
          
          <!-- Hero de Destaque com Gradiente Overlay sobre a imagem -->
          <?php if ($featured_post !== null): ?>
            <a href="blog/<?php echo urlencode($featured_post['slug']); ?>" class="blog-featured-hero">
              <!-- Imagem de fundo do hero -->
              <?php if (!empty($featured_post['imagem_url'])): ?>
                <img src="<?php echo htmlspecialchars($featured_post['imagem_url']); ?>" alt="<?php echo htmlspecialchars($featured_post['titulo']); ?>" class="hero-bg" loading="lazy">
              <?php endif; ?>
              <!-- Overlay gradiente escuro para legibilidade -->
              <div class="hero-overlay"></div>
              <!-- Conteúdo de texto sobre o hero -->
              <div class="hero-text">
                <div class="hero-meta">
                  <span class="hero-badge">⭐ Destaque</span>
                  <span class="hero-reading-time">
                    ⏱ <?php echo calc_reading_time($featured_post['conteudo'] ?? ''); ?> min de leitura
                  </span>
                </div>
                <h2 class="hero-title"><?php echo htmlspecialchars($featured_post['titulo']); ?></h2>
                <p class="hero-desc"><?php echo htmlspecialchars($featured_post['resumo'] ?? ''); ?></p>
                <span class="hero-cta">Ler artigo completo →</span>
              </div>
            </a>
          <?php endif; ?>
          
          <!-- Título da seção com linha gradiente decorativa -->
          <h2 class="blog-section-title">📰 Últimos Artigos</h2>
          
          <!-- Grid de cards dos artigos com animações staggered -->
          <?php if (!empty($artigos_paginated)): ?>
            <div class="blog-grid">
              <?php foreach ($artigos_paginated as $art): ?>
                <a href="blog/<?php echo urlencode($art['slug']); ?>" class="blog-card">
                  <div class="blog-card-img">
                    <?php if (!empty($art['imagem_url'])): ?>
                      <img src="<?php echo htmlspecialchars($art['imagem_url']); ?>" alt="<?php echo htmlspecialchars($art['titulo']); ?>" loading="lazy">
                    <?php else: ?>
                      <div class="img-placeholder">📝</div>
                    <?php endif; ?>
                  </div>
                  <div class="blog-card-body">
                    <!-- Metadados: data + tempo de leitura -->
                    <div class="blog-card-meta">
                      <span class="blog-card-date"><?php echo date('d/m/Y', strtotime($art['created_at'])); ?></span>
                      <span class="blog-card-reading">⏱ <?php echo calc_reading_time($art['conteudo'] ?? ''); ?> min</span>
                    </div>
                    <h3 class="blog-card-title"><?php echo htmlspecialchars($art['titulo']); ?></h3>
                    <p class="blog-card-excerpt"><?php echo htmlspecialchars($art['resumo'] ?? ''); ?></p>
                    <span class="blog-card-link">Ler mais →</span>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
            
            <!-- Paginação server-side com design moderno -->
            <?php if ($total_pages > 1): ?>
              <nav class="blog-pagination" aria-label="Paginação do blog">
                <!-- Botão Anterior -->
                <?php if ($current_page > 1): ?>
                  <a href="blog/?page=<?php echo $current_page - 1; ?>" title="Página anterior">← Anterior</a>
                <?php else: ?>
                  <span class="disabled">← Anterior</span>
                <?php endif; ?>
                
                <!-- Números das páginas -->
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                  <?php if ($i === $current_page): ?>
                    <span class="active"><?php echo $i; ?></span>
                  <?php else: ?>
                    <a href="blog/?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                  <?php endif; ?>
                <?php endfor; ?>
                
                <!-- Botão Próximo -->
                <?php if ($current_page < $total_pages): ?>
                  <a href="blog/?page=<?php echo $current_page + 1; ?>" title="Próxima página">Próxima →</a>
                <?php else: ?>
                  <span class="disabled">Próxima →</span>
                <?php endif; ?>
              </nav>
            <?php endif; ?>
            
          <?php else: ?>
            <p class="text-muted" style="text-align:center; padding: 3rem 0;">Nenhum artigo publicado no momento. Volte em breve!</p>
          <?php endif; ?>
          
        </main>
        
        <!-- Coluna Lateral (Sidebar com Widgets Dinâmicos) -->
        <aside class="blog-sidebar">
          
          <!-- Widget de Busca no Blog -->
          <div class="blog-widget">
            <h3 class="blog-widget-title">🔍 Buscar</h3>
            <form action="produtos.php" method="GET" class="blog-search-form">
              <input type="text" name="search" placeholder="Buscar no site..." required>
              <button type="submit" aria-label="Buscar">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
              </button>
            </form>
          </div>
          
          <!-- Widget de Categorias Dinâmicas (carregadas do Supabase) -->
          <div class="blog-widget">
            <h3 class="blog-widget-title">📂 Categorias</h3>
            <div class="blog-categories-list">
              <?php if (!empty($blog_categorias)): ?>
                <?php foreach ($blog_categorias as $cat): ?>
                  <!-- Link dinâmico para filtrar produtos pela categoria no catálogo -->
                  <a href="produtos.php?category=<?php echo $cat['id']; ?>">
                    <?php echo htmlspecialchars($cat['icone'] ?? '📦') . ' ' . htmlspecialchars($cat['nome']); ?>
                    <span class="cat-arrow">→</span>
                  </a>
                <?php endforeach; ?>
              <?php else: ?>
                <a href="produtos.php">Ver todos os produtos <span class="cat-arrow">→</span></a>
              <?php endif; ?>
            </div>
          </div>
          
          <!-- Widget de Artigos Populares -->
          <?php if (!empty($popular_posts)): ?>
            <div class="blog-widget">
              <h3 class="blog-widget-title">🔥 Mais Populares</h3>
              <div class="blog-popular-list">
                <?php foreach ($popular_posts as $pop): ?>
                  <a href="blog/<?php echo urlencode($pop['slug']); ?>" class="blog-popular-item">
                    <div class="blog-popular-thumb">
                      <?php if (!empty($pop['imagem_url'])): ?>
                        <img src="<?php echo htmlspecialchars($pop['imagem_url']); ?>" alt="<?php echo htmlspecialchars($pop['titulo']); ?>" loading="lazy">
                      <?php else: ?>
                        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">📝</div>
                      <?php endif; ?>
                    </div>
                    <div class="blog-popular-info">
                      <h4 class="pop-title"><?php echo htmlspecialchars($pop['titulo']); ?></h4>
                      <span class="pop-date"><?php echo date('d/m/Y', strtotime($pop['created_at'])); ?></span>
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
    // Inclui o rodapé global com categorias do Supabase
    include __DIR__ . '/../footer.php'; 
  ?>
  
  <!-- Script principal do site -->
  <script src="js/main.js"></script>
  
  <?php if ($slug !== null && $artigo !== null): ?>
  <!-- Script da barra de progresso de leitura: avança conforme o scroll do artigo -->
  <script>
    // Atualiza a largura da barra de progresso conforme o usuário rola a página
    (function() {
      const progressBar = document.getElementById('reading-progress');
      if (!progressBar) return;
      
      window.addEventListener('scroll', function() {
        // Calcula a porcentagem de scroll da página inteira
        const scrollTop = window.scrollY || document.documentElement.scrollTop;
        const docHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
        const progress = (scrollTop / docHeight) * 100;
        // Atualiza a largura da barra com o percentual calculado
        progressBar.style.width = Math.min(100, progress) + '%';
      });
    })();
  </script>
  <?php endif; ?>
</body>
</html>
