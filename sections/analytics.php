<?php
/*
  analytics.php: Carrega assincronamente as tags de monitoramento do Google Analytics (GA4) e Google Tag Manager (GTM)
  apenas se as constantes estiverem devidamente configuradas no arquivo .env.
*/

// Comentário explicativo: Recupera as credenciais de GA4 e GTM das constantes globais
$ga_id = defined('GOOGLE_ANALYTICS_ID') ? GOOGLE_ANALYTICS_ID : '';
$gtm_id = defined('GOOGLE_TAG_MANAGER_ID') ? GOOGLE_TAG_MANAGER_ID : '';

// Comentário explicativo: Se houver ID do Google Tag Manager cadastrado, carrega o script no head
if (!empty($gtm_id)):
?>
  <!-- Google Tag Manager -->
  <script>
    // Comentário explicativo: Inicializa o dataLayer e carrega dinamicamente a biblioteca do Google Tag Manager
    (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','<?php echo htmlspecialchars($gtm_id); ?>');
  </script>
  <!-- End Google Tag Manager -->
<?php 
endif;

// Comentário explicativo: Se houver ID do Google Analytics 4, carrega a tag gtag.js tradicional
if (!empty($ga_id)):
?>
  <!-- Global site tag (gtag.js) - Google Analytics -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo htmlspecialchars($ga_id); ?>"></script>
  <script>
    // Comentário de regra: Inicialização global do tracker de eventos do Google Analytics
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '<?php echo htmlspecialchars($ga_id); ?>');
  </script>
<?php endif; ?>

