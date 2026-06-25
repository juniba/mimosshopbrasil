<?php
/*
  link_request.php: Recebe via POST/AJAX o pedido de link de um cliente (pedidosLink).
  Insere as informações no Supabase e notifica o administrador via WhatsApp utilizando o CallMeBot.
*/

// Configura o cabeçalho de resposta como JSON
header('Content-Type: application/json; charset=utf-8');

// Requer as configurações globais e funções utilitárias
require_once 'config.php';

// Comentário de regra: Proteção contra flooding/spam - Limita a 3 pedidos de link por minuto por IP para evitar abuso do CallMeBot
if (!check_rate_limit('link_request', 3, 60)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Muitas solicitações enviadas em curto espaço de tempo. Aguarde um minuto e tente novamente.'
    ]);
    exit;
}

// Apenas aceita requisições do tipo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido.'
    ]);
    exit;
}

// Obtém e decodifica o corpo JSON da requisição
$input_data = json_decode(file_get_contents('php://input'), true);

$nome = isset($input_data['nome']) ? trim($input_data['nome']) : '';
$whatsapp = isset($input_data['whatsapp']) ? trim($input_data['whatsapp']) : '';
$link = isset($input_data['link']) ? trim($input_data['link']) : '';

// Valida se todos os campos obrigatórios foram preenchidos
if (empty($nome) || empty($whatsapp) || empty($link)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Por favor, preencha todos os campos do formulário.'
    ]);
    exit;
}

// Prepara o payload para inserção na tabela "pedidosLink" do Supabase
$payload = [
    'nome' => $nome,
    'whatsapp' => $whatsapp,
    'link' => $link
];

// Define o cabeçalho Prefer: return=minimal para a inserção (evita RLS select violation)
// Faz o POST na tabela "pedidosLink" (lembrando de usar aspas se houver camelCase na tabela)
$res = supabase_admin_request('POST', '/rest/v1/pedidosLink', $payload);

if ($res === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno ao registrar seu pedido de link. Tente novamente mais tarde.'
    ]);
    exit;
}

// --- ENVIO DA NOTIFICAÇÃO DO CALLMEBOT PARA O ADMINISTRADOR ---

// Recupera de forma segura a chave da API do CallMeBot das variáveis de ambiente, removendo possíveis aspas
$api_token = get_env_safe('WHATSAPP_API_KEY');
// Recupera o número de telefone do administrador das variáveis de ambiente (segurança: não expor no código-fonte)
$admin_phone = get_env_safe('ADMIN_WHATSAPP_PHONE');
if (empty($admin_phone)) {
    $admin_phone = "5521964120044"; // Fallback caso a variável não esteja configurada
}

if (!empty($api_token)) {
    // Monta uma mensagem detalhada formatada para o WhatsApp do administrador
    // Mensagem formatada para WhatsApp com a marca atualizada
    $notification_msg = "🔔 *Nova Encomenda de Link no Mimos Shop Brasil!* 🔔\n\n";
    $notification_msg .= "👤 *Cliente:* {$nome}\n";
    $notification_msg .= "📱 *WhatsApp:* {$whatsapp}\n";
    $notification_msg .= "🔗 *Link do Produto:* {$link}\n";
    
    // URL de requisição da API pública do CallMeBot
    $callmebot_url = "https://api.callmebot.com/whatsapp.php?phone=" . urlencode($admin_phone) . "&text=" . urlencode($notification_msg) . "&apikey=" . urlencode($api_token);
    
    // Função auxiliar interna para realizar requisição HTTP via cURL (com fallback para file_get_contents) e registrar logs detalhados
    $send_request = function($url) {
        if (function_exists('curl_init')) {
            // Inicializa a sessão cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Evita problemas com certificados SSL locais/incompletos
            curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Define tempo limite da requisição em 15 segundos
            
            $response = curl_exec($ch);
            if ($response === false) {
                error_log("CallMeBot cURL Error: " . curl_error($ch));
            } else {
                error_log("CallMeBot Response (cURL): " . $response);
            }
            curl_close($ch);
            return $response;
        } else {
            // Caso cURL não esteja disponível, faz requisição via file_get_contents com cabeçalhos de contexto
            $opts = [
                "http" => [
                    "method" => "GET",
                    "timeout" => 15,
                    "ignore_errors" => true // Captura a resposta mesmo se retornar status HTTP de erro
                ]
            ];
            $context = stream_context_create($opts);
            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                error_log("CallMeBot file_get_contents Error loading URL: " . $url);
            } else {
                error_log("CallMeBot Response (file_get_contents): " . $response);
            }
            return $response;
        }
    };
    
    // Dispara a notificação para a API do CallMeBot
    $send_request($callmebot_url);
}

// Retorna resposta de sucesso para o cliente no frontend
echo json_encode([
    'success' => true,
    'message' => '✓ Seu pedido de link foi enviado com sucesso! Aguarde o contato no seu WhatsApp.'
]);
exit;
?>
