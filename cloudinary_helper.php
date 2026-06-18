<?php
/*
  cloudinary_helper.php: Interface PHP para chamar o script Python
  que converte imagens para WebP e faz upload para o Cloudinary.
  Respeita a regra global de incluir comentários detalhados explicativos.
*/

require_once 'config.php';

/**
 * Envia uma imagem local para o Cloudinary (convertendo para WebP) via script Python.
 * Retorna a URL segura (HTTPS) ou false em caso de falha.
 */
function upload_image_to_cloudinary($file_path) {
    if (!file_exists($file_path)) {
        return false;
    }
    
    // Escapa o caminho do arquivo de origem para evitar problemas com shell injection
    $escaped_path = escapeshellarg($file_path);
    
    // Comando para chamar o script Python e passar o arquivo como argumento
    $cmd = "python3 " . escapeshellarg(__DIR__ . '/cloudinary_uploader.py') . " {$escaped_path} 2>&1";
    exec($cmd, $output_lines, $return_var);
    
    // Une as linhas de saída
    $output = implode("\n", $output_lines);
    
    // Se o comando terminou com sucesso (status 0)
    if ($return_var === 0) {
        $result = json_decode($output, true);
        if (isset($result['secure_url'])) {
            return $result['secure_url']; // Retorna a URL segura obtida
        }
    }
    
    return false; // Falha no processamento ou upload
}

/**
 * Gerencia a entrada de imagem de um formulário ou de URL externa.
 * Converte para WebP, envia para o Cloudinary e retorna a URL final.
 */
function handle_image_upload_or_url($file_array, $text_url) {
    // 1. Caso haja um arquivo enviado via upload de arquivo no formulário ($_FILES)
    if (!empty($file_array['tmp_name']) && is_uploaded_file($file_array['tmp_name'])) {
        $cloudinary_url = upload_image_to_cloudinary($file_array['tmp_name']);
        if ($cloudinary_url !== false) {
            return $cloudinary_url; // Sucesso ao subir o upload local
        }
    }
    
    // 2. Caso contrário, verifica se foi fornecida uma URL de texto
    if (!empty($text_url)) {
        $text_url = trim($text_url);
        
        // Se já for uma imagem hospedada no Cloudinary, retorna-a diretamente
        if (strpos($text_url, 'res.cloudinary.com') !== false) {
            return $text_url;
        }
        
        // Se for uma URL HTTP/HTTPS externa de internet
        if (preg_match('/^https?:\/\//i', $text_url)) {
            // Cria um arquivo temporário local e faz download da imagem
            $temp_source = tempnam(sys_get_temp_dir(), 'td_download_');
            $img_data = @file_get_contents($text_url);
            if ($img_data !== false && @file_put_contents($temp_source, $img_data) !== false) {
                $cloudinary_url = upload_image_to_cloudinary($temp_source);
                @unlink($temp_source); // Limpa o temporário baixado
                if ($cloudinary_url !== false) {
                    return $cloudinary_url;
                }
            } else {
                @unlink($temp_source);
            }
        }
        // Se for um caminho de arquivo interno do projeto local (ex: img/galaxy_a55.png)
        else {
            $local_path = __DIR__ . '/' . $text_url;
            if (file_exists($local_path)) {
                $cloudinary_url = upload_image_to_cloudinary($local_path);
                if ($cloudinary_url !== false) {
                    return $cloudinary_url;
                }
            }
        }
    }
    
    return $text_url; // Retorna o texto original como fallback
}
?>
