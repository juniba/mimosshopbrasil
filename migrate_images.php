<?php
/*
  migrate_images.php: Script de CLI para migração automatizada de imagens do banco de dados.
  Lê todos os produtos da tabela "produtos" no Supabase, processa suas imagens originais
  (convertendo para WebP e fazendo upload para o Cloudinary) e atualiza a base com o novo link seguro.
  Respeita a regra global de incluir comentários detalhados em todas as partes do código.
*/

// Garante que o script está sendo rodado via terminal (CLI)
if (php_sapi_name() !== 'cli') {
    die("Este script so pode ser executado via CLI (linha de comando).\n");
}

require_once 'config.php';
// Carrega o helper do Cloudinary que encapsula o upload e a conversão de formato para WebP
require_once 'cloudinary_helper.php';

echo "=== INICIANDO MIGRAÇÃO DE IMAGENS PARA CLOUDINARY (WEBP) ===\n";

/**
 * Função local para realizar requisições REST autenticadas com a API do Supabase.
 */
function supabase_request($method, $endpoint, $data = null) {
    $opts = [
        "http" => [
            "method" => $method,
            "header" => "apikey: " . SUPABASE_KEY . "\r\n" .
                        "Authorization: Bearer " . SUPABASE_KEY . "\r\n" .
                        "Content-Type: application/json\r\n"
        ]
    ];
    
    // Se houver dados (payload), codifica e anexa à requisição HTTP
    if ($data !== null) {
        $opts["http"]["content"] = json_encode($data);
    }
    
    // Configura preferência de retorno do objeto modificado em escritas
    if ($method !== 'GET') {
        $opts["http"]["header"] .= "Prefer: return=representation\r\n";
    }
    
    $context = stream_context_create($opts);
    $url = SUPABASE_URL . $endpoint;
    
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return false;
    }
    
    return json_decode($response, true);
}

// 1. Busca todos os produtos do Supabase
echo "Buscando produtos do banco de dados...\n";
$produtos = supabase_request('GET', '/rest/v1/produtos?select=*&order=id.asc');

if (empty($produtos)) {
    die("Nenhum produto encontrado ou erro ao conectar com o Supabase.\n");
}

$total = count($produtos);
echo "Total de produtos encontrados: {$total}\n\n";

$migrated = 0;
$skipped = 0;
$failed = 0;

// 2. Itera sobre cada produto para fazer a migração
foreach ($produtos as $p) {
    $id = $p['id'];
    $titulo = $p['titulo'];
    $url_original = $p['imagem_url'];
    
    echo "[ID {$id}] {$titulo}\n";
    echo "  Imagem atual: {$url_original}\n";
    
    // Se a imagem já estiver no Cloudinary, pula o processamento
    if (strpos($url_original, 'res.cloudinary.com') !== false) {
        echo "  -> Ignorado: Imagem ja esta hospedada no Cloudinary.\n\n";
        $skipped++;
        continue;
    }
    
    echo "  -> Processando conversao para WebP e upload no Cloudinary...\n";
    
    // Executa a lógica de download, conversão WebP e upload no Cloudinary
    $nova_url = handle_image_upload_or_url(null, $url_original);
    
    // Se o upload gerou uma URL válida no Cloudinary
    if (!empty($nova_url) && strpos($nova_url, 'res.cloudinary.com') !== false) {
        echo "  -> Upload concluido: {$nova_url}\n";
        echo "  -> Gravando nova URL no Supabase...\n";
        
        // Atualiza a URL da imagem correspondente no banco de dados Supabase
        $update_payload = ['imagem_url' => $nova_url];
        $update_res = supabase_request('PATCH', "/rest/v1/produtos?id=eq.{$id}", $update_payload);
        
        if ($update_res !== false) {
            echo "  -> Sucesso: Registro atualizado no banco.\n\n";
            $migrated++;
        } else {
            echo "  -> Erro: Falha ao atualizar a URL no Supabase.\n\n";
            $failed++;
        }
    } else {
        echo "  -> Erro: Falha na conversao ou upload da imagem.\n\n";
        $failed++;
    }
}

echo "=== RESUMO DA MIGRAÇÃO ===\n";
echo "Sucesso (migrados): {$migrated}\n";
echo "Ignorados (ja no Cloudinary): {$skipped}\n";
echo "Falhas: {$failed}\n";
echo "==========================\n";
?>
