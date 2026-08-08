<?php
/**
 * Agenda Escolar - Detalhes do Agendamento
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../db.php';

// Garantir autenticação antes de qualquer ação
requireLogin();

$db = db();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$agendamento = null;

if ($id) {
    try {
        // Obter detalhes do agendamento
        $stmt = $db->prepare("
            SELECT a.*, h.data, h.hora_inicio, h.hora_fim, s.nome as nome_segmento,
                   adm.nome as nome_admin_atualizador, adm.email as email_admin_atualizador,
                   ac.nome as nome_admin_confirmador, ac.email as email_admin_confirmador,
                   ar.nome as nome_admin_realizador, ar.email as email_admin_realizador
            FROM agendamentos a
            JOIN horarios_disponiveis h ON a.horario_id = h.id
            JOIN segmentos s ON a.segmento_id = s.id
            LEFT JOIN administradores adm ON a.atualizado_por_admin_id = adm.id
            LEFT JOIN administradores ac ON a.confirmado_por_admin_id = ac.id
            LEFT JOIN administradores ar ON a.realizado_por_admin_id = ar.id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        $agendamento = $stmt->fetch();
    } catch (Exception $e) {
        $agendamento = null;
    }
}

// Redireciona se não existir
if (!$agendamento) {
    flash('erro_detalhes', 'Agendamento não encontrado.', 'danger');
    redirect(BASE_URL . '/admin/agendamentos.php');
}

// Processamento de Ações de Atualização de Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['novo_status'])) {
    $novo_status = sanitizar($_POST['novo_status']);
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (validarCSRFToken($csrf_token)) {
        $status_permitidos = ['pendente', 'confirmado', 'realizado', 'cancelado'];
        if (in_array($novo_status, $status_permitidos)) {
            try {
                $admin_id = getAdminId();
                $sql_campos = "status = ?, atualizado_por_admin_id = ?, atualizado_em = CURRENT_TIMESTAMP";
                $params = [$novo_status, $admin_id];
                
                if ($novo_status === 'confirmado') {
                    $sql_campos .= ", confirmado_em = CURRENT_TIMESTAMP, confirmado_por_admin_id = ?";
                    $params[] = $admin_id;
                } elseif ($novo_status === 'realizado') {
                    $sql_campos .= ", realizado_em = CURRENT_TIMESTAMP, realizado_por_admin_id = ?";
                    $params[] = $admin_id;
                }
                
                $params[] = $id;
                $stmtUpdate = $db->prepare("UPDATE agendamentos SET $sql_campos WHERE id = ?");
                $stmtUpdate->execute($params);
                
                flash('detalhes_sucesso', 'Status da visita atualizado para "' . ucfirst($novo_status) . '" com sucesso.', 'success');
                redirect(BASE_URL . '/admin/agendamento-detalhes.php?id=' . $id);
            } catch (Exception $e) {
                flash('detalhes_erro', 'Erro ao atualizar o status.', 'danger');
            }
        }
    }
}

// Carrega o cabeçalho visual apenas após processar as ações (evitando "headers already sent" ao redirecionar)
require_once __DIR__ . '/includes/admin-header.php';
?>

<div style="margin-bottom: 30px; display: flex; align-items: center; gap: 12px;">
    <a href="agendamentos.php" class="btn btn-secondary btn-sm" style="padding: 8px 12px;">
        <i data-lucide="arrow-left" size="16"></i> Voltar para a lista
    </a>
    <div>
        <h2 style="font-size: 24px; font-weight: 700; margin-bottom: 2px;">Detalhes do Agendamento</h2>
        <p style="color: var(--text-muted); font-size: 13px;">Informações detalhadas do agendamento e controle de status.</p>
    </div>
</div>

<div class="grid-2fr-1fr">
    
    <!-- Lado Esquerdo: Ficha de Dados -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
        
        <!-- Bloco do Aluno/Criança -->
        <div class="card">
            <div class="card-header">
                <h3 style="font-size: 16px; display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="baby" size="18" style="color: var(--primary);"></i>
                    Dados do Aluno Interessado
                </h3>
            </div>
            
            <div class="grid-2col" style="gap: 20px;">
                <div>
                    <label class="form-label">Nome Completo</label>
                    <div style="font-weight: 600; font-size: 15px;"><?php echo $agendamento['nome_crianca']; ?></div>
                </div>
                <div>
                    <label class="form-label">Idade Informada</label>
                    <div style="font-weight: 600; font-size: 15px;"><?php echo $agendamento['idade_crianca']; ?></div>
                </div>
                <div>
                    <label class="form-label">Segmento Escolar</label>
                    <div style="font-weight: 600; font-size: 15px; color: var(--primary);"><?php echo $agendamento['nome_segmento']; ?></div>
                </div>
                <div>
                    <label class="form-label">Série de Interesse</label>
                    <div style="font-weight: 600; font-size: 15px;"><?php echo $agendamento['serie_interesse']; ?></div>
                </div>
            </div>
            
            <?php if (!empty($agendamento['observacoes'])): ?>
                <div style="margin-top: 20px; border-top: 1px solid var(--border); padding-top: 16px;">
                    <label class="form-label">Observações/Mensagem Enviada</label>
                    <div style="font-size: 14px; background-color: var(--bg-main); padding: 12px; border-radius: var(--radius-sm); border: 1px solid var(--border); white-space: pre-wrap;"><?php echo $agendamento['observacoes']; ?></div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Bloco do Responsável -->
        <div class="card">
            <div class="card-header">
                <h3 style="font-size: 16px; display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="user" size="18" style="color: var(--primary);"></i>
                    Dados do Responsável
                </h3>
            </div>
            
            <div class="grid-2col" style="gap: 20px;">
                <div style="grid-column: span 2;">
                    <label class="form-label">Nome Completo</label>
                    <div style="font-weight: 600; font-size: 15px;"><?php echo $agendamento['nome_responsavel']; ?></div>
                </div>
                <div>
                    <label class="form-label">Telefone de Contato</label>
                    <div style="font-weight: 600; font-size: 15px; display: flex; align-items: center; gap: 6px;">
                        <i data-lucide="phone" size="14" style="color: var(--text-muted);"></i>
                        <?php echo formatarTelefone($agendamento['telefone']); ?>
                    </div>
                </div>
                <div>
                    <label class="form-label">WhatsApp</label>
                    <div style="font-weight: 600; font-size: 15px; display: flex; align-items: center; gap: 6px;">
                        <i data-lucide="message-square" size="14" style="color: #25d366;"></i>
                        <?php echo formatarTelefone($agendamento['whatsapp']); ?>
                        <?php 
                        $num_whats = preg_replace('/[^0-9]/', '', $agendamento['whatsapp']);
                        if (!empty($num_whats) && strpos($num_whats, '55') !== 0) {
                            $num_whats = '55' . $num_whats;
                        }
                        ?>
                        <a href="https://wa.me/<?php echo $num_whats; ?>" target="_blank" class="btn btn-secondary btn-sm" style="padding: 2px 6px; font-size: 10px; margin-left: 8px;">
                            Conversar
                        </a>
                    </div>
                </div>
                <div style="grid-column: span 2;">
                    <label class="form-label">E-mail</label>
                    <div style="font-weight: 600; font-size: 15px; display: flex; align-items: center; gap: 6px;">
                        <i data-lucide="mail" size="14" style="color: var(--text-muted);"></i>
                        <?php echo $agendamento['email']; ?>
                        <a href="mailto:<?php echo $agendamento['email']; ?>" class="btn btn-secondary btn-sm" style="padding: 2px 6px; font-size: 10px; margin-left: 8px;">
                            Enviar E-mail
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Lado Direito: Status e Ações -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
        
        <!-- Status Atual -->
        <div class="card">
            <div class="card-header">
                <h3 style="font-size: 16px;">Status da Visita</h3>
            </div>
            
            <div style="text-align: center; padding: 12px 0;">
                <?php 
                $badgeClass = 'pending';
                if ($agendamento['status'] === 'confirmado') $badgeClass = 'confirmed';
                elseif ($agendamento['status'] === 'realizado') $badgeClass = 'completed';
                elseif ($agendamento['status'] === 'cancelado') $badgeClass = 'cancelled';
                ?>
                <span class="badge badge-<?php echo $badgeClass; ?>" style="font-size: 15px; padding: 8px 16px; border-radius: 50px;">
                    <?php echo ucfirst($agendamento['status']); ?>
                </span>
                
                <div style="font-size: 11px; color: var(--text-muted); font-family: monospace; margin-top: 12px; word-break: break-all;">
                    Token: <?php echo $agendamento['token']; ?>
                </div>

                <?php if (!empty($agendamento['nome_admin_atualizador'])): ?>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 10px; border-top: 1px solid var(--border); padding-top: 8px; text-align: left;">
                        <i data-lucide="user" size="12" style="vertical-align: middle; margin-right: 2px; color: var(--text-muted);"></i>
                        Última alteração por:<br><strong style="color: var(--text-main);"><?php echo htmlspecialchars($agendamento['nome_admin_atualizador']); ?></strong>
                    </div>
                <?php endif; ?>

                <?php if (!empty($agendamento['confirmado_em'])): ?>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 10px; border-top: 1px solid var(--border); padding-top: 8px; text-align: left;">
                        <i data-lucide="check" size="12" style="vertical-align: middle; margin-right: 2px; color: var(--success);"></i>
                        Confirmada em:<br>
                        <strong style="color: var(--text-main);"><?php echo date('d/m/Y H:i', strtotime($agendamento['confirmado_em'])); ?></strong>
                        <?php if (!empty($agendamento['nome_admin_confirmador'])): ?>
                            <br><small>por <?php echo htmlspecialchars($agendamento['nome_admin_confirmador']); ?></small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($agendamento['realizado_em'])): ?>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 10px; border-top: 1px solid var(--border); padding-top: 8px; text-align: left;">
                        <i data-lucide="smile" size="12" style="vertical-align: middle; margin-right: 2px; color: var(--success); font-weight: bold;"></i>
                        Realizada em:<br>
                        <strong style="color: var(--text-main);"><?php echo date('d/m/Y H:i', strtotime($agendamento['realizado_em'])); ?></strong>
                        <?php if (!empty($agendamento['nome_admin_realizador'])): ?>
                            <br><small>por <?php echo htmlspecialchars($agendamento['nome_admin_realizador']); ?></small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Controle de Mudança de Status -->
            <div style="border-top: 1px solid var(--border); padding-top: 20px; margin-top: 10px;">
                <form method="POST" action="agendamento-detalhes.php?id=<?php echo $id; ?>" style="display: flex; flex-direction: column; gap: 8px;">
                    <input type="hidden" name="csrf_token" value="<?php echo gerarCSRFToken(); ?>">
                    
                    <?php if ($agendamento['status'] !== 'confirmado' && $agendamento['status'] !== 'realizado'): ?>
                        <button type="submit" name="novo_status" value="confirmado" class="btn btn-primary" style="width: 100%;">
                            <i data-lucide="check" size="16"></i> Confirmar Visita
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($agendamento['status'] === 'confirmado'): ?>
                        <button type="submit" name="novo_status" value="realizado" class="btn btn-success" style="width: 100%;">
                            <i data-lucide="smile" size="16"></i> Marcar como Realizada
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($agendamento['status'] !== 'cancelado' && $agendamento['status'] !== 'realizado'): ?>
                        <button type="submit" name="novo_status" value="cancelado" class="btn btn-danger" style="width: 100%;" onclick="return confirm('Tem certeza que deseja cancelar esta visita?');">
                            <i data-lucide="x" size="16"></i> Cancelar Visita
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($agendamento['status'] === 'cancelado' || $agendamento['status'] === 'realizado'): ?>
                        <!-- Permitir reverter para pendente para correções -->
                        <button type="submit" name="novo_status" value="pendente" class="btn btn-secondary" style="width: 100%;">
                            Reverter para Pendente
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <!-- Detalhes do Slot da Agenda -->
        <div class="card">
            <div class="card-header">
                <h3 style="font-size: 16px;">Agenda Selecionada</h3>
            </div>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <div>
                    <span style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">Data Agendada</span>
                    <div style="font-size: 15px; font-weight: 700; margin-top: 2px;">
                        <?php echo formatarData($agendamento['data']); ?>
                    </div>
                </div>
                <div>
                    <span style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">Horário Reservado</span>
                    <div style="font-size: 15px; font-weight: 700; margin-top: 2px;">
                        <?php echo formatarHora($agendamento['hora_inicio']); ?> às <?php echo formatarHora($agendamento['hora_fim']); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Timeline do Agendamento -->
        <div class="card">
            <div class="card-header">
                <h3 style="font-size: 16px;">Histórico do Evento</h3>
            </div>
            
            <ul class="timeline">
                <li class="timeline-item">
                    <div class="timeline-time"><?php echo formatarDataHora($agendamento['criado_em']); ?></div>
                    <div class="timeline-title">Agendamento Criado</div>
                    <div class="timeline-desc">Solicitação inserida pelo responsável na área pública.</div>
                </li>
                <?php if ($agendamento['atualizado_em'] !== $agendamento['criado_em']): ?>
                    <li class="timeline-item active">
                        <div class="timeline-time"><?php echo formatarDataHora($agendamento['atualizado_em']); ?></div>
                        <div class="timeline-title">Status Modificado</div>
                        <div class="timeline-desc">O status foi alterado para <b><?php echo ucfirst($agendamento['status']); ?></b>.</div>
                    </li>
                <?php endif; ?>
            </ul>
        </div>

    </div>

</div>

<?php
require_once __DIR__ . '/includes/admin-footer.php';
?>
