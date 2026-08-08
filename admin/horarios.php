<?php
/**
 * Agenda Escolar - Gerenciamento de Horários de Visitas (Redesign v2 — UX Intuitiva)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../db.php';

requireLogin();

$db = db();
$mensagem_sucesso = '';
$mensagem_erro = '';

// ──────────────────────────────────────────────
// 1. Processamento de Ações do Formulário (POST)
// ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $acao = sanitizar($_POST['acao']);
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (validarCSRFToken($csrf_token)) {

        // ── Cadastrar Horário Único ──
        if ($acao === 'cadastrar_unico') {
            $data       = sanitizar($_POST['data'] ?? '');
            $hora_inicio = sanitizar($_POST['hora_inicio'] ?? '');
            $hora_fim   = sanitizar($_POST['hora_fim'] ?? '');
            $max_vagas  = filter_input(INPUT_POST, 'max_vagas', FILTER_VALIDATE_INT) ?: 1;

            if (empty($data) || empty($hora_inicio) || empty($hora_fim) || $max_vagas < 1) {
                $mensagem_erro = 'Preencha todos os campos do horário.';
            } elseif ($data < date('Y-m-d')) {
                $mensagem_erro = 'Não é possível cadastrar horários em datas passadas.';
            } elseif ($hora_inicio >= $hora_fim) {
                $mensagem_erro = 'O horário de término deve ser posterior ao de início.';
            } else {
                try {
                    $stmt = $db->prepare("INSERT INTO horarios_disponiveis (data, hora_inicio, hora_fim, max_vagas, ativo) VALUES (?, ?, ?, ?, 1)");
                    $stmt->execute([$data, $hora_inicio, $hora_fim, $max_vagas]);
                    flash('horarios_msg', 'Horário cadastrado com sucesso!', 'success');
                    redirect(BASE_URL . '/admin/horarios.php');
                } catch (PDOException $e) {
                    $mensagem_erro = 'Erro ao cadastrar horário: ' . $e->getMessage();
                }
            }
        }

        // ── Cadastrar em Lote ──
        elseif ($acao === 'cadastrar_lote') {
            $data_inicio = sanitizar($_POST['data_inicio'] ?? '');
            $data_fim    = sanitizar($_POST['data_fim'] ?? '');
            $hora_inicio = sanitizar($_POST['hora_inicio'] ?? '');
            $hora_fim    = sanitizar($_POST['hora_fim'] ?? '');
            $max_vagas   = filter_input(INPUT_POST, 'max_vagas', FILTER_VALIDATE_INT) ?: 1;
            $dias_semana = $_POST['dias_semana'] ?? [];

            if (empty($data_inicio) || empty($data_fim) || empty($hora_inicio) || empty($hora_fim) || empty($dias_semana)) {
                $mensagem_erro = 'Preencha todas as informações do período e selecione pelo menos um dia da semana.';
            } elseif ($data_inicio < date('Y-m-d')) {
                $mensagem_erro = 'A data de início não pode ser uma data passada.';
            } elseif ($data_inicio > $data_fim) {
                $mensagem_erro = 'A data de término deve ser igual ou posterior à data de início.';
            } elseif ($hora_inicio >= $hora_fim) {
                $mensagem_erro = 'O horário de término deve ser posterior ao de início.';
            } else {
                try {
                    $start_date = new DateTime($data_inicio);
                    $end_date   = new DateTime($data_fim);
                    $end_date->modify('+1 day');
                    $interval   = new DateInterval('P1D');
                    $date_range = new DatePeriod($start_date, $interval, $end_date);

                    $cadastrados = 0;
                    $db->beginTransaction();
                    $stmtInsert = $db->prepare("INSERT INTO horarios_disponiveis (data, hora_inicio, hora_fim, max_vagas, ativo) VALUES (?, ?, ?, ?, 1)");

                    foreach ($date_range as $date) {
                        if (in_array((int)$date->format('w'), $dias_semana)) {
                            $stmtInsert->execute([$date->format('Y-m-d'), $hora_inicio, $hora_fim, $max_vagas]);
                            $cadastrados++;
                        }
                    }

                    $db->commit();
                    flash('horarios_msg', "$cadastrados horário(s) gerado(s) com sucesso!", 'success');
                    redirect(BASE_URL . '/admin/horarios.php');
                } catch (Exception $e) {
                    if ($db->inTransaction()) $db->rollBack();
                    $mensagem_erro = 'Erro ao gerar os horários: ' . $e->getMessage();
                }
            }
        }

        // ── Editar Horário Existente ──
        elseif ($acao === 'editar') {
            $horario_id  = filter_input(INPUT_POST, 'horario_id', FILTER_VALIDATE_INT);
            $nova_data   = sanitizar($_POST['data'] ?? '');
            $hora_inicio = sanitizar($_POST['hora_inicio'] ?? '');
            $hora_fim    = sanitizar($_POST['hora_fim'] ?? '');
            $max_vagas   = filter_input(INPUT_POST, 'max_vagas', FILTER_VALIDATE_INT) ?: 1;

            if (!$horario_id || empty($nova_data) || empty($hora_inicio) || empty($hora_fim) || $max_vagas < 1) {
                $mensagem_erro = 'Preencha todos os campos corretamente.';
            } elseif ($hora_inicio >= $hora_fim) {
                $mensagem_erro = 'O horário de término deve ser posterior ao de início.';
            } else {
                try {
                    // Verificar quantos agendamentos ativos existem
                    $stmtAgend = $db->prepare("SELECT COUNT(*) FROM agendamentos WHERE horario_id = ? AND status != 'cancelado'");
                    $stmtAgend->execute([$horario_id]);
                    $total_agendados = (int)$stmtAgend->fetchColumn();

                    if ($max_vagas < $total_agendados) {
                        $mensagem_erro = "Não é possível reduzir para $max_vagas vaga(s). Já existem $total_agendados agendamento(s) confirmado(s) neste horário.";
                    } else {
                        $stmtUpdate = $db->prepare("UPDATE horarios_disponiveis SET data = ?, hora_inicio = ?, hora_fim = ?, max_vagas = ? WHERE id = ?");
                        $stmtUpdate->execute([$nova_data, $hora_inicio, $hora_fim, $max_vagas, $horario_id]);
                        flash('horarios_msg', 'Horário atualizado com sucesso!', 'success');
                        redirect(BASE_URL . '/admin/horarios.php');
                    }
                } catch (Exception $e) {
                    $mensagem_erro = 'Erro ao atualizar o horário.';
                }
            }
        }

        // ── Alternar Status (Ativo/Inativo) — individual ──
        elseif ($acao === 'alternar_status') {
            $horario_id = filter_input(INPUT_POST, 'horario_id', FILTER_VALIDATE_INT);
            if ($horario_id) {
                try {
                    $db->prepare("UPDATE horarios_disponiveis SET ativo = NOT ativo WHERE id = ?")->execute([$horario_id]);
                    flash('horarios_msg', 'Status do horário alterado.', 'success');
                    redirect(BASE_URL . '/admin/horarios.php');
                } catch (Exception $e) {
                    $mensagem_erro = 'Erro ao alterar status.';
                }
            }
        }

        // ── Excluir — individual ──
        elseif ($acao === 'excluir') {
            $horario_id = filter_input(INPUT_POST, 'horario_id', FILTER_VALIDATE_INT);
            if ($horario_id) {
                try {
                    $stmtCheck = $db->prepare("SELECT COUNT(*) FROM agendamentos WHERE horario_id = ?");
                    $stmtCheck->execute([$horario_id]);
                    $vinculados = (int)$stmtCheck->fetchColumn();
                    if ($vinculados > 0) {
                        $mensagem_erro = "Não é possível excluir: há $vinculados agendamento(s) vinculado(s). Desative o horário em vez disso.";
                    } else {
                        $db->prepare("DELETE FROM horarios_disponiveis WHERE id = ?")->execute([$horario_id]);
                        flash('horarios_msg', 'Horário excluído.', 'success');
                        redirect(BASE_URL . '/admin/horarios.php');
                    }
                } catch (Exception $e) {
                    $mensagem_erro = 'Erro ao excluir horário.';
                }
            }
        }

        // ── Ações em Lote (ativar/desativar/excluir vários) ──
        elseif ($acao === 'lote_ativar' || $acao === 'lote_desativar' || $acao === 'lote_excluir') {
            $ids_raw = $_POST['ids_selecionados'] ?? '';
            $ids = array_filter(array_map('intval', explode(',', $ids_raw)));

            if (empty($ids)) {
                $mensagem_erro = 'Nenhum horário selecionado.';
            } else {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                try {
                    if ($acao === 'lote_ativar') {
                        $db->prepare("UPDATE horarios_disponiveis SET ativo = 1 WHERE id IN ($placeholders)")->execute($ids);
                        flash('horarios_msg', count($ids) . ' horário(s) ativado(s).', 'success');
                    } elseif ($acao === 'lote_desativar') {
                        $db->prepare("UPDATE horarios_disponiveis SET ativo = 0 WHERE id IN ($placeholders)")->execute($ids);
                        flash('horarios_msg', count($ids) . ' horário(s) desativado(s).', 'success');
                    } else {
                        // Excluir apenas os que não têm agendamentos
                        $stmtChk = $db->prepare("SELECT horario_id FROM agendamentos WHERE horario_id IN ($placeholders) GROUP BY horario_id");
                        $stmtChk->execute($ids);
                        $com_agendamentos = $stmtChk->fetchAll(PDO::FETCH_COLUMN);
                        $ids_seguros = array_diff($ids, $com_agendamentos);

                        if (!empty($ids_seguros)) {
                            $ph2 = implode(',', array_fill(0, count($ids_seguros), '?'));
                            $db->prepare("DELETE FROM horarios_disponiveis WHERE id IN ($ph2)")->execute($ids_seguros);
                        }
                        $bloqueados = count($ids) - count($ids_seguros);
                        $msg = count($ids_seguros) . ' horário(s) excluído(s).';
                        if ($bloqueados > 0) $msg .= " $bloqueados não puderam ser excluídos (possuem agendamentos).";
                        flash('horarios_msg', $msg, $bloqueados > 0 ? 'warning' : 'success');
                    }
                    redirect(BASE_URL . '/admin/horarios.php');
                } catch (Exception $e) {
                    $mensagem_erro = 'Erro ao processar ação em lote.';
                }
            }
        }
    }
}

// ──────────────────────────────────────────────
// 2. Parâmetros de Filtro
// ──────────────────────────────────────────────
$filtro_periodo = sanitizar($_GET['periodo'] ?? 'futuros');
$filtro_status  = sanitizar($_GET['status'] ?? 'ativos');

// ──────────────────────────────────────────────
// 3. Buscar Horários com Filtros
// ──────────────────────────────────────────────
require_once __DIR__ . '/includes/admin-header.php';

$horarios = [];
$hoje = date('Y-m-d');
try {
    $where = [];
    $params = [];

    if ($filtro_periodo === 'futuros') {
        $where[] = "h.data >= ?";
        $params[] = $hoje;
    } elseif ($filtro_periodo === 'passados') {
        $where[] = "h.data < ?";
        $params[] = $hoje;
    }

    if ($filtro_status === 'ativos') {
        $where[] = "h.ativo = 1";
    } elseif ($filtro_status === 'inativos') {
        $where[] = "h.ativo = 0";
    }

    $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmtList = $db->prepare("
        SELECT h.*,
               (SELECT COUNT(*) FROM agendamentos a WHERE a.horario_id = h.id AND a.status != 'cancelado') as total_agendados
        FROM horarios_disponiveis h
        $where_sql
        ORDER BY h.data ASC, h.hora_inicio ASC
    ");
    $stmtList->execute($params);
    $horarios = $stmtList->fetchAll();
} catch (Exception $e) {
    // Silencia
}

// Agrupar por data
$horarios_por_data = [];
foreach ($horarios as $h) {
    $horarios_por_data[$h['data']][] = $h;
}

// Dias semana em português
$nomes_dia = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
$meses_nome = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
?>

<!-- ─────────────────── CABEÇALHO DA PÁGINA ─────────────────── -->
<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 28px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h2 style="font-size: 24px; font-weight: 700; margin-bottom: 6px;">Horários de Visitas</h2>
        <p style="color: var(--text-muted); font-size: 14px;">Crie e gerencie os períodos disponíveis para que as famílias possam agendar visitas guiadas.</p>
    </div>
    <button onclick="abrirModalHorario()" class="btn btn-primary" id="btn-novo-horario" style="display: flex; align-items: center; gap: 8px; font-size: 14px; padding: 12px 20px;">
        <i data-lucide="plus-circle" size="18"></i> Criar Novo Horário
    </button>
</div>

<?php if (!empty($mensagem_erro)): ?>
    <div style="background-color: var(--danger-bg); color: var(--danger); border: 1px solid #fecaca; padding: 14px 16px; border-radius: var(--radius-md); margin-bottom: 20px; font-size: 14px; display:flex; align-items:center; gap:10px;">
        <i data-lucide="alert-circle" size="18"></i>
        <?php echo $mensagem_erro; ?>
    </div>
<?php endif; ?>

<!-- ─────────────────── FLUXO VISUAL "COMO FUNCIONA" — 3 passos ─────────────────── -->
<div class="hor-flow-banner">
    <div class="hor-flow-step">
        <div class="hor-flow-icon" style="background: rgba(255,80,0,0.1); color: var(--primary);">
            <i data-lucide="calendar-plus" size="20"></i>
        </div>
        <div>
            <strong>1. Crie o horário</strong>
            <span>Defina data, hora e vagas</span>
        </div>
    </div>
    <div class="hor-flow-arrow">
        <i data-lucide="arrow-right" size="16"></i>
    </div>
    <div class="hor-flow-step">
        <div class="hor-flow-icon" style="background: rgba(59,130,246,0.1); color: #3b82f6;">
            <i data-lucide="users" size="20"></i>
        </div>
        <div>
            <strong>2. Família agenda</strong>
            <span>O horário aparece no site</span>
        </div>
    </div>
    <div class="hor-flow-arrow">
        <i data-lucide="arrow-right" size="16"></i>
    </div>
    <div class="hor-flow-step">
        <div class="hor-flow-icon" style="background: rgba(16,185,129,0.1); color: #10b981;">
            <i data-lucide="check-circle" size="20"></i>
        </div>
        <div>
            <strong>3. Confirme a visita</strong>
            <span>Gerencie pelo painel</span>
        </div>
    </div>
</div>


<!-- ─────────────────── BARRA DE FILTROS ─────────────────── -->
<div class="horarios-filter-bar">
    <div class="filter-group">
        <span class="filter-label">Período:</span>
        <div class="filter-pills">
            <a href="?periodo=futuros&status=<?php echo $filtro_status; ?>" class="filter-pill <?php echo $filtro_periodo === 'futuros' ? 'active' : ''; ?>">
                <i data-lucide="calendar-check" size="13"></i> Próximos
            </a>
            <a href="?periodo=todos&status=<?php echo $filtro_status; ?>" class="filter-pill <?php echo $filtro_periodo === 'todos' ? 'active' : ''; ?>">
                <i data-lucide="calendar" size="13"></i> Todos
            </a>
            <a href="?periodo=passados&status=<?php echo $filtro_status; ?>" class="filter-pill <?php echo $filtro_periodo === 'passados' ? 'active' : ''; ?>">
                <i data-lucide="history" size="13"></i> Passados
            </a>
        </div>
    </div>
    <div class="filter-group">
        <span class="filter-label">Status:</span>
        <div class="filter-pills">
            <a href="?periodo=<?php echo $filtro_periodo; ?>&status=ativos" class="filter-pill <?php echo $filtro_status === 'ativos' ? 'active' : ''; ?>">
                <i data-lucide="check-circle" size="13"></i> Ativos
            </a>
            <a href="?periodo=<?php echo $filtro_periodo; ?>&status=inativos" class="filter-pill <?php echo $filtro_status === 'inativos' ? 'active' : ''; ?>">
                <i data-lucide="eye-off" size="13"></i> Inativos
            </a>
            <a href="?periodo=<?php echo $filtro_periodo; ?>&status=todos" class="filter-pill <?php echo $filtro_status === 'todos' ? 'active' : ''; ?>">
                <i data-lucide="list" size="13"></i> Todos
            </a>
        </div>
    </div>
    <div style="margin-left: auto; font-size: 13px; color: var(--text-muted); display:flex; align-items:center;">
        <i data-lucide="database" size="14" style="margin-right:5px;"></i>
        <strong><?php echo count($horarios); ?></strong>&nbsp;horário(s) encontrado(s)
    </div>
</div>

<!-- ─────────────────── BARRA DE AÇÕES EM LOTE ─────────────────── -->
<form method="POST" action="horarios.php" id="form-lote-acoes" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo gerarCSRFToken(); ?>">
    <input type="hidden" name="ids_selecionados" id="ids-selecionados-input">
    <div class="bulk-action-bar" id="bulk-action-bar">
        <div style="display:flex; align-items:center; gap:10px;">
            <i data-lucide="check-square" size="18" style="color: var(--primary);"></i>
            <span id="bulk-count-label" style="font-weight:600; font-size:14px;">0 selecionado(s)</span>
        </div>
        <div style="display:flex; gap:10px;">
            <button type="submit" name="acao" value="lote_ativar" class="btn btn-sm" style="background-color: var(--success); border-color: var(--success); color: white;" onclick="return confirm('Ativar os horários selecionados?')">
                <i data-lucide="play-circle" size="14"></i> Ativar Selecionados
            </button>
            <button type="submit" name="acao" value="lote_desativar" class="btn btn-secondary btn-sm" onclick="return confirm('Desativar os horários selecionados?')">
                <i data-lucide="eye-off" size="14"></i> Desativar Selecionados
            </button>
            <button type="submit" name="acao" value="lote_excluir" class="btn btn-danger btn-sm" onclick="return confirm('Excluir os selecionados? Somente os que não têm agendamentos serão removidos.')">
                <i data-lucide="trash-2" size="14"></i> Excluir Selecionados
            </button>
            <button type="button" onclick="limparSelecao()" class="btn btn-secondary btn-sm">
                <i data-lucide="x" size="14"></i> Cancelar
            </button>
        </div>
    </div>
</form>

<!-- ─────────────────── LISTAGEM ─────────────────── -->
<?php if (empty($horarios_por_data)): ?>
    <!-- Estado Vazio — Guia Passo a Passo -->
    <div class="card" style="text-align:center; padding:50px 30px;">
        <i data-lucide="calendar-x" size="52" style="opacity:0.2; color:var(--primary); margin-bottom:20px;"></i>
        <div style="font-size:18px; font-weight:700; margin-bottom:8px; color: var(--text-main);">Nenhum horário encontrado</div>
        <p style="font-size:14px; color:var(--text-muted); margin-bottom:32px; max-width: 480px; margin-left:auto; margin-right:auto; line-height:1.6;">
            <?php if ($filtro_periodo === 'futuros'): ?>
                Para começar a receber agendamentos de visita, você precisa criar pelo menos um horário disponível. É rápido e fácil!
            <?php else: ?>
                Nenhum horário corresponde aos filtros selecionados. Tente alterar os filtros acima.
            <?php endif; ?>
        </p>

        <?php if ($filtro_periodo === 'futuros'): ?>
        <div class="hor-empty-steps">
            <div class="hor-empty-step">
                <div class="hor-empty-step-num">1</div>
                <p>Clique em <strong>"Criar Novo Horário"</strong></p>
            </div>
            <div class="hor-empty-step">
                <div class="hor-empty-step-num">2</div>
                <p>Escolha a <strong>data</strong>, o <strong>horário</strong> e quantas <strong>vagas</strong></p>
            </div>
            <div class="hor-empty-step">
                <div class="hor-empty-step-num">3</div>
                <p>Pronto! O horário já aparece <strong>no site</strong> para as famílias</p>
            </div>
        </div>
        <?php endif; ?>

        <button onclick="abrirModalHorario()" class="btn btn-primary" style="margin-top: 24px; padding: 12px 28px; font-size: 15px;">
            <i data-lucide="plus-circle" size="18"></i> Criar Primeiro Horário
        </button>
    </div>
<?php else: ?>

    <!-- Checkbox "Selecionar Todos" -->
    <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px; padding: 0 4px;">
        <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:13px; color:var(--text-muted); user-select:none;">
            <input type="checkbox" id="check-all" onchange="toggleTodos(this)" style="width:16px; height:16px; accent-color:var(--primary); cursor:pointer;">
            Selecionar todos os horários visíveis
        </label>
    </div>

    <?php foreach ($horarios_por_data as $data => $slots): ?>
        <?php
        $dt = new DateTime($data);
        $nome_dia = $nomes_dia[(int)$dt->format('w')];
        $data_fmt = $dt->format('d') . ' de ' . $meses_nome[(int)$dt->format('n') - 1] . ' de ' . $dt->format('Y');
        $e_passado = $data < date('Y-m-d');
        $e_hoje    = $data === date('Y-m-d');
        ?>

        <!-- Separador de Data -->
        <div class="date-group-header <?php echo $e_passado ? 'date-group-past' : ($e_hoje ? 'date-group-today' : ''); ?>">
            <div style="display:flex; align-items:center; gap:10px;">
                <i data-lucide="<?php echo $e_hoje ? 'star' : 'calendar-days'; ?>" size="16"></i>
                <span><?php echo $nome_dia ?>, <?php echo $data_fmt; ?></span>
                <?php if ($e_hoje): ?><span class="date-today-badge">HOJE</span><?php endif; ?>
                <?php if ($e_passado): ?><span class="date-past-badge">Passado</span><?php endif; ?>
            </div>
            <span style="font-size:12px; color:var(--text-muted);"><?php echo count($slots); ?> slot(s)</span>
        </div>

        <!-- Slots deste dia -->
        <div class="slots-container">
            <?php foreach ($slots as $h): ?>
                <?php
                $ocupacao_pct = $h['max_vagas'] > 0 ? round(($h['total_agendados'] / $h['max_vagas']) * 100) : 0;
                $cor_barra = $ocupacao_pct >= 100 ? 'red' : ($ocupacao_pct >= 60 ? 'orange' : 'green');
                $e_lotado = $h['total_agendados'] >= $h['max_vagas'] && $h['max_vagas'] > 0;
                $tem_agendamentos = $h['total_agendados'] > 0;
                ?>
                <div class="slot-card <?php echo !$h['ativo'] ? 'slot-inativo' : ''; ?>" data-id="<?php echo $h['id']; ?>">

                    <!-- Checkbox de seleção -->
                    <input type="checkbox" class="slot-checkbox" value="<?php echo $h['id']; ?>"
                           onchange="atualizarSelecao()"
                           style="width:16px; height:16px; accent-color:var(--primary); cursor:pointer; flex-shrink:0;">

                    <!-- Bloco Principal: Horário + Data + Status -->
                    <div class="slot-main">
                        <div class="slot-time-row">
                            <div class="slot-time">
                                <i data-lucide="clock" size="16" style="color:var(--primary);"></i>
                                <span><?php echo formatarHora($h['hora_inicio']); ?> — <?php echo formatarHora($h['hora_fim']); ?></span>
                            </div>
                            <?php if (!$h['ativo']): ?>
                                <span class="slot-badge slot-badge-inativo">INATIVO</span>
                            <?php elseif ($e_lotado): ?>
                                <span class="slot-badge slot-badge-lotado">LOTADO</span>
                            <?php else: ?>
                                <span class="slot-badge slot-badge-ativo">ATIVO</span>
                            <?php endif; ?>
                        </div>

                        <!-- Ocupação -->
                        <div class="slot-ocupacao">
                            <div class="slot-ocupacao-barra">
                                <div class="slot-ocupacao-fill slot-ocupacao-<?php echo $cor_barra; ?>"
                                     style="width: <?php echo min(100, $ocupacao_pct); ?>%"></div>
                            </div>
                            <span class="slot-ocupacao-label">
                                <?php echo $h['total_agendados']; ?>/<?php echo $h['max_vagas']; ?> vagas preenchidas
                            </span>
                        </div>
                    </div>

                    <!-- Ações -->
                    <div class="slot-actions">
                        <!-- Editar -->
                        <button type="button" class="slot-action-btn slot-action-edit"
                                onclick="abrirModalEditar(<?php echo $h['id']; ?>, '<?php echo $h['data']; ?>', '<?php echo $h['hora_inicio']; ?>', '<?php echo $h['hora_fim']; ?>', <?php echo $h['max_vagas']; ?>, <?php echo $h['total_agendados']; ?>)"
                                title="Editar este horário">
                            <i data-lucide="pencil" size="14"></i>
                            <span>Editar</span>
                        </button>

                        <!-- Ativar/Desativar -->
                        <form method="POST" action="horarios.php" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo gerarCSRFToken(); ?>">
                            <input type="hidden" name="horario_id" value="<?php echo $h['id']; ?>">
                            <button type="submit" name="acao" value="alternar_status"
                                    class="slot-action-btn <?php echo $h['ativo'] ? 'slot-action-deactivate' : 'slot-action-activate'; ?>"
                                    title="<?php echo $h['ativo'] ? 'Desativar este horário' : 'Ativar este horário'; ?>">
                                <i data-lucide="<?php echo $h['ativo'] ? 'pause-circle' : 'play-circle'; ?>" size="14"></i>
                                <span><?php echo $h['ativo'] ? 'Desativar' : 'Ativar'; ?></span>
                            </button>
                        </form>

                        <!-- Excluir -->
                        <form method="POST" action="horarios.php" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo gerarCSRFToken(); ?>">
                            <input type="hidden" name="horario_id" value="<?php echo $h['id']; ?>">
                            <button type="submit" name="acao" value="excluir"
                                    class="slot-action-btn slot-action-delete"
                                    <?php echo $tem_agendamentos ? 'disabled title="Não é possível excluir: há agendamentos vinculados"' : 'title="Excluir este horário"'; ?>
                                    onclick="return confirm('Confirma a exclusão deste horário?')">
                                <i data-lucide="trash-2" size="14"></i>
                                <span>Excluir</span>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>


<!-- ═══════════════════════════════════════════════════════════
     MODAL: CRIAR NOVO HORÁRIO (Abas: Data Específica / Lote)
     ═══════════════════════════════════════════════════════════ -->
<div class="modal" id="modalHorario">
    <div class="modal-content" style="max-width: 580px;">
        <div class="modal-header">
            <h3 style="font-size:16px; display:flex; align-items:center; gap:8px;">
                <i data-lucide="calendar-plus" size="18" style="color:var(--primary);"></i>
                Criar Novo Horário de Visita
            </h3>
            <button onclick="fecharModal('modalHorario')" class="close-btn"><i data-lucide="x" size="20"></i></button>
        </div>

        <!-- Abas -->
        <div class="horario-tabs">
            <button class="horario-tab active" id="tab-unico" onclick="trocarAba('unico')">
                <i data-lucide="calendar-check" size="15"></i>
                <span>Data Específica</span>
            </button>
            <button class="horario-tab" id="tab-lote" onclick="trocarAba('lote')">
                <i data-lucide="calendar-range" size="15"></i>
                <span>Vários Dias de Uma Vez</span>
            </button>
        </div>

        <!-- ── ABA 1: DATA ESPECÍFICA ── -->
        <div id="painel-unico" class="horario-painel active">
            <form method="POST" action="horarios.php">
                <input type="hidden" name="csrf_token" value="<?php echo gerarCSRFToken(); ?>">
                <input type="hidden" name="acao" value="cadastrar_unico">
                <div class="modal-body" style="display:flex; flex-direction:column; gap:18px;">

                    <div class="form-group" style="margin:0;">
                        <label class="form-label" for="data_u">Data da Visita *</label>
                        <input type="date" id="data_u" name="data" class="form-control" required
                               min="<?php echo date('Y-m-d'); ?>">
                        <small style="color:var(--text-muted); font-size:11px; margin-top:4px; display:block;">Selecione o dia em que a escola receberá visitas.</small>
                    </div>

                    <div class="grid-2col" style="gap:14px;">
                        <div class="form-group" style="margin:0;">
                            <label class="form-label" for="hora_inicio_u">Horário de Início *</label>
                            <input type="time" id="hora_inicio_u" name="hora_inicio" class="form-control" required>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label class="form-label" for="hora_fim_u">Horário de Término *</label>
                            <input type="time" id="hora_fim_u" name="hora_fim" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group" style="margin:0;">
                        <label class="form-label" for="max_vagas_u">Quantas famílias podem agendar neste horário? *</label>
                        <input type="number" id="max_vagas_u" name="max_vagas" class="form-control"
                               value="1" min="1" max="50" required>
                        <small style="color:var(--text-muted); font-size:11px; margin-top:4px; display:block;">
                            Exemplo: coloque 3 para permitir que até 3 famílias agendem no mesmo período.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="fecharModal('modalHorario')" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="save" size="15"></i> Salvar Horário
                    </button>
                </div>
            </form>
        </div>

        <!-- ── ABA 2: PERÍODO RECORRENTE (LOTE) ── -->
        <div id="painel-lote" class="horario-painel" style="display:none;">
            <form method="POST" action="horarios.php" id="form-lote">
                <input type="hidden" name="csrf_token" value="<?php echo gerarCSRFToken(); ?>">
                <input type="hidden" name="acao" value="cadastrar_lote">
                <div class="modal-body" style="display:flex; flex-direction:column; gap:22px;">

                    <!-- Explicação -->
                    <div style="background:var(--primary-bg); border-radius:var(--radius-md); padding:14px 16px; display:flex; gap:12px; align-items:flex-start;">
                        <i data-lucide="lightbulb" size="18" style="color:var(--primary); flex-shrink:0; margin-top:1px;"></i>
                        <p style="font-size:13px; color:var(--primary-dark); line-height:1.5; margin:0;">
                            Crie horários em vários dias de uma vez. Ideal para abrir uma semana inteira ou um mês completo de visitas.
                        </p>
                    </div>

                    <!-- Passo 1: Período -->
                    <div>
                        <div class="wizard-step-label">
                            <span class="wizard-step-number">1</span>
                            <span>De quando até quando?</span>
                        </div>
                        <div class="grid-2col" style="gap:14px; margin-top:12px;">
                            <div class="form-group" style="margin:0;">
                                <label class="form-label" for="data_inicio">Data de início *</label>
                                <input type="date" id="data_inicio" name="data_inicio" class="form-control"
                                       required min="<?php echo date('Y-m-d'); ?>" onchange="atualizarPreview()">
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label class="form-label" for="data_fim">Data de término *</label>
                                <input type="date" id="data_fim" name="data_fim" class="form-control"
                                       required min="<?php echo date('Y-m-d'); ?>" onchange="atualizarPreview()">
                            </div>
                        </div>
                    </div>

                    <!-- Passo 2: Dias da semana -->
                    <div>
                        <div class="wizard-step-label">
                            <span class="wizard-step-number">2</span>
                            <span>Em quais dias da semana?</span>
                        </div>
                        <div class="dias-semana-grid" style="margin-top:12px;">
                            <?php
                            $dias_nomes = [
                                0 => 'Dom',
                                1 => 'Segunda',
                                2 => 'Terça',
                                3 => 'Quarta',
                                4 => 'Quinta',
                                5 => 'Sexta',
                                6 => 'Sáb',
                            ];
                            foreach ($dias_nomes as $num => $nome):
                                $checked = ($num >= 1 && $num <= 5) ? 'checked' : '';
                            ?>
                                <label class="dia-btn <?php echo $checked ? 'dia-btn-ativo' : ''; ?>">
                                    <input type="checkbox" name="dias_semana[]" value="<?php echo $num; ?>"
                                           <?php echo $checked; ?> onchange="toggleDiaBtn(this); atualizarPreview();"
                                           style="display:none;">
                                    <?php echo $nome; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Passo 3: Horário e vagas -->
                    <div>
                        <div class="wizard-step-label">
                            <span class="wizard-step-number">3</span>
                            <span>Qual o horário e quantas vagas?</span>
                        </div>
                        <div class="grid-2col" style="gap:14px; margin-top:12px;">
                            <div class="form-group" style="margin:0;">
                                <label class="form-label" for="hora_inicio_l">Início *</label>
                                <input type="time" id="hora_inicio_l" name="hora_inicio" class="form-control"
                                       required onchange="atualizarPreview()">
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label class="form-label" for="hora_fim_l">Término *</label>
                                <input type="time" id="hora_fim_l" name="hora_fim" class="form-control"
                                       required onchange="atualizarPreview()">
                            </div>
                        </div>
                        <div class="form-group" style="margin:12px 0 0;">
                            <label class="form-label" for="max_vagas_l">Vagas por horário *</label>
                            <input type="number" id="max_vagas_l" name="max_vagas" class="form-control"
                                   value="1" min="1" max="50" required onchange="atualizarPreview()">
                        </div>
                    </div>

                    <!-- Pré-visualização dinâmica -->
                    <div id="preview-lote" class="preview-lote" style="display:none;">
                        <i data-lucide="eye" size="16" style="color:var(--primary);"></i>
                        <span id="preview-lote-texto"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="fecharModal('modalHorario')" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btn-gerar-lote" disabled>
                        <i data-lucide="zap" size="15"></i> Gerar Horários
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════════
     MODAL: EDITAR HORÁRIO EXISTENTE
     ═══════════════════════════════════════════════════════════ -->
<div class="modal" id="modalEditarHorario">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 style="font-size:16px; display:flex; align-items:center; gap:8px;">
                <i data-lucide="pencil" size="18" style="color:var(--primary);"></i>
                Editar Horário
            </h3>
            <button onclick="fecharModal('modalEditarHorario')" class="close-btn"><i data-lucide="x" size="20"></i></button>
        </div>
        <form method="POST" action="horarios.php">
            <input type="hidden" name="csrf_token" value="<?php echo gerarCSRFToken(); ?>">
            <input type="hidden" name="acao" value="editar">
            <input type="hidden" name="horario_id" id="edit-horario-id">
            <div class="modal-body" style="display:flex; flex-direction:column; gap:18px;">

                <!-- Aviso de agendamentos existentes -->
                <div id="edit-aviso-agendamentos" style="display:none; background:#fef3c7; border:1px solid #fde68a; border-radius:var(--radius-md); padding:12px 14px; font-size:13px; color:#92400e; display:none; align-items:flex-start; gap:10px;">
                    <i data-lucide="alert-triangle" size="16" style="flex-shrink:0; margin-top:1px;"></i>
                    <span id="edit-aviso-texto"></span>
                </div>

                <div class="form-group" style="margin:0;">
                    <label class="form-label" for="edit-data">Data da Visita *</label>
                    <input type="date" id="edit-data" name="data" class="form-control" required>
                </div>

                <div class="grid-2col" style="gap:14px;">
                    <div class="form-group" style="margin:0;">
                        <label class="form-label" for="edit-hora-inicio">Horário de Início *</label>
                        <input type="time" id="edit-hora-inicio" name="hora_inicio" class="form-control" required>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label" for="edit-hora-fim">Horário de Término *</label>
                        <input type="time" id="edit-hora-fim" name="hora_fim" class="form-control" required>
                    </div>
                </div>

                <div class="form-group" style="margin:0;">
                    <label class="form-label" for="edit-max-vagas">Número de Vagas *</label>
                    <input type="number" id="edit-max-vagas" name="max_vagas" class="form-control" min="1" max="50" required>
                    <small id="edit-vagas-hint" style="color:var(--text-muted); font-size:11px; margin-top:4px; display:block;"></small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="fecharModal('modalEditarHorario')" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="save" size="15"></i> Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════════
     ESTILOS DA PÁGINA
     ═══════════════════════════════════════════════════════════ -->
<style>
/* ── Fluxo visual "Como funciona" ── */
.hor-flow-banner {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    padding: 18px 24px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.hor-flow-step {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 180px;
}
.hor-flow-step div:last-child {
    display: flex;
    flex-direction: column;
}
.hor-flow-step strong {
    font-size: 13px;
    color: var(--text-main);
}
.hor-flow-step span {
    font-size: 11px;
    color: var(--text-muted);
}
.hor-flow-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.hor-flow-arrow {
    color: var(--border);
    display: flex;
    align-items: center;
    padding: 0 6px;
}

/* ── Estado vazio — passos ── */
.hor-empty-steps {
    display: flex;
    gap: 20px;
    justify-content: center;
    flex-wrap: wrap;
    margin-top: 8px;
}
.hor-empty-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    max-width: 180px;
    text-align: center;
}
.hor-empty-step p {
    font-size: 13px;
    color: var(--text-muted);
    line-height: 1.5;
    margin: 0;
}
.hor-empty-step-num {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    font-size: 16px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* ── Barra de filtros ── */
.horarios-filter-bar {
    display: flex;
    align-items: center;
    gap: 20px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    padding: 14px 18px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.filter-group {
    display: flex;
    align-items: center;
    gap: 10px;
}
.filter-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.4px;
    white-space: nowrap;
}
.filter-pills {
    display: flex;
    gap: 6px;
}
.filter-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 50px;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-muted);
    background: var(--bg-main);
    border: 1px solid var(--border);
    transition: var(--transition);
    text-decoration: none;
}
.filter-pill:hover {
    border-color: var(--primary);
    color: var(--primary);
}
.filter-pill.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

