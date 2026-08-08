<?php
/**
 * Agenda Escolar - Dashboard Administrativo
 */
require_once __DIR__ . '/includes/admin-header.php';

$db = db();

// Inicializar contadores
$contadores = [
    'pendente' => 0,
    'confirmado' => 0,
    'realizado' => 0,
    'cancelado' => 0
];

try {
    // Buscar contagem de cada status
    $stmtCounts = $db->query("SELECT status, COUNT(*) as total FROM agendamentos GROUP BY status");
    while ($row = $stmtCounts->fetch()) {
        $contadores[$row['status']] = (int)$row['total'];
    }
} catch (Exception $e) {
    // Silencia erro
}

// Buscar os 5 agendamentos mais recentes criados
$recentes = [];
try {
    $stmtRecentes = $db->query("
        SELECT a.*, h.data, h.hora_inicio, s.nome as nome_segmento
        FROM agendamentos a
        JOIN horarios_disponiveis h ON a.horario_id = h.id
        JOIN segmentos s ON a.segmento_id = s.id
        ORDER BY a.criado_em DESC
        LIMIT 5
    ");
    $recentes = $stmtRecentes->fetchAll();
} catch (Exception $e) {
    // Silencia erro
}

// Buscar as próximas 5 visitas agendadas (Confirmadas ou Pendentes)
$proximas_visitas = [];
try {
    $hoje = date('Y-m-d');
    $stmtProximas = $db->prepare("
        SELECT a.*, h.data, h.hora_inicio, h.hora_fim, s.nome as nome_segmento
        FROM agendamentos a
        JOIN horarios_disponiveis h ON a.horario_id = h.id
        JOIN segmentos s ON a.segmento_id = s.id
        WHERE h.data >= ? AND a.status IN ('pendente', 'confirmado')
        ORDER BY h.data ASC, h.hora_inicio ASC
        LIMIT 5
    ");
    $stmtProximas->execute([$hoje]);
    $proximas_visitas = $stmtProximas->fetchAll();
} catch (Exception $e) {
    // Silencia erro
}

// Buscar todos os agendamentos pendentes para o modal de confirmação rápida
$pendentes_lista = [];
try {
    $stmtPendentes = $db->query("
        SELECT a.*, h.data, h.hora_inicio, h.hora_fim, s.nome as nome_segmento
        FROM agendamentos a
        JOIN horarios_disponiveis h ON a.horario_id = h.id
        JOIN segmentos s ON a.segmento_id = s.id
        WHERE a.status = 'pendente'
        ORDER BY h.data ASC, h.hora_inicio ASC
    ");
    $pendentes_lista = $stmtPendentes->fetchAll();
} catch (Exception $e) {
    // Silencia erro
}
?>

<div style="margin-bottom: 30px;">
    <h2 style="font-size: 24px; font-weight: 700; margin-bottom: 6px;">Dashboard</h2>
    <p style="color: var(--text-muted); font-size: 14px;">Visão geral das estatísticas e agendamentos recentes do Agenda Escolar.</p>
</div>

<!-- Card Orientativo de Boas-Vindas / UX -->
<div class="card" style="margin-bottom: 24px; background: linear-gradient(135deg, #18181b, #27272a); color: white; border: none; padding: 20px 24px;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
        <div>
            <h3 style="font-size: 16px; font-weight: 700; color: white; display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                <i data-lucide="sparkles" size="18" style="color: var(--primary);"></i>
                Como funciona o seu painel de agendamentos?
            </h3>
            <p style="font-size: 13px; color: #a1a1aa; line-height: 1.5; max-width: 650px;">
                <strong>1. Horários:</strong> Cadastre as datas e horas de visita na aba <a href="horarios.php" style="color: var(--primary); text-decoration: underline;">Horários Disponíveis</a>.<br>
                <strong>2. Agendamentos:</strong> Quando uma família faz o agendamento no site, ele aparece como <span style="color: var(--warning); font-weight: 600;">Pendente</span> aqui no painel.<br>
                <strong>3. Atendimento:</strong> Entre em contato com o responsável para confirmar a visita e alterar o status para <span style="color: var(--primary); font-weight: 600;">Confirmado</span>.
            </p>
            <p style="font-size: 12px; color: #71717a; margin-top: 10px; display: flex; align-items: center; gap: 6px;">
                Ficou com dúvida? Converse com o <a href="ajuda.php" style="color: var(--primary); font-weight: 600; text-decoration: underline;">Assistente do Prof. Edu. [IA]</a> — ele responde na hora!
            </p>
        </div>
        <a href="horarios.php" class="btn btn-primary" style="white-space: nowrap;">
            <i data-lucide="plus" size="16"></i> Configurar Horários
        </a>
    </div>
</div>

<!-- Grid de Cards de Métricas -->
<div class="metrics-grid">
    <!-- Card Pendentes -->
    <div class="metric-card" onclick="abrirModalPendentes()" style="cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05)';" onmouseout="this.style.transform='none'; this.style.boxShadow='none';">
        <div class="metric-icon" style="background-color: var(--warning);">
            <i data-lucide="clock" size="24"></i>
        </div>
        <div class="metric-details">
            <h4>Pendentes</h4>
            <p><?php echo $contadores['pendente']; ?></p>
        </div>
    </div>
    
    <!-- Card Confirmados -->
    <div class="metric-card" onclick="window.location.href='agendamentos.php?status=confirmado'" style="cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05)';" onmouseout="this.style.transform='none'; this.style.boxShadow='none';">
        <div class="metric-icon" style="background-color: var(--primary);">
            <i data-lucide="check-square" size="24"></i>
        </div>
        <div class="metric-details">
            <h4>Confirmados</h4>
            <p><?php echo $contadores['confirmado']; ?></p>
        </div>
    </div>
    
    <!-- Card Realizados -->
    <div class="metric-card" onclick="window.location.href='agendamentos.php?status=realizado'" style="cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05)';" onmouseout="this.style.transform='none'; this.style.boxShadow='none';">
        <div class="metric-icon" style="background-color: var(--success);">
            <i data-lucide="smile" size="24"></i>
        </div>
        <div class="metric-details">
            <h4>Realizados</h4>
            <p><?php echo $contadores['realizado']; ?></p>
        </div>
    </div>
    
    <!-- Card Cancelados -->
    <div class="metric-card" onclick="window.location.href='agendamentos.php?status=cancelado'" style="cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05)';" onmouseout="this.style.transform='none'; this.style.boxShadow='none';">
        <div class="metric-icon" style="background-color: var(--danger);">
            <i data-lucide="x-circle" size="24"></i>
        </div>
        <div class="metric-details">
            <h4>Cancelados</h4>
            <p><?php echo $contadores['cancelado']; ?></p>
        </div>
    </div>
</div>

<div class="grid-dashboard-cards">
    
    <!-- Próximas Visitas Agendadas -->
    <div class="card">
        <div class="card-header">
            <h3 style="font-size: 16px; display: flex; align-items: center; gap: 8px;">
                <i data-lucide="calendar" size="18" style="color: var(--primary);"></i>
                Próximas Visitas Cronológicas
            </h3>
            <a href="agendamentos.php" style="font-size: 13px; font-weight: 600;">Ver todos</a>
        </div>
        
        <?php if (empty($proximas_visitas)): ?>
            <div style="text-align: center; padding: 30px 0; color: var(--text-muted); font-size: 14px;">
                Nenhuma visita futura agendada.
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <?php foreach ($proximas_visitas as $visita): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 12px; border-bottom: 1px solid rgba(0,0,0,0.05);">
                        <div>
                            <div style="font-weight: 600; font-size: 14px; color: var(--text-main);">
                                <?php echo $visita['nome_crianca']; ?>
                            </div>
                            <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                                Responsável: <?php echo $visita['nome_responsavel']; ?> | Segmento: <?php echo $visita['nome_segmento']; ?>
                            </div>
                        </div>
                        <div style="text-align: right; flex-shrink: 0; margin-left: 16px;">
                            <div style="font-weight: 700; font-size: 13px; color: var(--primary);">
                                <?php echo formatarData($visita['data']); ?>
                            </div>
                            <div style="font-size: 11px; color: var(--text-muted); margin-top: 1px;">
                                <?php echo formatarHora($visita['hora_inicio']); ?> - <?php echo formatarHora($visita['hora_fim']); ?>
                            </div>
                            <!-- Badge do status da visita -->
                            <span class="badge badge-<?php echo $visita['status'] === 'pendente' ? 'pending' : 'confirmed'; ?>" style="font-size: 9px; padding: 2px 6px; margin-top: 4px;">
                                <?php echo ucfirst($visita['status']); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Agendamentos Recém Criados -->
    <div class="card">
        <div class="card-header">
            <h3 style="font-size: 16px; display: flex; align-items: center; gap: 8px;">
                <i data-lucide="history" size="18" style="color: var(--primary);"></i>
                Agendamentos Mais Recentes (Criados)
            </h3>
            <a href="agendamentos.php" style="font-size: 13px; font-weight: 600;">Ver todos</a>
        </div>
        
        <?php if (empty($recentes)): ?>
            <div style="text-align: center; padding: 30px 0; color: var(--text-muted); font-size: 14px;">
                Nenhum agendamento realizado no sistema.
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <?php foreach ($recentes as $rec): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 12px; border-bottom: 1px solid rgba(0,0,0,0.05);">
                        <div>
                            <div style="font-weight: 600; font-size: 14px; color: var(--text-main);">
                                <?php echo $rec['nome_responsavel']; ?>
                            </div>
                            <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                                Criança: <?php echo $rec['nome_crianca']; ?> | Série: <?php echo $rec['serie_interesse']; ?>
                            </div>
                        </div>
                        <div style="text-align: right; flex-shrink: 0; margin-left: 16px;">
                            <div style="font-size: 11px; color: var(--text-muted);">
                                Criado em <?php echo formatarData($rec['criado_em']); ?>
                            </div>
                            <!-- Badge correspondente ao status -->
                            <?php 
                            $badgeClass = 'pending';
                            if ($rec['status'] === 'confirmado') $badgeClass = 'confirmed';
                            elseif ($rec['status'] === 'realizado') $badgeClass = 'completed';
                            elseif ($rec['status'] === 'cancelado') $badgeClass = 'cancelled';
                            ?>
                            <span class="badge badge-<?php echo $badgeClass; ?>" style="font-size: 9px; padding: 2px 6px; margin-top: 4px;">
                                <?php echo ucfirst($rec['status']); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- MODAL: AGENDAMENTOS PENDENTES -->
<div class="modal" id="modalPendentes">
    <div class="modal-content" style="max-width: 680px; width: 90%;">
        <div class="modal-header">
            <h3 style="font-size: 16px; display: flex; align-items: center; gap: 8px;">
                <i data-lucide="clock" size="18" style="color: var(--warning);"></i>
                Agendamentos Pendentes (<span id="modal-pendentes-count"><?php echo count($pendentes_lista); ?></span>)
            </h3>
            <button onclick="document.getElementById('modalPendentes').classList.remove('open')" class="close-btn">
                <i data-lucide="x" size="20"></i>
            </button>
        </div>
        
        <div class="modal-body" style="max-height: 450px; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 14px;">
            <div id="modal-pendentes-lista" style="display: flex; flex-direction: column; gap: 12px;">
                <?php if (empty($pendentes_lista)): ?>
                    <div style="text-align: center; padding: 30px 0; color: var(--text-muted); font-size: 14px;">
                        Nenhuma visita pendente de confirmação. Bom trabalho! 🎉
                    </div>
                <?php else: ?>
                    <?php foreach ($pendentes_lista as $p): ?>
                        <div class="pendente-item" id="pendente-row-<?php echo $p['id']; ?>" style="border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 14px; background: var(--bg-card); display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; transition: all 0.3s ease;">
                            <div style="flex: 1; min-width: 250px; text-align: left;">
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px; flex-wrap: wrap;">
                                    <span style="font-weight: 700; font-size: 14px; color: var(--text-main);"><?php echo htmlspecialchars($p['nome_responsavel']); ?></span>
                                    <span style="font-size: 11px; padding: 2px 6px; border-radius: 4px; background: rgba(255,80,0,0.08); color: var(--primary); font-weight: 600;"><?php echo htmlspecialchars($p['nome_segmento'] . ' (' . $p['serie_interesse'] . ')'); ?></span>
                                </div>
                                <div style="font-size: 13px; color: var(--text-muted); display: flex; flex-direction: column; gap: 4px;">
                                    <span><strong>Aluno:</strong> <?php echo htmlspecialchars($p['nome_crianca']); ?> (<?php echo htmlspecialchars($p['idade_crianca']); ?>)</span>
                                    <span><strong>Data/Hora:</strong> <?php echo date('d/m/Y', strtotime($p['data'])) . ' das ' . formatarHora($p['hora_inicio']) . ' às ' . formatarHora($p['hora_fim']); ?></span>
                                    <span style="display: flex; align-items: center; gap: 12px; margin-top: 4px; flex-wrap: wrap;">
                                        <a href="tel:<?php echo preg_replace('/\D/', '', $p['telefone']); ?>" style="color: inherit; display: inline-flex; align-items: center; gap: 4px;">
                                            <i data-lucide="phone" size="12"></i> <?php echo formatarTelefone($p['telefone']); ?>
                                        </a>
                                        <?php 
                                        $whats_clean = preg_replace('/\D/', '', $p['whatsapp']);
                                        if (!empty($whats_clean) && strpos($whats_clean, '55') !== 0) $whats_clean = '55' . $whats_clean;
                                        ?>
                                        <a href="https://wa.me/<?php echo $whats_clean; ?>" target="_blank" style="color: #25d366; display: inline-flex; align-items: center; gap: 4px; font-weight: 600;">
                                            <i data-lucide="message-square" size="12"></i> WhatsApp
                                        </a>
                                    </span>
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 6px; align-items: center;">
                                <button onclick="alterarStatusPendente(<?php echo $p['id']; ?>, 'confirmado')" class="btn btn-sm btn-success" style="padding: 6px 10px; font-size: 12px;" title="Confirmar Agendamento">
                                    <i data-lucide="check" size="14"></i> Confirmar
                                </button>
                                <button onclick="alterarStatusPendente(<?php echo $p['id']; ?>, 'cancelado')" class="btn btn-sm btn-danger" style="padding: 6px 10px; font-size: 12px;" title="Cancelar Agendamento">
                                    <i data-lucide="x" size="14"></i> Cancelar
                                </button>
                                <a href="agendamento-detalhes.php?id=<?php echo $p['id']; ?>" target="_blank" class="btn btn-sm btn-secondary" style="padding: 6px 8px;" title="Ver Ficha Completa">
                                    <i data-lucide="external-link" size="14"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" onclick="document.getElementById('modalPendentes').classList.remove('open')" class="btn btn-secondary">Fechar</button>
        </div>
    </div>
</div>

<script>
function abrirModalPendentes() {
    document.getElementById('modalPendentes').classList.add('open');
    // Recriar ícones do Lucide caso novos elementos surjam
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

function alterarStatusPendente(id, status) {
    if (status === 'cancelado' && !confirm('Tem certeza que deseja cancelar esta visita?')) {
        return;
    }
    
    var csrfToken = '<?php echo gerarCSRFToken(); ?>';
    
    // Preparar formData
    var formData = new FormData();
    formData.append('id', id);
    formData.append('status', status);
    formData.append('csrf_token', csrfToken);
    
    fetch('api/status.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.success) {
            // Efeito visual fade-out no item
            var row = document.getElementById('pendente-row-' + id);
            if (row) {
                row.style.opacity = '0';
                row.style.transform = 'scale(0.95)';
                setTimeout(function() {
                    row.remove();
                    
                    // Atualizar contadores na tela do modal
                    var countEl = document.getElementById('modal-pendentes-count');
                    var count = parseInt(countEl.textContent) - 1;
                    countEl.textContent = count;
                    
                    // Atualizar contador no card do Dashboard
                    var cardCountEl = document.querySelector('.metric-card[onclick*="abrirModalPendentes"] p');
                    if (cardCountEl) {
                        cardCountEl.textContent = count;
                    }
                    
                    // Atualizar badge do sidebar (se existir)
                    var sidebarBadge = document.querySelector('.admin-nav-link[href*="agendamentos.php"] .badge-danger');
                    if (sidebarBadge) {
                        if (count > 0) {
                            sidebarBadge.textContent = count;
                        } else {
                            sidebarBadge.remove();
                        }
                    }
                    
                    if (count === 0) {
                        document.getElementById('modal-pendentes-lista').innerHTML = `
                            <div style="text-align: center; padding: 30px 0; color: var(--text-muted); font-size: 14px;">
                                Nenhuma visita pendente de confirmação. Bom trabalho! 🎉
                            </div>
                        `;
                    }
                }, 300);
            }
        } else {
            alert('Erro ao atualizar status: ' + (data.error || 'Erro desconhecido.'));
        }
    })
    .catch(function(error) {
        console.error('Error:', error);
        alert('Erro ao processar a requisição no servidor.');
    });
}

// Fechar modal ao clicar fora
document.getElementById('modalPendentes').addEventListener('click', function(e) {
    if (e.target === this) {
        this.classList.remove('open');
    }
});
</script>

<?php
require_once __DIR__ . '/includes/admin-footer.php';
?>
