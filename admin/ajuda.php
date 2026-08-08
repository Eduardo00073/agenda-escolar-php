<?php
/**
 * Agenda Escolar — ProfEdu: Assistente Virtual com IA
 */
require_once __DIR__ . '/includes/admin-header.php';
?>

<!-- ── Cabeçalho da Página ── -->
<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:28px; flex-wrap:wrap; gap:16px;">
    <div>
        <h2 style="font-size:24px; font-weight:700; margin-bottom:6px; display:flex; align-items:center; gap:10px;">
            <i data-lucide="bot" size="28" style="color:var(--primary);"></i> Assistente do Prof. Edu. [IA]
        </h2>
        <p style="color:var(--text-muted); font-size:14px;">Assistente inteligente desenvolvido pelo <strong>Professor Eduardo Junior Alcântara da Silva</strong>. Tire suas dúvidas sobre o sistema Agenda Escolar.</p>
    </div>
    <div style="display:flex; align-items:center; gap:8px; background:var(--success-bg); border:1px solid var(--success); border-radius:50px; padding:6px 14px;">
        <span style="width:8px; height:8px; border-radius:50%; background:var(--success); display:inline-block; animation: pulse 2s infinite;"></span>
        <span style="font-size:13px; font-weight:600; color:var(--success);">Online</span>
    </div>
</div>

<div class="grid-ajuda">

    <!-- ── Área do Chat ── -->
    <div class="card" style="padding:0; display:flex; flex-direction:column; height:620px;">

        <!-- Header do chat -->
        <div style="display:flex; align-items:center; gap:14px; padding:18px 20px; border-bottom:1px solid var(--border); background:linear-gradient(135deg, #18181b, #27272a); border-radius:var(--radius-md) var(--radius-md) 0 0;">
            <div style="width:46px; height:46px; border-radius:50%; background:var(--primary); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <i data-lucide="bot" size="22" style="color:white;"></i>
            </div>
            <div>
                <div style="font-weight:700; font-size:15px; color:white;">Assistente do Prof. Edu. [IA]</div>
                <div style="font-size:12px; color:#a1a1aa;">Suporte inteligente ao painel</div>
            </div>
        </div>

        <!-- Área de mensagens -->
        <div id="chat-messages" style="flex:1; overflow-y:auto; padding:20px; display:flex; flex-direction:column; gap:16px; scroll-behavior:smooth;">
            <!-- Mensagem de boas-vindas -->
            <div class="chat-bubble chat-bubble-bot">
                <div class="chat-avatar" style="background:var(--primary); color:white; display:flex; align-items:center; justify-content:center;">
                    <i data-lucide="bot" size="14"></i>
                </div>
                <div class="chat-content">
                    <p>Olá! Sou o <strong>Assistente do Prof. Edu. [IA]</strong>, seu ajudante virtual aqui no Agenda Escolar!</p>
                    <p style="margin-top:8px;">Fui criado para tirar suas dúvidas e orientar no uso do painel do Agenda Escolar. Pode me fazer qualquer pergunta sobre as telas ou sobre o funcionamento do sistema.</p>
                    <p style="margin-top:8px; font-size:12px; color:var(--text-muted);">Clique em uma das sugestões rápidas à direita ou digite sua dúvida no campo abaixo.</p>
                </div>
            </div>
        </div>

        <!-- Indicador de digitando -->
        <div id="typing-indicator" style="display:none; padding:0 20px 10px; align-items:center; gap:10px;">
            <div style="width:32px; height:32px; border-radius:50%; background:var(--primary); display:flex; align-items:center; justify-content:center; flex-shrink:0; color:white;">
                <i data-lucide="bot" size="14"></i>
            </div>
            <div class="typing-dots">
                <span></span><span></span><span></span>
            </div>
        </div>

        <!-- Campo de entrada -->
        <div style="padding:16px 20px; border-top:1px solid var(--border); display:flex; gap:10px; align-items:flex-end;">
            <textarea id="chat-input"
                placeholder="Digite sua dúvida sobre o Agenda Escolar..."
                rows="2"
                style="flex:1; resize:none; border:1px solid var(--border); border-radius:var(--radius-md); padding:10px 14px; font-size:14px; font-family:inherit; background:var(--bg-main); color:var(--text-main); outline:none; transition:var(--transition); line-height:1.5;"
                onkeydown="handleInputKeydown(event)"
                oninput="this.style.height='auto'; this.style.height=Math.min(this.scrollHeight, 100)+'px'"
            ></textarea>
            <button id="btn-send" onclick="enviarPergunta()" class="btn btn-primary" style="height:46px; width:46px; padding:0; display:flex; align-items:center; justify-content:center; border-radius:var(--radius-md); flex-shrink:0;">
                <i data-lucide="send" size="18"></i>
            </button>
        </div>
    </div>

    <!-- ── Painel Lateral: Sugestões ── -->
    <div style="display:flex; flex-direction:column; gap:16px;">

        <!-- Perguntas Sugeridas -->
        <div class="card">
            <div style="font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-muted); margin-bottom:14px; display:flex; align-items:center; gap:6px;">
                <i data-lucide="zap" size="14" style="color:var(--primary);"></i>
                Perguntas rápidas
            </div>
            <div style="display:flex; flex-direction:column; gap:8px;" id="sugestoes-container">
                <?php
                $sugestoes = [
                    ['icone' => 'calendar-plus', 'texto' => 'Como cadastrar um novo horário de visita?'],
                    ['icone' => 'check-circle', 'texto' => 'Como confirmar ou realizar uma visita?'],
                    ['icone' => 'book-open', 'texto' => 'O que são segmentos de ensino?'],
                    ['icone' => 'calendar-range', 'texto' => 'Como gerar horários para várias semanas?'],
                    ['icone' => 'key-round', 'texto' => 'Como alterar minha senha?'],
                    ['icone' => 'file-text', 'texto' => 'O visitante recebe algum comprovante?'],
                    ['icone' => 'qr-code', 'texto' => 'Como funciona o QR Code?'],
                ];
                foreach ($sugestoes as $s): ?>
                    <button class="sugestao-btn" onclick="perguntaRapida(<?php echo htmlspecialchars(json_encode($s['texto']), ENT_QUOTES, 'UTF-8'); ?>)">
                        <i data-lucide="<?php echo $s['icone']; ?>" size="16" style="color:var(--primary);"></i>
                        <span><?php echo htmlspecialchars($s['texto']); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Sobre o Professor Eduardo -->
        <div class="card" style="background:linear-gradient(135deg, #18181b, #27272a); border-color:var(--border);">
            <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
                <div style="width:40px; height:40px; border-radius:50%; background:var(--primary); display:flex; align-items:center; justify-content:center; flex-shrink:0; color:white;">
                    <i data-lucide="user" size="18"></i>
                </div>
                <div>
                    <div style="font-weight:700; font-size:13px; color:white;">Prof. Eduardo Junior</div>
                    <div style="font-size:11px; color:#a1a1aa;">Desenvolvedor do Agenda Escolar</div>
                </div>
            </div>
            <p style="font-size:12px; color:#a1a1aa; line-height:1.6;">Professor de TI e Desenvolvedor Full Stack. Criador do sistema de agendamento de visitas do Colégio Exemplo Modelo.</p>
        </div>


        <!-- Contador de uso -->
        <div style="text-align:center; font-size:12px; color:var(--text-muted);">
            <i data-lucide="info" size="12" style="vertical-align:middle; margin-right:4px;"></i>
            <span id="uso-label">Pronto para ajudar!</span>
        </div>
    </div>

