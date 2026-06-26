<?php
/*
  favoritos.php: Exibe a lista de produtos favoritados pelo visitante (salvos no localStorage).
  Recebe os IDs dos produtos favoritos por parâmetro GET ("ids") e busca os dados no Supabase.
*/
require_once 'config.php';

// Captura e sanitiza os IDs de produtos favoritados passados do localStorage via GET
$ids_param = isset($_GET['ids']) ? trim($_GET['ids']) : '';
$produtos = [];

if (!empty($ids_param)) {
    // Remove qualquer caractere que não seja número ou vírgula
    $clean_ids = preg_replace('/[^0-9,]/', '', $ids_param);
    
    // Converte em um array e remove itens vazios
    $ids_array = array_filter(explode(',', $clean_ids));
    
    if (!empty($ids_array)) {
        // Constrói a string de IDs no padrão do PostgREST (ex: id=in.(1,2,3))
        $clean_ids_str = implode(',', $ids_array);
        
        // Define as credenciais do Supabase
        $opts = [
            "http" => [
                "method" => "GET",
                "header" => "apikey: " . SUPABASE_KEY . "\r\n" .
                            "Authorization: Bearer " . SUPABASE_KEY . "\r\n"
            ]
        ];
        $context = stream_context_create($opts);
        
        // Comentário de regra: Monta a URL de busca de produtos utilizando o filtro "in" do Supabase
        $url_produtos = SUPABASE_URL . "/rest/v1/produtos?select=*,store(nome,css_class)&id=in.(" . $clean_ids_str . ")";
        
        // Executa a busca (com cache de curto prazo, TTL de 60s)
        $response_produtos = fetch_supabase_with_cache($url_produtos, $context, 60);
        $produtos = $response_produtos !== false ? json_decode($response_produtos, true) : [];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Meus Favoritos – MCD Market Prime</title>
  <meta name="description" content="Confira os produtos salvos em sua lista de desejos no MCD Market Prime. Veja preços e cupons das melhores ofertas salvos para depois.">
  <meta name="robots" content="noindex, nofollow">
  <link rel="canonical" href="https://mcdmarketprime.com/favoritos.php">
  
  <!-- Preconnect e fontes do Google Fonts carregadas via head para otimização de renderização -->
  <!-- Comentário de regra: Este bloco carrega a fonte Inter de forma performática -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  
  <!-- Preload de recursos críticos de imagem para melhorar o LCP/CLS -->
  <link rel="preload" href="img/logo_mcdmarketprime.png" as="image">

  <!-- Favicon para exibição correta na aba do navegador -->
  <link rel="icon" type="image/png" href="img/favicon.png">
  <link rel="stylesheet" href="css/base.min.css">
  <link rel="stylesheet" href="css/components.min.css">
  
  <!-- Comentário de regra: Script inline crítico para ler favoritos do localStorage e redirecionar se desalinhado com os IDs da URL -->
  <script>
    (function() {
      try {
        const favs = JSON.parse(localStorage.getItem('mcd_favorites') || '[]');
        const urlParams = new URLSearchParams(window.location.search);
        const idsParam = urlParams.get('ids') || '';
        
        // Cria a string correspondente ordenada para comparação
        const localIdsStr = favs.sort().join(',');
        const sortedUrlIds = idsParam.split(',').filter(Boolean).sort().join(',');
        
        // Se houver divergência entre os favoritos do localStorage e os da URL, força o alinhamento
        if (localIdsStr !== sortedUrlIds) {
          window.location.replace('favoritos.php?ids=' + favs.join(','));
        }
      } catch (e) {
        console.error('Erro na sincronização de favoritos:', e);
      }
    })();
  </script>
  
  <?php 
    // Comentário de regra: Inclui as tags globais de monitoramento do Google Analytics
    include 'sections/analytics.php'; 
  ?>
</head>
<body>
  <?php 
    // Carrega o cabeçalho dinâmico baseado no status de login
    include 'header.php'; 
  ?>

  <!-- Container da Grade de Produtos Favoritados -->
  <div class="produtos-grid-wrap" style="min-height: 50vh; margin-top: 3rem;">
    <div class="produtos-header-row">
      <h2>Meus Favoritos <span style="font-size: 1.1rem; color: var(--text-light); font-weight: 600;">(Salvos no seu navegador)</span></h2>
    </div>

    <div class="products-grid" id="products-grid" style="margin-top: 0;">
      <?php 
        // Comentário de regra: Se houver produtos favoritados retornados, renderiza os cards
        if (!empty($produtos)): 
          foreach ($produtos as $p):
            // Renderiza o card do produto usando o template unificado
            include 'sections/product_card.php';
          endforeach; 
        else:
      ?>
        <!-- Mensagem de fallback caso não tenha nenhum item favoritado -->
        <div class="text-center" style="grid-column: 1 / -1; padding: 5rem 1.5rem;">
          <div style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem;">❤️</div>
          <p class="text-muted" style="font-size: 1.1rem; font-weight: 600; margin-bottom: 1.5rem;">Você ainda não favoritou nenhum produto.</p>
          <a href="produtos.php" class="btn-clear-filters">Explorar Ofertas</a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php 
    // Carrega o rodapé dinâmico do site (com categorias do Supabase)
    include 'footer.php'; 
  ?>
  <script src="js/main.min.js"></script>
</body>
</html>
