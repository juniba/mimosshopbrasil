<?php
/*
  produto.php: Página de pré-venda individual e dinâmica do produto.
  Exibe detalhes avançados (vantagens, desvantagens, público indicado, comparação e FAQ)
  com galeria interativa de imagens e botões de chamada para ação (CTA) altamente qualificados.
*/
require_once 'config.php';

// Comentário explicativo: Captura o ID do produto via parâmetro GET
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    // Redireciona para a listagem caso o ID seja inválido
    header('Location: produtos.php');
    exit;
}

// Comentário explicativo: Busca os detalhes do produto no Supabase de forma robusta com o helper supabase_admin_request
$endpoint_produto = "/rest/v1/produtos?id=eq." . $id . "&select=*,store(nome,css_class,cor),categorias(nome)";
$produto_data = supabase_admin_request('GET', $endpoint_produto);


if (empty($produto_data)) {
    // Redireciona caso o produto não seja localizado no banco de dados
    header('Location: produtos.php');
    exit;
}

$p = $produto_data[0];

// Comentário explicativo: Mapeia dados da loja
$store_name = !empty($p['store']['nome']) ? htmlspecialchars($p['store']['nome']) : 'Parceiro';
$store_color = !empty($p['store']['cor']) ? htmlspecialchars($p['store']['cor']) : 'var(--brand)';
$store_css = !empty($p['store']['css_class']) ? htmlspecialchars($p['store']['css_class']) : 'badge-amazon';

// Formatação dos preços para moeda nacional
$preco_novo_fmt = "R$ " . number_format($p['preco_novo'], 2, ',', '.');
$preco_antigo_html = "";
if (!empty($p['preco_antigo'])) {
    $preco_antigo_fmt = "R$ " . number_format($p['preco_antigo'], 2, ',', '.');
    $preco_antigo_html = '<div class="prod-price-old">' . $preco_antigo_fmt . '</div>';
}

// Nota em formato decimal para as estrelas
$nota_estrelas = '5.0';
if (empty($p['is_encomenda'])) {
    if ($p['id'] == 1) $nota_estrelas = '4.8';
    elseif ($p['id'] == 2) $nota_estrelas = '4.6';
    elseif ($p['id'] == 3) $nota_estrelas = '4.9';
    elseif ($p['id'] == 4) $nota_estrelas = '4.5';
    elseif ($p['id'] == 5) $nota_estrelas = '4.8';
    elseif ($p['id'] == 6) $nota_estrelas = '4.7';
    elseif ($p['id'] == 7) $nota_estrelas = '4.7';
    elseif ($p['id'] == 8) $nota_estrelas = '4.9';
}

// Comentário explicativo: Separa as vantagens por quebra de linha
$list_vantagens = [];
if (!empty($p['vantagens'])) {
    $list_vantagens = array_filter(array_map('trim', explode("\n", $p['vantagens'])));
}

// Comentário explicativo: Separa as desvantagens por quebra de linha
$list_desvantagens = [];
if (!empty($p['desvantagens'])) {
    $list_desvantagens = array_filter(array_map('trim', explode("\n", $p['desvantagens'])));
}

// Comentário explicativo: Processa imagens adicionais do produto
$imagens = [$p['imagem_url']];
if (!empty($p['imagens_adicionais'])) {
    $adicionais = array_filter(array_map('trim', explode("\n", $p['imagens_adicionais'])));
    $imagens = array_merge($imagens, $adicionais);
}

// Comentário explicativo: Monta a estrutura de FAQ de perguntas|respostas
$faq_items = [];
if (!empty($p['perguntas_frequentes'])) {
    $linhas_faq = array_filter(array_map('trim', explode("\n", $p['perguntas_frequentes'])));
    foreach ($linhas_faq as $linha) {
        $partes = explode("|", $linha, 2);
        if (count($partes) === 2) {
            $faq_items[] = [
                'pergunta' => trim($partes[0]),
                'resposta' => trim($partes[1])
            ];
        }
    }
}

