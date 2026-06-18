<?php
/*
  painel.php: Página administrativa (Painel de Controle) para gerenciamento completo (CRUD) de produtos.
  Fornece interfaces para visão geral de métricas, listagem tabular, formulários de criação/edição 
  e exclusão direta via chamadas REST autenticadas com a API do Supabase.
  Protegida por barreira de autenticação de sessão PHP no backend.
*/
require_once 'config.php';
// Carrega o helper do Cloudinary para lidar com conversão WebP e uploads de imagens
require_once 'cloudinary_helper.php';

// Proteção do Painel: Se não houver sessão ativa de admin, barra o acesso
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Armazena o e-mail do administrador logado para exibição personalizada
$adminEmail = htmlspecialchars($_SESSION['admin_email']);

// A função supabase_admin_request foi movida para o config.php para possibilitar seu reuso global em todo o projeto.


// Variáveis para feedback visual ao administrador
$alertMessage = '';
$alertClass = '';

// PROCESSAMENTO DO CRUD NO BACKEND

// 1. Ações de Envio de Formulário (POST) - Inserção e Atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        // === CRUD DE PRODUTOS (INSERT / UPDATE) ===
        if ($action === 'insert' || $action === 'update') {
            // Limpa e sanitiza os campos do formulário
            $titulo = trim($_POST['titulo']);
            $preco_novo = floatval(str_replace(',', '.', $_POST['preco_novo']));
            $preco_antigo = !empty($_POST['preco_antigo']) ? floatval(str_replace(',', '.', $_POST['preco_antigo'])) : null;
            
            // Calcula desconto automaticamente em formato percentual se houver preço antigo maior
            $desconto = null;
            if ($preco_antigo !== null && $preco_antigo > $preco_novo) {
                $desconto_percent = round((($preco_antigo - $preco_novo) / $preco_antigo) * 100);
                $desconto = '-' . $desconto_percent . '%';
            }
            
            $link_afiliado = trim($_POST['link_afiliado']);
            // Processa o arquivo enviado ou a URL de texto fornecida, convertendo para WebP e enviando ao Cloudinary
            $imagem_url = handle_image_upload_or_url($_FILES['imagem_file'] ?? null, $_POST['imagem_url'] ?? '');
            $categoria_id = intval($_POST['categoria_id']);
            $store_id = intval($_POST['store_id']);
            $is_featured = isset($_POST['is_featured']) ? true : false;
            // Captura o campo de encomenda enviado via formulário (booleano)
            $is_encomenda = isset($_POST['is_encomenda']) ? true : false;
            $slug_comparativo = !empty($_POST['slug_comparativo']) ? trim($_POST['slug_comparativo']) : null;
            $estrelas = !empty($_POST['estrelas']) ? trim($_POST['estrelas']) : '★★★★★';
            $avaliacoes = !empty($_POST['avaliacoes']) ? intval($_POST['avaliacoes']) : 0;
            
            // Recupera o nome da loja de forma dinâmica baseado no store_id para preencher a coluna string 'loja'
            $store_data = supabase_admin_request('GET', '/rest/v1/store?id=eq.' . $store_id);
            $loja = 'amazon'; // Fallback padrão
            if (!empty($store_data)) {
                $css = $store_data[0]['css_class'];
                $loja = str_replace('badge-', '', $css); // Converte badge-ml para ml, etc.
            }
            
            // Monta o payload de produto para a API do Supabase
            $payload = [
                'titulo' => $titulo,
                'preco_novo' => $preco_novo,
                'preco_antigo' => $preco_antigo,
                'desconto' => $desconto,
                'link_afiliado' => $link_afiliado,
                'imagem_url' => $imagem_url,
                'categoria_id' => $categoria_id,
                'store_id' => $store_id,
                'loja' => $loja,
                'is_featured' => $is_featured,
                // Adiciona o campo is_encomenda ao payload a ser enviado para o Supabase
                'is_encomenda' => $is_encomenda,
                'slug_comparativo' => $slug_comparativo,
                'estrelas' => $estrelas,
                'avaliacoes' => $avaliacoes
            ];
            
            if ($action === 'insert') {
                // Insere um novo registro na tabela produtos do Supabase usando a autenticação do admin
                $res = supabase_admin_request('POST', '/rest/v1/produtos', $payload, true);
                if ($res !== false) {
                    // Carrega o helper de WhatsApp e dispara os alertas para todos os cadastrados na newsletter
                    require_once 'whatsapp_helper.php';
                    send_new_product_notification($payload);

                    header('Location: painel.php?action=list&success=inserted');
                    exit;
                } else {
                    $alertMessage = "Erro ao cadastrar o produto no Supabase.";
                    $alertClass = "alert-error";
                }
            } elseif ($action === 'update') {
                // Atualiza o registro existente usando ID com autenticação de admin
                $id = intval($_POST['id']);
                $res = supabase_admin_request('PATCH', '/rest/v1/produtos?id=eq.' . $id, $payload, true);
                if ($res !== false) {
                    header('Location: painel.php?action=list&success=updated');
                    exit;
                } else {
                    $alertMessage = "Erro ao atualizar o produto no Supabase.";
                    $alertClass = "alert-error";
                }
            }
        }
        
        // === CRUD DE CATEGORIAS (INSERT / UPDATE) ===
        if ($action === 'insert_category' || $action === 'update_category') {
            // Sanitiza os campos do formulário de categoria
            $cat_nome = trim($_POST['cat_nome']);
            $cat_icone = trim($_POST['cat_icone']);
            
            // Monta o payload da categoria para a API do Supabase
            $cat_payload = [
                'nome' => $cat_nome,
                'icone' => $cat_icone
            ];
            
            if ($action === 'insert_category') {
                // Insere uma nova categoria na tabela categorias do Supabase com autenticação de admin
                $res = supabase_admin_request('POST', '/rest/v1/categorias', $cat_payload, true);
                if ($res !== false) {
                    header('Location: painel.php?action=categories&success=cat_inserted');
                    exit;
                } else {
                    $alertMessage = "Erro ao cadastrar a categoria no Supabase.";
                    $alertClass = "alert-error";
                }
            } elseif ($action === 'update_category') {
                // Atualiza a categoria existente usando ID com autenticação de admin
                $cat_id = intval($_POST['cat_id']);
                $res = supabase_admin_request('PATCH', '/rest/v1/categorias?id=eq.' . $cat_id, $cat_payload, true);
                if ($res !== false) {
                    header('Location: painel.php?action=categories&success=cat_updated');
                    exit;
                } else {
                    $alertMessage = "Erro ao atualizar a categoria no Supabase.";
                    $alertClass = "alert-error";
                }
            }
        }
        
        // === CRUD DE LOJAS (INSERT / UPDATE) ===
        if ($action === 'insert_store' || $action === 'update_store') {
            // Sanitiza os campos do formulário de loja
            $store_nome = trim($_POST['store_nome']);
            $store_css_class = trim($_POST['store_css_class']);
            $store_badge_text = trim($_POST['store_badge_text']);
            $store_cor = trim($_POST['store_cor']);
            
            // Monta o payload da loja para a API do Supabase
            $store_payload = [
                'nome' => $store_nome,
                'css_class' => $store_css_class,
                'badge_text' => $store_badge_text,
                'cor' => $store_cor
            ];
            
            if ($action === 'insert_store') {
                // Insere uma nova loja na tabela store do Supabase com autenticação de admin
                $res = supabase_admin_request('POST', '/rest/v1/store', $store_payload, true);
                if ($res !== false) {
                    header('Location: painel.php?action=stores&success=store_inserted');
                    exit;
                } else {
                    $alertMessage = "Erro ao cadastrar a loja no Supabase.";
                    $alertClass = "alert-error";
                }
            } elseif ($action === 'update_store') {
                // Atualiza a loja existente usando ID com autenticação de admin
                $store_edit_id = intval($_POST['store_edit_id']);
                $res = supabase_admin_request('PATCH', '/rest/v1/store?id=eq.' . $store_edit_id, $store_payload, true);
                if ($res !== false) {
                    header('Location: painel.php?action=stores&success=store_updated');
                    exit;
                } else {
                    $alertMessage = "Erro ao atualizar a loja no Supabase.";
                    $alertClass = "alert-error";
                }
            }
        }
        
        // === CADASTRO MANUAL DE LEAD NA NEWSLETTER ===
        if ($action === 'insert_newsletter') {
            $whatsapp_raw = trim($_POST['whatsapp']);
            // Higieniza o número removendo caracteres não numéricos
            $whatsapp = preg_replace('/\D/', '', $whatsapp_raw);
            if (strlen($whatsapp) === 10 || strlen($whatsapp) === 11) {
                $whatsapp = '55' . $whatsapp;
            }
            
            if (!empty($whatsapp)) {
                $news_payload = [
                    'whatsapp' => $whatsapp_raw // Salva o WhatsApp formatado conforme inserido pelo admin
                ];
                $res = supabase_admin_request('POST', '/rest/v1/newsletter', $news_payload, true);
                if ($res !== false) {
                    header('Location: painel.php?action=newsletter_leads&success=news_inserted');
                    exit;
                } else {
                    $alertMessage = "Erro ao cadastrar o WhatsApp na newsletter.";
                    $alertClass = "alert-error";
                }
            } else {
                $alertMessage = "Por favor, informe um número de WhatsApp válido.";
                $alertClass = "alert-error";
            }
        }
    }
}

