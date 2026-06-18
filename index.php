<?php
/*
  index.php: Página inicial do site TechDeal.
  Carrega o arquivo de configuração, cabeçalho dinâmico, conteúdo e rodapé.
*/
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <!-- SEO Meta Tags básicas -->
  <title>TechDeal – Melhores ofertas em eletrônicos e tecnologia</title>
  <meta name="description" content="Encontre as melhores ofertas e descontos do dia em smartphones, notebooks, fones de ouvido e eletrônicos no TechDeal. Compare preços da Amazon e Mercado Livre e economize.">
  <meta name="keywords" content="TechDeal, comparar preços, ofertas, descontos, eletrônicos, smartphones, notebooks, fones de ouvido, Amazon, Mercado Livre">
  <meta name="robots" content="index, follow">
  
  <!-- Open Graph Meta Tags (Para compartilhamento otimizado em redes sociais) -->
  <meta property="og:title" content="TechDeal – Melhores Ofertas em Eletrônicos e Tecnologia">
  <meta property="og:description" content="Compare preços das principais lojas, encontre descontos exclusivos e economize todos os dias com o comparador TechDeal.">
  <meta property="og:image" content="og_banner.png">
  <meta property="og:type" content="website">
  <meta property="og:url" content="index.php">
  <meta property="og:site_name" content="TechDeal">
  
  <!-- Favicon para exibição correta na aba do navegador -->
  <link rel="icon" type="image/png" href="favicon.png">
  
  <link rel="stylesheet" href="css/style.css">
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
  <script src="js/main.js"></script>
</body>
</html>
