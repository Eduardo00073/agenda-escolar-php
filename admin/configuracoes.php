<?php
/**
 * Agenda Escolar - Configurações Gerais do Sistema e Senha do Admin
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../db.php';

// Garantir autenticação antes de qualquer ação
requireLogin();

$db = db();
$mensagem_sucesso = '';
$mensagem_erro = '';

// Buscar configurações existentes do banco
$configs = [];
try {
    $stmtCfg = $db->query("SELECT chave, valor FROM configuracoes");
    while ($row = $stmtCfg->fetch()) {
        $configs[$row['chave']] = $row['valor'];
    }
} catch (Exception $e) {
    // Silencia
}

// Processar formulários (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $acao = sanitizar($_POST['acao']);
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (validarCSRFToken($csrf_token)) {
        
        // Ação: Atualizar Dados Gerais
        if ($acao === 'salvar_dados') {
            $nome_escola = sanitizar($_POST['nome_escola'] ?? '');
            $telefone_contato = sanitizar($_POST['telefone_contato'] ?? '');
            $email_contato = sanitizar($_POST['email_contato'] ?? '');
            $endereco_escola = sanitizar($_POST['endereco_escola'] ?? '');
            $mensagem_sucesso_field = sanitizar($_POST['mensagem_sucesso'] ?? '');
            $email_notificacao = sanitizar($_POST['email_notificacao'] ?? '');
            
            if (empty($nome_escola) || empty($telefone_contato) || empty($email_contato)) {
                $mensagem_erro = 'Os campos Nome da Escola, Telefone e E-mail são obrigatórios.';
            } else {
                try {
                    $stmtUpsert = $db->prepare("
                        INSERT INTO configuracoes (chave, valor) 
                        VALUES (?, ?) 
                        ON DUPLICATE KEY UPDATE valor = VALUES(valor)
                    ");
                    
                    $stmtUpsert->execute(['nome_escola', $nome_escola]);
                    $stmtUpsert->execute(['telefone_contato', $telefone_contato]);
                    $stmtUpsert->execute(['email_contato', $email_contato]);
                    $stmtUpsert->execute(['endereco_escola', $endereco_escola]);
                    $stmtUpsert->execute(['mensagem_sucesso', $mensagem_sucesso_field]);
                    $stmtUpsert->execute(['email_notificacao', $email_notificacao]);
                    
                    flash('config_msg', 'Configurações atualizadas com sucesso!', 'success');
                    redirect(BASE_URL . '/admin/configuracoes.php');
                } catch (Exception $e) {
                    $mensagem_erro = 'Erro ao salvar configurações no banco de dados.';
                }
            }
        }
        
        // Ação: Salvar Regras de Agendamento (Antecedência Mínima)
        elseif ($acao === 'salvar_regras_agendamento') {
            $antecedencia_raw = $_POST['antecedencia_minima_horas'] ?? '0';
            $antecedencia_val = filter_var($antecedencia_raw, FILTER_VALIDATE_FLOAT);
            if ($antecedencia_val === false || $antecedencia_val < 0) {
                $antecedencia_val = 0;
            }
            // Limitar a 168h (7 dias) para evitar valores absurdos
            $antecedencia_val = min(168, $antecedencia_val);
            try {
                $stmtUpsertAnt = $db->prepare("
                    INSERT INTO configuracoes (chave, valor) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE valor = VALUES(valor)
                ");
                $stmtUpsertAnt->execute(['antecedencia_minima_horas', (string)$antecedencia_val]);
                flash('config_msg', 'Regra de antecedência atualizada com sucesso!', 'success');
                redirect(BASE_URL . '/admin/configuracoes.php');
            } catch (Exception $e) {
                $mensagem_erro = 'Erro ao salvar a regra de antecedência.';
            }
        }
        
        // Ação: Salvar E-mail de Notificação do Usuário Logado
        elseif ($acao === 'salvar_email_notificacao') {
            $email_notificacao_usuario = sanitizar($_POST['email_notificacao_usuario'] ?? '');
            $admin_id = getAdminId();
            try {
                // ADD COLUMN IF NOT EXISTS (compatível sem migration)
                try {
                    $db->exec("ALTER TABLE administradores ADD COLUMN IF NOT EXISTS email_notificacao VARCHAR(150) DEFAULT NULL");
                } catch (Exception $ex2) { /* silencia se já existir */ }
                
                $stmtNotif = $db->prepare("UPDATE administradores SET email_notificacao = ? WHERE id = ?");
                $stmtNotif->execute([empty($email_notificacao_usuario) ? null : $email_notificacao_usuario, $admin_id]);
                flash('config_msg', 'E-mail de notificação atualizado com sucesso!', 'success');
                redirect(BASE_URL . '/admin/configuracoes.php');
            } catch (Exception $e) {
                $mensagem_erro = 'Erro ao salvar e-mail de notificação.';
            }
        }
        
        // Ação: Alterar Senha Admin
        elseif ($acao === 'alterar_senha') {
            $senha_atual = $_POST['senha_atual'] ?? '';
            $nova_senha = $_POST['nova_senha'] ?? '';
            $confirmacao = $_POST['confirmacao'] ?? '';
            
            if (empty($senha_atual) || empty($nova_senha) || empty($confirmacao)) {
                $mensagem_erro = 'Todos os campos de senha são obrigatórios.';
            } elseif ($nova_senha !== $confirmacao) {
                $mensagem_erro = 'A nova senha e a confirmação não conferem.';
            } elseif (strlen($nova_senha) < 6) {
                $mensagem_erro = 'A nova senha deve possuir pelo menos 6 caracteres.';
            } else {
                try {
                    $admin_id = getAdminId();
                    
                    // Buscar senha atual no banco
                    $stmtCheck = $db->prepare("SELECT senha FROM administradores WHERE id = ? LIMIT 1");
                    $stmtCheck->execute([$admin_id]);
                    $senha_hash = $stmtCheck->fetchColumn();
                    
                    if ($senha_hash && password_verify($senha_atual, $senha_hash)) {
                        // Criptografar nova senha
                        $novo_hash = password_hash($nova_senha, PASSWORD_BCRYPT);
                        
                        $stmtUpdate = $db->prepare("UPDATE administradores SET senha = ? WHERE id = ?");
                        $stmtUpdate->execute([$novo_hash, $admin_id]);
                        
                        flash('config_msg', 'Senha do usuário alterada com sucesso!', 'success');
                        redirect(BASE_URL . '/admin/configuracoes.php');
                    } else {
                        $mensagem_erro = 'A senha atual informada está incorreta.';
                    }
                } catch (Exception $e) {
                    $mensagem_erro = 'Erro ao alterar a senha administrativa.';
                }
            }
        }
    }
}

