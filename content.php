<?php
include 'sections/hero.html';
// Inclui a seção de selos de lojas carregadas dinamicamente do Supabase
include 'sections/store_badges.php';
// Inclui a seção de categorias carregadas dinamicamente do Supabase
include 'sections/categories.php';
echo '<hr class="divider">';
// Inclui a seção de ofertas do dia carregadas dinamicamente do Supabase
include 'sections/featured_deals.php';
echo '<hr class="divider">';
// Inclui a seção de comparativo de preços carregada dinamicamente do Supabase
include 'sections/price_comparison.php';
include 'sections/newsletter.html';
include 'sections/how_it_works.html';
?>
