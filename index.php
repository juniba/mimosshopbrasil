<?php
/*
  index.php: Página inicial do site MCDMarketPrime.
  Carrega o arquivo de configuração, cabeçalho dinâmico, conteúdo e rodapé.
*/
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <!-- SEO Meta Tags básicas - Atualizadas para MCD Market Prime -->
  <title>MCD Market Prime – Melhores ofertas e descontos do dia</title>
  <meta name="description" content="Encontre as melhores ofertas e descontos do dia em eletrônicos, utilidades e presentes no MCD Market Prime. Compare preços da Amazon e Mercado Livre e economize.">
  <meta name="keywords" content="MCD Market Prime, comparar preços, ofertas, descontos, eletrônicos, presentes, utilidades, Amazon, Mercado Livre">
  <meta name="robots" content="index, follow">
  
  <!-- Canonical URL para evitar duplicidade de conteúdo no Google -->
  <link rel="canonical" href="https://mcdmarketprime.com/">
  
  <!-- Open Graph Meta Tags com URLs absolutas para compartilhamento otimizado em redes sociais -->
  <meta property="og:title" content="MCD Market Prime – Melhores Ofertas e Descontos do Dia">
  <meta property="og:description" content="Compare preços das principais lojas, encontre descontos exclusivos e economize todos os dias com o comparador MCD Market Prime.">
  <meta property="og:image" content="https://mcdmarketprime.com/og_banner.png">
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://mcdmarketprime.com/">
  <meta property="og:site_name" content="MCD Market Prime">
  
  <!-- Twitter Card tags -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="MCD Market Prime – Melhores Ofertas e Descontos do Dia">
  <meta name="twitter:description" content="Compare preços das principais lojas, encontre descontos exclusivos e economize todos os dias com o comparador MCD Market Prime.">
  <meta name="twitter:image" content="https://mcdmarketprime.com/og_banner.png">

  <!-- Comentário de regra: JSON-LD Structured Data para melhorar a indexação e pesquisa estruturada do site -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebSite",
    "name": "MCD Market Prime",
    "url": "https://mcdmarketprime.com/",
    "potentialAction": {
      "@type": "SearchAction",
      "target": "https://mcdmarketprime.com/produtos.php?search={search_term_string}",
      "query-input": "required name=search_term_string"
    }
  }
  </script>

  
  <!-- Preconnect e fontes do Google Fonts carregadas via head para otimização de renderização -->
  <!-- Comentário de regra: Este bloco carrega a fonte Inter de forma performática -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  
  <!-- Preload de recursos críticos de imagem para melhorar o LCP/CLS da nova logo -->
  <link rel="preload" href="img/logo_mcdmarketprime.png" as="image">
  <link rel="preload" href="img/banner.png" as="image">

  <!-- Favicon para exibição correta na aba do navegador -->
  <link rel="icon" type="image/png" href="favicon.png">
  
  <link rel="stylesheet" href="css/base.min.css">
  <link rel="stylesheet" href="css/components.min.css">
  
  <?php 
    // Comentário de regra: Inclui as tags globais de monitoramento do Google Analytics
    include 'sections/analytics.php'; 
  ?>
</head>
<body>
  <?php 
    // Carrega o cabeçalho dinâmico baseado no status de login
    include 'header.php'; 
    // Carrega a seção de conteúdos principais do site
    include 'content.php'; 
    // Carrega o rodapé dinâmico do site (com categorias do Supabase)
    include 'footer.php'; 
  ?>
  <script src="js/main.min.js"></script>
</body>
</html>
