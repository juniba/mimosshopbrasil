<?php
/*
  header.php: Contém o cabeçalho unificado da página com navegação dinâmica.
  Verifica se a sessão do administrador está ativa para renderizar o link de acesso ao painel de forma segura.
*/
// Verifica se o administrador está logado na sessão ativa
$isAdminLogged = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
?>
<!-- header.php: contém o cabeçalho da página com navegação dinâmica e condicional -->
<header>
  <div class="header-inner">
    <!-- A logo aponta para a página inicial index.php e inclui o logotipo do Mimos Shop Brasil -->
    <a href="index.php" class="logo">
      <!-- Imagem do logo (utilizando o logo.png recortado com fundo vermelho) -->
      <img src="img/logo.png" alt="Logo Mimos Shop Brasil" class="logo-img">
      Mimos<span> Shop Brasil</span>
    </a>
    <!-- Menu de navegação (no centro em desktop, dropdown em mobile) -->
    <nav id="main-nav">
      <!-- Link para voltar para a página inicial (Home) -->
      <a href="index.php" onclick="toggleMenu(false)">Home</a>
      <!-- Os links direcionam para a seção correta na index.php -->
      <a href="index.php#ofertas" onclick="toggleMenu(false)">Ofertas</a>
      <!-- O link de produtos aponta para a nova página de catálogo pública produtos.php -->
      <a href="produtos.php" onclick="toggleMenu(false)">Produtos</a>
      <!-- Link para a página de encomendas de links finalizadas -->
      <a href="links.php" onclick="toggleMenu(false)">Links</a>
      <a href="index.php#comparar" onclick="toggleMenu(false)">Comparar</a>
      <a href="index.php#sobre" onclick="toggleMenu(false)">Sobre</a>
      
      <?php 
        // Exibe o link do Painel de Controle apenas se o administrador estiver autenticado
        if ($isAdminLogged): 
          // Consulta as contagens das tabelas no Supabase para exibir os avisos
          $header_news_count = 0;
          $header_req_count = 0;
          
          // Busca IDs da newsletter para contagem rápida
          $res_news = supabase_admin_request('GET', '/rest/v1/newsletter?select=id', null, true);
          if (is_array($res_news)) {
              $header_news_count = count($res_news);
          }
          
          // Busca IDs dos pedidos de links para contagem rápida
          $res_req = supabase_admin_request('GET', '/rest/v1/pedidosLink?select=id', null, true);
          if (is_array($res_req)) {
              $header_req_count = count($res_req);
          }
      ?>
        <a href="painel.php" onclick="toggleMenu(false)">Painel</a>
        
        <!-- Notificação de Pedidos de Links (fundo cinza se zero, colorido se ativo com badge) -->
        <a href="painel.php?action=requests" onclick="toggleMenu(false)" class="header-notification-icon <?php echo $header_req_count > 0 ? 'active' : ''; ?>" title="Pedidos de Links (<?php echo $header_req_count; ?>)">
          📩
          <?php if ($header_req_count > 0): ?>
            <span class="header-badge"><?php echo $header_req_count; ?></span>
          <?php endif; ?>
        </a>
        
        <!-- Notificação de Inscritos na Newsletter (fundo cinza se zero, colorido se ativo com badge) -->
        <a href="painel.php?action=newsletter_leads" onclick="toggleMenu(false)" class="header-notification-icon newsletter <?php echo $header_news_count > 0 ? 'active' : ''; ?>" title="Inscritos na Newsletter (<?php echo $header_news_count; ?>)">
          🔔
          <?php if ($header_news_count > 0): ?>
            <span class="header-badge"><?php echo $header_news_count; ?></span>
          <?php endif; ?>
        </a>
      <?php endif; ?>
    </nav>
    
    <!-- Lado direito do header: engloba busca e o controle do menu móvel -->
    <div class="header-right">
      <!-- Formulário de busca rápida integrado no cabeçalho que submete a pesquisa para a página de produtos -->
      <form action="produtos.php" method="GET" class="header-search">
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