/* ── Barra de ações em lote ── */
.bulk-action-bar {
    background: var(--secondary);
    color: white;
    border-radius: var(--radius-md);
    padding: 12px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 16px;
    animation: slideUp 0.2s ease;
}

/* ── Separador de data ── */
.date-group-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    margin-top: 16px;
    margin-bottom: 6px;
    background: linear-gradient(90deg, var(--primary-bg), transparent);
    border-left: 3px solid var(--primary);
    border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
    font-size: 14px;
    font-weight: 700;
    color: var(--text-main);
}
.date-group-header:first-of-type {
    margin-top: 0;
}
.date-group-past {
    background: linear-gradient(90deg, var(--bg-main), transparent);
    border-left-color: var(--border);
    opacity: 0.7;
}
.date-group-today {
    background: linear-gradient(90deg, #fff3ed, transparent);
    border-left-color: var(--warning);
}
.date-today-badge {
    background: var(--warning);
    color: white;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 50px;
    letter-spacing: 0.5px;
}
.date-past-badge {
    background: var(--border);
    color: var(--text-muted);
    font-size: 10px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 50px;
}

/* ── Container dos slots ── */
.slots-container {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 4px;
}

/* ── Cartão de slot — Redesign v2 ── */
.slot-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-left: 4px solid var(--success);
    border-radius: var(--radius-md);
    padding: 16px 18px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: var(--transition);
    flex-wrap: wrap;
}
.slot-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    border-color: var(--border-hover);
}
.slot-inativo {
    opacity: 0.6;
    border-left-color: #a1a1aa;
}

