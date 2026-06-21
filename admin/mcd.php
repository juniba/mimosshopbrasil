<?php
/*
  mcd.php: Interface visual para autenticação de administradores (antigo login.php).
  Carrega o SDK do Supabase para validação e define a sessão do backend.
*/
require_once __DIR__ . '/../config.php';

// Se o usuário já estiver logado, redireciona diretamente para o painel
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: painel.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Título atualizado para a marca Mimos Shop Brasil -->
  <title>Login Administrativo – Mimos Shop Brasil</title>
  <!-- Preconnect e fontes do Google Fonts carregadas via head para otimização de renderização -->
  <!-- Comentário de regra: Este bloco carrega a fonte Inter de forma performática -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  
  <!-- Caminho relativo aos módulos CSS na raiz do projeto (subindo um nível da pasta admin/) -->
  <link rel="stylesheet" href="../css/base.css">
  <link rel="stylesheet" href="../css/components.css">
  <link rel="stylesheet" href="../css/admin.css">
</head>
<body class="login-page">

  <!-- Caixa de Login (Card com efeito Glassmorphism) -->
  <div class="login-box">
    <!-- Logo atualizado para Mimos Shop Brasil -->
    <div class="login-logo">
      Mimos<span>Shop</span>
    </div>
    <h2>Painel do Admin</h2>
    <p>Insira seu e-mail e senha cadastrados para acessar o gerenciamento.</p>

    <!-- Exibe mensagens de erros retornadas pelo Supabase -->
    <div id="login-error" class="login-error-box" style="display: none;"></div>

    <form id="login-form">
      <div class="login-form-group">
        <label for="email">E-mail</label>
        <input type="email" id="email" required placeholder="admin@MimosShopBrasil.com" autocomplete="email">
      </div>
      <div class="login-form-group">
        <label for="password">Senha</label>
        <input type="password" id="password" required placeholder="Digite sua senha" autocomplete="current-password">
      </div>
      
      <!-- Botão de submit estilizado -->
      <button type="submit" class="btn-login" id="btn-submit">
        <span>Acessar Painel</span>
        <div class="loader" id="loader" style="display: none;"></div>
      </button>
    </form>

    <div class="login-back">
      <a href="../index.php">← Voltar para a página inicial</a>
    </div>
  </div>

  <!-- SDK do Supabase JS via CDN -->
  <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
  <script>
    // Recupera configurações do PHP
    const config = <?php echo exportSupabaseConfig(); ?>;
    
    // Inicializa o cliente do Supabase usando uma variável de nome diferente da biblioteca global
    const supabaseClient = supabase.createClient(config.url, config.key);

    const form = document.getElementById('login-form');
    const errorBox = document.getElementById('login-error');
    const submitBtn = document.getElementById('btn-submit');
    const loader = document.getElementById('loader');
    const btnText = submitBtn.querySelector('span');

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      
      const email = document.getElementById('email').value;
      const password = document.getElementById('password').value;
      
      // Limpa erros anteriores e exibe animação de carregamento
      errorBox.style.display = 'none';
      btnText.style.display = 'none';
      loader.style.display = 'inline-block';
      submitBtn.disabled = true;

      try {
        // Realiza o login do usuário no Supabase Auth usando o cliente instanciado
        const { data, error } = await supabaseClient.auth.signInWithPassword({
          email: email,
          password: password
        });

        if (error) {
          throw error;
        }

        // Se o login foi bem sucedido, envia os dados para iniciar a sessão no PHP
        const response = await fetch('auth_session.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            action: 'login',
            email: data.user.email,
            access_token: data.session.access_token
          })
        });

        const sessionResult = await response.json();

        if (sessionResult.success) {
          // Redireciona para o Painel Administrativo
          window.location.href = 'painel.php';
        } else {
          throw new Error('Falha ao registrar sessão no servidor.');
        }

      } catch (err) {
        // Exibe o erro na interface do usuário
        errorBox.textContent = err.message || 'Erro ao realizar login.';
        errorBox.style.display = 'block';
        btnText.style.display = 'inline-block';
        loader.style.display = 'none';
        submitBtn.disabled = false;
      }
    });
  </script>
</body>
</html>
