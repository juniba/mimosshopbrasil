<?php
/*
  config.php: Centraliza as variáveis de configuração de acesso ao Supabase (API e Chaves)
  e gerencia o início de sessões de forma segura em todo o site.
*/

// Define se a sessão ainda não está iniciada e a inicia de forma segura com cookies protegidos
if (session_status() === PHP_SESSION_NONE) {
    // Configura os cookies de sessão com proteções de segurança contra XSS e CSRF
    session_set_cookie_params([
        'lifetime' => 0,         // Cookie expira ao fechar o navegador
        'path' => '/',           // Disponível em todo o site
        'secure' => true,        // Transmitido apenas via HTTPS
        'httponly' => true,      // Inacessível ao JavaScript (proteção XSS)
        'samesite' => 'Strict'   // Bloqueia envio cross-site (proteção CSRF)
    ]);
    session_start();
}

/**
 * Gera ou retorna o token CSRF da sessão atual.
 * Utilizado para proteger formulários POST contra ataques de falsificação.
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        // Gera um token criptograficamente seguro de 32 bytes
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valida se o token CSRF enviado pelo formulário corresponde ao da sessão.
 * Retorna true se válido, false se inválido.
 */
function csrf_validate($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Gera o campo HTML hidden com o token CSRF para inserir em formulários.
 */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/**
 * Carrega as variáveis de ambiente a partir de um arquivo .env para o PHP (superglobais e getenv).
 * Respeita a regra global de incluir comentários detalhados em todas as partes.
 */
function load_env($file_path) {
    if (!file_exists($file_path)) {
        return false;
    }
    
    // Lê todas as linhas do arquivo ignorando linhas em branco e quebras de linha
    $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Ignora linhas de comentários do arquivo
        if (strpos($line, '#') === 0) {
            continue;
        }
        
        // Divide o conteúdo na primeira ocorrência do '='
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            
            // Remove aspas simples ou duplas extras do valor associado
            $value = trim($value, '"\'');
            
            // Grava nas variáveis de ambiente locais do servidor
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
    return true;
}

// Carrega as variáveis de ambiente a partir do arquivo .env local
load_env(__DIR__ . '/.env');

/**
 * Recupera uma variável de ambiente de forma segura, removendo quebras de linha (\r, \n),
 * retornos de carro, espaços extras e aspas (simples ou duplas) nas pontas que possam
 * quebrar o protocolo HTTP.
 */
function get_env_safe($key) {
    $val = getenv($key) ?: ($_ENV[$key] ?? '');
    // Remove quebras de linha e espaços
    $val = trim($val);
    // Remove aspas simples ou duplas
    $val = trim($val, '"\'');
    // Remove possíveis espaços restantes após retirar as aspas
    return trim($val);
}

// Configurações do Supabase (Carregadas a partir das variáveis de ambiente com limpeza de aspas)
define('SUPABASE_URL', get_env_safe('SUPABASE_URL'));
define('SUPABASE_KEY', get_env_safe('SUPABASE_KEY'));

// Configurações do Cloudinary para armazenamento de imagens (Carregadas com limpeza de aspas)
define('CLOUDINARY_CLOUD_NAME', get_env_safe('CLOUDINARY_CLOUD_NAME'));
define('CLOUDINARY_API_KEY', get_env_safe('CLOUDINARY_API_KEY'));
define('CLOUDINARY_API_SECRET', get_env_safe('CLOUDINARY_API_SECRET'));

/*
  Para uso no frontend JavaScript, expomos as variáveis do Supabase de forma segura.
  Como a anon key é pública por design do Supabase, não há riscos em expô-la no client-side.
*/
function exportSupabaseConfig() {
    return json_encode([
        'url' => SUPABASE_URL,
        'key' => SUPABASE_KEY
    ]);
}

/**
 * Realiza uma requisição HTTP GET para o Supabase usando cache local de arquivos.
 * Se o cache existir e for mais recente que o TTL, retorna o cache; caso contrário,
 * consulta a API REST do Supabase e atualiza o arquivo local de cache.
 */
function fetch_supabase_with_cache($url, $context = null, $ttl = 300) {
    // Caminho absoluto para a pasta de cache
    $cache_dir = __DIR__ . '/cache';
    
    // Cria a pasta de cache se não existir
    if (!is_dir($cache_dir)) {
        @mkdir($cache_dir, 0777, true);
    }
    
    // Cria uma chave MD5 baseada na URL de consulta
    $cache_key = md5($url);
    $cache_file = $cache_dir . '/' . $cache_key . '.json';
    
    // Retorna o cache se o arquivo existir e o TTL não estiver expirado
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $ttl) {
        $data = @file_get_contents($cache_file);
        if ($data !== false) {
            return $data;
        }
    }
    
    // Faz a consulta HTTP real
    $response = @file_get_contents($url, false, $context);
    if ($response !== false) {
        // Grava no cache
        @file_put_contents($cache_file, $response);
    }
    
    return $response;
}

