<?php
/*
  header.php: Contém o cabeçalho unificado da página com navegação dinâmica.
  Verifica se a sessão do administrador está ativa para renderizar o link de acesso ao painel de forma segura.
  
  Comentário de regra: Este arquivo detecta a página ativa para aplicar classe visual correspondente nos links do nav.
*/
// Verifica se o administrador está logado na sessão ativa
$isAdminLogged = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Determina o prefixo de caminho relativo para links quando o header for incluído de subpastas como /admin/ ou /blog/
$prefix = isset($path_prefix) ? $path_prefix : '';

// Detecta qual é a página ativa no menu de navegação com base no script atual
$current_script = $_SERVER['SCRIPT_NAME'] ?? '';
$is_home = (basename($current_script) === 'index.php' && strpos($current_script, '/blog/') === false && strpos($current_script, '/admin/') === false);
$is_produtos = (basename($current_script) === 'produtos.php');
$is_links = (basename($current_script) === 'links.php');
$is_favoritos = (basename($current_script) === 'favoritos.php');
$is_blog = (strpos($current_script, '/blog/') !== false);
$is_painel = (strpos($current_script, '/admin/') !== false);
?>
<?php include __DIR__ . '/seo.php'; ?>
<?php
// Gerar e imprimir tags SEO padrão
echo "<title>" . seo_title() . "</title>\n";
echo "<meta name=\"description\" content=\"" . seo_description() . "\">\n";
echo seo_open_graph();
echo "\n" . seo_json_ld_site();
?>
<?php
// Comentário explicativo: Se o GTM estiver ativo, injeta o snippet noscript logo no início do corpo da página para fallbacks
$header_gtm_id = defined('GOOGLE_TAG_MANAGER_ID') ? GOOGLE_TAG_MANAGER_ID : '';
if (!empty($header_gtm_id)):
?>
  <!-- Google Tag Manager (noscript) -->
  <noscript>
    <!-- Comentário explicativo: Carrega o iframe do GTM caso o usuário esteja com o JavaScript desativado no navegador -->
    <iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo htmlspecialchars($header_gtm_id); ?>"
    height="0" width="0" style="display:none;visibility:hidden"></iframe>
  </noscript>
  <!-- End Google Tag Manager (noscript) -->
