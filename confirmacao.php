<?php
/**
 * Agenda Escolar - Página de Confirmação de Agendamento
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/db.php';

$token = sanitizar($_GET['token'] ?? '');
$agendamento = null;

if (!empty($token)) {
    try {
        $db = db();
        
        // Tratar atualização de status rápida por Administrador Logado
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
            $acao = $_POST['acao'] ?? '';
            $csrf_token = $_POST['csrf_token'] ?? '';
            
            if ($acao === 'marcar_realizado' && validarCSRFToken($csrf_token)) {
                $admin_id = getAdminId();
                $stmtRealizar = $db->prepare("UPDATE agendamentos SET status = 'realizado', atualizado_por_admin_id = ?, realizado_em = CURRENT_TIMESTAMP, realizado_por_admin_id = ? WHERE token = ?");
                $stmtRealizar->execute([$admin_id, $admin_id, $token]);
                flash('sucesso_admin', 'Status do agendamento atualizado para Realizado!', 'success');
                redirect(BASE_URL . '/confirmacao.php?token=' . $token);
            }
        }

        // Busca agendamento pelo token, fazendo join com o horário e segmento correspondentes
        $stmt = $db->prepare("
            SELECT a.*, 
                   h.data, h.hora_inicio, h.hora_fim,
                   s.nome as nome_segmento
            FROM agendamentos a
            JOIN horarios_disponiveis h ON a.horario_id = h.id
            JOIN segmentos s ON a.segmento_id = s.id
            WHERE a.token = ?
        ");
        $stmt->execute([$token]);
        $agendamento = $stmt->fetch();
    } catch (PDOException $e) {
        $agendamento = null;
    }
}

// Se não encontrar o agendamento pelo token, redireciona para a página inicial
if (!$agendamento) {
    flash('erro_geral', 'Agendamento não encontrado ou token inválido.', 'danger');
    redirect(BASE_URL . '/index.php');
}

// Buscar endereço da escola configurado no banco de dados
$endereco_escola = 'Colégio Exemplo Modelo';
try {
    $stmtEnd = $db->prepare("SELECT valor FROM configuracoes WHERE chave = 'endereco_escola' LIMIT 1");
    $stmtEnd->execute();
    $val = $stmtEnd->fetchColumn();
    if ($val) {
        $endereco_escola = $val;
    }
} catch (Exception $e) {}

// Buscar telefone de contato da escola para o WhatsApp
$whatsapp_escola = '';
try {
    $stmtTel = $db->prepare("SELECT valor FROM configuracoes WHERE chave = 'telefone_contato' LIMIT 1");
    $stmtTel->execute();
    $tel_raw = $stmtTel->fetchColumn();
    if ($tel_raw) {
        // Limpar caracteres não numéricos e adicionar código do Brasil se necessario
        $tel_limpo = preg_replace('/\D/', '', $tel_raw);
        if (strlen($tel_limpo) <= 11) {
            $tel_limpo = '55' . $tel_limpo;
        }
        $whatsapp_escola = $tel_limpo;
    }
} catch (Exception $e) {}

// Formatar data e hora para o padrão UTC do Google Calendar
$dt_inicio = new DateTime($agendamento['data'] . ' ' . $agendamento['hora_inicio'], new DateTimeZone('America/Sao_Paulo'));
$dt_fim = new DateTime($agendamento['data'] . ' ' . $agendamento['hora_fim'], new DateTimeZone('America/Sao_Paulo'));
$dt_inicio->setTimezone(new DateTimeZone('UTC'));
$dt_fim->setTimezone(new DateTimeZone('UTC'));
$gcal_dates = $dt_inicio->format('Ymd\THis\Z') . '/' . $dt_fim->format('Ymd\THis\Z');

// Detalhes e link do evento
$gcal_titulo = 'Visita Guiada - ' . SCHOOL_NAME;
$gcal_detalhes = 'Visita agendada para conhecer as dependências da escola. Código de Acompanhamento: ' . $agendamento['token'] . ' | Série de Interesse: ' . $agendamento['serie_interesse'];
$link_gcal = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
    . '&text=' . urlencode($gcal_titulo)
    . '&dates=' . urlencode($gcal_dates)
    . '&details=' . urlencode($gcal_detalhes)
    . '&location=' . urlencode($endereco_escola);

$titulo_pagina = 'Confirmação de Agendamento';
require_once __DIR__ . '/includes/header.php';

// Exibir painel de ações rápidas se for um admin logado escanear o QR Code
if (isLoggedIn()): ?>
    <div class="admin-quick-actions" style="max-width: 650px; margin: 24px auto 0; background-color: #f4f4f5; border: 1px solid var(--border); padding: 16px 20px; border-radius: var(--radius); text-align: left; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div style="flex-grow: 1; min-width: 250px;">
            <div style="font-weight: 700; font-size: 13px; color: var(--text-main); display: flex; align-items: center; gap: 6px; margin-bottom: 2px;">
                <i data-lucide="shield-check" style="color: var(--primary);" size="16"></i>
                Você está logado como Administrador
            </div>
            <p style="font-size: 12px; color: var(--text-muted);">
                Escanear rápido: gerencie o status desta visita por aqui.
            </p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <?php if ($agendamento['status'] !== 'realizado' && $agendamento['status'] !== 'cancelado'): ?>
                <form method="POST" action="confirmacao.php?token=<?php echo $agendamento['token']; ?>" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo gerarCSRFToken(); ?>">
                    <input type="hidden" name="acao" value="marcar_realizado">
                    <button type="submit" class="btn btn-primary btn-sm" style="font-size: 12px; padding: 8px 14px; height: auto;">
                        <i data-lucide="check-circle" size="14"></i> Marcar como Realizado
                    </button>
                </form>
            <?php endif; ?>
            <a href="<?php echo BASE_URL; ?>/admin/agendamento-detalhes.php?id=<?php echo $agendamento['id']; ?>" class="btn btn-secondary btn-sm" style="font-size: 12px; padding: 8px 14px; height: auto;">
                <i data-lucide="external-link" size="14"></i> Ver Ficha Completa
            </a>
        </div>
    </div>
<?php endif; ?>

<div class="container" style="max-width: 650px; margin-bottom: 80px; text-align: center;">

    <!-- Card de Confirmação Principal -->
    <div class="card" style="padding: 40px 30px; margin-top: 20px; overflow: visible;">
        
        <!-- Círculo de Sucesso Animado -->
        <div style="background-color: var(--success-bg); color: var(--accent); width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.15);">
            <i data-lucide="check" size="48"></i>
        </div>

        <h2 style="font-size: 26px; margin-bottom: 12px; color: var(--accent-dark);">Agendamento Pré-Registrado!</h2>
        
        <!-- Mensagem de Sucesso Dynamic do Config/Banco -->
        <p style="color: var(--text-muted); font-size: 15px; margin-bottom: 30px; line-height: 1.6;">
            <?php 
            try {
                $stmtMsg = $db->prepare("SELECT valor FROM configuracoes WHERE chave = 'mensagem_sucesso'");
                $stmtMsg->execute();
                echo $stmtMsg->fetchColumn() ?: 'Seu agendamento foi pré-registrado com sucesso! Nossa equipe entrará em contato em breve para confirmar a visita.';
            } catch (Exception $e) {
                echo 'Seu agendamento foi pré-registrado com sucesso! Nossa equipe entrará em contato em breve para confirmar a visita.';
            }
            ?>
        </p>

        <!-- Detalhes do Agendamento (Visual elegante) -->
        <div style="background-color: var(--bg-main); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 24px; text-align: left; margin-bottom: 30px; position: relative;">
            <div style="position: absolute; top: -12px; right: 20px; background-color: var(--primary); color: white; font-size: 10px; font-weight: 700; text-transform: uppercase; padding: 4px 10px; border-radius: 50px; letter-spacing: 0.5px;">
                Resumo da Visita
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr; gap: 16px; margin-top: 8px;">
                <div>
                    <div style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">Data e Horário</div>
                    <div style="font-size: 16px; font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 8px; margin-top: 2px;">
                        <i data-lucide="calendar" size="16" style="color: var(--primary);"></i>
                        <?php echo formatarData($agendamento['data']); ?> das <?php echo formatarHora($agendamento['hora_inicio']); ?> às <?php echo formatarHora($agendamento['hora_fim']); ?>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; border-top: 1px solid rgba(0,0,0,0.05); padding-top: 12px;">
                    <div>
                        <div style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">Segmento</div>
                        <div style="font-size: 14px; font-weight: 600; color: var(--text-main); margin-top: 2px;"><?php echo $agendamento['nome_segmento']; ?></div>
                    </div>
                    <div>
                        <div style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">Série</div>
                        <div style="font-size: 14px; font-weight: 600; color: var(--text-main); margin-top: 2px;"><?php echo $agendamento['serie_interesse']; ?></div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; border-top: 1px solid rgba(0,0,0,0.05); padding-top: 12px;">
                    <div>
                        <div style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">Aluno(a)</div>
                        <div style="font-size: 14px; font-weight: 600; color: var(--text-main); margin-top: 2px;"><?php echo $agendamento['nome_crianca']; ?> (<?php echo $agendamento['idade_crianca']; ?>)</div>
                    </div>
                    <div>
                        <div style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">Responsável</div>
                        <div style="font-size: 14px; font-weight: 600; color: var(--text-main); margin-top: 2px;"><?php echo $agendamento['nome_responsavel']; ?></div>
                    </div>
                </div>

                <div style="border-top: 1px solid rgba(0,0,0,0.05); padding-top: 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; text-align: left;">
                    <div>
                        <div style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">Código de Acompanhamento</div>
                        <div style="font-size: 16px; font-family: monospace; font-weight: 700; color: var(--primary); margin-top: 4px; padding: 6px 12px; background-color: rgba(0,0,0,0.03); border-radius: var(--radius-sm); border: 1px dashed var(--border); display: inline-block; letter-spacing: 0.5px;">
                            <?php echo $agendamento['token']; ?>
                        </div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 6px;">
                            Apresente este código ou escanear o QR Code ao lado na recepção.
                        </div>
                    </div>
                    <div style="text-align: center; background: white; padding: 8px; border: 1px solid var(--border); border-radius: var(--radius-sm);">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?php echo urlencode(BASE_URL . '/confirmacao.php?token=' . $agendamento['token']); ?>" alt="QR Code do Agendamento" style="display: block; width: 100px; height: 100px;">
                        <span style="font-size: 9px; color: var(--text-muted); display: block; margin-top: 4px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">QR CODE</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Orientações Úteis -->
        <div style="text-align: left; margin-bottom: 30px;">
            <h4 style="font-size: 14px; font-weight: 700; margin-bottom: 12px; color: var(--text-main); display: flex; align-items: center; gap: 6px;">
                <i data-lucide="info" size="16" style="color: var(--primary);"></i>
                Orientações para o dia da visita:
            </h4>
            <ul style="padding-left: 20px; font-size: 13px; color: var(--text-muted); line-height: 1.6;">
                <li style="margin-bottom: 6px;">Chegar com aproximadamente 10 minutos de antecedência.</li>
                <li style="margin-bottom: 6px;">O ponto de encontro inicial será na recepção principal do colégio.</li>
                <li style="margin-bottom: 6px;">Se desejar, traga o aluno(a) para que ele também possa vivenciar o ambiente escolar.</li>
                <li>Caso necessite cancelar ou reagendar, favor entrar em contato pelo telefone <b>(17) 98154-0011</b> informando seu Token.</li>
            </ul>
        </div>

        <!-- Ações -->
        <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
            <?php
            // Montar mensagem pré-preenchida do WhatsApp
            $data_fmt = formatarData($agendamento['data']);
            $hora_fmt = formatarHora($agendamento['hora_inicio']) . ' às ' . formatarHora($agendamento['hora_fim']);
            $msg_whats = "Olá! Eu realizei um agendamento de visita ao Colégio Exemplo Modelo e gostaria de confirmar.%0A%0A";
            $msg_whats .= "• Responsável: " . urlencode($agendamento['nome_responsavel']) . "%0A";
            $msg_whats .= "• Aluno(a): " . urlencode($agendamento['nome_crianca']) . "%0A";
            $msg_whats .= "• Segmento: " . urlencode($agendamento['nome_segmento']) . "%0A";
            $msg_whats .= "• Série de Interesse: " . urlencode($agendamento['serie_interesse']) . "%0A";
            $msg_whats .= "• Data: " . urlencode($data_fmt) . "%0A";
            $msg_whats .= "• Horário: " . urlencode($hora_fmt) . "%0A";
            $msg_whats .= "• Código de Confirmação: " . urlencode($agendamento['token']) . "%0A";
            ?>
            <?php if (!empty($whatsapp_escola)): ?>
            <a href="https://wa.me/<?php echo $whatsapp_escola; ?>?text=<?php echo $msg_whats; ?>" 
               target="_blank" 
               class="btn" 
               style="background-color: #25d366; color: white; display: inline-flex; align-items: center; gap: 8px; font-weight: 700; box-shadow: 0 4px 12px rgba(37,211,102,0.3);">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0;">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                    <path d="M12 0C5.373 0 0 5.373 0 12c0 2.127.557 4.124 1.533 5.857L.054 23.18a.75.75 0 0 0 .918.918l5.323-1.479A11.953 11.953 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.75a9.706 9.706 0 0 1-4.961-1.362l-.356-.212-3.684 1.023 1.022-3.684-.212-.356A9.706 9.706 0 0 1 2.25 12C2.25 6.615 6.615 2.25 12 2.25S21.75 6.615 21.75 12 17.385 21.75 12 21.75z"/>
                </svg>
                WhatsApp
            </a>
            <?php endif; ?>
            <a href="<?php echo $link_gcal; ?>" target="_blank" class="btn" id="btn-add-gcal" style="background-color: #4285f4; color: white;">
                <i data-lucide="calendar-plus" size="16"></i> Adicionar ao Google Agenda
            </a>
            <button onclick="window.print();" class="btn btn-secondary">
                <i data-lucide="printer" size="16"></i> Imprimir Comprovante
            </button>
            <a href="index.php" class="btn btn-primary">
                <i data-lucide="home" size="16"></i> Voltar para o Início
            </a>
        </div>

    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var btnGcal = document.getElementById("btn-add-gcal");
    if (!btnGcal) return;

    btnGcal.addEventListener("click", function(e) {
        var userAgent = navigator.userAgent || navigator.vendor || window.opera;
        var isAndroid = /android/i.test(userAgent);
        var isIOS = /iPad|iPhone|iPod/.test(userAgent) && !window.MSStream;

        // Se for computador, deixa o navegador tratar o link normalmente
        if (!isAndroid && !isIOS) {
            return;
        }

        e.preventDefault();

        // Extrai parâmetros da URL para compor o link de deep link do app
        var webUrl = this.getAttribute("href");
        var params = webUrl.substring(webUrl.indexOf('?') + 1);

        if (isAndroid) {
            // Android intent: força a abertura no aplicativo Google Agenda oficial
            var intentUrl = "intent://calendar.google.com/calendar/render?" + params + "#Intent;scheme=https;package=com.google.android.calendar;end";
            window.location.href = intentUrl;
        } else if (isIOS) {
            // iOS URL Scheme: tenta abrir o app oficial se o usuário tiver instalado
            var iosAppUrl = "googlecalendar://calendar.google.com/calendar/render?" + params;
            var start = Date.now();
            window.location.href = iosAppUrl;
            
            // Fallback para a versão web caso o app do Google Agenda não esteja instalado no iOS
            setTimeout(function() {
                if (Date.now() - start < 2000) {
                    window.open(webUrl, "_blank");
                }
            }, 1500);
        }
    });
});
</script>

<style>
    /* Estilos extras específicos para impressão do comprovante */
    @media print {
        header, footer, .btn, .nav-links, .logo, .toast-container, .admin-quick-actions {
            display: none !important;
        }
        body {
            background-color: white !important;
        }
        .container {
            max-width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        .card {
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
        }
    }
</style>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