</div>

<!-- ══════════════════════════════════════════
     ESTILOS DO CHAT
     ══════════════════════════════════════════ -->
<style>
/* ── Bolhas de chat ── */
.chat-bubble {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    animation: fadeInUp 0.3s ease;
}
.chat-bubble-bot {
    flex-direction: row;
}
.chat-bubble-user {
    flex-direction: row-reverse;
}
.chat-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
    margin-top: 2px;
}
.chat-content {
    max-width: 80%;
    padding: 12px 16px;
    border-radius: 16px;
    font-size: 14px;
    line-height: 1.6;
}
.chat-bubble-bot .chat-content {
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 4px 16px 16px 16px;
    color: var(--text-main);
}
.chat-bubble-user .chat-content {
    background: var(--primary);
    color: white;
    border-radius: 16px 4px 16px 16px;
}
.chat-bubble-user .chat-avatar {
    background: var(--secondary);
    font-size: 14px;
}
.chat-content p { margin: 0; }
.chat-content p + p { margin-top: 6px; }

/* ── Markdown rendering no conteúdo da IA ── */
.chat-content strong { font-weight: 700; }
.chat-content ul, .chat-content ol {
    margin: 8px 0 0 18px;
    padding: 0;
}
.chat-content li { margin-bottom: 4px; }

/* ── Typing dots ── */
.typing-dots {
    display: flex;
    align-items: center;
    gap: 4px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 12px 16px;
}
.typing-dots span {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--text-muted);
    animation: typing-bounce 1.2s infinite;
}
.typing-dots span:nth-child(2) { animation-delay: 0.2s; }
.typing-dots span:nth-child(3) { animation-delay: 0.4s; }
@keyframes typing-bounce {
    0%, 80%, 100% { transform: translateY(0); opacity: 0.4; }
    40%            { transform: translateY(-6px); opacity: 1; }
}

