/**
 * JavaScript do projeto MimosShopBrasil.
 * Controla os filtros da vitrine de produtos e fornece feedback visual 
 * interativo ao se inscrever na newsletter de ofertas.
 */

/**
 * Filtra a grade de produtos baseada no tipo de loja ou ordenação por maior desconto.
 * @param {string} type - Tipo de filtro ('all', 'amazon', 'ml', 'discount')
 * @param {HTMLElement} btn - O botão de filtro clicado pelo usuário
 */
function filterProducts(type, btn) {
  // Remove a classe 'active' de todos os botões de filtro e adiciona apenas no botão que foi clicado
  document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');

  // Seleciona todos os cards de produtos na vitrine
  const cards = document.querySelectorAll('.product-card');
  cards.forEach(card => {
    // Se o filtro for 'todos' ou 'maior desconto', todos os produtos devem ficar visíveis
    if (type === 'all' || type === 'discount') {
      card.style.display = '';
      return;
    }
    // Caso contrário, mostra apenas o produto cuja loja corresponda ao filtro selecionado (amazon ou ml)
    const store = card.dataset.store;
    card.style.display = store === type ? '' : 'none';
  });

  // Se o filtro selecionado for 'discount', reordena os elementos na tela de acordo com a porcentagem de desconto
  if (type === 'discount') {
    const grid = document.getElementById('products-grid');
    const cardsArr = [...grid.querySelectorAll('.product-card')];
    
    // Ordena os cards em ordem decrescente de desconto
    cardsArr.sort((a, b) => {
      const getDisc = el => parseInt(el.querySelector('.badge-discount').textContent);
      return getDisc(b) - getDisc(a);
    });
    
    // Reaplica os elementos ordenados no grid da página
    cardsArr.forEach(c => grid.appendChild(c));
  }
}

/**
 * Função simuladora para rastrear cliques de afiliados direcionados às lojas.
 * @param {string} store - Nome da loja de destino (ex: amazon, ml)
 * @param {string} product - Identificador único do produto clicado
 */
function trackClick(store, product) {
  console.log(`[Afiliado] Clique registrado: Loja=${store} / Produto ID=${product}`);
}

/**
 * Gerencia a submissão do formulário de newsletter via AJAX para newsletter.php,
 * persistindo o número de WhatsApp no Supabase e aplicando feedback visual.
 * @param {Event} e - Evento de submit do formulário
 */
function handleSubscribe(e) {
  e.preventDefault(); // Impede o envio padrão do formulário
  
  const input = e.target.querySelector('input');
  const btn = e.target.querySelector('button');
  const whatsappValue = input.value.trim();
  
  // Desabilita o botão temporariamente para evitar duplo clique
  btn.disabled = true;
  const originalText = btn.textContent;
  btn.textContent = 'Enviando...';
  
  // Realiza a requisição fetch enviando o número do WhatsApp como JSON
  fetch('newsletter.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ whatsapp: whatsappValue })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Exibe feedback visual de sucesso na cor verde
      btn.textContent = '✓ Cadastrado!';
      btn.style.background = '#16A34A';
      btn.style.color = 'white';
      input.value = ''; // Limpa o campo do formulário
    } else {
      // Exibe erro retornado pelo backend
      alert(data.message || 'Erro ao realizar o cadastro.');
      btn.textContent = originalText;
    }
  })
  .catch(err => {
    console.error(err);
    alert('Ocorreu um erro de conexão. Tente novamente mais tarde.');
    btn.textContent = originalText;
  })
  .finally(() => {
    // Reabilita o botão após a resposta e remove os estilos de sucesso após 3 segundos
    btn.disabled = false;
    setTimeout(() => {
      if (btn.textContent === '✓ Cadastrado!') {
        btn.textContent = originalText;
        btn.style.background = '';
        btn.style.color = '';
      }
    }, 3000);
  });
  
  return false;
}

/**
 * Controla a exibição do menu de navegação responsivo (hambúrguer)
 * em dispositivos móveis, alternando as classes de ativação e animação.
 * @param {boolean|null} forceState - Se passado, força a abertura (true) ou fechamento (false) do menu
 */
function toggleMenu(forceState = null) {
  const nav = document.getElementById('main-nav');
  const toggleBtn = document.getElementById('menu-toggle');
  
  // Caso nav ou toggleBtn não existam na página (ex: visualização parcial), evita erros no console
  if (!nav || !toggleBtn) return;

  // Se um estado forçado foi fornecido (ex: fechar ao clicar em um link)
  if (forceState !== null) {
    if (forceState) {
      nav.classList.add('active');
      toggleBtn.classList.add('active');
    } else {
      nav.classList.remove('active');
      toggleBtn.classList.remove('active');
    }
    return;
  }

  // Comportamento padrão: alterna a classe 'active' (abre se estiver fechado, fecha se estiver aberto)
  nav.classList.toggle('active');
  toggleBtn.classList.toggle('active');
}

/**
 * Gerencia a submissão do formulário de encomendas de links (pedidosLink) via AJAX para link_request.php,
 * salvando os dados no Supabase e notificando o administrador.
 * @param {Event} e - Evento de submit do formulário
 */
