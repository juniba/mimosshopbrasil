<?php
/*
  newsletter.php: Processa o cadastro de números de WhatsApp para o envio de alertas de ofertas.
  Valida a entrada e insere o número no banco de dados Supabase (tabela newsletter).
  Retorna uma resposta JSON indicando o sucesso ou falha da operação.
*/

// Define o cabeçalho de resposta como JSON
header('Content-Type: application/json; charset=utf-8');

// Inclui o arquivo de configuração geral com as credenciais do Supabase
require_once 'config.php';

// Verifica se a requisição é do tipo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido. Utilize o método POST.'
    ]);
    exit;
}

// Tenta decodificar o corpo JSON (caso seja enviado como JSON no AJAX)
$input_data = json_decode(file_get_contents('php://input'), true);

// Recupera o número do WhatsApp da entrada POST ou do JSON
$whatsapp = isset($_POST['whatsapp']) ? trim($_POST['whatsapp']) : '';
if (empty($whatsapp) && isset($input_data['whatsapp'])) {
    $whatsapp = trim($input_data['whatsapp']);
}

// Valida se o campo de WhatsApp foi preenchido
if (empty($whatsapp)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Por favor, informe o seu WhatsApp (Zap).'
    ]);
    exit;
}

// Limpa caracteres especiais do telefone para salvar apenas números (opcional, mas mantém padronizado)
$whatsapp_clean = preg_replace('/[^0-9]/', '', $whatsapp);

// Validação simples de tamanho de número de telefone brasileiro
if (strlen($whatsapp_clean) < 10 || strlen($whatsapp_clean) > 11) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Formato de WhatsApp inválido. Digite o número com DDD.'
    ]);
    exit;
}

// Prepara os dados para salvar na tabela 'newsletter' do Supabase
$payload = [
    'whatsapp' => $whatsapp
];

// Envia a requisição POST para o Supabase
$res = supabase_admin_request('POST', '/rest/v1/newsletter', $payload);

// Se a inserção falhar ou retornar falso, reporta o erro
if ($res === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ocorreu um erro ao salvar seu cadastro no servidor. Tente novamente mais tarde.'
    ]);
    exit;
}

// Retorna resposta de sucesso
echo json_encode([
    'success' => true,
    'message' => '✓ Seu WhatsApp foi cadastrado com sucesso! Avisaremos das ofertas!'
]);
exit;
?>