/* ── Bloco principal do slot ── */
.slot-main {
    flex: 1;
    min-width: 200px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.slot-time-row {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.slot-time {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 17px;
    font-weight: 700;
    color: var(--text-main);
}

/* ── Badges de status ── */
.slot-badge {
    font-size: 10px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 50px;
    letter-spacing: 0.5px;
    white-space: nowrap;
    text-transform: uppercase;
}
.slot-badge-ativo {
    background: #dcfce7;
    color: #16a34a;
}
.slot-badge-inativo {
    background: #f4f4f5;
    color: #71717a;
    border: 1px solid #d4d4d8;
}
.slot-badge-lotado {
    background: var(--danger-bg);
    color: var(--danger);
}

/* ── Barra de ocupação ── */
.slot-ocupacao {
    display: flex;
    align-items: center;
    gap: 10px;
}
.slot-ocupacao-barra {
    flex: 1;
    height: 8px;
    background: var(--bg-main);
    border-radius: 50px;
    overflow: hidden;
    border: 1px solid var(--border);
    min-width: 80px;
    max-width: 200px;
}
.slot-ocupacao-fill {
    height: 100%;
    border-radius: 50px;
    transition: width 0.5s ease;
}
.slot-ocupacao-green  { background: var(--success); }
.slot-ocupacao-orange { background: var(--warning); }
.slot-ocupacao-red    { background: var(--danger); }
.slot-ocupacao-label {
    font-size: 12px;
    font-weight: 500;
    color: var(--text-muted);
    white-space: nowrap;
}

/* ── Ações do slot — 3 botões textuais ── */
.slot-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
    flex-wrap: wrap;
}
.slot-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    border-radius: var(--radius-sm);
    font-size: 12px;
    font-weight: 600;
    border: 1px solid var(--border);
    background: var(--bg-card);
    color: var(--text-muted);
    cursor: pointer;
    transition: var(--transition);
    white-space: nowrap;
}
.slot-action-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--primary-bg);
}
.slot-action-edit:hover {
    border-color: #3b82f6;
    color: #3b82f6;
    background: #eff6ff;
}
.slot-action-activate:hover {
    border-color: var(--success);
    color: var(--success);
    background: var(--success-bg);
}
.slot-action-deactivate:hover {
    border-color: var(--warning);
    color: var(--warning);
    background: #fffbeb;
}
.slot-action-delete:hover {
    border-color: var(--danger);
    color: var(--danger);
    background: var(--danger-bg);
}
.slot-action-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
    pointer-events: none;
}