// Comentário explicativo: Busca produtos com o mesmo slug comparativo para exibição de ofertas alternativas de forma robusta
$comparativos = [];
if (!empty($p['slug_comparativo'])) {
    $endpoint_comp = "/rest/v1/produtos?slug_comparativo=eq." . urlencode($p['slug_comparativo']) . "&select=*,store(nome,css_class,cor)&order=preco_novo.asc";
    $comparativos = supabase_admin_request('GET', $endpoint_comp) ?: [];
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <!-- SEO tags dinâmicas baseadas no produto para melhor indexação no Google -->
  <title><?php echo htmlspecialchars($p['titulo']); ?> – Vale a pena comprar? | MCD Market Prime</title>
  <meta name="description" content="Confira nossa análise detalhada, prós e contras, preço atualizado e perguntas frequentes sobre o produto <?php echo htmlspecialchars($p['titulo']); ?>. Faça uma compra segura.">
  <meta name="robots" content="index, follow">
  <link rel="canonical" href="https://mcdmarketprime.com/produto.php?id=<?php echo $id; ?>">
  
  <!-- Twitter & Facebook Open Graph Tags -->
  <meta property="og:title" content="<?php echo htmlspecialchars($p['titulo']); ?> – Vale a pena comprar?">
  <meta property="og:description" content="Saiba tudo antes de comprar! Veja as vantagens, desvantagens, avaliação e onde encontrar o menor preço na Amazon ou Mercado Livre.">
  <meta property="og:image" content="<?php echo htmlspecialchars($p['imagem_url']); ?>">
  <meta property="og:type" content="product">
  <meta property="og:url" content="https://mcdmarketprime.com/produto.php?id=<?php echo $id; ?>">

  <!-- Preconnect e Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  
  <!-- Favicon -->
  <!-- Favicon para exibição correta na aba do navegador -->
  <link rel="icon" type="image/png" href="img/favicon.png">

  <!-- Estilos globais e componentes -->
  <link rel="stylesheet" href="css/base.min.css">
  <link rel="stylesheet" href="css/components.min.css">

  <!-- Comentário explicativo: Google Analytics de monitoramento global -->
  <?php include 'sections/analytics.php'; ?>

  <!-- Estilos Customizados Premium específicos para a página do produto (Layout de Pré-Venda) -->
  <style>
    :root {
      --prod-accent: <?php echo $store_color; ?>;
    }
    
    .produto-detail-wrap {
      max-width: 1100px;
      margin: 2rem auto;
      padding: 0 1.5rem;
    }

    /* Galeria e informações principais */
    .prod-main-grid {
      display: grid;
      grid-template-columns: 1.1fr 0.9fr;
      gap: 2.5rem;
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 2.5rem;
      box-shadow: var(--shadow-sm);
      margin-bottom: 2.5rem;
      backdrop-filter: blur(10px);
    }

    /* Galeria Interativa de Fotos */
    .prod-gallery {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }
    .prod-main-img-container {
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      padding: 1.5rem;
      display: flex;
      align-items: center;
      justify-content: center;
      height: 380px;
      overflow: hidden;
      position: relative;
    }
    .prod-main-img-container img {
      max-width: 100%;
      max-height: 100%;
      object-fit: contain;
      transition: transform 0.3s ease;
    }
    .prod-main-img-container:hover img {
      transform: scale(1.05);
    }
    .prod-thumbnails {
      display: flex;
      gap: 0.5rem;
      overflow-x: auto;
      padding-bottom: 0.25rem;
    }
    .prod-thumb {
      width: 70px;
      height: 70px;
      border: 2px solid var(--border);
      border-radius: var(--radius-sm);
      padding: 0.25rem;
      cursor: pointer;
      background: var(--white);
      display: flex;
      align-items: center;
      justify-content: center;
      transition: border-color 0.2s, transform 0.1s;
    }
    .prod-thumb:hover {
      border-color: var(--prod-accent);
    }
    .prod-thumb.active {
      border-color: var(--prod-accent);
      transform: translateY(-2px);
    }
    .prod-thumb img {
      max-width: 100%;
      max-height: 100%;
      object-fit: contain;
    }

    /* Detalhes de compra e chamada de ação */
    .prod-info {
      display: flex;
      flex-direction: column;
      justify-content: center;
    }
    .prod-meta {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      margin-bottom: 1rem;
    }
    .prod-store-badge {
      display: inline-block;
      padding: 0.25rem 0.75rem;
      border-radius: 99px;
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
    }
    .prod-title {
      font-size: 1.8rem;
      font-weight: 800;
      color: var(--text);
      line-height: 1.25;
      margin-bottom: 1rem;
    }
    .prod-rating {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.9rem;
      color: var(--text-light);
      margin-bottom: 1.5rem;
    }
    .prod-rating .stars {
      color: #F59E0B;
      font-size: 1.1rem;
      letter-spacing: 1px;
    }
    .prod-pricing {
      margin-bottom: 2rem;
      padding: 1.25rem;
      background: var(--bg);
      border-radius: var(--radius-sm);
      border-left: 4px solid var(--prod-accent);
    }
    .prod-price-old {
      font-size: 0.95rem;
      text-decoration: line-through;
      color: var(--text-light);
      margin-bottom: 0.25rem;
    }
    .prod-price-new {
      font-size: 2.2rem;
      font-weight: 900;
      color: var(--text);
      letter-spacing: -1px;
    }
    .prod-price-new small {
      font-size: 1rem;
      font-weight: 500;
    }

    /* Botão CTA Ultra Convertedor */
    .btn-prod-cta {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.75rem;
      background: var(--prod-accent);
      color: var(--white);
      text-decoration: none;
      font-weight: 800;
      font-size: 1.15rem;
      padding: 1.1rem 2rem;
      border-radius: var(--radius);
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
      transition: transform 0.2s, box-shadow 0.2s, filter 0.2s;
      text-align: center;
    }
    .btn-prod-cta:hover {
      filter: brightness(1.05);
      transform: translateY(-2px);
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }
    .btn-prod-cta:active {
      transform: translateY(0);
    }

    /* Seções de Pré-Venda (Vantagens, Objeções, Comparação) */
    .prod-sec-title {
      font-size: 1.4rem;
      font-weight: 800;
      color: var(--text);
      margin-bottom: 1.25rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .prod-split-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 2rem;
      margin-bottom: 2.5rem;
    }
    .prod-card-box {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 2rem;
      box-shadow: var(--shadow-sm);
    }

    .vantagens-list, .desvantagens-list {
      list-style: none;
      padding: 0;
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
    }
    .vantagens-list li {
      position: relative;
      padding-left: 1.75rem;
      line-height: 1.4;
      font-weight: 500;
      color: var(--text-light);
    }
    .vantagens-list li::before {
      content: "✓";
      position: absolute;
      left: 0;
      top: 0;
      color: #10B981;
      font-weight: 900;
      font-size: 1.1rem;
    }
    .desvantagens-list li {
      position: relative;
      padding-left: 1.75rem;
      line-height: 1.4;
      font-weight: 500;
      color: var(--text-light);
    }
    .desvantagens-list li::before {
      content: "×";
      position: absolute;
      left: 0;
      top: -2px;
      color: #EF4444;
      font-weight: 900;
      font-size: 1.3rem;
    }

    /* Indicado e Comparação */
    .prod-full-box {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 2rem;
      box-shadow: var(--shadow-sm);
      margin-bottom: 2.5rem;
    }
    .prod-desc-text {
      line-height: 1.6;
      color: var(--text-light);
      font-size: 0.98rem;
    }

    /* Tabela de Preços Comparativa */
    .price-comp-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 1rem;
    }
    .price-comp-table th {
      text-align: left;
      padding: 0.75rem 1rem;
      background: var(--bg);
      border-bottom: 2px solid var(--border);
      font-weight: 700;
      font-size: 0.85rem;
      text-transform: uppercase;
      color: var(--text-light);
    }
    .price-comp-table td {
      padding: 1rem;
      border-bottom: 1px solid var(--border);
      vertical-align: middle;
    }
    .price-comp-table tr:hover td {
      background: rgba(0,0,0,0.01);
    }
    .price-comp-btn {
      display: inline-block;
      padding: 0.45rem 1rem;
      border-radius: var(--radius-sm);
      color: var(--white);
      text-decoration: none;
      font-weight: 700;
      font-size: 0.85rem;
      transition: filter 0.2s;
    }
    .price-comp-btn:hover {
      filter: brightness(1.05);
    }

    /* FAQ (Perguntas Frequentes) */
    .faq-accordion {
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
    }
    .faq-item {
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      overflow: hidden;
      transition: border-color 0.2s;
    }
    .faq-item:hover {
      border-color: var(--prod-accent);
    }
    .faq-header {
      padding: 1.1rem 1.5rem;
      background: var(--white);
      cursor: pointer;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-weight: 700;
      color: var(--text);
      transition: background 0.2s;
      user-select: none;
    }
    .faq-header:hover {
      background: var(--bg);
    }
    .faq-icon {
      transition: transform 0.2s;
      font-weight: 400;
      font-size: 1.1rem;
      color: var(--text-light);
    }
    .faq-content {
      padding: 0 1.5rem;
      max-height: 0;
      overflow: hidden;
      background: var(--white);
      transition: max-height 0.2s ease-out, padding 0.2s;
      color: var(--text-light);
      line-height: 1.6;
    }
    .faq-item.active .faq-icon {
      transform: rotate(45deg);
    }
    .faq-item.active .faq-content {
      padding: 1.1rem 1.5rem;
      max-height: 300px;
      border-top: 1px solid var(--border);
    }

    /* Responsividade */
    @media (max-width: 768px) {
      .prod-main-grid {
        grid-template-columns: 1fr;
        padding: 1.5rem;
        gap: 1.5rem;
      }
      .prod-main-img-container {
        height: 280px;
      }
      .prod-split-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
      }
      .prod-title {
        font-size: 1.5rem;
      }
    }
  </style>