// Buscar email_notificacao do usuário logado
$email_notificacao_usuario = '';
try {
    $db->exec("ALTER TABLE administradores ADD COLUMN IF NOT EXISTS email_notificacao VARCHAR(150) DEFAULT NULL");
} catch (Exception $ex3) { /* silencia */ }
try {
    $stmtMeuEmail = $db->prepare("SELECT email_notificacao FROM administradores WHERE id = ? LIMIT 1");
    $stmtMeuEmail->execute([getAdminId()]);
    $email_notificacao_usuario = $stmtMeuEmail->fetchColumn() ?? '';
} catch (Exception $ex4) { /* silencia */ }

// Busca o valor atual de antecedência mínima para pré-preencher o formulário
$antecedencia_atual = (float)($configs['antecedencia_minima_horas'] ?? 2);

// Carrega o cabeçalho visual apenas após processar as ações (evitando "headers already sent" ao redirecionar)
require_once __DIR__ . '/includes/admin-header.php';
?>

<div style="margin-bottom: 30px;">
    <h2 style="font-size: 24px; font-weight: 700; margin-bottom: 6px;">Configurações do Sistema</h2>
    <p style="color: var(--text-muted); font-size: 14px;">Gerencie as definições da escola, regras de agendamento, mensagens de retorno e credenciais de acesso.</p>