// 2. Ações de Exclusão de Produtos (GET)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    // Remove o produto no Supabase usando a autenticação do admin
    $res = supabase_admin_request('DELETE', '/rest/v1/produtos?id=eq.' . $id, null, true);
    if ($res !== false) {
        header('Location: painel.php?action=list&success=deleted');
        exit;
    } else {
        $alertMessage = "Erro ao excluir o produto no Supabase.";
        $alertClass = "alert-error";
    }
}

// 2b. Ações de Exclusão de Categorias (GET)
if (isset($_GET['action']) && $_GET['action'] === 'delete_category' && isset($_GET['id'])) {
    $cat_del_id = intval($_GET['id']);
    // Remove a categoria da tabela categorias do Supabase usando a autenticação do admin
    $res = supabase_admin_request('DELETE', '/rest/v1/categorias?id=eq.' . $cat_del_id, null, true);
    if ($res !== false) {
        header('Location: painel.php?action=categories&success=cat_deleted');
        exit;
    } else {
        $alertMessage = "Erro ao excluir a categoria no Supabase.";
        $alertClass = "alert-error";
    }
}

// 2c. Ações de Exclusão de Lojas (GET)
if (isset($_GET['action']) && $_GET['action'] === 'delete_store' && isset($_GET['id'])) {
    $store_del_id = intval($_GET['id']);
    // Remove a loja da tabela store do Supabase usando a autenticação do admin
    $res = supabase_admin_request('DELETE', '/rest/v1/store?id=eq.' . $store_del_id, null, true);
    if ($res !== false) {
        header('Location: painel.php?action=stores&success=store_deleted');
        exit;
    } else {
        $alertMessage = "Erro ao excluir a loja no Supabase.";
        $alertClass = "alert-error";
    }
}

// 2d. Ações de Exclusão de Pedidos de Link (GET)
if (isset($_GET['action']) && $_GET['action'] === 'delete_request' && isset($_GET['id'])) {
    $req_del_id = intval($_GET['id']);
    // Remove a solicitação de link do Supabase usando autenticação admin
    $res = supabase_admin_request('DELETE', '/rest/v1/pedidosLink?id=eq.' . $req_del_id, null, true);
    if ($res !== false) {
        header('Location: painel.php?action=requests&success=req_deleted');
        exit;
    } else {
        $alertMessage = "Erro ao excluir o pedido de link no Supabase.";
        $alertClass = "alert-error";
    }
}

// 2e. Ações de Exclusão de Contatos da Newsletter (GET)
if (isset($_GET['action']) && $_GET['action'] === 'delete_newsletter' && isset($_GET['id'])) {
    $news_del_id = intval($_GET['id']);
    // Remove o lead de WhatsApp cadastrado na newsletter usando autenticação admin
    $res = supabase_admin_request('DELETE', '/rest/v1/newsletter?id=eq.' . $news_del_id, null, true);
    if ($res !== false) {
        header('Location: painel.php?action=newsletter_leads&success=news_deleted');
        exit;
    } else {
        $alertMessage = "Erro ao excluir o contato do Supabase.";
        $alertClass = "alert-error";
    }
}

// 3. Captura alertas de sucesso vindos de redirecionamentos anteriores
if (isset($_GET['success'])) {
    $success_type = $_GET['success'];
    // Alertas de produtos
    if ($success_type === 'inserted') {
        $alertMessage = "Produto cadastrado com sucesso!";
        $alertClass = "alert-success";
    } elseif ($success_type === 'updated') {
        $alertMessage = "Produto atualizado com sucesso!";
        $alertClass = "alert-success";
    } elseif ($success_type === 'deleted') {
        $alertMessage = "Produto removido com sucesso!";
        $alertClass = "alert-success";
    }
    // Alertas de categorias
    elseif ($success_type === 'cat_inserted') {
        $alertMessage = "Categoria cadastrada com sucesso!";
        $alertClass = "alert-success";
    } elseif ($success_type === 'cat_updated') {
        $alertMessage = "Categoria atualizada com sucesso!";
        $alertClass = "alert-success";
    } elseif ($success_type === 'cat_deleted') {
        $alertMessage = "Categoria removida com sucesso!";
        $alertClass = "alert-success";
    }
    // Alertas de lojas
    elseif ($success_type === 'store_inserted') {
        $alertMessage = "Loja cadastrada com sucesso!";
        $alertClass = "alert-success";
    } elseif ($success_type === 'store_updated') {
        $alertMessage = "Loja atualizada com sucesso!";
        $alertClass = "alert-success";
    } elseif ($success_type === 'store_deleted') {
        $alertMessage = "Loja removida com sucesso!";
        $alertClass = "alert-success";
    }
    // Alertas de Pedidos de Link
    elseif ($success_type === 'req_deleted') {
        $alertMessage = "Solicitação de link excluída com sucesso!";
        $alertClass = "alert-success";
    }
    // Alertas de Newsletter
    elseif ($success_type === 'news_inserted') {
        $alertMessage = "Número de WhatsApp adicionado à newsletter com sucesso!";
        $alertClass = "alert-success";
    } elseif ($success_type === 'news_deleted') {
        $alertMessage = "WhatsApp removido da newsletter com sucesso!";
        $alertClass = "alert-success";
    }
}

// CARREGAMENTO DOS DADOS PARA AS TELAS

// Define qual tela administrativa será renderizada na página
$current_action = $_GET['action'] ?? 'dashboard';

// Busca contagens e listas para os badges de notificação e cards (sempre carregados para uso na sidebar)
$newsletter_count = 0;
$requests_count = 0;

$all_newsletter_count_res = supabase_admin_request('GET', '/rest/v1/newsletter?select=id', null, true);
if (is_array($all_newsletter_count_res)) {
    $newsletter_count = count($all_newsletter_count_res);
}

$all_requests_count_res = supabase_admin_request('GET', '/rest/v1/pedidosLink?select=id', null, true);
if (is_array($all_requests_count_res)) {
    $requests_count = count($all_requests_count_res);
}

// Busca lista de produtos com relacionamento de loja e categoria do Supabase
$products_list = [];
if ($current_action === 'list') {
    $products_list = supabase_admin_request('GET', '/rest/v1/produtos?select=*,store(nome),categorias(nome)&order=id.asc');
}

// Busca listas auxiliares de categorias e lojas caso a tela de formulário esteja ativa
$categories_list = [];
$stores_list = [];
if ($current_action === 'new' || $current_action === 'edit') {
    $categories_list = supabase_admin_request('GET', '/rest/v1/categorias?select=*&order=id.asc');
    $stores_list = supabase_admin_request('GET', '/rest/v1/store?select=*&order=id.asc');
}

// Busca lista de pedidos de link se estiver na respectiva tela
$requests_list = [];
if ($current_action === 'requests') {
    $requests_list = supabase_admin_request('GET', '/rest/v1/pedidosLink?select=*&order=id.desc', null, true);
}

// Busca lista de leads da newsletter se estiver na respectiva tela
$newsletter_leads_list = [];
if ($current_action === 'newsletter_leads') {
    $newsletter_leads_list = supabase_admin_request('GET', '/rest/v1/newsletter?select=*&order=id.desc', null, true);
}

