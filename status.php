<?php
# status.php: Script de diagnóstico público para testar a conectividade e autenticação com o Supabase.
# Este script ajuda a identificar se há problemas com as chaves de API, variáveis de ambiente ou rede no Render.

require_once 'config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO DE CONEXÃO SUPABASE (DETALHADO COM cURL) ===\n\n";

// 1. Mostrar as variáveis (mascaradas para segurança)
$url = SUPABASE_URL;
$key = SUPABASE_KEY;

echo "Supabase URL definida: " . (!empty($url) ? substr($url, 0, 15) . "..." : "NÃO DEFINIDA") . "\n";
echo "Supabase Key definida: " . (!empty($key) ? substr($key, 0, 10) . "..." . substr($key, -10) : "NÃO DEFINIDA") . "\n";

if (empty($url) || empty($key)) {
    echo "Erro: As variáveis do Supabase estão ausentes no ambiente do servidor!\n";
    exit;
}

// 2. Realizar o teste de POST via cURL direto e imprimir TODOS os detalhes
echo "\n--- TESTANDO POST DIRETO COM cURL NO SUPABASE ---\n";

$payload = [
    'whatsapp' => '5521964120044'
];

$url_post = $url . '/rest/v1/newsletter';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url_post);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$json_content = json_encode($payload);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json_content);

$headers = [
    "apikey: " . $key,
    "Authorization: Bearer " . $key,
    "Content-Type: application/json",
    "Content-Length: " . strlen($json_content),
    "Prefer: return=minimal"
];
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

echo "Enviando POST para: " . $url_post . "\n";
echo "Headers enviados:\n" . implode("\n", $headers) . "\n";
echo "Corpo do JSON enviado: " . $json_content . "\n\n";

$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "=== RESULTADO DO cURL ===\n";
echo "Status HTTP Retornado: " . $status . "\n";
if ($response === false) {
    echo "Erro cURL: " . $error . "\n";
} else {
    echo "Corpo da resposta:\n" . $response . "\n";
}

echo "\nFim do diagnóstico.\n";
?>