function handleLinkRequest(e) {
  e.preventDefault(); // Impede o envio padrão do formulário
  
  const nomeInput = document.getElementById('request_nome');
  const whatsappInput = document.getElementById('request_whatsapp');
  const linkInput = document.getElementById('request_link');
  const btn = e.target.querySelector('button');
  
  // Desabilita o botão temporariamente para evitar duplo clique
  btn.disabled = true;
  const originalText = btn.textContent;
  btn.textContent = 'Enviando...';
  
  // Envia os dados no corpo do POST
  fetch('link_request.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      nome: nomeInput.value.trim(),
      whatsapp: whatsappInput.value.trim(),
      link: linkInput.value.trim()
    })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Feedback visual de sucesso com cor verde
      btn.textContent = '✓ Encomendado!';
      btn.style.background = '#16A34A';
      btn.style.color = 'white';
      nomeInput.value = '';
      whatsappInput.value = '';
      linkInput.value = '';
    } else {
      // Exibe erro do backend
      alert(data.message || 'Erro ao encomendar o seu link.');
      btn.textContent = originalText;
    }
  })
  .catch(err => {
    console.error(err);
    alert('Ocorreu um erro de conexão. Tente novamente mais tarde.');
    btn.textContent = originalText;
  })
  .finally(() => {
    // Reabilita o botão e limpa o feedback de sucesso após 3 segundos
    btn.disabled = false;
    setTimeout(() => {
      if (btn.textContent === '✓ Encomendado!') {
        btn.textContent = originalText;
        btn.style.background = '';
        btn.style.color = '';
      }
    }, 3000);
  });
  
  return false;
}

// Escuta a inicialização do DOM para adicionar as máscaras do input de telefone em tempo real
document.addEventListener('DOMContentLoaded', () => {
  // Configura a máscara para o campo da newsletter
  const phoneInput = document.getElementById('newsletter_whatsapp');
  if (phoneInput) {
    phoneInput.addEventListener('input', (e) => {
      let x = e.target.value.replace(/\D/g, '').match(/(\d{0,2})(\d{0,5})(\d{0,4})/);
      // Formata como (XX) XXXXX-XXXX
      e.target.value = !x[2] ? x[1] : '(' + x[1] + ') ' + x[2] + (x[3] ? '-' + x[3] : '');
    });
  }

  // Configura a máscara para o campo de encomendas de links
  const requestPhoneInput = document.getElementById('request_whatsapp');
  if (requestPhoneInput) {
    requestPhoneInput.addEventListener('input', (e) => {
      let x = e.target.value.replace(/\D/g, '').match(/(\d{0,2})(\d{0,5})(\d{0,4})/);
      // Formata como (XX) XXXXX-XXXX
      e.target.value = !x[2] ? x[1] : '(' + x[1] + ') ' + x[2] + (x[3] ? '-' + x[3] : '');
    });
  }

  // Configura a máscara para o campo de cadastro manual de WhatsApp no painel de administração
  const adminPhoneInput = document.getElementById('whatsapp');
  if (adminPhoneInput) {
    adminPhoneInput.addEventListener('input', (e) => {
      let x = e.target.value.replace(/\D/g, '').match(/(\d{0,2})(\d{0,5})(\d{0,4})/);
      // Formata como (XX) XXXXX-XXXX
      e.target.value = !x[2] ? x[1] : '(' + x[1] + ') ' + x[2] + (x[3] ? '-' + x[3] : '');
    });
  }

  // Comportamento do botão Voltar ao Topo (Back to Top)
  // Comentário de regra: Esta rotina escuta o scroll e gerencia a visibilidade do botão flutuante.
  const backToTopBtn = document.getElementById('back-to-top');
  if (backToTopBtn) {
    // Escuta o scroll da tela
    window.addEventListener('scroll', () => {
      if (window.scrollY > 300) {
        backToTopBtn.classList.add('show');
      } else {
        backToTopBtn.classList.remove('show');
      }
    });

    // Rola suavemente ao topo ao clicar
    backToTopBtn.addEventListener('click', () => {
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    });
  }

  // Comentário de regra: Inicializa o status dos corações de favoritos a partir do localStorage
  updateHeartButtons();
});

/**
 * Adiciona ou remove um ID de produto do localStorage (favoritos) e atualiza os botões visuais.
 * @param {number} productId - ID do produto
 * @param {Event} event - Evento de clique para impedir propagação e comportamento padrão
 */
function toggleFavorite(productId, event) {
  if (event) {
    event.preventDefault();
    event.stopPropagation();
  }
  
  // Comentário de regra: Obtém a lista atual de favoritos salvos em JSON no localStorage
  let favorites = JSON.parse(localStorage.getItem('mimos_favorites') || '[]');
  const index = favorites.indexOf(productId);
  
  if (index === -1) {
    // Adiciona à lista se não existir
    favorites.push(productId);
  } else {
    // Remove da lista se já existir
    favorites.splice(index, 1);
  }
  
  // Salva a lista atualizada de volta no localStorage
  localStorage.setItem('mimos_favorites', JSON.stringify(favorites));
  
  // Atualiza as cores dos corações
  updateHeartButtons();
  
  // Se o usuário estiver na página de favoritos, recarrega a página para atualizar a vitrine
  if (window.location.pathname.includes('favoritos.php')) {
    // Constrói a nova lista de IDs para recarregar com os parâmetros GET sincronizados
    const currentIds = favorites.join(',');
    window.location.href = 'favoritos.php?ids=' + currentIds;
  }
}

/**
 * Varre todos os botões com a classe .btn-wishlist e ativa o preenchimento vermelho se o ID estiver nos favoritos.
 */
function updateHeartButtons() {
  const favorites = JSON.parse(localStorage.getItem('mimos_favorites') || '[]');
  document.querySelectorAll('.btn-wishlist').forEach(btn => {
    const id = parseInt(btn.dataset.id);
    if (favorites.includes(id)) {
      btn.classList.add('active');
    } else {
      btn.classList.remove('active');
    }
  });
}