</div>

<?php if (!empty($mensagem_erro)): ?>
    <div style="background-color: var(--danger-bg); color: var(--danger); border: 1px solid #fecaca; padding: 16px; border-radius: var(--radius-md); margin-bottom: 24px; font-size: 14px;">
        <i data-lucide="alert-circle" size="18" style="vertical-align: middle; margin-right: 8px;"></i>
        <?php echo $mensagem_erro; ?>
    </div>
<?php endif; ?>

<!-- Card: Regras de Agendamento (Antecedência Mínima) -->
<div class="card" style="margin-bottom: 28px; overflow: hidden;">

    <!-- Faixa de destaque no topo do card -->
    <div style="background: linear-gradient(135deg, #ff5000 0%, #ff7a3d 100%); padding: 16px 24px; display: flex; align-items: center; gap: 12px;">
        <div style="width: 36px; height: 36px; background: rgba(255,255,255,0.2); border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <i data-lucide="alarm-clock" size="18" style="color: white;"></i>
        </div>
        <div>
            <h3 style="font-size: 15px; font-weight: 700; color: white; margin: 0 0 2px;">Regras de Agendamento</h3>
            <p style="font-size: 12px; color: rgba(255,255,255,0.8); margin: 0;">Controle com quantas horas de antecedência os visitantes podem marcar uma visita.</p>
        </div>
    </div>

    <div style="padding: 24px;">
        <form method="POST" action="configuracoes.php" id="formAntecedencia">
            <input type="hidden" name="csrf_token" value="<?php echo gerarCSRFToken(); ?>">
            <input type="hidden" name="acao" value="salvar_regras_agendamento">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 28px; align-items: start;">

                <!-- Coluna esquerda: controle numérico -->
                <div>
                    <label style="font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 5px; margin-bottom: 10px;">
                        <i data-lucide="timer" size="13" style="color: var(--primary);"></i>
                        Antecedência Mínima
                    </label>

                    <!-- Stepper principal -->
                    <div style="display: flex; align-items: stretch; border: 2px solid var(--border); border-radius: 12px; overflow: hidden; background: white; box-shadow: 0 1px 4px rgba(0,0,0,0.05); transition: border-color 0.2s;" id="stepperWrapper">
                        <button type="button" id="btnMenos"
                            style="width: 52px; background: #f8fafc; border: none; border-right: 1px solid var(--border); cursor: pointer; color: var(--text-muted); display: flex; align-items: center; justify-content: center; transition: all 0.15s; font-size: 0;"
                            onmouseover="this.style.background='#f1f5f9';this.style.color='var(--primary)'" onmouseout="this.style.background='#f8fafc';this.style.color='var(--text-muted)'"
                            title="Diminuir 1h">
                            <i data-lucide="minus" size="18"></i>
                        </button>
                        <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 14px 8px;">
                            <input type="number" id="antecedencia_minima_horas" name="antecedencia_minima_horas"
                                value="<?php echo $antecedencia_atual; ?>"
                                min="0" max="168" step="0.5"
                                style="width: 100%; border: none; text-align: center; font-size: 28px; font-weight: 800; color: var(--text-main); outline: none; -moz-appearance: textfield; appearance: textfield; background: transparent; line-height: 1;"
                                oninput="atualizarPreview()">
                            <span style="font-size: 11px; color: var(--text-muted); font-weight: 500; margin-top: 2px;" id="labelUnidade">hora(s)</span>
                        </div>
                        <button type="button" id="btnMais"
                            style="width: 52px; background: #f8fafc; border: none; border-left: 1px solid var(--border); cursor: pointer; color: var(--text-muted); display: flex; align-items: center; justify-content: center; transition: all 0.15s; font-size: 0;"
                            onmouseover="this.style.background='#f1f5f9';this.style.color='var(--primary)'" onmouseout="this.style.background='#f8fafc';this.style.color='var(--text-muted)'"
                            title="Aumentar 1h">
                            <i data-lucide="plus" size="18"></i>
                        </button>
                    </div>

                    <!-- Atalhos rápidos -->
                    <div style="margin-top: 10px; display: flex; gap: 6px; flex-wrap: wrap;">
                        <button type="button" class="quick-set" data-val="0"  style="padding: 4px 10px; border-radius: 20px; border: 1px solid var(--border); background: #f8fafc; font-size: 11px; font-weight: 600; color: var(--text-muted); cursor: pointer; transition: all 0.15s;">Sem limite</button>
                        <button type="button" class="quick-set" data-val="1"  style="padding: 4px 10px; border-radius: 20px; border: 1px solid var(--border); background: #f8fafc; font-size: 11px; font-weight: 600; color: var(--text-muted); cursor: pointer; transition: all 0.15s;">1h</button>
                        <button type="button" class="quick-set" data-val="2"  style="padding: 4px 10px; border-radius: 20px; border: 1px solid var(--border); background: #f8fafc; font-size: 11px; font-weight: 600; color: var(--text-muted); cursor: pointer; transition: all 0.15s;">2h</button>
                        <button type="button" class="quick-set" data-val="4"  style="padding: 4px 10px; border-radius: 20px; border: 1px solid var(--border); background: #f8fafc; font-size: 11px; font-weight: 600; color: var(--text-muted); cursor: pointer; transition: all 0.15s;">4h</button>
                        <button type="button" class="quick-set" data-val="24" style="padding: 4px 10px; border-radius: 20px; border: 1px solid var(--border); background: #f8fafc; font-size: 11px; font-weight: 600; color: var(--text-muted); cursor: pointer; transition: all 0.15s;">1 dia</button>
                        <button type="button" class="quick-set" data-val="48" style="padding: 4px 10px; border-radius: 20px; border: 1px solid var(--border); background: #f8fafc; font-size: 11px; font-weight: 600; color: var(--text-muted); cursor: pointer; transition: all 0.15s;">2 dias</button>
                    </div>

                    <small style="color: var(--text-muted); font-size: 11px; margin-top: 8px; display: flex; align-items: center; gap: 4px;">
                        <i data-lucide="info" size="11"></i>
                        Aceita decimais (ex: 1.5 = 1h30min). Máximo: 168h (7 dias).
                    </small>
                </div>

                <!-- Coluna direita: preview dinâmico -->
                <div>
                    <label style="font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 5px; margin-bottom: 10px;">
                        <i data-lucide="eye" size="13" style="color: var(--primary);"></i>
                        Efeito Prático
                    </label>

                    <!-- Caixa de descrição -->
                    <div id="previewAntecedencia"
                        style="background: #fff7f5; border: 1.5px solid #ffd5c8; border-radius: 10px; padding: 14px 16px; font-size: 13px; color: #9a3412; line-height: 1.65; min-height: 60px; display: flex; align-items: flex-start; gap: 10px;">
                    </div>

                    <!-- Caixa de exemplo -->
                    <div id="previewExemplo"
                        style="margin-top: 10px; background: #f0fdf4; border: 1px solid #86efac; border-radius: 10px; padding: 11px 14px; font-size: 12px; color: #166534; line-height: 1.6; display: flex; align-items: center; gap: 8px;">
                    </div>
                </div>
            </div>

            <div style="margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                <p style="font-size: 12px; color: var(--text-muted); margin: 0; display: flex; align-items: center; gap: 5px;">
                    <i data-lucide="zap" size="13" style="color: #f59e0b;"></i>
                    A mudança entra em vigor imediatamente para todos os visitantes.
                </p>
                <button type="submit" class="btn btn-primary" style="gap: 6px;">
                    <i data-lucide="save" size="15"></i> Salvar Regra
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Ícones SVG Lucide inline para uso no preview dinâmico (substitui emojis)
const ICON_TIMER    = `<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px;"><path d="M10 2h4"/><path d="M12 14v-4"/><circle cx="12" cy="14" r="8"/><path d="m4.93 4.93 1.41 1.41"/></svg>`;
const ICON_CALENDAR = `<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px;"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>`;
const ICON_ZAP      = `<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>`;
const ICON_CHECK    = `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><polyline points="20 6 9 17 4 12"/></svg>`;
const ICON_ARROW    = `<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin:0 2px;"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>`;

