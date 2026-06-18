<?php
# status.php: Script de diagnóstico público para testar a conectividade e autenticação com o Supabase.
# Este script ajuda a identificar se há problemas com as chaves de API, variáveis de ambiente ou rede no Render.

require_once 'config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO DE CONEXÃO SUPABASE ===\n\n";

// 1. Mostrar as variáveis (mascaradas para segurança)
$url = SUPABASE_URL;
$key = SUPABASE_KEY;

echo "Supabase URL definida: " . (!empty($url) ? substr($url, 0, 15) . "..." : "NÃO DEFINIDA") . "\n";
echo "Supabase Key definida: " . (!empty($key) ? substr($key, 0, 10) . "..." . substr($key, -10) : "NÃO DEFINIDA") . "\n";

if (empty($url) || empty($key)) {
    echo "Erro: As variáveis do Supabase estão ausentes no ambiente do servidor!\n";
    exit;
}

// 2. Realizar um teste de GET simples na tabela 'newsletter'
echo "\nTestando consulta na tabela 'newsletter'...\n";

$endpoint = '/rest/v1/newsletter?select=count';
$url_full = $url . $endpoint;

$opts = [
    "http" => [
        "method" => "GET",
        "header" => "apikey: " . $key . "\r\n" .
                    "Authorization: Bearer " . $key . "\r\n" .
                    "Content-Type: application/json\r\n",
        "ignore_errors" => true
    ]
];

$context = stream_context_create($opts);
$response = @file_get_contents($url_full, false, $context);

if (isset($http_response_header)) {
    echo "HTTP Status Retornado: " . $http_response_header[0] . "\n";
    echo "Cabeçalhos de resposta:\n";
    foreach ($http_response_header as $header) {
        if (strpos($header, 'HTTP') === 0 || strpos($header, 'Content-Range') === 0 || strpos($header, 'sb-') === 0) {
            echo "  - " . $header . "\n";
        }
    }
} else {
    echo "Erro: Não foi possível obter os cabeçalhos de resposta HTTP.\n";
}

echo "\nCorpo da Resposta:\n";
if ($response === false) {
    echo "Falha crítica: file_get_contents retornou false (problema de conexão ou rede).\n";
} else {
    echo $response . "\n";
}

echo "\nFim do diagnóstico.\n";
?>
