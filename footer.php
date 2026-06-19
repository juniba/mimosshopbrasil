<?php
/*
  footer.php: Rodapé dinâmico do site TechDeal.
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
        <!-- Logo gráfica do Mimos Shop Brasil no rodapé -->
        <div class="footer-logo">
          <img src="img/logo.png" alt="Mimos Shop Brasil" style="height: 50px; width: auto; object-fit: contain;">
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
        <a href="#">Ofertas Amazon</a>
        <a href="#">Ofertas Mercado Livre</a>
        <a href="#">Comparar Preços</a>
        <a href="#">Cupons</a>
        <a href="#">Prime Day</a>
      </div>
      <div class="footer-col">
        <h4>Sobre</h4>
        <a href="#">Como funciona</a>
        <a href="#">Política de privacidade</a>
        <a href="#">Termos de uso</a>
        <a href="#">Contato</a>
        <a href="#">Newsletter</a>
      </div>
    </div>
    <div class="footer-bottom">
      <!-- Direitos autorais atualizados para Mimos Shop Brasil -->
      <span>© 2025 Mimos Shop Brasil. Todos os direitos reservados.</span>
      <span>Preços e disponibilidade podem variar. Verifique na loja antes de comprar.</span>
    </div>
  </div>
</footer>