function atualizarPreview() {
    const horas = parseFloat(document.getElementById('antecedencia_minima_horas').value) || 0;
    const preview  = document.getElementById('previewAntecedencia');
    const exemplo  = document.getElementById('previewExemplo');
    const labelUnd = document.getElementById('labelUnidade');
    const stepper  = document.getElementById('stepperWrapper');

    // Atualiza rótulo da unidade e cor da borda do stepper
    if (horas === 0) {
        labelUnd.textContent = 'sem limite';
        stepper.style.borderColor = '#d1d5db';
    } else if (horas < 1) {
        labelUnd.textContent = 'minutos: ' + Math.round(horas * 60) + 'min';
        stepper.style.borderColor = '#ff5000';
    } else if (horas < 24) {
        labelUnd.textContent = 'hora' + (horas === 1 ? '' : 's');
        stepper.style.borderColor = '#ff5000';
    } else {
        const dias = (horas / 24).toFixed(1).replace('.0','');
        labelUnd.textContent = '≈ ' + dias + ' dia' + (parseFloat(dias) === 1 ? '' : 's');
        stepper.style.borderColor = '#ff5000';
    }

    // Atualiza destaque dos atalhos rápidos
    document.querySelectorAll('.quick-set').forEach(btn => {
        const v = parseFloat(btn.dataset.val);
        if (v === horas) {
            btn.style.background = '#fff3ed';
            btn.style.borderColor = '#ff5000';
            btn.style.color = '#ff5000';
        } else {
            btn.style.background = '#f8fafc';
            btn.style.borderColor = 'var(--border)';
            btn.style.color = 'var(--text-muted)';
        }
    });

    // Texto descritivo
    let desc = '';
    if (horas === 0) {
        desc = `${ICON_ZAP}<span><strong>Sem antecedência:</strong> Visitantes podem agendar qualquer horário que ainda não passou, incluindo horários do próprio dia.</span>`;
    } else if (horas < 1) {
        const min = Math.round(horas * 60);
        desc = `${ICON_TIMER}<span><strong>Antecedência de ${min} minutos:</strong> O visitante só pode marcar horários com pelo menos <strong>${min} min</strong> de distância do momento atual.</span>`;
    } else if (horas < 24) {
        const hStr = horas % 1 === 0 ? horas + 'h' : horas.toFixed(1) + 'h';
        desc = `${ICON_TIMER}<span><strong>Antecedência de ${hStr}:</strong> O visitante só pode marcar horários com pelo menos <strong>${hStr}</strong> de antecedência.</span>`;
    } else {
        const dias = Math.floor(horas / 24);
        const resto = horas % 24;
        const diasStr = dias === 1 ? '1 dia' : dias + ' dias';
        const restoStr = resto > 0 ? ' e ' + resto + 'h' : '';
        desc = `${ICON_CALENDAR}<span><strong>Antecedência de ${diasStr}${restoStr}:</strong> O visitante só pode marcar horários com pelo menos <strong>${diasStr}${restoStr}</strong> de antecedência.</span>`;
    }
    preview.innerHTML = desc;

    // Exemplo prático com hora atual
    const agora = new Date();
    const limite = new Date(agora.getTime() + horas * 60 * 60 * 1000);
    const pad = n => String(n).padStart(2, '0');
    const limiteStr  = pad(limite.getHours()) + ':' + pad(limite.getMinutes());
    const dataLimite = limite.getDate() + '/' + pad(limite.getMonth() + 1) + '/' + limite.getFullYear();
    const hojeStr    = agora.getDate() + '/' + pad(agora.getMonth() + 1) + '/' + agora.getFullYear();
    const agoraStr   = pad(agora.getHours()) + ':' + pad(agora.getMinutes());

    if (horas === 0) {
        exemplo.innerHTML = `${ICON_CHECK}<span>Apenas horários que já passaram são ocultados automaticamente.</span>`;
    } else if (dataLimite === hojeStr) {
        exemplo.innerHTML = `${ICON_CHECK}<span>São <strong>${agoraStr}</strong> agora ${ICON_ARROW} horários antes das <strong>${limiteStr}</strong> de hoje ficam ocultos.</span>`;
    } else {
        exemplo.innerHTML = `${ICON_CHECK}<span>São <strong>${agoraStr}</strong> hoje ${ICON_ARROW} horários antes de <strong>${limiteStr} em ${dataLimite}</strong> ficam ocultos.</span>`;
    }
}