</head>
<body>

  <?php 
    // Carrega o cabeçalho dinâmico baseado no status de login
    include 'header.php'; 
  ?>

  <div class="produto-detail-wrap">
    
    <!-- Breadcrumbs de Navegação Hierárquica -->
    <nav class="breadcrumbs" aria-label="Breadcrumb" style="margin-bottom: 1.5rem; padding: 0.5rem 0;">
      <a href="index.php">Home</a>
      <span class="separator">/</span>
      <a href="produtos.php">Produtos</a>
      <span class="separator">/</span>
      <span class="current">
        <?php 
          // Comentário explicativo: Limita o tamanho do título no breadcrumb com fallback caso mbstring não esteja instalado
          $breadcrumb_title = $p['titulo'];
          if (function_exists('mb_strimwidth')) {
              $breadcrumb_title = mb_strimwidth($breadcrumb_title, 0, 45, '...');
          } elseif (strlen($breadcrumb_title) > 45) {
              $breadcrumb_title = substr($breadcrumb_title, 0, 42) . '...';
          }
          echo htmlspecialchars($breadcrumb_title); 
        ?>
      </span>

    </nav>

    <!-- Estrutura Principal do Produto -->
    <section class="prod-main-grid">
      <!-- Lado Esquerdo: Imagem e Galeria -->
      <div class="prod-gallery">
        <div class="prod-main-img-container">
          <img id="main-product-img" src="<?php echo htmlspecialchars($imagens[0]); ?>" alt="<?php echo htmlspecialchars($p['titulo']); ?>">
        </div>
        
        <?php if (count($imagens) > 1): ?>
          <!-- Miniaturas adicionais interativas -->
          <div class="prod-thumbnails">
            <?php foreach ($imagens as $index => $img_url): ?>
              <button class="prod-thumb <?php echo $index === 0 ? 'active' : ''; ?>" onclick="changeMainImage('<?php echo htmlspecialchars($img_url); ?>', this)">
                <img src="<?php echo htmlspecialchars($img_url); ?>" alt="Miniatura <?php echo $index + 1; ?>">
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Lado Direito: Informações e Compra -->
      <div class="prod-info">
        <div class="prod-meta">
          <span class="prod-store-badge <?php echo $store_css; ?>-sm"><?php echo $store_name; ?></span>
          <?php if (!empty($p['categorias']['nome'])): ?>
            <span class="text-muted" style="font-size: 0.8rem; font-weight: 600;">📁 <?php echo htmlspecialchars($p['categorias']['nome']); ?></span>
          <?php endif; ?>
        </div>

        <h1 class="prod-title"><?php echo htmlspecialchars($p['titulo']); ?></h1>

        <div class="prod-rating">
          <span class="stars"><?php echo htmlspecialchars($p['estrelas'] ?? '★★★★★'); ?></span>
          <span><strong><?php echo $nota_estrelas; ?></strong> (<?php echo number_format($p['avaliacoes'] ?? 0, 0, ',', '.'); ?> avaliações de compradores)</span>
        </div>

        <div class="prod-pricing">
          <?php echo $preco_antigo_html; ?>
          <div class="prod-price-new">
            <?php echo $preco_novo_fmt; ?>
            <small>em até 10x sem juros</small>
          </div>
        </div>

        <!-- Botão CTA Principal de Redirecionamento de Afiliado -->
        <a href="<?php echo htmlspecialchars($p['link_afiliado']); ?>" 
           target="_blank" 
           rel="noopener noreferrer"
           class="btn-prod-cta" 
           onclick="trackClick('<?php echo htmlspecialchars($p['loja']); ?>', 'detalhes-cta-<?php echo $p['id']; ?>')">
          🛍️ Ver melhor preço na <?php echo $store_name; ?> →
        </a>
      </div>
    </section>

    <!-- Seção de Vantagens e Desvantagens (Prós e Contras) -->
    <?php if (!empty($list_vantagens) || !empty($list_desvantagens)): ?>
      <section class="prod-split-grid">
        <div class="prod-card-box">
          <h2 class="prod-sec-title">👍 Vantagens</h2>
          <ul class="vantagens-list">
            <?php if (!empty($list_vantagens)): ?>
              <?php foreach ($list_vantagens as $item): ?>
                <li><?php echo htmlspecialchars($item); ?></li>
              <?php endforeach; ?>
            <?php else: ?>
              <li style="list-style: none; padding-left: 0; color: var(--text-muted);">Informações de vantagens em breve.</li>
            <?php endif; ?>
          </ul>
        </div>

        <div class="prod-card-box">
          <h2 class="prod-sec-title">👎 Desvantagens</h2>
          <ul class="desvantagens-list">
            <?php if (!empty($list_desvantagens)): ?>
              <?php foreach ($list_desvantagens as $item): ?>
                <li><?php echo htmlspecialchars($item); ?></li>
              <?php endforeach; ?>
            <?php else: ?>
              <li style="list-style: none; padding-left: 0; color: var(--text-muted);">Nenhum ponto negativo crítico relatado.</li>
            <?php endif; ?>
          </ul>
        </div>
      </section>
    <?php endif; ?>

    <!-- Seção de Público Indicado -->
    <?php if (!empty($p['para_quem_indicado'])): ?>
      <section class="prod-full-box">
        <h2 class="prod-sec-title">🎯 Para quem é indicado?</h2>
        <div class="prod-desc-text">
          <p><?php echo nl2br(htmlspecialchars($p['para_quem_indicado'])); ?></p>
        </div>
      </section>
    <?php endif; ?>

    <!-- Seção Comparativa e Ofertas Alternativas -->
    <?php if (count($comparativos) > 1): ?>
      <section class="prod-full-box">
        <h2 class="prod-sec-title">⚖️ Comparativo de Preços & Lojas</h2>
        <p class="prod-desc-text" style="margin-bottom: 1.5rem;">
          Encontramos o mesmo produto em diferentes lojas parceiras do <strong>MCD Market Prime</strong>. Escolha a que oferece a melhor oferta hoje:
        </p>
        
        <table class="price-comp-table">
          <thead>
            <tr>
              <th>Loja</th>
              <th>Preço à Vista</th>
              <th>Condição</th>
              <th>Ação</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($comparativos as $c): ?>
              <tr>
                <td>
                  <strong style="color: <?php echo !empty($c['store']['cor']) ? htmlspecialchars($c['store']['cor']) : 'inherit'; ?>;">
                    <?php echo htmlspecialchars($c['store']['nome'] ?? 'Parceiro'); ?>
                  </strong>
                </td>
                <td>
                  <strong style="font-size: 1.1rem; color: var(--text);"><?php echo "R$ " . number_format($c['preco_novo'], 2, ',', '.'); ?></strong>
                </td>
                <td>
                  <span style="font-size: 0.85rem; color: var(--text-light); font-weight: 500;">
                    <?php echo !empty($c['desconto']) ? "Economia de " . htmlspecialchars($c['desconto']) : "Preço Padrão"; ?>
                  </span>
                </td>
                <td>
                  <a href="<?php echo htmlspecialchars($c['link_afiliado']); ?>" 
                     target="_blank" 
                     rel="noopener noreferrer"
                     class="price-comp-btn"
                     style="background: <?php echo !empty($c['store']['cor']) ? htmlspecialchars($c['store']['cor']) : 'var(--brand)'; ?>;"
                     onclick="trackClick('<?php echo htmlspecialchars($c['loja']); ?>', 'comparativo-<?php echo $c['id']; ?>')">
                    Ir para a loja →
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        
        <?php if (!empty($p['comparacao'])): ?>
          <div class="prod-desc-text" style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px dashed var(--border);">
            <strong>Análise do MCD Market Prime:</strong> <?php echo nl2br(htmlspecialchars($p['comparacao'])); ?>
          </div>
        <?php endif; ?>
      </section>
    <?php elseif (!empty($p['comparacao'])): ?>
      <!-- Se só houver a descrição comparativa sem múltiplos lojistas -->
      <section class="prod-full-box">
        <h2 class="prod-sec-title">🔍 Vale a pena? Análise Técnica</h2>
        <div class="prod-desc-text">
          <p><?php echo nl2br(htmlspecialchars($p['comparacao'])); ?></p>
        </div>
      </section>
    <?php endif; ?>

    <!-- Perguntas Frequentes (FAQ) -->
    <?php if (!empty($faq_items)): ?>
      <section class="prod-full-box" style="margin-bottom: 4rem;">
        <h2 class="prod-sec-title">❓ Perguntas Frequentes</h2>
        <div class="faq-accordion">
          <?php foreach ($faq_items as $faq): ?>
            <div class="faq-item">
              <div class="faq-header" onclick="toggleFaq(this)">
                <span><?php echo htmlspecialchars($faq['pergunta']); ?></span>
                <span class="faq-icon">+</span>
              </div>
              <div class="faq-content">
                <p><?php echo htmlspecialchars($faq['resposta']); ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

  </div>

  <?php 
    // Carrega o rodapé dinâmico do site (com categorias do Supabase)
    include 'footer.php'; 
  ?>

  <!-- Scripts Lucide e Javascript Interativo -->
  <script src="js/main.min.js"></script>
  <script>
    // Comentário explicativo: Gerencia a troca da foto principal na galeria ao clicar nas miniaturas
    function changeMainImage(url, element) {
      document.getElementById('main-product-img').src = url;
      document.querySelectorAll('.prod-thumb').forEach(btn => btn.classList.remove('active'));
      element.classList.add('active');
    }

    // Comentário explicativo: Controla o recolhimento e expansão suave das perguntas do FAQ (Acordeão)
    function toggleFaq(header) {
      const item = header.parentElement;
      const isActive = item.classList.contains('active');
      
      // Fecha todos os itens abertos
      document.querySelectorAll('.faq-item').forEach(i => {
        i.classList.remove('active');
        i.querySelector('.faq-content').style.maxHeight = null;
      });

      // Se o item clicado não estava ativo, abre ele
      if (!isActive) {
        item.classList.add('active');
        const content = item.querySelector('.faq-content');
        content.style.maxHeight = content.scrollHeight + "px";
      }
    }
  </script>
</body>
</html>
