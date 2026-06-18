<?php
/*
  store_badges.php: Carrega dinamicamente a lista de lojas parceiras do banco de dados do Supabase
  e renderiza os selos/badges informativos de afiliado na página inicial.
  Agora utiliza o campo 'cor' do banco para aplicar as cores dinamicamente.
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

// URL do endpoint de lojas ordenadas por ID de forma ascendente
$url = SUPABASE_URL . "/rest/v1/store?select=*&order=id.asc";

// Faz a requisição HTTP GET para obter o JSON de lojas (com cache local)
$response = fetch_supabase_with_cache($url, $context);
$lojas = json_decode($response, true);
?>
<!-- Seção Store Badges: Exibe de forma visual os selos informando a afiliação oficial com as lojas parceiras puxados diretamente do Supabase -->
<div class="store-badges">
  <?php 
    // Se houver lojas parceiras cadastradas, exibe cada uma delas como um selo com a cor dinâmica do banco
    if (!empty($lojas)): 
      foreach ($lojas as $loja):
        // Recupera a cor cadastrada no banco ou usa uma cor padrão caso não exista
        $cor = !empty($loja['cor']) ? htmlspecialchars($loja['cor']) : '#6366f1';
        // Gera uma versão clara da cor para o fundo do selo (15% de opacidade)
        $bgColor = $cor . '26'; // Adiciona canal alfa hex de ~15% ao HEX da cor
  ?>
    <!-- Selo/Link informativo de afiliado da loja parceira, redirecionando ao filtro correspondente na página de produtos -->
    <a class="store-badge" href="produtos.php?store=<?php echo $loja['id']; ?>" style="border-color: <?php echo $cor; ?>; background: <?php echo $bgColor; ?>; color: <?php echo $cor; ?>;">
      <!-- Indicador visual em forma de ponto interno com a cor da loja -->
      <div class="dot" style="background: <?php echo $cor; ?>;"></div>
      <!-- Texto do selo descritivo de afiliação -->
      <span><?php echo htmlspecialchars($loja['badge_text']); ?></span>
    </a>
  <?php 
      endforeach; 
    endif; 
  ?>
</div>
