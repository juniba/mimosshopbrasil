<?php
/*
  price_comparison.php: Carrega os produtos que participam da comparação de preços
  (slug_comparativo não nulo) do Supabase, agrupa-os por slug comparativo e exibe
  uma tabela comparando as ofertas da Amazon e Mercado Livre, indicando o melhor preço.
*/

// Prepara o contexto HTTP com as chaves de autenticação do Supabase
$opts = [
    "http" => [
        "method" => "GET",
        "header" => "apikey: " . SUPABASE_KEY . "\r\n" .
                    "Authorization: Bearer " . SUPABASE_KEY . "\r\n"
    ]
];
$context = stream_context_create($opts);

// URL da API para trazer produtos com slug_comparativo, ordenado por slug e depois pelo menor preço
$url = SUPABASE_URL . "/rest/v1/produtos?select=*,store(nome,css_class)&slug_comparativo=not.is.null&order=slug_comparativo.asc,preco_novo.asc";

// Faz a requisição REST HTTP GET no Supabase (com cache local)
$response = fetch_supabase_with_cache($url, $context);
$produtos_comp = json_decode($response, true);

// Agrupa os produtos por slug_comparativo preservando a ordenação de menor preço (primeiro item)
$grouped = [];
if (!empty($produtos_comp)) {
    foreach ($produtos_comp as $p) {
        $slug = $p['slug_comparativo'];
        if (!isset($grouped[$slug])) {
            // Inicializa a estrutura do grupo de comparação
            $grouped[$slug] = [
                'display_name' => '',
                'offers' => []
            ];
            
            // Define um nome de exibição amigável para o comparativo com base no título
            if (strpos($p['titulo'], 'Sony WH-1000XM5') !== false) {
                $grouped[$slug]['display_name'] = 'Fone Sony WH-1000XM5';
            } elseif (strpos($p['titulo'], 'Galaxy A55') !== false) {
                $grouped[$slug]['display_name'] = 'Samsung Galaxy A55 5G';
            } elseif (strpos($p['titulo'], 'JBL Charge 5') !== false) {
                $grouped[$slug]['display_name'] = 'JBL Charge 5';
            } else {
                $grouped[$slug]['display_name'] = $p['titulo'];
            }
        }
        $grouped[$slug]['offers'][] = $p;
    }
}
?>
<!-- Seção Comparativo de Preços (Price Comparison): Tabela detalhada comparando valores e links de produtos entre a Amazon e Mercado Livre -->
<section class="section" id="comparar">
  <div class="section-header">
    <h2>Comparativo de preços</h2>
    <a href="#">Comparar outro produto →</a>
  </div>
  <div class="comparison-wrap">
    <!-- Tabela comparativa estruturada sem colunas de parcelas e frete -->
    <table class="comparison-table">
      <thead>
        <tr>
          <th>Produto</th>
          <th>Loja</th>
          <th>Preço</th>
          <th>Ação</th>
        </tr>
      </thead>
      <tbody>
        <?php 
          // Se houver grupos de comparação cadastrados, renderiza cada um deles de forma dinâmica
          if (!empty($grouped)):
            $group_idx = 0;
            foreach ($grouped as $slug => $group):
              $offers = $group['offers'];
              $num_offers = count($offers);
              
              // Define o estilo de alternância de linha (background cinza claro nos grupos ímpares) para guiar o olhar
              $row_style = ($group_idx % 2 === 1) ? ' style="background:#F8FAFC;"' : '';
              
              // Loop pelas ofertas daquele produto
              foreach ($offers as $idx => $offer):
                // O primeiro item do loop (idx = 0) é sempre o menor preço devido à ordenação preco_novo.asc
                $is_best = ($idx === 0);
                $price_class = $is_best ? ' price-compare price-better' : 'price-compare';
                $check_mark = $is_best ? ' ✓' : '';
                
                // Formatação monetária local brasileira
                $preco_fmt = "R$ " . number_format($offer['preco_novo'], 2, ',', '.') . $check_mark;
                
                // Nome amigável da loja cadastrada
                $store_name = !empty($offer['store']['nome']) ? htmlspecialchars($offer['store']['nome']) : 'Parceiro';
                
                // Tag de estilo CSS correspondente à loja (ex: tag-amazon, tag-ml)
                $tag_class = 'tag-' . htmlspecialchars($offer['loja']);
        ?>
          <tr<?php echo $row_style; ?>>
            <?php 
              // A primeira coluna com o nome do produto deve ocupar todas as linhas correspondentes às ofertas (rowspan)
              if ($idx === 0): 
            ?>
              <td rowspan="<?php echo $num_offers; ?>" style="font-weight:600;"><?php echo htmlspecialchars($group['display_name']); ?></td>
            <?php endif; ?>
            
            <!-- Loja parceira, Preço final e link com monitoramento de clique -->
            <td><span class="<?php echo $tag_class; ?>"><?php echo $store_name; ?></span></td>
            <td class="<?php echo $price_class; ?>"><?php echo $preco_fmt; ?></td>
            <td>
              <a href="<?php echo htmlspecialchars($offer['link_afiliado']); ?>" 
                 class="btn-buy btn-<?php echo htmlspecialchars($offer['loja']); ?>" 
                 style="display:inline-block;padding:0.4rem 0.8rem;font-size:0.8rem;border-radius:6px;text-decoration:none;"
                 onclick="trackClick('<?php echo htmlspecialchars($offer['loja']); ?>', '<?php echo htmlspecialchars($slug); ?>')">
                 Comprar
              </a>
            </td>
          </tr>
        <?php 
              endforeach;
              $group_idx++;
            endforeach;
          else:
        ?>
          <!-- Fallback amigável caso não existam dados no banco de dados -->
          <tr>
            <td colspan="4" class="text-center text-muted">Nenhum comparativo disponível no momento.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
    <!-- Nota informativa da tabela -->
    <p style="font-size:0.78rem;color:var(--text-light);margin-top:0.75rem;">✓ = Melhor preço encontrado hoje. Preços sujeitos a alteração.</p>
    
    <!-- Indicador de rolagem lateral para experiência mobile otimizada -->
    <!-- Comentário de regra: Este parágrafo é exibido apenas em telas menores via media queries do CSS global -->
    <p class="mobile-table-hint" style="display:none; font-size:0.75rem; color:var(--text-light); text-align:center; margin-top:0.5rem; gap:0.25rem; align-items:center; justify-content:center;">
      <span>📱 Deslize a tabela para o lado para ver mais</span>
    </p>
  </div>
</section>