/* ── Botões de sugestão ── */
.sugestao-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    text-align: left;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 9px 12px;
    font-size: 13px;
    color: var(--text-main);
    cursor: pointer;
    transition: var(--transition);
    line-height: 1.4;
}
.sugestao-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--primary-bg);
    transform: translateX(3px);
}
.sugestao-btn span:first-child {
    font-size: 16px;
    flex-shrink: 0;
}

/* ── Input focus ── */
#chat-input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-bg);
}

/* ── Animações ── */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50%       { opacity: 0.5; transform: scale(0.8); }
}

/* ── Responsivo ── */
@media (max-width: 860px) {
    .card + div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<!-- ══════════════════════════════════════════
     JAVASCRIPT DO CHAT
     ══════════════════════════════════════════ -->
<script>
// ── Respostas pré-prontas (não consomem créditos da API) ──
const RESPOSTAS_CACHE = {
    "Como cadastrar um novo horário de visita?": "Acesse **Horários Disponíveis** no menu lateral. Clique em **Novo Horário** e escolha a aba:\n\n• **Data Específica** — para um dia avulso. Preencha data, hora de início, hora de término e quantidade de vagas.\n\n• **Vários Dias de Uma Vez** — para abrir várias semanas. Siga os 3 passos: escolha o período (de/até), selecione os dias da semana, defina o horário e as vagas. Uma prévia mostrará quantos horários serão criados antes de confirmar.",
    "Como confirmar ou realizar uma visita?": "Acesse **Agendamentos** no menu. Para ações rápidas, use os botões na tabela:\n\n- Botão laranja **(✓)** = Confirmar (status Pendente)\n- Botão verde **(☺)** = Marcar como Realizada (status Confirmado)\n- Botão vermelho **(✕)** = Cancelar\n\nOu clique em **Detalhes** para abrir a ficha completa com mais opções.",
    "O que são segmentos de ensino?": "São as divisões escolares que aparecem no formulário público para o visitante escolher. Exemplos: Berçário, Educação Infantil, Anos Iniciais, Anos Finais, Ensino Médio.\n\nAcesse **Segmentos de Ensino** no menu para adicionar, editar ou ativar/desativar cada um. A **ordem de exibição** define a sequência na lista (menor número aparece primeiro).",
    "Como gerar horários para várias semanas?": "Na tela **Horários Disponíveis**, clique em **Novo Horário** e escolha a aba **Vários Dias de Uma Vez**.\n\n1. Defina o período: data de início e término (ex: 01/07 a 31/07)\n2. Selecione os dias da semana clicando nos botões (ex: Segunda, Quarta, Sexta)\n3. Defina o horário (ex: 08:00 às 11:00) e as vagas (ex: 3)\n\nAntes de salvar, uma **prévia em verde** mostrará exatamente quantos horários serão criados.",
    "Como alterar minha senha?": "Acesse **Configurações** no menu lateral. No card **Alterar Senha**, preencha:\n\n1. Sua senha atual\n2. A nova senha (mínimo 6 caracteres)\n3. Confirmação da nova senha\n\nClique em **Alterar Credencial**. Nota: Não há recuperação por e-mail — guarde bem sua senha!",
    "O visitante recebe algum comprovante?": "Sim! Após finalizar o agendamento, o visitante é redirecionado para uma **página de confirmação** com:\n\n• Código de acompanhamento **XV-{número}** em destaque\n• **QR Code** com os dados do agendamento\n• Botão **Adicionar ao Google Agenda** que cria automaticamente o evento no celular dele\n• Resumo completo: data, horário, dados da criança\n\nO visitante pode guardar ou imprimir essa página como comprovante.",
    "Como funciona o QR Code?": "Cada agendamento gera um QR Code único na página de confirmação. Quando escaneado, ele abre a ficha completa do agendamento diretamente no sistema.\n\nSe você estiver **logado como administrador**, ao escanear o QR Code verá a ficha completa e poderá marcar a visita como **Realizada** na mesma tela. Muito útil na hora da visita presencial!"
};

let perguntasEnviadas = 0;
const csrfToken = '<?php echo gerarCSRFToken(); ?>';

// ── Renderizar Markdown simples ──
function renderMarkdown(text) {
    return text
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        .replace(/\n\n/g, '</p><p>')
        .replace(/\n/g, '<br>')
        .replace(/^/, '<p>')
        .replace(/$/, '</p>')
        // Converter listas com bullet • ou numeradas
        .replace(/<p>(•[^<]+(?:<br>[^<]*)*)<\/p>/g, (match, list) => {
            const items = list.split('<br>').map(i => i.replace(/^•\s*/, '').trim()).filter(Boolean);
            return '<ul>' + items.map(i => `<li>${i}</li>`).join('') + '</ul>';
        })
        .replace(/<p>((?:\d+️⃣[^<]+(?:<br>[^<]*)*)+)<\/p>/g, (match, list) => {
            const items = list.split('<br>').map(i => i.replace(/^\d+️⃣\s*/, '').trim()).filter(Boolean);
            return '<ol>' + items.map(i => `<li>${i}</li>`).join('') + '</ol>';
        });
}

// ── Adicionar mensagem ao chat ──
function adicionarMensagem(texto, tipo) {
    const container = document.getElementById('chat-messages');
    const bubble = document.createElement('div');
    bubble.className = `chat-bubble chat-bubble-${tipo}`;

    if (tipo === 'bot') {
        bubble.innerHTML = `
            <div class="chat-avatar" style="background:var(--primary); color:white; display:flex; align-items:center; justify-content:center;">
                <i data-lucide="bot" size="14"></i>
            </div>
            <div class="chat-content">${renderMarkdown(texto)}</div>
        `;
    } else {
        const adminIniciais = '<?php 
            $nomes = explode(' ', getAdminNome());
            $iniciais = '';
            foreach (array_slice($nomes, 0, 2) as $n) { $iniciais .= strtoupper(substr($n, 0, 1)); }
            echo $iniciais;
        ?>';
        bubble.innerHTML = `
            <div class="chat-avatar" style="background:var(--secondary); font-size:12px; font-weight:700;">${adminIniciais}</div>
            <div class="chat-content">${htmlEscape(texto)}</div>
        `;
    }

    container.appendChild(bubble);
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    container.scrollTop = container.scrollHeight;
    return bubble;
}

function htmlEscape(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Mostrar / ocultar indicador de digitando ──
function mostrarDigitando(mostrar) {
    const indicator = document.getElementById('typing-indicator');
    indicator.style.display = mostrar ? 'flex' : 'none';
    if (mostrar) {
        document.getElementById('chat-messages').scrollTop = 9999;
    }
}

// ── Atualizar contador de uso ──
function atualizarUso() {
    perguntasEnviadas++;
    const label = document.getElementById('uso-label');
    if (perguntasEnviadas < 10) {
        label.textContent = `${perguntasEnviadas} pergunta(s) enviada(s)`;
    } else {
        label.textContent = `${perguntasEnviadas} perguntas enviadas hoje`;
    }
}

// ── Pergunta rápida (usa cache, sem chamar a API) ──
function perguntaRapida(texto) {
    adicionarMensagem(texto, 'user');

    // Verificar no cache
    const resposta = RESPOSTAS_CACHE[texto];
    if (resposta) {
        setTimeout(() => {
            adicionarMensagem(resposta, 'bot');
            atualizarUso();
        }, 600); // Simula um pequeno delay para parecer natural
    } else {
        // Se não houver cache, chama a IA
        chamarAPI(texto);
    }

    // Limpar input e focar
    document.getElementById('chat-input').value = '';
    document.getElementById('chat-input').focus();
}

// ── Enviar pergunta livre ──
function enviarPergunta() {
    const input = document.getElementById('chat-input');
    const pergunta = input.value.trim();
    if (!pergunta) return;

    input.value = '';
    input.style.height = 'auto';
    adicionarMensagem(pergunta, 'user');

    // Verificar no cache primeiro
    const respostaCached = RESPOSTAS_CACHE[pergunta];
    if (respostaCached) {
        setTimeout(() => {
            adicionarMensagem(respostaCached, 'bot');
            atualizarUso();
        }, 600);
        return;
    }

    // Chamar a IA
    chamarAPI(pergunta);
}

// ── Chamar a API Gemini via proxy PHP ──
async function chamarAPI(pergunta) {
    mostrarDigitando(true);
    document.getElementById('btn-send').disabled = true;
    document.getElementById('chat-input').disabled = true;

    try {
        const response = await fetch('api/chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pergunta: pergunta })
        });

        const data = await response.json();
        mostrarDigitando(false);

        if (!response.ok || data.error) {
            adicionarMensagem('❌ ' + (data.error || 'Ocorreu um erro inesperado. Tente novamente.'), 'bot');
        } else {
            adicionarMensagem(data.resposta, 'bot');
            atualizarUso();
        }
    } catch (err) {
        mostrarDigitando(false);
        adicionarMensagem('❌ Não consegui me conectar ao servidor. Verifique sua conexão e tente novamente.', 'bot');
    } finally {
        document.getElementById('btn-send').disabled = false;
        document.getElementById('chat-input').disabled = false;
        document.getElementById('chat-input').focus();
    }
}

// ── Enter para enviar (Shift+Enter para nova linha) ──
function handleInputKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        enviarPergunta();
    }
}

// ── Inicialização ──
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('chat-input').focus();
    if (typeof lucide !== 'undefined') lucide.createIcons();
});
</script>

<?php
require_once __DIR__ . '/includes/admin-footer.php';
?>