/**
 * Limpa todos os arquivos de cache salvos no servidor local.
 * Chamado automaticamente ao inserir, atualizar ou remover produtos.
 */
function clear_supabase_cache() {
    $cache_dir = __DIR__ . '/cache';
    if (is_dir($cache_dir)) {
        // Obtém todos os arquivos da pasta e os exclui
        $files = glob($cache_dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}

/**
 * Helper para fazer requisições administrativas à API REST do Supabase.
 * Tenta utilizar cURL se estiver ativo no servidor (altamente recomendado e robusto para o Render).
 * Caso contrário (como no ambiente local de desenvolvimento sem cURL), faz o fallback
 * usando file_get_contents configurando HTTP/1.1 para o envio do corpo da requisição de forma correta.
 */
function supabase_admin_request($method, $endpoint, $data = null, $use_admin_auth = false) {
    // Se for uma operação de modificação (POST, PATCH, DELETE), invalida todo o cache de consultas do Supabase
    if ($method !== 'GET') {
        clear_supabase_cache();
    }
    
    // Recupera o token de acesso da sessão do administrador apenas se explicitamente solicitado (operações do painel)
    $bearerToken = SUPABASE_KEY;
    if ($use_admin_auth && isset($_SESSION['admin_token']) && !empty($_SESSION['admin_token'])) {
        $bearerToken = $_SESSION['admin_token'];
    }

    $url = SUPABASE_URL . $endpoint;
    
    // Configura os cabeçalhos padrão da requisição
    $headers = [
        "apikey: " . SUPABASE_KEY,
        "Authorization: Bearer " . $bearerToken,
        "Content-Type: application/json"
    ];
    
    // Converte os dados para JSON e calcula o tamanho se houver corpo
    $json_content = null;
    if ($data !== null) {
        $json_content = json_encode($data);
        $headers[] = "Content-Length: " . strlen($json_content);
    }
    
    // Define a preferência de retorno (return=minimal é necessária para evitar erros de RLS em tabelas públicas)
    if ($method !== 'GET') {
        if (strpos($endpoint, '/newsletter') !== false || strpos($endpoint, '/pedidosLink') !== false) {
            $headers[] = "Prefer: return=minimal";
        } else {
            $headers[] = "Prefer: return=representation";
        }
    }

    // MODO 1: Se o cURL estiver disponível, utiliza ele para fazer o envio (ideal para produção)
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Verificação SSL ativada para segurança contra ataques MITM
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        if ($json_content !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_content);
        }
        
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Verifica erros de rede cURL
        if ($response === false) {
            error_log("Supabase cURL Error: " . $error);
            return false;
        }
        
        // Verifica se a API do Supabase retornou erro HTTP >= 400
        if ($status >= 400) {
            error_log("Supabase cURL API Error ($status): " . $response);
            return false;
        }
        
        return json_decode($response, true);
    } 
    // MODO 2: Se cURL estiver desativado, usa o fallback baseado no stream de dados com HTTP/1.1
    else {
        $opts = [
            "http" => [
                "method" => $method,
                "header" => implode("\r\n", $headers) . "\r\n",
                "protocol_version" => "1.1", // Necessário para que proxies na nuvem encaminhem o corpo do POST
                "ignore_errors" => true
            ]
        ];
        
        if ($json_content !== null) {
            $opts["http"]["content"] = $json_content;
        }
        
        $context = stream_context_create($opts);
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            return false;
        }
        
        // Trata retornos de erro do Supabase via cabeçalhos de fluxo
        if (isset($http_response_header)) {
            preg_match('{HTTP\/\S+\s+(\d+)}', $http_response_header[0], $matches);
            $status = intval($matches[1]);
            if ($status >= 400) {
                error_log("Supabase Stream API Error ($status): " . $response);
                return false;
            }
        }
        
        return json_decode($response, true);
    }
}
?>
