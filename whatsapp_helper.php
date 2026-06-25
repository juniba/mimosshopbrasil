<?php
/*
  whatsapp_helper.php: Centraliza as funções de envio de alertas de novos produtos
  para os números de WhatsApp cadastrados no banco de dados.
  Se as chaves de API não estiverem configuradas no .env, registra em logs para simulação.
*/

require_once 'config.php';

/**
 * Envia mensagens de alerta para todos os números cadastrados na newsletter
 * informando sobre um novo produto anunciado.
 * 
 * @param array $product - Dados do produto cadastrado
 * @return bool - Retorna true se finalizou o processo
 */
function send_new_product_notification($product) {
    // 1. Busca todos os números de WhatsApp cadastrados na tabela 'newsletter' do Supabase
    // Como a operação é executada pelo painel administrativo, usamos a autenticação do admin
    $leads = supabase_admin_request('GET', '/rest/v1/newsletter?select=whatsapp', null, true);
    
    if (empty($leads)) {
        error_log("[WhatsApp Alert] Nenhum número cadastrado na newsletter para envio.");
        return false;
    }
    
    // 2. Extrai e higieniza as informações do produto para a mensagem
    $titulo = $product['titulo'] ?? 'Produto sem título';
    $preco = isset($product['preco_novo']) ? number_format($product['preco_novo'], 2, ',', '.') : '0,00';
    $link = $product['link_afiliado'] ?? '';
    
    // 3. Monta o corpo da mensagem com formatação para WhatsApp (negrito com asteriscos)
    $message = "🔥 *NOVA OFERTA ANUNCIADA NO MimosShopBrasil!* 🔥\n\n";
    $message .= "*{$titulo}*\n";
    $message .= "Preço incrível: *R$ {$preco}*\n\n";
    if (!empty($link)) {
        $message .= "👉 Compre aqui: {$link}\n";
    }
    
    // 4. Carrega as configurações da API do WhatsApp do arquivo .env usando a função segura get_env_safe para remover aspas indesejadas
    $api_url = get_env_safe('WHATSAPP_API_URL');
    $api_token = get_env_safe('WHATSAPP_API_KEY');
    
    // Caminho do arquivo de logs locais para simulação / auditoria
    $log_file = __DIR__ . '/whatsapp_log.txt';
    
    // Registra o cabeçalho no arquivo de log local para simulação visual
    $log_content = "==================================================\n";
    $log_content .= "DATA DO DISPARO: " . date('d/m/Y H:i:s') . "\n";
    $log_content .= "CONTEÚDO DA MENSAGEM:\n\n{$message}\n";
    $log_content .= "--------------------------------------------------\n";
    $log_content .= "STATUS DOS DISPAROS:\n";
    
    // 5. Itera sobre a lista de contatos para realizar o envio
    foreach ($leads as $lead) {
        $raw_phone = $lead['whatsapp'] ?? '';
        if (empty($raw_phone)) continue;
        
        // Remove todos os caracteres não numéricos para obter apenas o número de telefone
        $phone_digits = preg_replace('/\D/', '', $raw_phone);
        
        // Padroniza DDI do Brasil (55) caso o número tenha DDD + telefone (10 ou 11 dígitos)
        if (strlen($phone_digits) === 10 || strlen($phone_digits) === 11) {
            $phone_digits = '55' . $phone_digits;
        }
        
        // Carrega qual serviço de disparo está ativo limpando as aspas extras
        $service = get_env_safe('WHATSAPP_API_SERVICE');

        // Se o CallMeBot estiver configurado, executa a requisição via método GET
        if ($service === 'callmebot' && !empty($api_token)) {
            // CallMeBot requer requisição GET e a mensagem deve estar codificada na URL (URL-safe)
            $url_callmebot = "https://api.callmebot.com/whatsapp.php?phone=" . urlencode($phone_digits) . "&text=" . urlencode($message) . "&apikey=" . urlencode($api_token);
            $response = @file_get_contents($url_callmebot);
            
            if ($response === false) {
                $status_msg = "[-] Falha ao enviar para {$raw_phone} via CallMeBot (API retornou erro)\n";
            } else {
                $status_msg = "[+] Enviado com sucesso para {$raw_phone} via CallMeBot\n";
            }
        }
        // Caso seja a API padrão/Evolution/Z-API (via POST JSON)
        elseif (!empty($api_url)) {
            $payload = [
                'phone' => $phone_digits,
                'message' => $message
            ];
            
            // Configura o contexto HTTP para o envio do disparo POST
            $opts = [
                "http" => [
                    "method" => "POST",
                    "header" => "Content-Type: application/json\r\n" .
                                "Authorization: Bearer " . $api_token . "\r\n",
                    "content" => json_encode($payload),
                    "timeout" => 5
                ]
            ];
            $context = stream_context_create($opts);
            $response = @file_get_contents($api_url, false, $context);
            
            if ($response === false) {
                $status_msg = "[-] Falha ao enviar para {$raw_phone} (API retornou erro)\n";
            } else {
                $status_msg = "[+] Enviado com sucesso para {$raw_phone} via API\n";
            }
        } else {
            // Se nenhum serviço estiver ativo no .env, realiza a simulação local salvando em arquivo de texto
            $status_msg = "[Simulação] Mensagem direcionada para {$raw_phone} (Número formatado: {$phone_digits})\n";
        }
        
        $log_content .= $status_msg;
    }
    
    $log_content .= "==================================================\n\n";
    
    // Salva o log simulado no servidor
    @file_put_contents($log_file, $log_content, FILE_APPEND);
    return true;
}
?>
