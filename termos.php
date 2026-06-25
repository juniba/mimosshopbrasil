<?php
/*
  termos.php: Página de Termos de Uso do Mimos Shop Brasil.
  Descreve as responsabilidades do usuário e as notas de afiliação e preços.
*/
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Termos de Uso – Mimos Shop Brasil</title>
  <meta name="description" content="Leia os Termos de Uso do Mimos Shop Brasil. Entenda os termos de serviço, isenção de responsabilidade sobre preços e políticas de links afiliados.">
  <meta name="robots" content="index, follow">
  <link rel="canonical" href="https://mimosshopbrasil.com/termos.php">
  
  <!-- Preconnect e fontes do Google Fonts carregadas via head para otimização de renderização -->
  <!-- Comentário de regra: Este bloco carrega a fonte Inter de forma performática -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  
  <!-- Preload de recursos críticos de imagem para melhorar o LCP/CLS -->
  <link rel="preload" href="img/logo_mcdtrenddeals.png" as="image">

  <link rel="icon" type="image/png" href="favicon.png">
  <link rel="stylesheet" href="css/base.min.css">
  <link rel="stylesheet" href="css/components.min.css">
  <style>
    /* Estilos customizados para a página institucional */
    .institucional-container {
      max-width: 800px;
      margin: 4rem auto;
      padding: 0 1.5rem;
      line-height: 1.8;
      color: var(--text);
    }
    .institucional-container h1 {
      font-size: 2.2rem;
      font-weight: 800;
      margin-bottom: 1.5rem;
      letter-spacing: -1px;
    }
    .institucional-container h2 {
      font-size: 1.4rem;
      font-weight: 700;
      margin-top: 2rem;
      margin-bottom: 1rem;
    }
    .institucional-container p {
      margin-bottom: 1.5rem;
      color: var(--text-muted);
    }
    .institucional-container ul {
      margin-bottom: 1.5rem;
      padding-left: 1.5rem;
      color: var(--text-muted);
    }
  </style>
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

  <!-- Comentário de regra: Container principal com os termos de uso, regras de divulgação e política de isenção de preços -->
  <main class="institucional-container">
    <h1>Termos de Uso</h1>
    <p>Última atualização: 20 de Junho de 2026</p>
    
    <p>Bem-vindo ao <strong>Mimos Shop Brasil</strong>. Ao acessar e utilizar este site, você concorda em cumprir e estar vinculado aos seguintes Termos de Uso. Caso não concorde com qualquer uma das condições estabelecidas, solicitamos que não utilize nossa plataforma.</p>

    <h2>1. Escopo dos Serviços</h2>
    <p>O Mimos Shop Brasil atua como um comparador de preços, agregador de ofertas e canal de divulgação de cupons de desconto. Nós não vendemos nenhum produto diretamente. Todas as transações financeiras, envios de mercadorias e garantias pós-venda são de responsabilidade exclusiva dos respectivos parceiros varejistas (como Amazon e Mercado Livre).</p>

    <h2>2. Isenção de Responsabilidade sobre Preços e Estoques</h2>
    <p>Trabalhamos intensamente para manter os preços e links de ofertas sempre atualizados. No entanto:</p>
    <ul>
      <li>Os preços e a disponibilidade dos produtos nas lojas parceiras mudam frequentemente e podem sofrer alterações sem aviso prévio.</li>
      <li>Em caso de divergência entre os valores exibidos em nossa plataforma e os valores da página de check-out do parceiro final, <strong>prevalece sempre o preço e a condição exibidos na loja parceira correspondente</strong>.</li>
      <li>Não assumimos responsabilidade por erros de digitação, ofertas expiradas ou problemas logísticos dos vendedores finais.</li>
    </ul>

    <h2>3. Política de Links de Afiliados</h2>
    <p>Para manter o serviço gratuito e ativo, o Mimos Shop Brasil utiliza links de afiliado. Isso significa que ao clicar em um link promocional exibido em nosso catálogo ou solicitado via formulário de pedidos, e concluir uma compra no site parceiro, nós poderemos receber uma pequena comissão pela indicação. Esta operação não adiciona nenhum centavo ao custo final do seu produto.</p>

    <h2>4. Modificações do Termo</h2>
    <p>Reservamo-nos o direito de alterar estes Termos de Uso a qualquer momento para refletir melhorias no serviço ou adequações legais. Recomendamos que os usuários visitem esta página periodicamente.</p>
  </main>

  <?php 
    // Carrega o rodapé dinâmico do site (com categorias do Supabase)
    include 'footer.php'; 
  ?>
  <script src="js/main.min.js"></script>
</body>
</html>