// Busca os dados do produto específico em caso de tela de edição
$edit_product = null;
if ($current_action === 'edit' && isset($_GET['id'])) {
    $edit_id = intval($_GET['id']);
    $res = supabase_admin_request('GET', '/rest/v1/produtos?id=eq.' . $edit_id);
    if (!empty($res)) {
        $edit_product = $res[0];
    } else {
        $alertMessage = "Produto não encontrado para edição.";
        $alertClass = "alert-error";
        $current_action = 'list';
    }
}

// === CARREGAMENTO DE DADOS PARA TELAS DE CATEGORIAS ===

// Busca a lista completa de categorias para a tela de listagem de categorias
$admin_categories_list = [];
if ($current_action === 'categories') {
    $admin_categories_list = supabase_admin_request('GET', '/rest/v1/categorias?select=*&order=id.asc');
}

// Busca os dados da categoria específica para a tela de edição
$edit_category = null;
if ($current_action === 'edit_category' && isset($_GET['id'])) {
    $edit_cat_id = intval($_GET['id']);
    $res = supabase_admin_request('GET', '/rest/v1/categorias?id=eq.' . $edit_cat_id);
    if (!empty($res)) {
        $edit_category = $res[0];
    } else {
        $alertMessage = "Categoria não encontrada para edição.";
        $alertClass = "alert-error";
        $current_action = 'categories';
        // Recarrega a lista após o fallback
        $admin_categories_list = supabase_admin_request('GET', '/rest/v1/categorias?select=*&order=id.asc');
    }
}

// === CARREGAMENTO DE DADOS PARA TELAS DE LOJAS ===

// Busca a lista completa de lojas para a tela de listagem de lojas
$admin_stores_list = [];
if ($current_action === 'stores') {
    $admin_stores_list = supabase_admin_request('GET', '/rest/v1/store?select=*&order=id.asc');
}

// Busca os dados da loja específica para a tela de edição
$edit_store = null;
if ($current_action === 'edit_store' && isset($_GET['id'])) {
    $edit_store_id = intval($_GET['id']);
    $res = supabase_admin_request('GET', '/rest/v1/store?id=eq.' . $edit_store_id);
    if (!empty($res)) {
        $edit_store = $res[0];
    } else {
        $alertMessage = "Loja não encontrada para edição.";
        $alertClass = "alert-error";
        $current_action = 'stores';
        // Recarrega a lista após o fallback
        $admin_stores_list = supabase_admin_request('GET', '/rest/v1/store?select=*&order=id.asc');
    }
}