<?php endif; ?>
<!-- header.php: contém o cabeçalho da página com navegação dinâmica e condicional -->
<header>

  <div class="header-inner">
    <!-- A logo aponta para a página inicial index.php e exibe apenas a marca gráfica do MCD TrendDeals -->
    <a href="<?php echo $prefix; ?>index.php" class="logo">
      <!-- Comentário explicativo: Substituição do caminho e do texto alt para a nova marca do site -->
      <img src="<?php echo $prefix; ?>img/logo_mcdmarketprime.png" alt="MCD TrendDeals" class="logo-img" style="height: 40px;">
    </a>
    <!-- Menu de navegação (no centro em desktop, dropdown em mobile) -->
    <nav id="main-nav">
      <!-- Link para voltar para a página inicial (Home) -->
      <a href="<?php echo $prefix; ?>index.php" onclick="toggleMenu(false)" class="<?php echo $is_home ? 'active' : ''; ?>">Home</a>
      <!-- Os links direcionam para a seção correta na index.php -->
      <a href="<?php echo $prefix; ?>index.php#ofertas" onclick="toggleMenu(false)">Ofertas</a>
      <!-- O link de produtos aponta para a nova página de catálogo pública produtos.php -->
      <a href="<?php echo $prefix; ?>produtos.php" onclick="toggleMenu(false)" class="<?php echo $is_produtos ? 'active' : ''; ?>">Produtos</a>
      <!-- Link para a página de encomendas de links finalizadas -->
      <a href="<?php echo $prefix; ?>links.php" onclick="toggleMenu(false)" class="<?php echo $is_links ? 'active' : ''; ?>">Links</a>
      <!-- Link para a página de produtos favoritados pelo visitante (localStorage) -->
      <a href="<?php echo $prefix; ?>favoritos.php" onclick="toggleMenu(false)" class="<?php echo $is_favoritos ? 'active' : ''; ?>">Favoritos</a>
      <!-- Link para a nova página pública de Blog (pasta blog/) -->
      <a href="<?php echo $prefix; ?>blog/" onclick="toggleMenu(false)" class="<?php echo $is_blog ? 'active' : ''; ?>">Blog</a>
      <a href="<?php echo $prefix; ?>index.php#comparar" onclick="toggleMenu(false)">Comparar</a>
      <a href="<?php echo $prefix; ?>index.php#sobre" onclick="toggleMenu(false)">Sobre</a>
      
      <?php 
        // Exibe o link do Painel de Controle apenas se o administrador estiver autenticado
        if ($isAdminLogged): 
          // Consulta as contagens das tabelas no Supabase para exibir os avisos
          $header_news_count = 0;
          $header_req_count = 0;
          
          // Busca IDs da newsletter para contagem rápida filtrando apenas pelos não lidos (lido=eq.false)
          $res_news = supabase_admin_request('GET', '/rest/v1/newsletter?select=id&lido=eq.false', null, true);
          if (is_array($res_news)) {
              $header_news_count = count($res_news);
          }
          
          // Busca IDs dos pedidos de links para contagem rápida filtrando apenas pelos não lidos (lido=eq.false)
          $res_req = supabase_admin_request('GET', '/rest/v1/pedidosLink?select=id&lido=eq.false', null, true);
          if (is_array($res_req)) {
              $header_req_count = count($res_req);
          }
      ?>
        <a href="<?php echo $prefix; ?>admin/painel.php" onclick="toggleMenu(false)" class="<?php echo $is_painel ? 'active' : ''; ?>">Painel</a>
        
        <!-- Notificação de Pedidos de Links (fundo cinza se zero, colorido se ativo com badge) -->
        <a href="<?php echo $prefix; ?>admin/painel.php?action=requests" onclick="toggleMenu(false)" class="header-notification-icon <?php echo $header_req_count > 0 ? 'active' : ''; ?>" title="Pedidos de Links (<?php echo $header_req_count; ?>)">
          <i data-lucide="mail"></i>
          <?php if ($header_req_count > 0): ?>
            <span class="header-badge"><?php echo $header_req_count; ?></span>
          <?php endif; ?>
        </a>
        
        <!-- Notificação de Inscritos na Newsletter (fundo cinza se zero, colorido se ativo com badge) -->
        <a href="<?php echo $prefix; ?>admin/painel.php?action=newsletter_leads" onclick="toggleMenu(false)" class="header-notification-icon newsletter <?php echo $header_news_count > 0 ? 'active' : ''; ?>" title="Inscritos na Newsletter (<?php echo $header_news_count; ?>)">
          <i data-lucide="bell"></i>
          <?php if ($header_news_count > 0): ?>
            <span class="header-badge"><?php echo $header_news_count; ?></span>
          <?php endif; ?>
        </a>
      <?php endif; ?>
    </nav>
    
    <!-- Lado direito do header: engloba busca e o controle do menu móvel -->
    <div class="header-right">
      <!-- Formulário de busca rápida integrado no cabeçalho que submete a pesquisa para a página de produtos -->
      <form action="<?php echo $prefix; ?>produtos.php" method="GET" class="header-search">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        <input type="text" name="search" placeholder="Buscar produto..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
      </form>

      <!-- Botão do menu hambúrguer para dispositivos móveis (3 linhas horizontais) -->
      <button class="menu-toggle" id="menu-toggle" aria-label="Abrir menu" onclick="toggleMenu()">
        <span></span>
        <span></span>
        <span></span>
      </button>
    </div>
  </div>
</header>

