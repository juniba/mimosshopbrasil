<?php
/*
  sections/hero.php: Painel principal com layout split (duas colunas) no desktop.
  Agora carrega dinamicamente a quantidade real de produtos cadastrados no Supabase.
*/

// Prepara o contexto HTTP com as credenciais do Supabase para fazer a requisição de contagem
$opts_count = [
    "http" => [
        "method" => "GET",
        "header" => "apikey: " . SUPABASE_KEY . "\r\n" .
                    "Authorization: Bearer " . SUPABASE_KEY . "\r\n"
    ]
];
$context_count = stream_context_create($opts_count);

// URL da API para trazer a lista de identificadores dos produtos cadastrados
$url_count = SUPABASE_URL . "/rest/v1/produtos?select=id";

// Realiza a chamada com cache local de 5 minutos (300 segundos) para otimizar o desempenho do site
$response_count = fetch_supabase_with_cache($url_count, $context_count, 300);
$produtos_list = json_decode($response_count, true);
$total_produtos = is_array($produtos_list) ? count($produtos_list) : 0;
?>
<!-- Seção Hero: Painel principal com layout split (duas colunas) no desktop para destaque visual e otimização de SEO -->
<section class="hero">
  <!-- Container estruturado para dispor o texto de conversão (SEO) e o banner ilustrativo lado a lado -->
  <div class="hero-container">
    
    <!-- Coluna de Texto Principal: Título H1 único da página com palavras-chave estratégicas -->
    <div class="hero-content">
      <!-- Badge indicativo de informações atualizadas em tempo real -->
      <div class="hero-badge">
        <svg width="12" height="12" fill="currentColor" viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
        Atualizado todos os dias
      </div>
      <!-- Título H1: Foco principal em SEO para atrair pesquisas de ofertas de eletrônicos -->
      <h1>As melhores ofertas em <em>eletrônicos e tecnologia</em></h1>
      <!-- Descrição curta de conversão: Explica a utilidade do comparador do site -->
      <p>Comparamos preços na Amazon e Mercado Livre para você economizar de verdade. Sem pagar mais do que precisa.</p>
      <!-- Botões de chamada para ação rápidos (CTAs) -->
      <div class="hero-ctas">
        <a href="#ofertas" class="btn btn-white">Ver ofertas de hoje</a>
        <a href="#comparar" class="btn btn-outline">Comparar preços</a>
      </div>
      <!-- Estatísticas gerais do site para gerar prova social e autoridade -->
      <div class="hero-stats">
        <div class="hero-stat"><strong>+<?php echo number_format($total_produtos, 0, ',', '.'); ?></strong><span>Produtos</span></div>
        <div class="hero-stat"><strong>Até 70%</strong><span>de desconto</span></div>
        <div class="hero-stat"><strong>2 lojas</strong><span>comparadas</span></div>
      </div>
    </div>
    
    <!-- Coluna do Banner Visual: Exibe o banner ilustrativo adicionado pelo usuário -->
    <div class="hero-image">
      <!-- Banner otimizado com tag alt descritiva para melhorar o posicionamento no Google Imagens -->
      <!-- Banner com alt descritivo atualizado para SEO e identidade Mimos Shop Brasil -->
      <img src="img/banner.png" alt="Comparador de Preços e Promoções de Eletrônicos no Mimos Shop Brasil" class="hero-banner-img" loading="lazy">
    </div>

  </div>
</section>
