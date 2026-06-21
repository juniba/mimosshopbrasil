<?php
/*
  auth_session.php: Gerencia a criação e destruição de sessões PHP
  a partir de requisições AJAX enviadas pelo client-side (Supabase Auth).
  
  SEGURANÇA: Antes de criar a sessão de admin, o access_token JWT é validado
  diretamente contra a API de autenticação do Supabase (GoTrue) para garantir
  que o token é legítimo e pertence ao e-mail informado.
*/
require_once __DIR__ . '/../config.php';

// Configura o cabeçalho de resposta para JSON
header('Content-Type: application/json');

// Apenas aceita requisições via método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método de requisição inválido.']);
    exit;
}

// Obtém o corpo da requisição JSON enviado pelo JavaScript
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

if (!$data || !isset($data['action'])) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

$action = $data['action'];

/**
 * Valida o access_token JWT contra a API de autenticação do Supabase.
 * Faz uma requisição GET para /auth/v1/user com o token como Bearer.
 * Se o token for válido, o Supabase retorna os dados do usuário autenticado.
 * 
 * @param string $accessToken - O JWT access_token recebido do client-side
 * @return array|false - Retorna os dados do usuário se válido, ou false se inválido
 */
function verifySupabaseToken($accessToken) {
    // Monta a URL do endpoint de verificação de usuário do Supabase Auth (GoTrue)
    $url = SUPABASE_URL . '/auth/v1/user';

    // Configura os cabeçalhos HTTP necessários: apikey (anon key) e Authorization (Bearer JWT)
    $opts = [
        "http" => [
            "method" => "GET",
            "header" => "apikey: " . SUPABASE_KEY . "\r\n" .
                        "Authorization: Bearer " . $accessToken . "\r\n",
            // Define um timeout de 10 segundos para não travar o servidor em caso de lentidão
            "timeout" => 10,
            // Ignora erros HTTP para poder tratar o retorno manualmente
            "ignore_errors" => true
        ]
    ];
    $context = stream_context_create($opts);

    // Faz a requisição GET ao endpoint de autenticação do Supabase
    $response = @file_get_contents($url, false, $context);

    // Se a requisição falhou completamente (ex: sem internet), retorna false
    if ($response === false) {
        return false;
    }

    // Decodifica a resposta JSON do Supabase
    $userData = json_decode($response, true);

    // Verifica se o Supabase retornou um objeto de usuário válido com ID e e-mail
    // Se houver campo 'error' ou 'msg' na resposta, significa que o token é inválido
    if (!$userData || isset($userData['error']) || isset($userData['msg']) || !isset($userData['id'])) {
        return false;
    }

    // Token válido: retorna os dados do usuário confirmados pelo Supabase
    return $userData;
}

// Ação de Login: valida o token JWT no Supabase antes de criar a sessão
if ($action === 'login') {
    if (!isset($data['email']) || !isset($data['access_token'])) {
        echo json_encode(['success' => false, 'message' => 'Credenciais incompletas na requisição.']);
        exit;
    }

    // SEGURANÇA: Valida o access_token contra a API do Supabase Auth
    $supabaseUser = verifySupabaseToken($data['access_token']);

    // Se o token for inválido ou expirado, rejeita o login
    if ($supabaseUser === false) {
        echo json_encode(['success' => false, 'message' => 'Token de autenticação inválido ou expirado.']);
        exit;
    }

    // SEGURANÇA: Verifica se o e-mail do token bate com o e-mail enviado pelo client
    // Isso previne que alguém envie um token válido de outra conta com um e-mail diferente
    if (strtolower($supabaseUser['email']) !== strtolower($data['email'])) {
        echo json_encode(['success' => false, 'message' => 'O e-mail não corresponde ao token fornecido.']);
        exit;
    }

    // Token validado com sucesso pelo Supabase — regenera o ID da sessão para prevenir session fixation
    session_regenerate_id(true);
    
    // Salva a sessão de admin autenticado com dados confirmados pelo Supabase
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_email'] = htmlspecialchars($supabaseUser['email']); // Usa o email confirmado pelo Supabase
    $_SESSION['admin_token'] = $data['access_token'];
    $_SESSION['admin_id'] = $supabaseUser['id']; // Armazena o UUID do usuário no Supabase

    echo json_encode(['success' => true]);
    exit;
}

// Ação de Logout: destrói a sessão PHP
if ($action === 'logout') {
    // Limpa todas as variáveis de sessão
    $_SESSION = [];

    // Se a sessão foi iniciada por cookie, apaga o cookie correspondente
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Destrói a sessão no servidor
    session_destroy();

    echo json_encode(['success' => true]);
    exit;
}

// Caso a ação recebida seja desconhecida
echo json_encode(['success' => false, 'message' => 'Ação desconhecida.']);
?>
