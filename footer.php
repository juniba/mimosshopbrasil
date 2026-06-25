<?php
/*
  footer.php: Rodapé dinâmico do site Mimos Shop Brasil.
  Carrega as categorias do Supabase para exibir links dinâmicos no rodapé,
  garantindo que novas categorias adicionadas via painel apareçam automaticamente.
*/

// Prepara o contexto HTTP com os cabeçalhos de autenticação da API REST do Supabase
$footer_opts = [
    "http" => [
        "method" => "GET",
        "header" => "apikey: " . SUPABASE_KEY . "\r\n" .
                    "Authorization: Bearer " . SUPABASE_KEY . "\r\n"
    ]
];
$footer_context = stream_context_create($footer_opts);

// URL do endpoint de categorias ordenadas por ID de forma ascendente
$footer_url = SUPABASE_URL . "/rest/v1/categorias?select=*&order=id.asc";

// Faz a requisição HTTP GET para obter o JSON de categorias (com cache local)
$footer_response = fetch_supabase_with_cache($footer_url, $footer_context);
$footer_categorias = json_decode($footer_response, true);
?>
<!-- FOOTER: Rodapé dinâmico com categorias carregadas do Supabase -->
<footer>
  <div class="footer-inner">
    <div class="footer-grid">
      <div>
        <!-- Logo gráfica do MCD TrendDeals no rodapé usando a versão branca (logo_mcdtrenddeals_branca.png) para se destacar no fundo escuro -->
        <div class="footer-logo">
          <!-- Comentário explicativo: Substituição do caminho da imagem e alt do logo no rodapé -->
          <img src="img/logo_mcdmarketprime_branca.png" alt="MCD TrendDeals" style="height: 50px; width: auto; object-fit: contain;">
        </div>
        <p class="footer-desc">Comparador de preços de eletrônicos e utilidades. Encontre as melhores ofertas na Amazon e Mercado Livre todos os dias.</p>
        <div class="affiliate-notice" style="margin-top:1rem;">
          ⚠️ Afiliado: Este site participa do Programa de Associados da Amazon e do Mercado Livre. Ganhamos comissões em compras qualificadas, sem custo adicional para o consumidor.
        </div>
      </div>
      <!-- Coluna de categorias dinâmicas carregadas do Supabase -->
      <div class="footer-col">
        <h4>Categorias</h4>
        <?php if (!empty($footer_categorias)): ?>
          <?php foreach ($footer_categorias as $fc): ?>
            <!-- Link dinâmico para filtrar produtos pela categoria no catálogo -->
            <a href="produtos.php?category=<?php echo $fc['id']; ?>"><?php echo htmlspecialchars($fc['nome']); ?></a>
          <?php endforeach; ?>
        <?php else: ?>
          <a href="produtos.php">Ver produtos</a>
        <?php endif; ?>
      </div>
      <div class="footer-col">
        <h4>Lojas</h4>
        <!-- Links funcionais direcionando para filtros de loja no catálogo -->
        <a href="produtos.php?store=1">Ofertas Amazon</a>
        <a href="produtos.php?store=2">Ofertas Mercado Livre</a>
        <a href="index.php#comparar">Comparar Preços</a>
        <a href="index.php#ofertas">Cupons</a>
      </div>
      <div class="footer-col">
        <h4>Sobre</h4>
        <!-- Links funcionais para as seções existentes da página -->
        <a href="index.php#sobre">Como funciona</a>
        <a href="blog/">Blog</a>
        <a href="links.php">Links de Desconto</a>
        <a href="index.php#newsletter">Newsletter</a>
        <!-- Comentário de regra: Links institucionais obrigatórios de privacidade e termos da LGPD/Afiliados -->
        <a href="privacidade.php">Política de Privacidade</a>
        <a href="termos.php">Termos de Uso</a>
      </div>
    </div>
    <div class="footer-bottom">
      <!-- Direitos autorais atualizados para a nova marca MCD TrendDeals -->
      <span>© 2026 MCD TrendDeals. Todos os direitos reservados.</span>
      <span>Preços e disponibilidade podem variar. Verifique na loja antes de comprar.</span>
    </div>
  </div>
  
  <!-- Botão Voltar ao Topo (Back to Top) com ícone de seta elegante -->
  <!-- Comentário de regra: Este botão auxilia a navegabilidade em páginas longas e é controlado via js/main.js -->
  <button id="back-to-top" class="back-to-top" aria-label="Voltar ao topo" title="Voltar ao topo">
    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
    </svg>
  </button>
</footer>

<!-- Biblioteca de ícones Lucide para visual premium -->
<script src="https://unpkg.com/lucide@latest"></script>
<script>
  // Inicialização do Lucide para carregar os ícones SVG nos elementos com atributo data-lucide
  document.addEventListener('DOMContentLoaded', () => {
    if (typeof lucide !== 'undefined') {
      lucide.createIcons();
    }
  });
</script>
