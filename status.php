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

$res_get = supabase_admin_request('GET', '/rest/v1/newsletter?select=count');
echo "Resultado do GET: ";
var_dump($res_get);


// 3. Realizar o teste de POST utilizando a função oficial configurada no config.php
echo "\n--- TESTANDO POST OFICIAL VIA CONFIG.PHP (SUPABASE_ADMIN_REQUEST) ---\n";

$payload = [
    'whatsapp' => '5521964120044'
];

$res_post = supabase_admin_request('POST', '/rest/v1/newsletter', $payload);
echo "Resultado do POST:\n";
var_dump($res_post);

echo "\nFim do diagnóstico.\n";
?>
