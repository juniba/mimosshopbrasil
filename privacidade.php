<?php
/*
  privacidade.php: Página de Política de Privacidade do Mimos Shop Brasil.
  Exibe os termos de conformidade com a LGPD e regras do programa de afiliados.
*/
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Política de Privacidade – Mimos Shop Brasil</title>
  <meta name="description" content="Leia a Política de Privacidade do Mimos Shop Brasil. Entenda como tratamos seus dados e nossa conformidade com a LGPD e programas de afiliados.">
  <meta name="robots" content="index, follow">
  <link rel="canonical" href="https://mimosshopbrasil.com/privacidade.php">
  
  <!-- Preconnect e fontes do Google Fonts carregadas via head para otimização de renderização -->
  <!-- Comentário de regra: Este bloco carrega a fonte Inter de forma performática -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  
  <!-- Preload de recursos críticos de imagem para melhorar o LCP/CLS -->
  <link rel="preload" href="img/logo.png" as="image">

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

  <!-- Comentário de regra: Container principal com texto informativo da política de privacidade em conformidade com a LGPD -->
  <main class="institucional-container">
    <h1>Política de Privacidade</h1>
    <p>Última atualização: 20 de Junho de 2026</p>
    
    <p>A sua privacidade é de extrema importância para nós do <strong>Mimos Shop Brasil</strong>. Esta Política de Privacidade descreve como coletamos, usamos, processamos e protegemos as informações fornecidas por você ao acessar nosso site e ao utilizar nossos serviços de indicação de produtos e ofertas.</p>

    <h2>1. Informações Coletadas</h2>
    <p>Coletamos informações das seguintes formas:</p>
    <ul>
      <li><strong>Formulários de Newsletter:</strong> Quando você se cadastra para receber alertas de ofertas via WhatsApp, solicitamos seu número de WhatsApp.</li>
      <li><strong>Pedidos de Links:</strong> Quando você solicita links de descontos customizados, coletamos seu nome, número de WhatsApp e a URL ou descrição do produto solicitado.</li>
      <li><strong>Dados de Navegação (Cookies):</strong> Podemos utilizar cookies e tecnologias de rastreamento para analisar padrões de tráfego e melhorar a experiência do usuário em nosso site.</li>
    </ul>

    <h2>2. Uso das Informações</h2>
    <p>As informações coletadas são utilizadas exclusivamente para:</p>
    <ul>
      <li>Enviar alertas de ofertas e cupons relevantes por meio dos canais autorizados.</li>
      <li>Processar e atender pedidos de links personalizados.</li>
      <li>Melhorar e personalizar as funcionalidades do site.</li>
      <li>Garantir a segurança da nossa plataforma contra acessos indevidos e spams (como o rate limiting ativo por IP).</li>
    </ul>

    <h2>3. Divulgação a Terceiros e Links de Afiliados</h2>
    <p>Nós não vendemos nem alugamos suas informações pessoais. No entanto, observe que:</p>
    <ul>
      <li><strong>Programas de Afiliados:</strong> O Mimos Shop Brasil participa de programas de afiliados (como o Associados da Amazon e o Mercado Livre). Ao clicar em links de ofertas e cupons, você será redirecionado para a loja parceira, que possui sua própria política de privacidade e cookies de rastreamento de vendas.</li>
      <li><strong>Serviços de Envio:</strong> Utilizamos integrações terceirizadas de entrega de mensagens (como CallMeBot) para disparar notificações ao administrador e aos usuários, respeitando as normas aplicáveis de segurança da informação.</li>
    </ul>

    <h2>4. Direitos sob a LGPD</h2>
    <p>Em conformidade com a Lei Geral de Proteção de Dados (Lei nº 13.709/2018), você possui o direito de:</p>
    <ul>
      <li>Confirmar a existência de tratamento de dados pessoais.</li>
      <li>Acessar seus dados pessoais e solicitar a correção de dados incompletos ou inexatos.</li>
      <li>Solicitar a exclusão definitiva dos seus dados (como o cancelamento do WhatsApp cadastrado na newsletter).</li>
    </ul>
    <p>Para exercer esses direitos, por favor, entre em contato conosco.</p>

    <h2>5. Contato</h2>
    <p>Se você tiver alguma dúvida sobre esta Política de Privacidade, entre em contato através de nosso WhatsApp de atendimento oficial cadastrado no rodapé do site.</p>
  </main>

  <?php 
    // Carrega o rodapé dinâmico do site (com categorias do Supabase)
    include 'footer.php'; 
  ?>
  <script src="js/main.min.js"></script>
</body>
</html>