// Botões + / -
document.getElementById('btnMenos').addEventListener('click', function() {
    const input = document.getElementById('antecedencia_minima_horas');
    let val = parseFloat(input.value) || 0;
    val = Math.max(0, val - 1);
    input.value = val % 1 === 0 ? val : val.toFixed(1);
    atualizarPreview();
});
document.getElementById('btnMais').addEventListener('click', function() {
    const input = document.getElementById('antecedencia_minima_horas');
    let val = parseFloat(input.value) || 0;
    val = Math.min(168, val + 1);
    input.value = val % 1 === 0 ? val : val.toFixed(1);
    atualizarPreview();
});

// Atalhos rápidos
document.querySelectorAll('.quick-set').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('antecedencia_minima_horas').value = this.dataset.val;
        atualizarPreview();
    });
    btn.addEventListener('mouseover', function() {
        if (parseFloat(this.dataset.val) !== (parseFloat(document.getElementById('antecedencia_minima_horas').value) || 0)) {
            this.style.background = '#fff3ed';
            this.style.borderColor = '#fdba74';
        }
    });
    btn.addEventListener('mouseout', function() {
        atualizarPreview(); // Reaplica estado correto
    });
});

// Preview inicial ao carregar
atualizarPreview();
</script>

<div class="grid-2fr-1fr">
    
    <!-- Lado Esquerdo: Dados Gerais da Escola -->
    <div class="card">
        <div class="card-header">
            <h3 style="font-size: 16px; display: flex; align-items: center; gap: 8px;">
                <i data-lucide="school" size="18" style="color: var(--primary);"></i>
                Dados Institucionais
            </h3>
        </div>
        
        <form method="POST" action="configuracoes.php">
            <input type="hidden" name="csrf_token" value="<?php echo gerarCSRFToken(); ?>">
            <input type="hidden" name="acao" value="salvar_dados">
            
            <div class="form-group">
                <label class="form-label" for="nome_escola">Nome da Escola *</label>
                <input type="text" id="nome_escola" name="nome_escola" class="form-control" value="<?php echo $configs['nome_escola'] ?? ''; ?>" required>
            </div>
            
            <div class="grid-2col">
                <div class="form-group">
                    <label class="form-label" for="telefone_contato">Telefone Geral *</label>
                    <input type="text" id="telefone_contato" name="telefone_contato" class="form-control" value="<?php echo $configs['telefone_contato'] ?? ''; ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="email_contato">E-mail de Contato *</label>
                    <input type="email" id="email_contato" name="email_contato" class="form-control" value="<?php echo $configs['email_contato'] ?? ''; ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="endereco_escola">Endereço Completo</label>
                <input type="text" id="endereco_escola" name="endereco_escola" class="form-control" value="<?php echo htmlspecialchars($configs['endereco_escola'] ?? ''); ?>">
            </div>
            
            <div class="form-group" style="margin-bottom: 24px;">
                <label class="form-label" for="mensagem_sucesso">Mensagem pós-agendamento (visitante)</label>
                <textarea id="mensagem_sucesso" name="mensagem_sucesso" class="form-control" rows="4"><?php echo htmlspecialchars($configs['mensagem_sucesso'] ?? ''); ?></textarea>
                <small style="color: var(--text-muted); font-size: 11px; margin-top: 4px; display: block;">Esta frase será exibida ao visitante na tela de sucesso ao concluir o agendamento.</small>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i data-lucide="save" size="16"></i> Salvar Alterações
            </button>
        </form>
    </div>
    
    <!-- Lado Direito: Perfil e Senha do Usuário Logado -->
    <div style="display: flex; flex-direction: column; gap: 24px;">

        <!-- Card: E-mail de Notificação Pessoal -->
        <div class="card">
            <div class="card-header">
                <h3 style="font-size: 16px; display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="bell" size="18" style="color: var(--primary);"></i>
                    Notificações por E-mail
                </h3>
            </div>
            
            <form method="POST" action="configuracoes.php">
                <input type="hidden" name="csrf_token" value="<?php echo gerarCSRFToken(); ?>">
                <input type="hidden" name="acao" value="salvar_email_notificacao">
                
                <div class="form-group" style="margin-bottom: 12px;">
                    <label class="form-label" for="email_notificacao_usuario" style="display: flex; align-items: center; gap: 6px;">
                        Meu E-mail para Receber Novos Agendamentos
                    </label>
                    <input type="email" id="email_notificacao_usuario" name="email_notificacao_usuario" class="form-control" 
                        value="<?php echo htmlspecialchars($email_notificacao_usuario ?? ''); ?>"
                        placeholder="Ex: seuemail@gmail.com">
                    <small style="color: var(--text-muted); font-size: 11px; margin-top: 6px; display: block; line-height: 1.4;">
                        Se preenchido, você receberá um e-mail automático neste endereço sempre que um visitante realizar um agendamento. Deixe em branco para não receber.
                    </small>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i data-lucide="mail" size="16"></i> Salvar E-mail de Notificação
                </button>
            </form>
        </div>

        <!-- Card: Alterar Senha do Usuário -->
        <div class="card">
            <div class="card-header">
                <h3 style="font-size: 16px; display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="key-round" size="18" style="color: var(--primary);"></i>
                    Alterar Senha do Usuário
                </h3>
            </div>
            
            <form method="POST" action="configuracoes.php">
                <input type="hidden" name="csrf_token" value="<?php echo gerarCSRFToken(); ?>">
                <input type="hidden" name="acao" value="alterar_senha">
                
                <div class="form-group">
                    <label class="form-label" for="senha_atual">Senha Atual *</label>
                    <input type="password" id="senha_atual" name="senha_atual" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="nova_senha">Nova Senha *</label>
                    <input type="password" id="nova_senha" name="nova_senha" class="form-control" placeholder="Mínimo 6 caracteres" required>
                </div>
                
                <div class="form-group" style="margin-bottom: 24px;">
                    <label class="form-label" for="confirmacao">Confirmar Nova Senha *</label>
                    <input type="password" id="confirmacao" name="confirmacao" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-secondary" style="width: 100%; border-color: var(--primary); color: var(--primary);">
                    <i data-lucide="shield-alert" size="16"></i> Alterar Senha do Usuário
                </button>
            </form>
        </div>

    </div>

</div>

<?php
require_once __DIR__ . '/includes/admin-footer.php';
?>
