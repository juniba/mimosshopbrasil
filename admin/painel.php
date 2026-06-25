<?php
/*
  painel.php: Página administrativa (Painel de Controle) para gerenciamento completo (CRUD) de produtos.
  Fornece interfaces para visão geral de métricas, listagem tabular, formulários de criação/edição 
  e exclusão direta via chamadas REST autenticadas com a API do Supabase.
  Protegida por barreira de autenticação de sessão PHP no backend.
*/
require_once __DIR__ . '/../config.php';
// Carrega o helper do Cloudinary para lidar com conversão WebP e uploads de imagens
require_once __DIR__ . '/../cloudinary_helper.php';

// Proteção do Painel: Se não houver sessão ativa de admin, barra o acesso e redireciona para mcd.php (antigo login.php)
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: mcd.php'); // Redireciona para o login (dentro da mesma pasta admin/)
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
            
            // Comentário explicativo: Captura e higieniza os novos campos para a página individual do produto
            $imagens_adicionais = !empty($_POST['imagens_adicionais']) ? trim($_POST['imagens_adicionais']) : null;
            $vantagens = !empty($_POST['vantagens']) ? trim($_POST['vantagens']) : null;
            $desvantagens = !empty($_POST['desvantagens']) ? trim($_POST['desvantagens']) : null;
            $para_quem_indicado = !empty($_POST['para_quem_indicado']) ? trim($_POST['para_quem_indicado']) : null;
            $comparacao = !empty($_POST['comparacao']) ? trim($_POST['comparacao']) : null;
            $perguntas_frequentes = !empty($_POST['perguntas_frequentes']) ? trim($_POST['perguntas_frequentes']) : null;

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
                'avaliacoes' => $avaliacoes,
                // Comentário explicativo: Inclui as novas colunas destinadas à página de detalhes do produto
                'imagens_adicionais' => $imagens_adicionais,
                'vantagens' => $vantagens,
                'desvantagens' => $desvantagens,
                'para_quem_indicado' => $para_quem_indicado,
                'comparacao' => $comparacao,
                'perguntas_frequentes' => $perguntas_frequentes
            ];

            
            if ($action === 'insert') {
                // Insere um novo registro na tabela produtos do Supabase usando a autenticação do admin
                $res = supabase_admin_request('POST', '/rest/v1/produtos', $payload, true);
                if ($res !== false) {
                    // Carrega o helper de WhatsApp e dispara os alertas para todos os cadastrados na newsletter
                    require_once __DIR__ . '/../whatsapp_helper.php';
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
        
        // === AUXILIAR: GERAÇÃO DE SLUG E CRUD DE ARTIGOS DO BLOG (INSERT / UPDATE) ===
        // Função auxiliar para higienizar e transformar o título em um slug amigável para URLs
        if (!function_exists('generate_slug')) {
            function generate_slug($text) {
                // Substitui caracteres acentuados ou especiais por hífens
                $text = preg_replace('~[^\pL\d]+~u', '-', $text);
                // Translitera caracteres não-ASCII
                $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
                // Remove caracteres indesejados
                $text = preg_replace('~[^-\w]+~', '', $text);
                $text = trim($text, '-');
                // Remove hífens duplicados
                $text = preg_replace('~-+~', '-', $text);
                $text = strtolower($text);
                return empty($text) ? 'n-a' : $text;
            }
        }

        if ($action === 'insert_article' || $action === 'update_article') {
            // Sanitiza os campos vindos do formulário
            $titulo = trim($_POST['titulo']);
            $resumo = trim($_POST['resumo']);
            $conteudo = trim($_POST['conteudo']);
            $publicado = isset($_POST['publicado']) ? true : false;
            
            // Define o slug a partir do título (ou slug manual se fornecido)
            $slug = !empty($_POST['slug']) ? generate_slug($_POST['slug']) : generate_slug($titulo);
            
            // Processa o upload da imagem do banner usando o helper do Cloudinary
            $imagem_url = handle_image_upload_or_url($_FILES['imagem_file'] ?? null, $_POST['imagem_url'] ?? '');
            
            // Payload a ser enviado para o Supabase
            $payload = [
                'titulo' => $titulo,
                'slug' => $slug,
                'resumo' => $resumo,
                'conteudo' => $conteudo,
                'imagem_url' => $imagem_url,
                'publicado' => $publicado
            ];
            
            if ($action === 'insert_article') {
                // Insere o novo artigo no banco de dados do Supabase
                $res = supabase_admin_request('POST', '/rest/v1/artigos', $payload, true);
                if ($res !== false) {
                    header('Location: painel.php?action=articles&success=art_inserted');
                    exit;
                } else {
                    $alertMessage = "Erro ao cadastrar o artigo do blog no Supabase.";
                    $alertClass = "alert-error";
                }
            } elseif ($action === 'update_article') {
                // Atualiza o artigo existente no Supabase usando ID do registro
                $id = intval($_POST['id']);
                $res = supabase_admin_request('PATCH', '/rest/v1/artigos?id=eq.' . $id, $payload, true);
                if ($res !== false) {
                    header('Location: painel.php?action=articles&success=art_updated');
                    exit;
                } else {
                    $alertMessage = "Erro ao atualizar o artigo do blog no Supabase.";
                    $alertClass = "alert-error";
                }
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

// 2h. Ação para sincronizar atualizações com o GitHub localmente (GET)
if (isset($_GET['action']) && $_GET['action'] === 'git_sync') {
    // Define a mensagem do commit com a data e hora atual
    $commit_msg = 'atualizacao automatica painel - ' . date('d/m/Y H:i:s');
    
    // Armazena o diretório de trabalho atual do PHP
    $original_dir = getcwd();
    // Altera o diretório de trabalho para a raiz do projeto (uma pasta acima do diretório admin) para que o git atue no repositório inteiro
    chdir(__DIR__ . '/../');
    
    // Executa o comando para adicionar todas as alterações locais ao git (incluindo novos arquivos e modificações na raiz ou subpastas)
    $output_add = shell_exec('git add . 2>&1');
    // Cria um novo commit com as alterações adicionadas usando a mensagem definida
    $output_commit = shell_exec('git commit -m ' . escapeshellarg($commit_msg) . ' 2>&1');
    // Envia o commit local para o repositório remoto na branch principal (main)
    $output_push = shell_exec('git push origin main 2>&1');
    
    // Restaura o diretório de trabalho original do PHP para evitar impactos em outras operações do script
    chdir($original_dir);
    
    // Verifica se não havia nada para ser commitado (repositório limpo)
    if (strpos($output_commit, 'nothing to commit') !== false || strpos($output_commit, 'nada para submeter') !== false) {
        $alertMessage = "Sem novas alterações locais para enviar ao GitHub.";
        $alertClass = "alert-info";
    // Verifica se o push foi realizado com sucesso ou se já estava tudo atualizado
    } elseif (strpos($output_push, 'Everything up-to-date') !== false || strpos($output_push, 'tudo atualizado') !== false || strpos($output_push, 'To ') !== false || strpos($output_push, 'main -> main') !== false) {
        $alertMessage = "Projeto atualizado e enviado para o GitHub com sucesso!";
        $alertClass = "alert-success";
    // Lida com erros ou retornos informativos inesperados do comando push
    } else {
        $alertMessage = "Atualização enviada para o GitHub. Resposta do Git: " . nl2br(htmlspecialchars($output_push ?? ''));
        $alertClass = "alert-success";
    }
}

// 2g. Ação de Exclusão de Artigos do Blog (GET)
if (isset($_GET['action']) && $_GET['action'] === 'delete_article' && isset($_GET['id'])) {
    $art_del_id = intval($_GET['id']);
    // Remove o artigo no Supabase usando a autenticação do admin
    $res = supabase_admin_request('DELETE', '/rest/v1/artigos?id=eq.' . $art_del_id, null, true);
    if ($res !== false) {
        header('Location: painel.php?action=articles&success=art_deleted');
        exit;
    } else {
        $alertMessage = "Erro ao excluir o artigo do blog no Supabase.";
        $alertClass = "alert-error";
    }
}

// 2f. Ação para marcar todos os registros como lidos (GET)
// Esta rota atualiza o campo lido para true na tabela correspondente do Supabase
if (isset($_GET['action']) && $_GET['action'] === 'mark_all_read' && isset($_GET['type'])) {
    $type = $_GET['type'];
    if ($type === 'requests') {
        // Envia requisição PATCH para pedidosLink marcando registros não lidos como lidos
        $res = supabase_admin_request('PATCH', '/rest/v1/pedidosLink?lido=eq.false', ['lido' => true], true);
        if ($res !== false) {
            header('Location: painel.php?action=requests&success=req_marked_read');
            exit;
        } else {
            $alertMessage = "Erro ao marcar os pedidos de link como lidos.";
            $alertClass = "alert-error";
        }
    } elseif ($type === 'newsletter') {
        // Envia requisição PATCH para newsletter marcando registros não lidos como lidos
        $res = supabase_admin_request('PATCH', '/rest/v1/newsletter?lido=eq.false', ['lido' => true], true);
        if ($res !== false) {
            header('Location: painel.php?action=newsletter_leads&success=news_marked_read');
            exit;
        } else {
            $alertMessage = "Erro ao marcar os leads da newsletter como lidos.";
            $alertClass = "alert-error";
        }
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
    } elseif ($success_type === 'req_marked_read') {
        $alertMessage = "Todas as solicitações de link foram marcadas como lidas!";
        $alertClass = "alert-success";
    }
    // Alertas de Newsletter
    elseif ($success_type === 'news_inserted') {
        $alertMessage = "Número de WhatsApp adicionado à newsletter com sucesso!";
        $alertClass = "alert-success";
    } elseif ($success_type === 'news_deleted') {
        $alertMessage = "WhatsApp removido da newsletter com sucesso!";
        $alertClass = "alert-success";
    } elseif ($success_type === 'news_marked_read') {
        $alertMessage = "Todos os contatos da newsletter foram marcados como lidos!";
        $alertClass = "alert-success";
    }
    // Alertas de Artigos do Blog
    elseif ($success_type === 'art_inserted') {
        $alertMessage = "Artigo do blog cadastrado com sucesso!";
        $alertClass = "alert-success";
    } elseif ($success_type === 'art_updated') {
        $alertMessage = "Artigo do blog atualizado com sucesso!";
        $alertClass = "alert-success";
    } elseif ($success_type === 'art_deleted') {
        $alertMessage = "Artigo do blog removido com sucesso!";
        $alertClass = "alert-success";
    }
}

// CARREGAMENTO DOS DADOS PARA AS TELAS

// Define qual tela administrativa será renderizada na página
$current_action = $_GET['action'] ?? 'dashboard';

// Busca contagens e listas para os badges de notificação e cards (filtrando apenas pelos não lidos [lido=eq.false] para exibir nos badges da sidebar)
$newsletter_count = 0;
$requests_count = 0;
$newsletter_total = 0;
$requests_total = 0;

// Comentário explicativo: Busca leads não lidos da newsletter
$all_newsletter_count_res = supabase_admin_request('GET', '/rest/v1/newsletter?select=id&lido=eq.false', null, true);
if (is_array($all_newsletter_count_res)) {
    $newsletter_count = count($all_newsletter_count_res);
}

// Comentário explicativo: Busca o total histórico de leads da newsletter
$total_newsletter_res = supabase_admin_request('GET', '/rest/v1/newsletter?select=id', null, true);
if (is_array($total_newsletter_res)) {
    $newsletter_total = count($total_newsletter_res);
}

// Comentário explicativo: Busca pedidos de link não lidos
$all_requests_count_res = supabase_admin_request('GET', '/rest/v1/pedidosLink?select=id&lido=eq.false', null, true);
if (is_array($all_requests_count_res)) {
    $requests_count = count($all_requests_count_res);
}

// Comentário explicativo: Busca o total histórico de pedidos de link
$total_requests_res = supabase_admin_request('GET', '/rest/v1/pedidosLink?select=id', null, true);
if (is_array($total_requests_res)) {
    $requests_total = count($total_requests_res);
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

// Busca estatísticas gerais na dashboard (produtos, categorias, lojas e artigos do blog)
$total_products = 0;
$total_categories = 0;
$total_stores = 0;
$total_articles = 0;
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
    
    // Conta todos os artigos do blog cadastrados no Supabase
    $all_articles = supabase_admin_request('GET', '/rest/v1/artigos?select=id');
    if (is_array($all_articles)) {
        $total_articles = count($all_articles);
    }
}

// Busca lista de artigos do blog do Supabase caso a tela de listagem de artigos esteja ativa
$articles_list = [];
if ($current_action === 'articles') {
    $articles_list = supabase_admin_request('GET', '/rest/v1/artigos?select=*&order=created_at.desc', null, true);
}

// Busca os dados do artigo específico no Supabase para a tela de edição
$edit_article = null;
if ($current_action === 'edit_article' && isset($_GET['id'])) {
    $edit_art_id = intval($_GET['id']);
    $res = supabase_admin_request('GET', '/rest/v1/artigos?id=eq.' . $edit_art_id);
    if (!empty($res)) {
        $edit_article = $res[0];
    } else {
        $alertMessage = "Artigo do blog não localizado para edição.";
        $alertClass = "alert-error";
        $current_action = 'articles';
        // Recarrega a listagem de fallback
        $articles_list = supabase_admin_request('GET', '/rest/v1/artigos?select=*&order=created_at.desc', null, true);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Título do painel atualizado para Mimos Shop Brasil -->
  <title>Painel de Controle – Mimos Shop Brasil</title>
  <!-- Preconnect e fontes do Google Fonts carregadas via head para otimização de renderização -->
  <!-- Comentário de regra: Este bloco carrega a fonte Inter de forma performática -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  
  <!-- Preload de recursos críticos de imagem para melhorar o LCP/CLS -->
  <link rel="preload" href="../img/logo_mcdmarketprime.png" as="image">

  <!-- Caminho relativo aos módulos CSS na raiz do projeto (subindo um nível da pasta admin/) -->
  <link rel="stylesheet" href="../css/base.min.css">
  <link rel="stylesheet" href="../css/components.min.css">
  <link rel="stylesheet" href="../css/admin.min.css">
  
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
    
    /* Comentário explicativo: Força o alinhamento à esquerda de todos os links de navegação da sidebar */
    .sidebar-nav a {
      padding: 0.6rem 0.75rem !important;
      font-size: 0.85rem !important;
      gap: 0.5rem !important;
      display: flex !important;
      align-items: center !important;
      justify-content: flex-start !important;
      text-align: left !important;
      width: 100% !important;
    }

    /* Comentário explicativo: Mantém apenas o link de Sair (Logout) centralizado com margens visuais */
    .sidebar-nav a[onclick] {
      justify-content: center !important;
      text-align: center !important;
      margin-top: 1rem !important;
      border-top: 1px solid var(--border) !important;
      padding-top: 0.75rem !important;
    }

    /* Comentário explicativo: Define cores premium individuais para cada ícone do menu lateral */
    .sidebar-nav a[href*="action=dashboard"] i, 
    .sidebar-nav a[href*="action=dashboard"] svg {
      color: #8B5CF6 !important; /* Roxo */
    }
    
    .sidebar-nav a[href*="action=list"] i, 
    .sidebar-nav a[href*="action=list"] svg {
      color: #F59E0B !important; /* Ouro/Laranja */
    }
    
    .sidebar-nav a[href*="action=new"] i, 
    .sidebar-nav a[href*="action=new"] svg {
      color: #10B981 !important; /* Verde */
    }
    
    .sidebar-nav a[href*="action=categories"] i, 
    .sidebar-nav a[href*="action=categories"] svg {
      color: #06B6D4 !important; /* Ciano */
    }
    
    .sidebar-nav a[href*="action=stores"] i, 
    .sidebar-nav a[href*="action=stores"] svg {
      color: #0D9488 !important; /* Teal */
    }
    
    .sidebar-nav a[href*="action=requests"] i, 
    .sidebar-nav a[href*="action=requests"] svg {
      color: #EF4444 !important; /* Vermelho */
    }
    
    .sidebar-nav a[href*="action=newsletter_leads"] i, 
    .sidebar-nav a[href*="action=newsletter_leads"] svg {
      color: #2563EB !important; /* Azul */
    }
    
    .sidebar-nav a[href*="action=articles"] i, 
    .sidebar-nav a[href*="action=articles"] svg {
      color: #10B981 !important; /* Verde-Esmeralda */
    }
    
    .sidebar-nav a[href*="action=git_sync"] i, 
    .sidebar-nav a[href*="action=git_sync"] svg {
      color: #4B5563 !important; /* Cinza Escuro */
    }
    
    .sidebar-nav a[onclick] i, 
    .sidebar-nav a[onclick] svg {
      color: #9CA3AF !important; /* Cinza Claro para o botão Sair */
    }

    /* Comentário explicativo: Garante que os ícones fiquem brancos para alto contraste quando o link estiver ativo */
    .sidebar-nav a.active i, 
    .sidebar-nav a.active svg {
      color: var(--white) !important;
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

    /* Comentário explicativo: CSS Customizado Premium Compacto para os Cards de Métricas da Dashboard */
    .dashboard-summary {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) !important;
      gap: 1rem !important;
      margin-top: 1rem !important;
    }
    
    .stat-card-premium {
      position: relative;
      background: var(--accent-bg) !important;
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      padding: 1rem;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
      overflow: hidden;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
    }
    
    .stat-card-premium:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 16px -5px rgba(0, 0, 0, 0.08), 0 6px 6px -5px rgba(0, 0, 0, 0.04);
      border-color: var(--accent-color) !important;
    }

    .stat-card-premium::before {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 3px;
      background: var(--accent-color);
    }

    .stat-card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 0.5rem;
    }

    .stat-card-title {
      font-size: 0.72rem;
      font-weight: 700;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin: 0;
    }

    .stat-card-icon-wrap {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 30px;
      height: 30px;
      border-radius: 6px;
      background: var(--white);
      color: var(--accent-color);
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
    }

    
    .stat-card-icon-wrap i, 
    .stat-card-icon-wrap svg {
      width: 14px !important;
      height: 14px !important;
    }

    .stat-card-body {
      margin-bottom: 0.5rem;
      text-align: left;
    }

    .stat-card-value {
      font-size: 1.6rem;
      font-weight: 900;
      color: var(--text);
      line-height: 1;
    }

    .stat-card-link {
      display: flex;
      align-items: center;
      gap: 0.25rem;
      font-size: 0.75rem;
      color: var(--accent-color) !important;
      font-weight: 700;
      text-decoration: none;
      transition: color 0.2s, padding-left 0.2s;
      margin-top: auto;
      text-align: left;
    }
    
    .stat-card-link:hover {
      padding-left: 3px;
      color: var(--text) !important;
    }
  </style>


</head>
<body>
  <?php 
    // Define o prefixo do caminho relativo para subir um nível a partir do diretório /admin/
    $path_prefix = '../';
    // Inclui o cabeçalho dinâmico compartilhado localizado na raiz do projeto
    include __DIR__ . '/../header.php'; 
  ?>

  <!-- Container principal do Painel (Layout de Duas Colunas) -->
  <div class="painel-container">
    
    <!-- Sidebar (Menu Lateral de Ações Administrativas Dinâmico) -->
    <aside class="painel-sidebar">
      <nav class="sidebar-nav">
        <!-- Links de navegação interna com ativação dinâmica com base no parâmetro action -->
        <a href="painel.php?action=dashboard" class="<?php echo $current_action === 'dashboard' ? 'active' : ''; ?>"><i data-lucide="bar-chart-2" style="width: 16px; height: 16px; display: inline-block; vertical-align: middle; margin-right: 4px;"></i> Visão Geral</a>
        <a href="painel.php?action=list" class="<?php echo ($current_action === 'list' || $current_action === 'edit') ? 'active' : ''; ?>"><i data-lucide="package" style="width: 16px; height: 16px; display: inline-block; vertical-align: middle; margin-right: 4px;"></i> Listar Produtos</a>
        <a href="painel.php?action=new" class="<?php echo $current_action === 'new' ? 'active' : ''; ?>"><i data-lucide="plus-circle" style="width: 16px; height: 16px; display: inline-block; vertical-align: middle; margin-right: 4px;"></i> Adicionar Produto</a>
        <!-- Links de gerenciamento de Categorias e Lojas com ativação dinâmica -->
        <a href="painel.php?action=categories" class="<?php echo in_array($current_action, ['categories', 'new_category', 'edit_category']) ? 'active' : ''; ?>"><i data-lucide="tag" style="width: 16px; height: 16px; display: inline-block; vertical-align: middle; margin-right: 4px;"></i> Categorias</a>
        <a href="painel.php?action=stores" class="<?php echo in_array($current_action, ['stores', 'new_store', 'edit_store']) ? 'active' : ''; ?>"><i data-lucide="store" style="width: 16px; height: 16px; display: inline-block; vertical-align: middle; margin-right: 4px;"></i> Lojas</a>
        
        <!-- Link de gerenciamento de Pedidos de Link com badge de contagem dinâmica (notificação de novidades) -->
        <a href="painel.php?action=requests" class="<?php echo $current_action === 'requests' ? 'active' : ''; ?>">
          <i data-lucide="mail" style="width: 16px; height: 16px; display: inline-block; vertical-align: middle; margin-right: 4px;"></i> Pedidos
          <?php if ($requests_count > 0): ?>
            <span class="sidebar-badge"><?php echo $requests_count; ?></span>
          <?php endif; ?>
        </a>
        
        <!-- Link de gerenciamento de Leads da Newsletter com badge de contagem dinâmica -->
        <a href="painel.php?action=newsletter_leads" class="<?php echo $current_action === 'newsletter_leads' ? 'active' : ''; ?>">
          <i data-lucide="bell" style="width: 16px; height: 16px; display: inline-block; vertical-align: middle; margin-right: 4px;"></i> Newsletter
          <?php if ($newsletter_count > 0): ?>
            <span class="sidebar-badge-newsletter"><?php echo $newsletter_count; ?></span>
          <?php endif; ?>
        </a>

        <!-- Link para gerenciamento de Artigos do Blog -->
        <a href="painel.php?action=articles" class="<?php echo in_array($current_action, ['articles', 'new_article', 'edit_article']) ? 'active' : ''; ?>">
          <i data-lucide="file-text" style="width: 16px; height: 16px; display: inline-block; vertical-align: middle; margin-right: 4px;"></i> Artigos do Blog
        </a>
        
        <!-- Link para sincronização direta com o GitHub através de comandos locais executados via shell_exec -->
        <a href="painel.php?action=git_sync" class="<?php echo $current_action === 'git_sync' ? 'active' : ''; ?>" style="color: #24292e; font-weight: 600;">
          <svg height="16" width="16" viewBox="0 0 16 16" version="1.1" aria-hidden="true" style="fill: currentColor; vertical-align: text-bottom; margin-right: 2px;"><path fill-rule="evenodd" d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"></path></svg>
          Sincronizar GitHub
        </a>
        
        <a href="#" onclick="handleLogout(event)"><i data-lucide="log-out" style="width: 16px; height: 16px; display: inline-block; vertical-align: middle; margin-right: 4px;"></i> Sair</a>
      </nav>
    </aside>

    <!-- Área de Conteúdo Principal (Organizada com base no roteador action) -->
    <main class="painel-content">
      
      <!-- 1.1 TELA: SINCRONIZAR GITHUB -->
      <?php if ($current_action === 'git_sync'): ?>
        <div class="painel-card">
          <h1>Sincronizar com o GitHub</h1>
          <p>Execute os comandos locais para salvar as alterações do projeto e enviar diretamente para o repositório remoto.</p>
          
          <?php if (!empty($alertMessage)): ?>
            <div class="alert-box <?php echo $alertClass; ?>">
              <?php echo htmlspecialchars($alertMessage); ?>
            </div>
          <?php endif; ?>
          
          <div style="background: #1e1e1e; color: #39ff14; padding: 1.5rem; border-radius: 6px; font-family: monospace; line-height: 1.6; margin-top: 1rem; box-shadow: inset 0 0 10px rgba(0,0,0,0.8); overflow-x: auto;">
            <div style="color: #888; margin-bottom: 0.5rem;">// Console Git Local</div>
            <div>$ git add .</div>
            <div style="color: #aaa; margin-left: 10px;"><?php echo nl2br(htmlspecialchars($output_add ?? 'Sucesso (Arquivos adicionados para o commit)')); ?></div>
            
            <div style="margin-top: 1rem;">$ git commit -m "<?php echo htmlspecialchars($commit_msg ?? 'atualizacao automatica painel'); ?>"</div>
            <div style="color: #aaa; margin-left: 10px;"><?php echo nl2br(htmlspecialchars($output_commit ?? 'Nenhum commit gerado ainda')); ?></div>
            
            <div style="margin-top: 1rem;">$ git push origin main</div>
            <div style="color: #aaa; margin-left: 10px;"><?php echo nl2br(htmlspecialchars($output_push ?? 'Nenhum envio efetuado ainda')); ?></div>
          </div>
          
          <div style="margin-top: 1.5rem;">
            <a href="painel.php?action=git_sync" class="btn-admin-primary" style="background: #24292e;">🔄 Sincronizar Novamente</a>
            <a href="painel.php?action=dashboard" class="btn-admin-secondary">Voltar ao Painel</a>
          </div>
        </div>
      <?php endif; ?>

      <!-- 1. TELA: DASHBOARD / VISÃO GERAL -->
      <?php if ($current_action === 'dashboard'): ?>
        <div class="painel-card">
          <h1>Painel de Controle</h1>
          <p>Bem-vindo, <strong><?php echo $adminEmail; ?></strong>! Use a barra lateral para gerenciar as ofertas e categorias do site.</p>
          
          <!-- Blocos com estatísticas simplificadas da vitrine de produtos obtidas do Supabase -->
          <div class="dashboard-summary">
            <!-- 1. Total de Produtos -->
            <div class="stat-card-premium" style="--accent-color: #8B5CF6; --accent-bg: #F5F3FF;">
              <div class="stat-card-header">
                <span class="stat-card-title">Total de Produtos</span>
                <div class="stat-card-icon-wrap">
                  <i data-lucide="package"></i>
                </div>
              </div>
              <div class="stat-card-body">
                <div class="stat-card-value"><?php echo $total_products; ?></div>
              </div>
              <a href="painel.php?action=list" class="stat-card-link">Ver Produtos →</a>
            </div>

            <!-- 2. Produtos em Destaque -->
            <div class="stat-card-premium" style="--accent-color: #F59E0B; --accent-bg: #FEF3C7;">
              <div class="stat-card-header">
                <span class="stat-card-title">Destaques da Home</span>
                <div class="stat-card-icon-wrap">
                  <i data-lucide="star"></i>
                </div>
              </div>
              <div class="stat-card-body">
                <div class="stat-card-value"><?php echo $featured_count; ?></div>
              </div>
              <a href="painel.php?action=list" class="stat-card-link">Ver Produtos →</a>
            </div>

            <!-- 3. Categorias -->
            <div class="stat-card-premium" style="--accent-color: #06B6D4; --accent-bg: #ECFEFF;">
              <div class="stat-card-header">
                <span class="stat-card-title">Categorias</span>
                <div class="stat-card-icon-wrap">
                  <i data-lucide="tag"></i>
                </div>
              </div>
              <div class="stat-card-body">
                <div class="stat-card-value"><?php echo $total_categories; ?></div>
              </div>
              <a href="painel.php?action=categories" class="stat-card-link">Ver Categorias →</a>
            </div>

            <!-- 4. Lojas Parceiras -->
            <div class="stat-card-premium" style="--accent-color: #0D9488; --accent-bg: #F0FDFA;">
              <div class="stat-card-header">
                <span class="stat-card-title">Lojas Parceiras</span>
                <div class="stat-card-icon-wrap">
                  <i data-lucide="store"></i>
                </div>
              </div>
              <div class="stat-card-body">
                <div class="stat-card-value"><?php echo $total_stores; ?></div>
              </div>
              <a href="painel.php?action=stores" class="stat-card-link">Ver Lojas →</a>
            </div>

             <!-- 5. Pedidos de Link -->
            <div class="stat-card-premium" style="--accent-color: #EF4444; --accent-bg: #FEF2F2;">
              <div class="stat-card-header">
                <span class="stat-card-title">Pedidos de Link</span>
                <div class="stat-card-icon-wrap">
                  <i data-lucide="mail"></i>
                </div>
              </div>
              <div class="stat-card-body">
                <div class="stat-card-value">
                  <?php echo $requests_count; ?> <span style="font-size: 0.9rem; font-weight: 500; color: var(--text-muted);">novos</span>
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-top: 0.25rem;">Total: <?php echo $requests_total; ?></div>
              </div>
              <a href="painel.php?action=requests" class="stat-card-link">Ver Pedidos →</a>
            </div>

            <!-- 6. Newsletter Leads -->
            <div class="stat-card-premium" style="--accent-color: #2563EB; --accent-bg: #EFF6FF;">
              <div class="stat-card-header">
                <span class="stat-card-title">Newsletter Leads</span>
                <div class="stat-card-icon-wrap">
                  <i data-lucide="bell"></i>
                </div>
              </div>
              <div class="stat-card-body">
                <div class="stat-card-value">
                  <?php echo $newsletter_count; ?> <span style="font-size: 0.9rem; font-weight: 500; color: var(--text-muted);">novos</span>
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-top: 0.25rem;">Total: <?php echo $newsletter_total; ?></div>
              </div>
              <a href="painel.php?action=newsletter_leads" class="stat-card-link">Ver Leads →</a>
            </div>


            <!-- 7. Artigos do Blog -->
            <div class="stat-card-premium" style="--accent-color: #10B981; --accent-bg: #ECFDF5;">
              <div class="stat-card-header">
                <span class="stat-card-title">Artigos do Blog</span>
                <div class="stat-card-icon-wrap">
                  <i data-lucide="file-text"></i>
                </div>
              </div>
              <div class="stat-card-body">
                <div class="stat-card-value"><?php echo $total_articles; ?></div>
              </div>
              <a href="painel.php?action=articles" class="stat-card-link">Ver Artigos →</a>
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
                  Ao usar o mesmo slug comparativo em múltiplos produtos com lojas diferentes, o MimosShopBrasil agrupa-os na tabela de comparação de preços automaticamente.
                </small>
              </div>

              <!-- Comentário explicativo: Seção do formulário contendo campos dinâmicos para a página de pré-venda individual -->
              <h3 style="grid-column: 1 / -1; margin-top: 1.5rem; margin-bottom: 0.5rem; font-size: 1.1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">Informações da Página de Pré-Venda (Individual)</h3>

              <!-- Imagens Adicionais -->
              <div class="form-field form-group-full">
                <label for="imagens_adicionais">Imagens Adicionais (URLs separadas por quebra de linha)</label>
                <textarea id="imagens_adicionais" name="imagens_adicionais" rows="3" placeholder="https://exemplo.com/foto1.jpg&#10;https://exemplo.com/foto2.jpg"><?php echo $current_action === 'edit' && isset($edit_product['imagens_adicionais']) ? htmlspecialchars($edit_product['imagens_adicionais']) : ''; ?></textarea>
              </div>

              <!-- Vantagens -->
              <div class="form-field">
                <label for="vantagens">Vantagens / Prós (Uma por linha)</label>
                <textarea id="vantagens" name="vantagens" rows="4" placeholder="Excelente custo-benefício&#10;Bateria dura o dia todo&#10;Design moderno e leve"><?php echo $current_action === 'edit' && isset($edit_product['vantagens']) ? htmlspecialchars($edit_product['vantagens']) : ''; ?></textarea>
              </div>

              <!-- Desvantagens -->
              <div class="form-field">
                <label for="desvantagens">Desvantagens / Contras (Uma por linha)</label>
                <textarea id="desvantagens" name="desvantagens" rows="4" placeholder="Carregador não incluso na caixa&#10;Preço ligeiramente elevado no lançamento"><?php echo $current_action === 'edit' && isset($edit_product['desvantagens']) ? htmlspecialchars($edit_product['desvantagens']) : ''; ?></textarea>
              </div>

              <!-- Para Quem é Indicado -->
              <div class="form-field form-group-full">
                <label for="para_quem_indicado">Para quem é indicado este produto?</label>
                <textarea id="para_quem_indicado" name="para_quem_indicado" rows="3" placeholder="Indicado para entusiastas de fotografia e criadores de conteúdo que buscam uma câmera compacta e potente..."><?php echo $current_action === 'edit' && isset($edit_product['para_quem_indicado']) ? htmlspecialchars($edit_product['para_quem_indicado']) : ''; ?></textarea>
              </div>

              <!-- Comparação com outros -->
              <div class="form-field form-group-full">
                <label for="comparacao">Comparativo / Por que escolher este produto?</label>
                <textarea id="comparacao" name="comparacao" rows="3" placeholder="Comparado ao modelo anterior, este traz foco automático híbrido ultra rápido de 425 pontos..."><?php echo $current_action === 'edit' && isset($edit_product['comparacao']) ? htmlspecialchars($edit_product['comparacao']) : ''; ?></textarea>
              </div>

              <!-- Perguntas Frequentes (FAQ) -->
              <div class="form-field form-group-full">
                <label for="perguntas_frequentes">Perguntas Frequentes (Formato: Pergunta|Resposta - Uma por linha)</label>
                <textarea id="perguntas_frequentes" name="perguntas_frequentes" rows="4" placeholder="É bivolt?|Sim, funciona tanto em 110v quanto em 220v.&#10;Acompanha lente?|Sim, acompanha a lente padrão de zoom 16-50mm."><?php echo $current_action === 'edit' && isset($edit_product['perguntas_frequentes']) ? htmlspecialchars($edit_product['perguntas_frequentes']) : ''; ?></textarea>
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
          <!-- Cabeçalho com botão para marcar todas como lidas e contadores de total/novos -->
          <div class="crud-header">
            <div>
              <h1>Pedidos de Links <span style="font-size: 1.1rem; font-weight: normal; color: var(--text-muted); margin-left: 0.5rem;">(Total: <?php echo count($requests_list); ?> | Novos: <?php echo $requests_count; ?>)</span></h1>
              <p style="color: var(--text-light); margin-top: 0.25rem;">Gerencie as solicitações de links de descontos feitas por clientes.</p>
            </div>
            <?php if ($requests_count > 0): ?>
              <!-- Botão para marcar todos os pedidos de link como vistos/lidos -->
              <a href="painel.php?action=mark_all_read&type=requests" class="btn-admin-secondary" style="font-size: 0.85rem; padding: 0.5rem 1rem;">
                ✓ Marcar todos como lidos
              </a>
            <?php endif; ?>
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
                  <th style="width: 100px;">ID</th>
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
                    <!-- Aplica um fundo azul bem claro se a solicitação ainda não foi vista -->
                    <tr <?php echo (isset($req['lido']) && !$req['lido']) ? 'style="background-color: #EFF6FF;"' : ''; ?>>
                      <td>
                        <strong>#<?php echo $req['id']; ?></strong>
                        <?php if (isset($req['lido']) && !$req['lido']): ?>
                          <!-- Badge indicador de nova solicitação -->
                          <span style="font-size: 0.65rem; background: #3B82F6; color: white; padding: 0.1rem 0.3rem; border-radius: 4px; margin-left: 0.25rem; display: inline-block; vertical-align: middle;">Novo</span>
                        <?php endif; ?>
                      </td>
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
          <!-- Cabeçalho com botão para marcar todas como lidas e contadores de total/novos -->
          <div class="crud-header">
            <div>
              <h1>Contatos da Newsletter <span style="font-size: 1.1rem; font-weight: normal; color: var(--text-muted); margin-left: 0.5rem;">(Total: <?php echo count($newsletter_leads_list); ?> | Novos: <?php echo $newsletter_count; ?>)</span></h1>
              <p style="color: var(--text-light); margin-top: 0.25rem;">Gerencie os números de WhatsApp cadastrados para receber alertas de ofertas.</p>
            </div>
            <?php if ($newsletter_count > 0): ?>
              <!-- Botão para marcar todos os contatos como vistos/lidos -->
              <a href="painel.php?action=mark_all_read&type=newsletter" class="btn-admin-secondary" style="font-size: 0.85rem; padding: 0.5rem 1rem;">
                ✓ Marcar todos como lidos
              </a>
            <?php endif; ?>
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
                  <th style="width: 100px;">ID</th>
                  <th>WhatsApp</th>
                  <th style="width: 200px;">Data de Cadastro</th>
                  <th style="width: 80px; text-align: center;">Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($newsletter_leads_list)): ?>
                  <?php foreach ($newsletter_leads_list as $lead): ?>
                    <!-- Aplica um fundo azul bem claro se o contato ainda não foi visto -->
                    <tr <?php echo (isset($lead['lido']) && !$lead['lido']) ? 'style="background-color: #EFF6FF;"' : ''; ?>>
                      <td>
                        <strong>#<?php echo $lead['id']; ?></strong>
                        <?php if (isset($lead['lido']) && !$lead['lido']): ?>
                          <!-- Badge indicador de novo contato -->
                          <span style="font-size: 0.65rem; background: #3B82F6; color: white; padding: 0.1rem 0.3rem; border-radius: 4px; margin-left: 0.25rem; display: inline-block; vertical-align: middle;">Novo</span>
                        <?php endif; ?>
                      </td>
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

      <!-- 10. TELA: LISTAGEM DE ARTIGOS DO BLOG (CRUD) -->
      <?php if ($current_action === 'articles'): ?>
        <div class="painel-card">
          <div class="crud-header">
            <div>
              <h1>Artigos do Blog <span style="font-size: 1.1rem; font-weight: normal; color: var(--text-muted); margin-left: 0.5rem;">(Total: <?php echo count($articles_list); ?>)</span></h1>
              <p style="color: var(--text-light); margin-top: 0.25rem;">Gerencie as postagens do blog do seu site.</p>
            </div>
            <!-- Botão para redirecionar para a tela de criação de novos artigos -->
            <a href="painel.php?action=new_article" class="btn-admin-primary">➕ Adicionar Artigo</a>
          </div>
          
          <!-- Alertas de sucesso ou erro do processamento backend -->
          <?php if (!empty($alertMessage)): ?>
            <div class="alert-box <?php echo $alertClass; ?>">
              <?php echo ($alertClass === 'alert-success' ? '✓ ' : '⚠ ') . htmlspecialchars($alertMessage); ?>
            </div>
          <?php endif; ?>
          
          <!-- Tabela com listagem estruturada de artigos de blog -->
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th style="width: 60px;">ID</th>
                  <th style="width: 50px;">Banner</th>
                  <th>Título</th>
                  <th>Slug</th>
                  <th style="width: 120px;">Publicado?</th>
                  <th style="width: 150px;">Data de Cadastro</th>
                  <th style="width: 120px; text-align: center;">Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($articles_list)): ?>
                  <?php foreach ($articles_list as $art): ?>
                    <tr>
                      <td><strong>#<?php echo $art['id']; ?></strong></td>
                      <td>
                        <?php if (!empty($art['imagem_url'])): ?>
                          <!-- Exibe a miniatura do banner do artigo -->
                          <img src="<?php echo htmlspecialchars($art['imagem_url']); ?>" alt="" class="admin-thumb">
                        <?php else: ?>
                          <div class="admin-thumb" style="display:flex; align-items:center; justify-content:center; background:#E2E8F0;">📝</div>
                        <?php endif; ?>
                      </td>
                      <td><strong><?php echo htmlspecialchars($art['titulo']); ?></strong></td>
                      <td><code><?php echo htmlspecialchars($art['slug']); ?></code></td>
                      <td>
                        <!-- Badge indicador visual do status de publicação -->
                        <?php if ($art['publicado'] === true): ?>
                          <span style="background:#D1FAE5; color:#065F46; padding:0.25rem 0.5rem; border-radius:99px; font-weight:700; font-size:0.75rem;">Sim</span>
                        <?php else: ?>
                          <span style="background:#F3F4F6; color:#374151; padding:0.25rem 0.5rem; border-radius:99px; font-weight:700; font-size:0.75rem;">Rascunho</span>
                        <?php endif; ?>
                      </td>
                      <td><?php echo date('d/m/Y H:i', strtotime($art['created_at'])); ?></td>
                      <td>
                        <div style="display:flex; gap:0.4rem; justify-content: center;">
                          <!-- Link para visualizar o artigo publicado diretamente no site usando a rota amigável da pasta blog -->
                          <a href="blog/<?php echo urlencode($art['slug']); ?>" 
                             class="btn-action btn-edit" 
                             style="background: #E0F2FE; color: #0369A1; border: 1px solid #BAE6FD;" 
                             target="_blank"
                             title="Visualizar Artigo">
                            👁️
                          </a>
                          <!-- Link para editar o artigo -->
                          <a href="painel.php?action=edit_article&id=<?php echo $art['id']; ?>" 
                             class="btn-action btn-edit" 
                             title="Editar Artigo">
                            ✏️
                          </a>
                          <!-- Link para deletar o artigo -->
                          <a href="painel.php?action=delete_article&id=<?php echo $art['id']; ?>" 
                             class="btn-action btn-delete" 
                             title="Remover Artigo"
                             onclick="return confirm('Tem certeza que deseja remover este artigo do blog?');">
                             🗑️
                          </a>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="7" class="text-center text-muted" style="padding: 3rem; text-align: center;">Nenhum artigo cadastrado no momento.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

      <!-- 11. TELA: FORMULÁRIO DE CRIAÇÃO / EDIÇÃO DE ARTIGOS (CRUD) -->
      <?php if ($current_action === 'new_article' || $current_action === 'edit_article'): ?>
        <?php 
          $isEdit = ($current_action === 'edit_article');
          $titleText = $isEdit ? 'Editar Artigo' : 'Adicionar Novo Artigo';
          $actionVal = $isEdit ? 'update_article' : 'insert_article';
        ?>
        <div class="painel-card">
          <div class="crud-header">
            <div>
              <h1><?php echo $titleText; ?></h1>
              <p style="color: var(--text-light); margin-top: 0.25rem;">Preencha os campos abaixo para criar ou atualizar um artigo no blog.</p>
            </div>
          </div>
          
          <!-- Formulário de gerenciamento de Artigo com suporte a envio de arquivos (multipart/form-data) -->
          <form action="painel.php?action=articles" method="POST" enctype="multipart/form-data" class="product-form-grid">
            <input type="hidden" name="action" value="<?php echo $actionVal; ?>">
            <?php if ($isEdit): ?>
              <input type="hidden" name="id" value="<?php echo $edit_article['id']; ?>">
            <?php endif; ?>
            
            <!-- Campo Título -->
            <div class="form-field form-group-full">
              <label for="titulo">Título do Artigo</label>
              <input type="text" id="titulo" name="titulo" required 
                     value="<?php echo $isEdit ? htmlspecialchars($edit_article['titulo']) : ''; ?>" 
                     placeholder="Ex: Os Melhores Celulares Custo-Benefício de 2025">
            </div>

            <!-- Campo Slug -->
            <div class="form-field">
              <label for="slug">URL Slug (Opcional)</label>
              <input type="text" id="slug" name="slug" 
                     value="<?php echo $isEdit ? htmlspecialchars($edit_article['slug']) : ''; ?>" 
                     placeholder="Ex: os-melhores-celulares (se vazio, gerará automaticamente)">
            </div>

            <!-- Campo Status Publicado -->
            <div class="form-field checkbox-field" style="display:flex; align-items:center; gap:0.5rem; margin-top:1.5rem;">
              <input type="checkbox" id="publicado" name="publicado" value="1" 
                     <?php echo ($isEdit && $edit_article['publicado'] === true) ? 'checked' : ''; ?>>
              <label for="publicado">Publicar imediatamente no site?</label>
            </div>

            <!-- Campo Banner Image -->
            <div class="form-field">
              <label for="imagem_file">Upload de Banner (Arquivo local)</label>
              <input type="file" id="imagem_file" name="imagem_file" accept="image/*">
            </div>
            
            <div class="form-field">
              <label for="imagem_url">Banner URL (Alternativa)</label>
              <input type="text" id="imagem_url" name="imagem_url" 
                     value="<?php echo ($isEdit && empty($_FILES['imagem_file']['name'])) ? htmlspecialchars($edit_article['imagem_url'] ?? '') : ''; ?>" 
                     placeholder="https://exemplo.com/imagem.png">
            </div>

            <!-- Campo Resumo -->
            <div class="form-field form-group-full">
              <label for="resumo">Resumo / Prévia</label>
              <input type="text" id="resumo" name="resumo" required 
                     value="<?php echo $isEdit ? htmlspecialchars($edit_article['resumo'] ?? '') : ''; ?>" 
                     placeholder="Ex: Uma breve descrição de 1 ou 2 frases sobre o conteúdo do artigo.">
            </div>

            <!-- Campo Conteúdo (HTML completo do Artigo) -->
            <div class="form-field form-group-full">
              <label for="conteudo">Conteúdo Completo (HTML aceito)</label>
              <textarea id="conteudo" name="conteudo" required rows="15" 
                        style="width: 100%; padding: 1rem; border-radius: var(--radius-sm); border: 1.5px solid var(--border); font-family: monospace; font-size: 0.92rem; outline: none; transition: border 0.2s;"
                        placeholder="Escreva aqui o conteúdo do seu artigo. Você pode usar tags HTML básicas como <p>, <h2>, <strong>, <ul>, etc."><?php echo $isEdit ? htmlspecialchars($edit_article['conteudo']) : ''; ?></textarea>
            </div>

            <!-- Ações do formulário -->
            <div class="form-actions form-group-full">
              <button type="submit" class="btn-admin-primary">💾 Salvar Artigo</button>
              <a href="painel.php?action=articles" class="btn-admin-secondary">Cancelar</a>
            </div>
          </form>
        </div>
      <?php endif; ?>

    </main>

  </div>
  <?php 
    // Inclui o rodapé compartilhado da página resolvendo o caminho correto a partir do diretório admin/
    include __DIR__ . '/../footer.php'; 
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
  <script src="../js/main.min.js"></script>
</body>
</html>
