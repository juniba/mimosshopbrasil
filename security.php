<?php
// security.php – Funções de segurança auxiliares
// Comentário: Implementa geração e verificação de tokens CSRF e sanitização de inputs.

/**
 * Gera um token CSRF único e o armazena na sessão.
 * @return string Token gerado
 */
function csrf_token(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifica se o token CSRF enviado via POST corresponde ao da sessão.
 * @return bool Verdadeiro se válido
 */
function csrf_check(): bool {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

/**
 * Sanitiza uma string para saída segura em HTML.
 */
function esc_html(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
