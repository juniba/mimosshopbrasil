<?php
/*
  config.php: Centraliza as variáveis de configuração de acesso ao Supabase (API e Chaves)
  e gerencia o início de sessões de forma segura em todo o site.
*/

// Define se a sessão ainda não está iniciada e a inicia de forma segura
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
 * Recupera uma variável de ambiente de forma segura, removendo quaisquer aspas extras
 * (simples ou duplas) que possam ter sido injetadas pelo sistema ou pelo arquivo .env.
 */
function get_env_safe($key) {
    $val = getenv($key) ?: ($_ENV[$key] ?? '');
    return trim($val, '"\'');
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
 * Helper para fazer requisições administrativas à API REST do Supabase utilizando o contexto de streams HTTP do PHP.
 * Centralizado no config.php para permitir o reuso tanto no painel admin quanto em chamadas públicas (ex: newsletter).
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

    // Monta os cabeçalhos em formato de array para estruturação mais segura e compatibilidade de rede
    $headers = [
        "apikey: " . SUPABASE_KEY,
        "Authorization: Bearer " . $bearerToken,
        "Content-Type: application/json"
    ];
    
    // Converte os dados para JSON e calcula o Content-Length caso exista corpo na requisição
    $json_content = null;
    if ($data !== null) {
        $json_content = json_encode($data);
        $headers[] = "Content-Length: " . strlen($json_content);
    }
    
    // Configura o cabeçalho Prefer com base no endpoint (evitando problemas de permissão em tabelas públicas)
    if ($method !== 'GET') {
        if (strpos($endpoint, '/newsletter') !== false || strpos($endpoint, '/pedidosLink') !== false) {
            $headers[] = "Prefer: return=minimal";
        } else {
            $headers[] = "Prefer: return=representation";
        }
    }

    // Configura as opções do stream HTTP
    $opts = [
        "http" => [
            "method" => $method,
            // Junta todos os cabeçalhos separados por quebra de linha CR+LF obrigatória do protocolo HTTP
            "header" => implode("\r\n", $headers) . "\r\n",
            "ignore_errors" => true // Captura a resposta mesmo se retornar status de erro (4xx/5xx)
        ]
    ];
    
    // Vincula o corpo da requisição ao stream de dados
    if ($json_content !== null) {
        $opts["http"]["content"] = $json_content;
    }
    
    $context = stream_context_create($opts);
    $url = SUPABASE_URL . $endpoint;
    
    // Executa a requisição REST
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return false;
    }
    
    // Verifica se houve erro HTTP analisando os cabeçalhos de resposta
    if (isset($http_response_header)) {
        preg_match('{HTTP\/\S+\s+(\d+)}', $http_response_header[0], $matches);
        $status = intval($matches[1]);
        if ($status >= 400) {
            // Loga o erro retornado pela API do Supabase no log do sistema
            error_log("Supabase API Error ($status): " . $response);
            return false;
        }
    }
    
    return json_decode($response, true);
}
?>