// Busca estatísticas gerais na dashboard (produtos, categorias e lojas)
$total_products = 0;
$total_categories = 0;
$total_stores = 0;
$featured_count = 0;
if ($current_action === 'dashboard') {
    // Conta todos os produtos e produtos em destaque
    $all_prods = supabase_admin_request('GET', '/rest/v1/produtos?select=id,is_featured');
    if (is_array($all_prods)) {
        $total_products = count($all_prods);
        foreach ($all_prods as $p) {
            if ($p['is_featured'] === true) {
                $featured_count++;
            }
        }
    }
    
    // Conta todas as categorias cadastradas
    $all_cats = supabase_admin_request('GET', '/rest/v1/categorias?select=id');
    if (is_array($all_cats)) {
        $total_categories = count($all_cats);
    }
    
    // Conta todas as lojas parceiras cadastradas
    $all_stores = supabase_admin_request('GET', '/rest/v1/store?select=id');
    if (is_array($all_stores)) {
        $total_stores = count($all_stores);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Painel de Controle – TechDeal</title>
  <link rel="stylesheet" href="css/style.css">
  
  <!-- Estilos customizados locais para interface premium de administração do CRUD -->
  <style>
    /* Ajustes de Layout para colar o painel no cabeçalho e alinhar a barra lateral à esquerda */
    header .header-inner {
      max-width: 100%; /* Permite que o cabeçalho se estenda por toda a largura da tela no painel */
      padding: 0 2rem; /* Adiciona espaçamento nas extremidades para harmonizar com o grid */
    }
    
    .painel-container {
      max-width: 100%; /* Estica o container por toda a largura da viewport */
      margin: 0; /* Remove a margem superior de 2rem para colar no menu superior */
      padding: 0; /* Remove o padding lateral */
      gap: 0; /* Remove o gap entre o sidebar e a área de conteúdo */
      grid-template-columns: 220px 1fr; /* Define 220px fixo para a sidebar (mais estreita) e ocupa o resto com o conteúdo */
      min-height: calc(100vh - 64px); /* Garante que o container ocupe a altura restante da tela */
    }
    
    .painel-sidebar {
      border-radius: 0; /* Torna a sidebar reta para se encaixar nas bordas da tela */
      border-top: none; /* Remove a borda superior para colar perfeitamente no cabeçalho */
      border-bottom: none; /* Remove a borda inferior */
      border-left: none; /* Remove a borda esquerda */
      border-right: 1px solid var(--border); /* Mantém apenas a borda divisória da direita */
      box-shadow: none; /* Remove sombra para visual flat mais corporativo */
      height: calc(100vh - 64px); /* Trava a altura da sidebar no tamanho da tela visível */
      position: sticky; /* Torna a sidebar fixa ao rolar a página */
      top: 64px; /* Fixa o topo logo abaixo do cabeçalho de 64px */
      overflow-y: auto; /* Permite rolar a sidebar internamente se houver muitos itens */
      padding: 1rem 0.6rem; /* Padding reduzido para deixar a sidebar compacta com pouco espaço lateral */
    }
    
    /* Ajustes específicos de padding e fonte dos links para ficarem mais compactos na barra lateral estreita */
    .sidebar-nav a {
      padding: 0.6rem 0.75rem; /* Ajusta espaçamento interno do link */
      font-size: 0.85rem; /* Diminui ligeiramente a fonte para caber no espaço estreito */
      gap: 0.5rem; /* Menor distância entre o ícone/emoji e o texto */
    }
    
    .painel-content {
      padding: 0; /* Remove o padding para colar o conteúdo na barra lateral e no cabeçalho */
      background: var(--white); /* Alinha com o fundo dos cards */
      min-height: calc(100vh - 64px); /* Garante que a área de conteúdo ocupe toda a altura visível */
      font-size: 0.88rem; /* Reduz o tamanho da fonte global dentro de toda a área de conteúdo */
    }
    
    /* Remove bordas, cantos arredondados e sombras do card administrativo para visual unificado */
    .painel-card {
      border: none !important;
      border-radius: 0 !important;
      box-shadow: none !important;
      padding: 1.25rem 2.5rem 2.5rem 2.5rem; /* Margem superior reduzida de 2.5rem para 1.25rem para subir o conteúdo */
      min-height: calc(100vh - 64px); /* Força o preenchimento de altura total */
    }

    /* Ajustes específicos de tamanho de fonte para os títulos principais */
    .painel-card h1 {
      font-size: 1.4rem !important; /* Diminui a fonte do título principal */
      margin-top: 0;
      margin-bottom: 0.25rem;
    }

    .painel-card p {
      font-size: 0.85rem !important; /* Diminui a descrição do painel */
      margin-bottom: 1.5rem !important;
    }

    /* Estilos de cabeçalho e posicionamento de botão */
    .crud-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
      flex-wrap: wrap;
      gap: 1rem;
    }
    
    /* Botões premium administrativos */
    .btn-admin-primary {
      background: var(--brand);
      color: var(--white);
      padding: 0.65rem 1.25rem;
      border-radius: var(--radius-sm);
      text-decoration: none;
      font-weight: 600;
      font-size: 0.9rem;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      border: none;
      cursor: pointer;
      transition: background 0.2s, transform 0.1s;
    }
    .btn-admin-primary:hover {
      background: var(--brand-dark);
    }
    .btn-admin-primary:active {
      transform: scale(0.98);
    }
    
    .btn-admin-secondary {
      background: var(--bg);
      border: 1px solid var(--border);
      color: var(--text);
      padding: 0.65rem 1.25rem;
      border-radius: var(--radius-sm);
      text-decoration: none;
      font-weight: 600;
      font-size: 0.9rem;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      cursor: pointer;
      transition: background 0.2s;
    }
    .btn-admin-secondary:hover {
      background: #E5E7EB;
    }
    
    /* Botões de ação direta na tabela compactos (apenas ícones emojis com tooltips) */
    .btn-action {
      padding: 0.35rem 0.5rem; /* Padding reduzido para deixá-los menores e mais quadrados */
      font-size: 0.75rem; /* Ícones emojis menores */
      border-radius: 6px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
    }
    .btn-edit {
      background: #FEF3C7;
      color: #D97706;
      border: 1px solid #FCD34D;
    }
    .btn-edit:hover {
      background: #FDE68A;
      box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .btn-delete {
      background: #FEE2E2;
      color: #DC2626;
      border: 1px solid #FCA5A5;
    }
    .btn-delete:hover {
      background: #FCA5A5;
      color: #991B1B;
      box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    
    /* Caixas de alertas de erro/sucesso */
    .alert-box {
      padding: 0.85rem 1.25rem;
      border-radius: var(--radius-sm);
      margin-bottom: 1.5rem;
      font-weight: 600;
      font-size: 0.9rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .alert-success {
      background: #DCFCE7;
      border: 1px solid #86EFAC;
      color: #15803d;
    }
    .alert-error {
      background: #FEE2E2;
      border: 1px solid #FCA5A5;
      color: #B91C1C;
    }
    
    /* Tabela responsiva de listagem */
    .admin-table-wrap {
      overflow-x: auto;
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      box-shadow: var(--shadow);
      background: var(--white);
    }
    .admin-table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
      font-size: 0.82rem; /* Reduz a fonte da tabela de produtos */
    }
    .admin-table th, .admin-table td {
      padding: 0.35rem 0.75rem; /* Padding reduzido para diminuir a altura das linhas e deixar mais compacto */
      border-bottom: 1px solid var(--border);
      vertical-align: middle;
    }
    .admin-table th {
      background: var(--bg);
      font-weight: 700;
      color: var(--text-muted);
      border-bottom: 2px solid var(--border);
    }
    .admin-table tr:last-child td {
      border-bottom: none;
    }
    .admin-table tr:hover {
      background: #F8FAFC;
    }
    
    /* Limita título do produto a apenas uma linha com reticências (...) */
    .ellipsis-cell-title {
      max-width: 260px; /* Limite de largura para o título */
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    
    /* Limita o slug do comparativo a apenas uma linha com reticências (...) */
    .ellipsis-cell-compare {
      max-width: 130px; /* Limite de largura para o slug comparativo */
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    
    /* Imagem miniatura na tabela (tamanho reduzido para diminuir a altura das linhas da tabela) */
    .admin-thumb {
      width: 32px;
      height: 32px;
      object-fit: contain;
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: 6px;
      padding: 2px;
    }
    
    /* Formulários e layouts de campos */
    .product-form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.5rem;
    }
    @media (max-width: 768px) {
      .product-form-grid {
        grid-template-columns: 1fr;
      }
    }
    .form-group-full {
      grid-column: span 2;
    }
    @media (max-width: 768px) {
      .form-group-full {
        grid-column: span 1;
      }
    }
    
    .form-field {
      display: flex;
      flex-direction: column;
      gap: 0.45rem;
    }
    .form-field label {
      font-size: 0.8rem;
      font-weight: 700;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .form-field input, .form-field select {
      padding: 0.8rem 1rem;
      border-radius: var(--radius-sm);
      border: 1.5px solid var(--border);
      font-size: 0.95rem;
      outline: none;
      background: var(--white);
      transition: all 0.2s;
    }
    .form-field input:focus, .form-field select:focus {
      border-color: var(--brand);
      box-shadow: 0 0 0 4px rgba(0, 87, 255, 0.12);
    }
    
    /* Campo de seleção de checkbox */
    .checkbox-field {
      flex-direction: row;
      align-items: center;
      gap: 0.75rem;
      padding: 0.75rem 0;
      grid-column: span 2;
    }
    @media (max-width: 768px) {
      .checkbox-field {
        grid-column: span 1;
      }
    }
    .checkbox-field input {
      width: 20px;
      height: 20px;
      cursor: pointer;
    }
    .checkbox-field label {
      cursor: pointer;
      text-transform: none;
      letter-spacing: 0;
      font-size: 0.92rem;
      color: var(--text);
    }
    
    .form-actions {
      display: flex;
      gap: 1rem;
      margin-top: 2rem;
      border-top: 1px solid var(--border);
      padding-top: 1.5rem;
    }
    
    /* Badges de estilo na tabela */
    .tag-amazon, .tag-ml {
      display: inline-block;
      padding: 0.25rem 0.5rem;
      font-size: 0.75rem;
      font-weight: 700;
      border-radius: 4px;
      text-transform: uppercase;
      white-space: nowrap; /* Garante que o nome da loja caiba apenas em uma única linha */
    }
    .tag-amazon {
      background: #FFF3E0;
      color: #E65100;
      border: 1px solid #FFE0B2;
    }
    .tag-ml {
      background: #EBF5FF;
      color: #1E88E5;
      border: 1px solid #BBDEFB;
    }
  </style>
</head>
<body>
  <?php 
    // Inclui o cabeçalho dinâmico compartilhado da página
    include 'header.php'; 
  ?>

  <!-- Container principal do Painel (Layout de Duas Colunas) -->
  <div class="painel-container">
    
    <!-- Sidebar (Menu Lateral de Ações Administrativas Dinâmico) -->
    <aside class="painel-sidebar">
      <nav class="sidebar-nav">
        <!-- Links de navegação interna com ativação dinâmica com base no parâmetro action -->
        <a href="painel.php?action=dashboard" class="<?php echo $current_action === 'dashboard' ? 'active' : ''; ?>">📊 Visão Geral</a>
        <a href="painel.php?action=list" class="<?php echo ($current_action === 'list' || $current_action === 'edit') ? 'active' : ''; ?>">📦 Listar Produtos</a>
        <a href="painel.php?action=new" class="<?php echo $current_action === 'new' ? 'active' : ''; ?>">➕ Adicionar Produto</a>
        <!-- Links de gerenciamento de Categorias e Lojas com ativação dinâmica -->
        <a href="painel.php?action=categories" class="<?php echo in_array($current_action, ['categories', 'new_category', 'edit_category']) ? 'active' : ''; ?>">🏷️ Categorias</a>
        <a href="painel.php?action=stores" class="<?php echo in_array($current_action, ['stores', 'new_store', 'edit_store']) ? 'active' : ''; ?>">🏪 Lojas</a>
        
        <!-- Link de gerenciamento de Pedidos de Link com badge de contagem dinâmica (notificação de novidades) -->
        <a href="painel.php?action=requests" class="<?php echo $current_action === 'requests' ? 'active' : ''; ?>">
          📩 Pedidos
          <?php if ($requests_count > 0): ?>
            <span class="sidebar-badge"><?php echo $requests_count; ?></span>
          <?php endif; ?>
        </a>
        
        <!-- Link de gerenciamento de Leads da Newsletter com badge de contagem dinâmica -->
        <a href="painel.php?action=newsletter_leads" class="<?php echo $current_action === 'newsletter_leads' ? 'active' : ''; ?>">
          🔔 Newsletter
          <?php if ($newsletter_count > 0): ?>
            <span class="sidebar-badge-newsletter"><?php echo $newsletter_count; ?></span>
          <?php endif; ?>
        </a>
        
        <a href="#" onclick="handleLogout(event)">🚪 Sair</a>
      </nav>
    </aside>

    <!-- Área de Conteúdo Principal (Organizada com base no roteador action) -->
    <main class="painel-content">
      
      <!-- 1. TELA: DASHBOARD / VISÃO GERAL -->
      <?php if ($current_action === 'dashboard'): ?>
        <div class="painel-card">
          <h1>Painel de Controle</h1>
          <p>Bem-vindo, <strong><?php echo $adminEmail; ?></strong>! Use a barra lateral para gerenciar as ofertas e categorias do site.</p>
          
          <!-- Blocos com estatísticas simplificadas da vitrine de produtos obtidas do Supabase -->
          <div class="dashboard-summary">
            <div class="stat-box">
              <h3>Total de Produtos</h3>
              <span class="number"><?php echo $total_products; ?></span>
            </div>
            <div class="stat-box">
              <h3>Produtos em Destaque</h3>
              <span class="number"><?php echo $featured_count; ?></span>
            </div>
            <div class="stat-box">
              <h3>Categorias</h3>
              <span class="number"><?php echo $total_categories; ?></span>
            </div>
            <div class="stat-box">
              <h3>Lojas Parceiras</h3>
              <span class="number"><?php echo $total_stores; ?></span>
            </div>
            <!-- Novo card de estatística para encomendas de links -->
            <div class="stat-box" style="border-left: 4px solid #EF4444;">
              <h3>Pedidos de Link</h3>
              <span class="number"><?php echo $requests_count; ?></span>
              <a href="painel.php?action=requests" style="font-size: 0.8rem; color: var(--brand); font-weight: 600; text-decoration: none; display: block; margin-top: 0.5rem;">Gerenciar Pedidos →</a>
            </div>
            <!-- Novo card de estatística para contatos da newsletter -->
            <div class="stat-box" style="border-left: 4px solid #2563EB;">
              <h3>Newsletter Leads</h3>
              <span class="number"><?php echo $newsletter_count; ?></span>
              <a href="painel.php?action=newsletter_leads" style="font-size: 0.8rem; color: var(--brand); font-weight: 600; text-decoration: none; display: block; margin-top: 0.5rem;">Gerenciar Leads →</a>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- 2. TELA: LISTAGEM DE PRODUTOS (READ) -->
      <?php if ($current_action === 'list'): ?>
        <div class="painel-card">
          <div class="crud-header">
            <h1>Listar Produtos</h1>
            <a href="painel.php?action=new" class="btn-admin-primary">➕ Novo Produto</a>
          </div>
          
          <!-- Alertas de sucesso ou erro do processamento backend -->
          <?php if (!empty($alertMessage)): ?>
            <div class="alert-box <?php echo $alertClass; ?>">
              <?php echo ($alertClass === 'alert-success' ? '✓ ' : '⚠ ') . htmlspecialchars($alertMessage); ?>
            </div>
          <?php endif; ?>
          
          <!-- Tabela com listagem estruturada -->
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th style="width: 60px;">ID</th>
                  <th style="width: 70px;">Imagem</th>
                  <th>Título</th>
                  <th>Preço</th>
                  <th style="width: 145px;">Loja</th> <!-- Aumentado para acomodar Mercado Livre em uma linha -->
                  <th>Destaque?</th>
                  <!-- Coluna indicando se o produto é uma encomenda -->
                  <th>Encomenda?</th>
                  <th>Categoria</th>
                  <th style="width: 80px;">Ações</th> <!-- Reduzido para ficar compacto com botões menores -->
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($products_list)): ?>
                  <?php foreach ($products_list as $p): ?>
                    <tr>
                      <td><strong>#<?php echo $p['id']; ?></strong></td>
                      <td>
                        <?php if (!empty($p['imagem_url'])): ?>
                          <img src="<?php echo htmlspecialchars($p['imagem_url']); ?>" alt="Miniatura" class="admin-thumb">
                        <?php else: ?>
                          <span style="color: var(--text-light);">—</span>
                        <?php endif; ?>
                      </td>
                      <!-- Exibe o título com corte de reticências se exceder o limite horizontal da célula -->
                      <td class="ellipsis-cell-title" title="<?php echo htmlspecialchars($p['titulo']); ?>"><?php echo htmlspecialchars($p['titulo']); ?></td>
                      <!-- Removeu R$ conforme solicitação do usuário -->
                      <td><strong><?php echo number_format($p['preco_novo'], 2, ',', '.'); ?></strong></td>
                      <td>
                        <span class="tag-<?php echo htmlspecialchars($p['loja']); ?>">
                          <?php echo htmlspecialchars($p['store']['nome'] ?? $p['loja']); ?>
                        </span>
                      </td>
                      <td>
                        <?php echo $p['is_featured'] ? '<span style="color:#16A34A; font-weight:bold;">Sim</span>' : '<span style="color:var(--text-light);">Não</span>'; ?>
                      </td>
                      <!-- Renderiza se o produto é marcado como encomenda -->
                      <td>
                        <?php echo (isset($p['is_encomenda']) && $p['is_encomenda']) ? '<span style="color:#2563EB; font-weight:bold;">Sim</span>' : '<span style="color:var(--text-light);">Não</span>'; ?>
                      </td>
                      <!-- Exibe o nome da categoria relacionada no Supabase -->
                      <td>
                        <span style="color: var(--text-dark); font-weight: 500;">
                          <?php echo htmlspecialchars($p['categorias']['nome'] ?? '—'); ?>
                        </span>
                      </td>
                      <td>
                        <div style="display:flex; gap:0.4rem;">
                          <!-- Emojis menores sem textos e com tooltips explicativos -->
                          <a href="painel.php?action=edit&id=<?php echo $p['id']; ?>" class="btn-action btn-edit" title="Editar produto">✏️</a>
                          <a href="painel.php?action=delete&id=<?php echo $p['id']; ?>" 
                             class="btn-action btn-delete" 
                             title="Excluir produto"
                             onclick="return confirm('Tem certeza que deseja excluir o produto &quot;<?php echo addslashes($p['titulo']); ?>&quot;? Esta ação não pode ser desfeita.');">
                             🗑️
                          </a>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="8" class="text-center text-muted" style="padding: 3rem; text-align: center;">Nenhum produto cadastrado no momento no Supabase.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

      <!-- 3. TELA: FORMULÁRIO DE CADASTRO/EDIÇÃO (CREATE & UPDATE) -->
      <?php if ($current_action === 'new' || $current_action === 'edit'): ?>
        <div class="painel-card">
          <h1><?php echo $current_action === 'new' ? 'Adicionar Novo Produto' : 'Editar Produto #' . $edit_product['id']; ?></h1>
          <p>Preencha os campos abaixo para salvar as informações de forma segura no banco de dados.</p>
          
          <!-- Formulário habilitado para multipart/form-data para suportar uploads de arquivos locais -->
          <form action="painel.php?action=<?php echo $current_action; ?>" method="POST" enctype="multipart/form-data">
            <!-- Campos escondidos para controle de ação e ID de produto -->
            <input type="hidden" name="action" value="<?php echo $current_action === 'new' ? 'insert' : 'update'; ?>">
            <?php if ($current_action === 'edit'): ?>
              <input type="hidden" name="id" value="<?php echo $edit_product['id']; ?>">
            <?php endif; ?>
            
            <div class="product-form-grid">
              <!-- Título -->
              <div class="form-field form-group-full">
                <label for="titulo">Título do Produto</label>
                <input type="text" id="titulo" name="titulo" required placeholder="Ex: Fone de Ouvido Sony WH-1000XM5 Bluetooth"
                       value="<?php echo $current_action === 'edit' ? htmlspecialchars($edit_product['titulo']) : (isset($_GET['pre_title']) ? htmlspecialchars($_GET['pre_title']) : ''); ?>">
              </div>
              
              <!-- Preço Novo -->
              <div class="form-field">
                <label for="preco_novo">Preço Atual (R$)</label>
                <input type="text" id="preco_novo" name="preco_novo" required placeholder="Ex: 1349.90"
                       value="<?php echo $current_action === 'edit' ? htmlspecialchars($edit_product['preco_novo']) : ''; ?>">
              </div>
              
              <!-- Preço Antigo -->
              <div class="form-field">
                <label for="preco_antigo">Preço Antigo (R$) - Opcional (calcula desconto automaticamente)</label>
                <input type="text" id="preco_antigo" name="preco_antigo" placeholder="Ex: 2299.00"
                       value="<?php echo ($current_action === 'edit' && $edit_product['preco_antigo'] !== null) ? htmlspecialchars($edit_product['preco_antigo']) : ''; ?>">
              </div>
              
              <!-- Categoria -->
              <div class="form-field">
                <label for="categoria_id">Categoria</label>
                <select id="categoria_id" name="categoria_id" required>
                  <option value="">Selecione uma Categoria...</option>
                  <?php foreach ($categories_list as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>"
                      <?php echo ($current_action === 'edit' && $edit_product['categoria_id'] == $cat['id']) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($cat['icone'] . ' ' . $cat['nome']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              
              <!-- Loja Parceira -->
              <div class="form-field">
                <label for="store_id">Loja Parceira</label>
                <select id="store_id" name="store_id" required>
                  <option value="">Selecione uma Loja...</option>
                  <?php foreach ($stores_list as $store): ?>
                    <option value="<?php echo $store['id']; ?>"
                      <?php echo ($current_action === 'edit' && $edit_product['store_id'] == $store['id']) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($store['nome']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <!-- Link Afiliado -->
              <div class="form-field form-group-full">
                <label for="link_afiliado">Link de Afiliado</label>
                <input type="text" id="link_afiliado" name="link_afiliado" required placeholder="Ex: https://amazon.com.br/dp/..."
                       value="<?php echo $current_action === 'edit' ? htmlspecialchars($edit_product['link_afiliado']) : (isset($_GET['pre_link']) ? htmlspecialchars($_GET['pre_link']) : '#'); ?>">
              </div>
              
              <!-- Upload ou link externo de Imagem do Produto -->
              <div class="form-field form-group-full" style="display: flex; flex-direction: column; gap: 0.5rem;">
                <label for="imagem_file" style="font-weight: 700;">Upload de Imagem Local (Recomendado)</label>
                <input type="file" id="imagem_file" name="imagem_file" accept="image/*" style="border: 1.5px dashed var(--border); padding: 0.75rem; border-radius: var(--radius-sm); cursor: pointer; background: var(--white);">
                
                <label for="imagem_url" style="font-weight: 700; margin-top: 0.5rem;">Ou URL da Imagem (Externa ou existente)</label>
                <input type="text" id="imagem_url" name="imagem_url" placeholder="Ex: https://site.com/imagem.png ou deixe em branco se fizer upload"
                       value="<?php echo $current_action === 'edit' ? htmlspecialchars($edit_product['imagem_url']) : ''; ?>">
                <span style="font-size: 0.75rem; color: var(--text-muted); line-height: 1.4;">
                  💡 Qualquer imagem (por upload ou URL externa) será automaticamente convertida em <strong>WebP</strong> e salva no Cloudinary.
                </span>
              </div>
              
              <!-- Estrelas -->
              <div class="form-field">
                <label for="estrelas">Avaliação Visual (Estrelas)</label>
                <select id="estrelas" name="estrelas">
                  <option value="★★★★★" <?php echo ($current_action === 'edit' && $edit_product['estrelas'] === '★★★★★') ? 'selected' : ''; ?>>★★★★★ (5 Estrelas)</option>
                  <option value="★★★★☆" <?php echo ($current_action === 'edit' && $edit_product['estrelas'] === '★★★★☆') ? 'selected' : ''; ?>>★★★★☆ (4 Estrelas)</option>
                  <option value="★★★☆☆" <?php echo ($current_action === 'edit' && $edit_product['estrelas'] === '★★★☆☆') ? 'selected' : ''; ?>>★★★☆☆ (3 Estrelas)</option>
                  <option value="★★☆☆☆" <?php echo ($current_action === 'edit' && $edit_product['estrelas'] === '★★☆☆☆') ? 'selected' : ''; ?>>★★☆☆☆ (2 Estrelas)</option>
                  <option value="★☆☆☆☆" <?php echo ($current_action === 'edit' && $edit_product['estrelas'] === '★☆☆☆☆') ? 'selected' : ''; ?>>★☆☆☆☆ (1 Estrela)</option>
                </select>
              </div>
              
              <!-- Avaliações -->
              <div class="form-field">
                <label for="avaliacoes">Contagem de Avaliações</label>
                <input type="number" id="avaliacoes" name="avaliacoes" min="0" placeholder="Ex: 2341"
                       value="<?php echo $current_action === 'edit' ? intval($edit_product['avaliacoes']) : '0'; ?>">
              </div>
              
              <!-- Slug Comparativo -->
              <div class="form-field form-group-full">
                <label for="slug_comparativo">Slug Comparativo (Deixe em branco se não quiser comparar preços)</label>
                <input type="text" id="slug_comparativo" name="slug_comparativo" placeholder="Ex: sony-wh1000xm5"
                       value="<?php echo ($current_action === 'edit' && $edit_product['slug_comparativo'] !== null) ? htmlspecialchars($edit_product['slug_comparativo']) : ''; ?>">
                <small style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.2rem; display: block;">
                  Ao usar o mesmo slug comparativo em múltiplos produtos com lojas diferentes, o TechDeal agrupa-os na tabela de comparação de preços automaticamente.
                </small>
              </div>
              
              <!-- Destaque na Home -->
              <div class="form-field checkbox-field">
                <input type="checkbox" id="is_featured" name="is_featured" value="1"
                       <?php echo ($current_action === 'edit' && $edit_product['is_featured']) ? 'checked' : ($current_action === 'new' ? 'checked' : ''); ?>>
                <label for="is_featured">Exibir produto em destaque na grade principal da página inicial (Ofertas do dia)</label>
              </div>
              
              <!-- Checkbox para marcar o produto como encomenda de link (exibido na página de links) -->
              <div class="form-field checkbox-field">
                <input type="checkbox" id="is_encomenda" name="is_encomenda" value="1"
                       <?php echo ($current_action === 'edit' && isset($edit_product['is_encomenda']) && $edit_product['is_encomenda']) ? 'checked' : ''; ?>>
                <label for="is_encomenda">Marcar como Encomenda de Link (Exibir apenas na página de Links)</label>
              </div>
            </div>
            
            <!-- Botões de Ação do formulário -->
            <div class="form-actions">
              <button type="submit" class="btn-admin-primary">💾 Salvar Produto</button>
              <a href="painel.php?action=list" class="btn-admin-secondary">Cancelar</a>
            </div>
          </form>
        </div>
      <?php endif; ?>

      <!-- 4. TELA: LISTAGEM DE CATEGORIAS (READ) -->
      <?php if ($current_action === 'categories'): ?>
        <div class="painel-card">
          <div class="crud-header">
            <h1>Gerenciar Categorias</h1>
            <a href="painel.php?action=new_category" class="btn-admin-primary">➕ Nova Categoria</a>
          </div>
          
          <!-- Alertas de sucesso ou erro do processamento backend -->
          <?php if (!empty($alertMessage)): ?>
            <div class="alert-box <?php echo $alertClass; ?>">
              <?php echo ($alertClass === 'alert-success' ? '✓ ' : '⚠ ') . htmlspecialchars($alertMessage); ?>
            </div>
          <?php endif; ?>
          
          <!-- Tabela com listagem de categorias -->
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th style="width: 60px;">ID</th>
                  <th style="width: 80px;">Ícone</th>
                  <th>Nome</th>
                  <th style="width: 80px;">Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($admin_categories_list)): ?>
                  <?php foreach ($admin_categories_list as $cat): ?>
                    <tr>
                      <td><strong>#<?php echo $cat['id']; ?></strong></td>
                      <!-- Exibe o emoji/ícone da categoria com tamanho visual ampliado -->
                      <td style="font-size: 1.5rem; text-align: center;"><?php echo htmlspecialchars($cat['icone']); ?></td>
                      <td><strong><?php echo htmlspecialchars($cat['nome']); ?></strong></td>
                      <td>
                        <div style="display:flex; gap:0.4rem;">
                          <!-- Botões de ação com emojis e tooltips -->
                          <a href="painel.php?action=edit_category&id=<?php echo $cat['id']; ?>" class="btn-action btn-edit" title="Editar categoria">✏️</a>
                          <a href="painel.php?action=delete_category&id=<?php echo $cat['id']; ?>" 
                             class="btn-action btn-delete" 
                             title="Excluir categoria"
                             onclick="return confirm('Tem certeza que deseja excluir a categoria &quot;<?php echo addslashes($cat['nome']); ?>&quot;? Produtos vinculados podem ser afetados.');"
                             >🗑️</a>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="4" style="padding: 3rem; text-align: center; color: var(--text-muted);">Nenhuma categoria cadastrada no momento.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

      <!-- 5. TELA: FORMULÁRIO DE CADASTRO/EDIÇÃO DE CATEGORIA (CREATE & UPDATE) -->
      <?php if ($current_action === 'new_category' || $current_action === 'edit_category'): ?>
        <div class="painel-card">
          <h1><?php echo $current_action === 'new_category' ? 'Nova Categoria' : 'Editar Categoria #' . $edit_category['id']; ?></h1>
          <p>Preencha os campos abaixo para salvar a categoria no banco de dados.</p>
          
          <!-- Formulário de categoria -->
          <form action="painel.php?action=<?php echo $current_action; ?>" method="POST">
            <!-- Campo escondido para controlar a ação de inserção ou atualização -->
            <input type="hidden" name="action" value="<?php echo $current_action === 'new_category' ? 'insert_category' : 'update_category'; ?>">
            <?php if ($current_action === 'edit_category'): ?>
              <!-- ID da categoria para atualização -->
              <input type="hidden" name="cat_id" value="<?php echo $edit_category['id']; ?>">
            <?php endif; ?>
            
            <div class="product-form-grid">
              <!-- Nome da Categoria -->
              <div class="form-field">
                <label for="cat_nome">Nome da Categoria</label>
                <input type="text" id="cat_nome" name="cat_nome" required placeholder="Ex: Smartphones"
                       value="<?php echo $current_action === 'edit_category' ? htmlspecialchars($edit_category['nome']) : ''; ?>">
              </div>
              
              <!-- Ícone/Emoji da Categoria -->
              <div class="form-field">
                <label for="cat_icone">Ícone (Emoji)</label>
                <input type="text" id="cat_icone" name="cat_icone" required placeholder="Ex: 📱"
                       value="<?php echo $current_action === 'edit_category' ? htmlspecialchars($edit_category['icone']) : ''; ?>">
                <small style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.2rem; display: block;">
                  💡 Cole um emoji que represente visualmente a categoria. Ex: 📱 💻 🎧 📺 🎮 📷
                </small>
              </div>
            </div>
            
            <!-- Botões de Ação do formulário -->
            <div class="form-actions">
              <button type="submit" class="btn-admin-primary">💾 Salvar Categoria</button>
              <a href="painel.php?action=categories" class="btn-admin-secondary">Cancelar</a>
            </div>
          </form>
        </div>
      <?php endif; ?>

      <!-- 6. TELA: LISTAGEM DE LOJAS (READ) -->
      <?php if ($current_action === 'stores'): ?>
        <div class="painel-card">
          <div class="crud-header">
            <h1>Gerenciar Lojas Parceiras</h1>
            <a href="painel.php?action=new_store" class="btn-admin-primary">➕ Nova Loja</a>
          </div>
          
          <!-- Alertas de sucesso ou erro do processamento backend -->
          <?php if (!empty($alertMessage)): ?>
            <div class="alert-box <?php echo $alertClass; ?>">
              <?php echo ($alertClass === 'alert-success' ? '✓ ' : '⚠ ') . htmlspecialchars($alertMessage); ?>
            </div>
          <?php endif; ?>
          
          <!-- Tabela com listagem de lojas parceiras -->
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th style="width: 60px;">ID</th>
                  <th style="width: 60px;">Cor</th>
                  <th>Nome</th>
                  <th>Classe CSS</th>
                  <th>Badge Text</th>
                  <th style="width: 80px;">Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($admin_stores_list)): ?>
                  <?php foreach ($admin_stores_list as $st): ?>
                    <?php 
                      // Recupera a cor da loja para o preview visual na tabela
                      $st_cor = !empty($st['cor']) ? htmlspecialchars($st['cor']) : '#6366f1'; 
                    ?>
                    <tr>
                      <td><strong>#<?php echo $st['id']; ?></strong></td>
                      <!-- Preview visual da cor da loja em formato de círculo colorido -->
                      <td>
                        <div style="width: 24px; height: 24px; border-radius: 50%; background: <?php echo $st_cor; ?>; border: 2px solid var(--border); margin: 0 auto;" title="<?php echo $st_cor; ?>"></div>
                      </td>
                      <td><strong><?php echo htmlspecialchars($st['nome']); ?></strong></td>
                      <!-- Classe CSS usada para estilizar badges e botões -->
                      <td><code style="font-size: 0.8rem; background: var(--bg); padding: 0.2rem 0.5rem; border-radius: 4px;"><?php echo htmlspecialchars($st['css_class']); ?></code></td>
                      <!-- Texto exibido no selo/badge da loja na home -->
                      <td style="font-size: 0.82rem;"><?php echo htmlspecialchars($st['badge_text']); ?></td>
                      <td>
                        <div style="display:flex; gap:0.4rem;">
                          <!-- Botões de ação com emojis e tooltips -->
                          <a href="painel.php?action=edit_store&id=<?php echo $st['id']; ?>" class="btn-action btn-edit" title="Editar loja">✏️</a>
                          <a href="painel.php?action=delete_store&id=<?php echo $st['id']; ?>" 
                             class="btn-action btn-delete" 
                             title="Excluir loja"
                             onclick="return confirm('Tem certeza que deseja excluir a loja &quot;<?php echo addslashes($st['nome']); ?>&quot;? Produtos vinculados podem ser afetados.');"
                             >🗑️</a>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="6" style="padding: 3rem; text-align: center; color: var(--text-muted);">Nenhuma loja cadastrada no momento.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

      <!-- 7. TELA: FORMULÁRIO DE CADASTRO/EDIÇÃO DE LOJA (CREATE & UPDATE) -->
      <?php if ($current_action === 'new_store' || $current_action === 'edit_store'): ?>
        <div class="painel-card">
          <h1><?php echo $current_action === 'new_store' ? 'Nova Loja Parceira' : 'Editar Loja #' . $edit_store['id']; ?></h1>
          <p>Preencha os campos abaixo para salvar a loja parceira no banco de dados.</p>
          
          <!-- Formulário de loja -->
          <form action="painel.php?action=<?php echo $current_action; ?>" method="POST">
            <!-- Campo escondido para controlar a ação de inserção ou atualização -->
            <input type="hidden" name="action" value="<?php echo $current_action === 'new_store' ? 'insert_store' : 'update_store'; ?>">
            <?php if ($current_action === 'edit_store'): ?>
              <!-- ID da loja para atualização -->
              <input type="hidden" name="store_edit_id" value="<?php echo $edit_store['id']; ?>">
            <?php endif; ?>
            
            <div class="product-form-grid">
              <!-- Nome da Loja -->
              <div class="form-field">
                <label for="store_nome">Nome da Loja</label>
                <input type="text" id="store_nome" name="store_nome" required placeholder="Ex: Amazon Brasil"
                       value="<?php echo $current_action === 'edit_store' ? htmlspecialchars($edit_store['nome']) : ''; ?>">
              </div>
              
              <!-- Classe CSS da Loja -->
              <div class="form-field">
                <label for="store_css_class">Classe CSS</label>
                <input type="text" id="store_css_class" name="store_css_class" required placeholder="Ex: badge-amazon"
                       value="<?php echo $current_action === 'edit_store' ? htmlspecialchars($edit_store['css_class']) : ''; ?>">
                <small style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.2rem; display: block;">
                  💡 Usado para estilizar os badges dos produtos. Ex: <strong>badge-amazon</strong>, <strong>badge-ml</strong>
                </small>
              </div>
              
              <!-- Badge Text da Loja -->
              <div class="form-field">
                <label for="store_badge_text">Texto do Selo (Badge)</label>
                <input type="text" id="store_badge_text" name="store_badge_text" required placeholder="Ex: ✓ Parceiro Oficial Amazon"
                       value="<?php echo $current_action === 'edit_store' ? htmlspecialchars($edit_store['badge_text']) : ''; ?>">
                <small style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.2rem; display: block;">
                  💡 Texto exibido no selo de afiliação na página inicial. Ex: "✓ Parceiro Oficial Amazon"
                </small>
              </div>
              
              <!-- Cor da Loja (Color Picker) -->
              <div class="form-field">
                <label for="store_cor">Cor da Loja</label>
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                  <!-- Input de cor (color picker nativo do navegador) -->
                  <input type="color" id="store_cor" name="store_cor" 
                         value="<?php echo $current_action === 'edit_store' ? htmlspecialchars($edit_store['cor'] ?? '#6366f1') : '#FF9900'; ?>"
                         style="width: 50px; height: 40px; border: 1.5px solid var(--border); border-radius: var(--radius-sm); cursor: pointer; padding: 2px;">
                  <!-- Exibe o código hex da cor selecionada -->
                  <span id="store_cor_hex" style="font-family: monospace; font-size: 0.9rem; color: var(--text-muted);">
                    <?php echo $current_action === 'edit_store' ? htmlspecialchars($edit_store['cor'] ?? '#6366f1') : '#FF9900'; ?>
                  </span>
                </div>
                <small style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.2rem; display: block;">
                  💡 Cor usada nos selos e botões da loja. Selecione ou digite o código hexadecimal.
                </small>
              </div>
            </div>
            
            <!-- Botões de Ação do formulário -->
            <div class="form-actions">
              <button type="submit" class="btn-admin-primary">💾 Salvar Loja</button>
              <a href="painel.php?action=stores" class="btn-admin-secondary">Cancelar</a>
            </div>
          </form>
        </div>
      <?php endif; ?>

      <!-- 8. TELA: LISTAGEM DE PEDIDOS DE LINK (READ & DELETE) -->
      <?php if ($current_action === 'requests'): ?>
        <div class="painel-card">
          <div class="crud-header">
            <div>
              <h1>Pedidos de Links</h1>
              <p style="color: var(--text-light); margin-top: 0.25rem;">Gerencie as solicitações de links de descontos feitas por clientes.</p>
            </div>
          </div>
          
          <!-- Alertas de sucesso ou erro do processamento backend -->
          <?php if (!empty($alertMessage)): ?>
            <div class="alert-box <?php echo $alertClass; ?>">
              <?php echo ($alertClass === 'alert-success' ? '✓ ' : '⚠ ') . htmlspecialchars($alertMessage); ?>
            </div>
          <?php endif; ?>
          
          <!-- Tabela com listagem estruturada de solicitações de links -->
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th style="width: 60px;">ID</th>
                  <th>Cliente</th>
                  <th>WhatsApp</th>
                  <th>Link do Produto</th>
                  <th style="width: 140px;">Data do Envio</th>
                  <th style="width: 100px; text-align: center;">Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($requests_list)): ?>
                  <?php foreach ($requests_list as $req): ?>
                    <tr>
                      <td><strong>#<?php echo $req['id']; ?></strong></td>
                      <td><strong><?php echo htmlspecialchars($req['nome']); ?></strong></td>
                      <td><?php echo htmlspecialchars($req['whatsapp']); ?></td>
                      <td style="max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <a href="<?php echo htmlspecialchars($req['link']); ?>" target="_blank" title="<?php echo htmlspecialchars($req['link']); ?>" style="color: var(--brand); font-weight: 600;">
                          <?php echo htmlspecialchars($req['link']); ?>
                        </a>
                      </td>
                      <td><?php echo date('d/m/Y H:i', strtotime($req['created_at'])); ?></td>
                      <td>
                        <div style="display:flex; gap:0.4rem; justify-content: center;">
                          <!-- Atalho rápido para cadastrar o produto (encomenda finalizada) pré-preenchendo os dados da solicitação -->
                          <a href="painel.php?action=new&pre_title=<?php echo urlencode('Encomenda: ' . $req['nome']); ?>&pre_link=<?php echo urlencode($req['link']); ?>" 
                             class="btn-action btn-edit" 
                             style="background: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0;" 
                             title="Cadastrar link de afiliado pronto">
                            🛒
                          </a>
                          <a href="painel.php?action=delete_request&id=<?php echo $req['id']; ?>" 
                             class="btn-action btn-delete" 
                             title="Excluir pedido"
                             onclick="return confirm('Tem certeza que deseja excluir o pedido de &quot;<?php echo addslashes($req['nome']); ?>&quot;?');">
                             🗑️
                          </a>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="6" class="text-center text-muted" style="padding: 3rem; text-align: center;">Nenhum pedido de link registrado no momento.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

      <!-- 9. TELA: LISTAGEM E CADASTRO DE LEADS DA NEWSLETTER (CRUD) -->
      <?php if ($current_action === 'newsletter_leads'): ?>
        <div class="painel-card">
          <div class="crud-header">
            <div>
              <h1>Contatos da Newsletter</h1>
              <p style="color: var(--text-light); margin-top: 0.25rem;">Gerencie os números de WhatsApp cadastrados para receber alertas de ofertas.</p>
            </div>
          </div>
          
          <!-- Alertas de sucesso ou erro do processamento backend -->
          <?php if (!empty($alertMessage)): ?>
            <div class="alert-box <?php echo $alertClass; ?>">
              <?php echo ($alertClass === 'alert-success' ? '✓ ' : '⚠ ') . htmlspecialchars($alertMessage); ?>
            </div>
          <?php endif; ?>
          
          <!-- Formulário rápido para cadastro manual de novos contatos na newsletter -->
          <div style="background: var(--bg); border: 1.5px solid var(--border); padding: 1.25rem 1.75rem; border-radius: var(--radius-sm); margin-bottom: 2rem;">
            <h3 style="margin-top: 0; margin-bottom: 0.75rem; font-size: 0.95rem; color: var(--text); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Adicionar Novo Contato</h3>
            <form action="painel.php?action=newsletter_leads" method="POST" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
              <input type="hidden" name="action" value="insert_newsletter">
              <div class="form-field" style="flex: 1; min-width: 220px; gap: 0.35rem;">
                <label for="whatsapp" style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted);">WhatsApp (com DDD)</label>
                <input type="tel" id="whatsapp" name="whatsapp" required placeholder="(XX) XXXXX-XXXX">
              </div>
              <button type="submit" class="btn-admin-primary" style="padding: 0.78rem 1.5rem; font-size: 0.9rem;">💾 Cadastrar</button>
            </form>
          </div>
          
          <!-- Tabela com listagem estruturada de leads de WhatsApp -->
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th style="width: 60px;">ID</th>
                  <th>WhatsApp</th>
                  <th style="width: 200px;">Data de Cadastro</th>
                  <th style="width: 80px; text-align: center;">Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($newsletter_leads_list)): ?>
                  <?php foreach ($newsletter_leads_list as $lead): ?>
                    <tr>
                      <td><strong>#<?php echo $lead['id']; ?></strong></td>
                      <td><strong><?php echo htmlspecialchars($lead['whatsapp']); ?></strong></td>
                      <td><?php echo date('d/m/Y H:i', strtotime($lead['created_at'])); ?></td>
                      <td>
                        <div style="display:flex; justify-content: center;">
                          <a href="painel.php?action=delete_newsletter&id=<?php echo $lead['id']; ?>" 
                             class="btn-action btn-delete" 
                             title="Remover contato"
                             onclick="return confirm('Tem certeza que deseja remover este contato da newsletter?');">
                             🗑️
                          </a>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="4" class="text-center text-muted" style="padding: 3rem; text-align: center;">Nenhum número cadastrado na newsletter.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

    </main>

  </div>

  <?php 
    // Inclui o rodapé compartilhado da página (agora dinâmico com PHP)
    include 'footer.php'; 
  ?>
  
  <!-- SDK do Supabase JS para efetuar o logout no client-side também -->
  <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
  <script>
    // Recupera configurações do PHP para inicializar o Supabase no painel
    const config = <?php echo exportSupabaseConfig(); ?>;
    // Inicializa o cliente do Supabase usando uma variável de nome diferente da biblioteca global
    const supabaseClient = supabase.createClient(config.url, config.key);

    // Função que lida com o logout e destrói as sessões local e do Supabase
    async function handleLogout(e) {
      e.preventDefault();
      
      try {
        // Envia requisição para limpar a sessão PHP no backend
        const response = await fetch('auth_session.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({ action: 'logout' })
        });
        
        const result = await response.json();
        
        // Efetua o sign out no Supabase Auth no client-side usando o cliente instanciado
        await supabaseClient.auth.signOut();
        
        // Redireciona o usuário para a página inicial pública
        window.location.href = 'index.php';
        
      } catch (err) {
        console.error('Erro ao efetuar logout:', err);
        // Em caso de falha, força o redirecionamento
        window.location.href = 'index.php';
      }
    }

    // Atualiza o texto hex em tempo real quando o color picker for alterado
    const corInput = document.getElementById('store_cor');
    const corHex = document.getElementById('store_cor_hex');
    if (corInput && corHex) {
      corInput.addEventListener('input', function() {
        // Exibe o valor hexadecimal selecionado no span ao lado do seletor de cor
        corHex.textContent = this.value.toUpperCase();
      });
    }
  </script>
  <script src="js/main.js"></script>
</body>
</html>