/* ── Abas do Modal ── */
.horario-tabs {
    display: flex;
    border-bottom: 1px solid var(--border);
    background: var(--bg-main);
}
.horario-tab {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 14px 16px;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-muted);
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    transition: var(--transition);
}
.horario-tab:hover { color: var(--primary); }
.horario-tab.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
    background: var(--bg-card);
}

/* ── Wizard steps no modal ── */
.wizard-step-label {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    font-weight: 700;
    color: var(--text-main);
}
.wizard-step-number {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    font-size: 12px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

/* ── Dias da semana (pills toggle) ── */
.dias-semana-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.dia-btn {
    padding: 8px 14px;
    border-radius: 50px;
    border: 1.5px solid var(--border);
    background: var(--bg-card);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    color: var(--text-muted);
    transition: var(--transition);
    user-select: none;
}
.dia-btn:hover { border-color: var(--primary); color: var(--primary); }
.dia-btn-ativo {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

/* ── Preview do lote ── */
.preview-lote {
    background: var(--success-bg);
    border: 1px solid var(--success);
    border-radius: var(--radius-md);
    padding: 14px 16px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: 13px;
    color: #065f46;
    line-height: 1.6;
}

/* ── Responsividade ── */
@media (max-width: 768px) {
    .hor-flow-banner {
        flex-direction: column;
        gap: 14px;
        align-items: flex-start;
    }
    .hor-flow-arrow {
        display: none;
    }
    .slot-card {
        flex-direction: column;
        align-items: flex-start;
    }
    .slot-actions {
        width: 100%;
        justify-content: flex-start;
        padding-top: 8px;
        border-top: 1px solid var(--border);
        margin-top: 4px;
    }
    .horarios-filter-bar {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    .hor-empty-steps {
        flex-direction: column;
        align-items: center;
    }
}
</style>


<!-- ═══════════════════════════════════════════════════════════
     JAVASCRIPT
     ═══════════════════════════════════════════════════════════ -->
<script>
// ── Modal genérico ──
function abrirModalHorario() {
    document.getElementById('modalHorario').classList.add('open');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}
function fecharModal(id) {
    document.getElementById(id).classList.remove('open');
}
document.getElementById('modalHorario').addEventListener('click', function(e) {
    if (e.target === this) fecharModal('modalHorario');
});
document.getElementById('modalEditarHorario').addEventListener('click', function(e) {
    if (e.target === this) fecharModal('modalEditarHorario');
});

// ── Modal de Edição ──
function abrirModalEditar(id, data, horaInicio, horaFim, maxVagas, totalAgendados) {
    document.getElementById('edit-horario-id').value = id;
    document.getElementById('edit-data').value = data;
    document.getElementById('edit-hora-inicio').value = horaInicio;
    document.getElementById('edit-hora-fim').value = horaFim;
    document.getElementById('edit-max-vagas').value = maxVagas;

    // Ajustar min do campo vagas
    var minVagas = Math.max(1, totalAgendados);
    document.getElementById('edit-max-vagas').min = minVagas;

    // Aviso de agendamentos
    var aviso = document.getElementById('edit-aviso-agendamentos');
    var avisoTexto = document.getElementById('edit-aviso-texto');
    if (totalAgendados > 0) {
        aviso.style.display = 'flex';
        avisoTexto.innerHTML = 'Este horário já possui <strong>' + totalAgendados + ' agendamento(s)</strong>. O número de vagas não pode ser inferior a ' + totalAgendados + '.';
    } else {
        aviso.style.display = 'none';
    }

    // Hint das vagas
    var hint = document.getElementById('edit-vagas-hint');
    if (totalAgendados > 0) {
        hint.textContent = 'Mínimo: ' + minVagas + ' (já existem ' + totalAgendados + ' agendamento(s) neste horário).';
    } else {
        hint.textContent = 'Nenhum agendamento vinculado. Pode alterar livremente.';
    }

    document.getElementById('modalEditarHorario').classList.add('open');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

// ── Abas ──
function trocarAba(aba) {
    document.getElementById('tab-unico').classList.toggle('active', aba === 'unico');
    document.getElementById('tab-lote').classList.toggle('active', aba === 'lote');
    document.getElementById('painel-unico').style.display = aba === 'unico' ? '' : 'none';
    document.getElementById('painel-lote').style.display  = aba === 'lote'  ? '' : 'none';
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

// ── Toggle visual dos dias da semana ──
function toggleDiaBtn(checkbox) {
    var label = checkbox.closest('label');
    label.classList.toggle('dia-btn-ativo', checkbox.checked);
}

// ── Pré-visualização dinâmica do lote ──
function atualizarPreview() {
    var inicio    = document.getElementById('data_inicio').value;
    var fim       = document.getElementById('data_fim').value;
    var hInicio   = document.getElementById('hora_inicio_l').value;
    var hFim      = document.getElementById('hora_fim_l').value;
    var vagas     = document.getElementById('max_vagas_l').value;
    var checkboxes = document.querySelectorAll('#form-lote input[name="dias_semana[]"]:checked');
    var dias      = Array.from(checkboxes).map(function(c) { return parseInt(c.value); });

    var preview   = document.getElementById('preview-lote');
    var previewTxt = document.getElementById('preview-lote-texto');
    var btnGerar  = document.getElementById('btn-gerar-lote');

    if (!inicio || !fim || !hInicio || !hFim || dias.length === 0 || inicio > fim) {
        preview.style.display = 'none';
        btnGerar.disabled = true;
        return;
    }

    // Contar os dias que serão gerados
    var count = 0;
    var dt = new Date(inicio + 'T12:00:00');
    var dtFim = new Date(fim + 'T12:00:00');
    while (dt <= dtFim) {
        if (dias.indexOf(dt.getDay()) !== -1) count++;
        dt.setDate(dt.getDate() + 1);
    }

    var nomesDias = {0:'domingos',1:'segundas',2:'terças',3:'quartas',4:'quintas',5:'sextas',6:'sábados'};
    var diasNomes = dias.map(function(d) { return nomesDias[d]; }).join(', ');

    var fmtDate = function(s) {
        var p = s.split('-');
        return p[2] + '/' + p[1] + '/' + p[0];
    };

    previewTxt.innerHTML = count === 0
        ? '⚠️ Nenhum horário será gerado. Verifique as datas e os dias selecionados.'
        : 'Serão criados <strong>' + count + ' horário(s)</strong> entre <strong>' + fmtDate(inicio) + '</strong> e <strong>' + fmtDate(fim) + '</strong>, toda(s) <strong>' + diasNomes + '</strong>, das <strong>' + hInicio + '</strong> às <strong>' + hFim + '</strong>, com <strong>' + vagas + ' vaga(s)</strong> cada.';

    preview.style.display = 'flex';
    btnGerar.disabled = (count === 0);
}

// ── Seleção em massa ──
function atualizarSelecao() {
    var checks = Array.from(document.querySelectorAll('.slot-checkbox:checked'));
    var ids = checks.map(function(c) { return c.value; });
    var form = document.getElementById('form-lote-acoes');
    var label = document.getElementById('bulk-count-label');
    var input = document.getElementById('ids-selecionados-input');

    input.value = ids.join(',');
    label.textContent = ids.length + ' selecionado(s)';

    if (ids.length > 0) {
        form.style.display = 'block';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    } else {
        form.style.display = 'none';
    }

    // Sincronizar check-all
    var total = document.querySelectorAll('.slot-checkbox').length;
    document.getElementById('check-all').checked = (ids.length === total && total > 0);
    document.getElementById('check-all').indeterminate = (ids.length > 0 && ids.length < total);
}

function toggleTodos(checkbox) {
    document.querySelectorAll('.slot-checkbox').forEach(function(c) { c.checked = checkbox.checked; });
    atualizarSelecao();
}

function limparSelecao() {
    document.querySelectorAll('.slot-checkbox').forEach(function(c) { c.checked = false; });
    document.getElementById('check-all').checked = false;
    document.getElementById('form-lote-acoes').style.display = 'none';
}
</script>

<?php
require_once __DIR__ . '/includes/admin-footer.php';
?>
