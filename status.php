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
echo "\n--- TESTANDO GET NA TABELA 'newsletter' ---\n";

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
} else {
    echo "Erro: Não foi possível obter os cabeçalhos de resposta HTTP.\n";
}

echo "Corpo da Resposta:\n";
echo ($response === false ? "Falha" : $response) . "\n";


// 3. Realizar um teste de POST na tabela 'newsletter' usando o método implementado no config.php
echo "\n--- TESTANDO POST NA TABELA 'newsletter' (VIA CONFIG) ---\n";

$payload = [
    'whatsapp' => '5521964120044'
];

$headers_post = [
    "apikey: " . SUPABASE_KEY,
    "Authorization: Bearer " . SUPABASE_KEY,
    "Content-Type: application/json"
];

$json_content = json_encode($payload);
$headers_post[] = "Content-Length: " . strlen($json_content);
$headers_post[] = "Prefer: return=minimal";

$opts_post = [
    "http" => [
        "method" => "POST",
        "header" => implode("\r\n", $headers_post) . "\r\n",
        "content" => $json_content,
        "ignore_errors" => true
    ]
];

$context_post = stream_context_create($opts_post);
$url_post = SUPABASE_URL . '/rest/v1/newsletter';

echo "URL de destino: " . $url_post . "\n";
echo "Cabeçalhos enviados:\n" . implode("\n", $headers_post) . "\n";
echo "Corpo enviado: " . $json_content . "\n";

$response_post = @file_get_contents($url_post, false, $context_post);

if (isset($http_response_header)) {
    echo "HTTP Status Retornado no POST: " . $http_response_header[0] . "\n";
    echo "Cabeçalhos de resposta no POST:\n";
    foreach ($http_response_header as $header) {
        echo "  - " . $header . "\n";
    }
} else {
    echo "Erro ao capturar cabeçalhos de resposta do POST.\n";
}

echo "Corpo da resposta do POST:\n";
var_dump($response_post);

echo "\nFim do diagnóstico.\n";
?>
