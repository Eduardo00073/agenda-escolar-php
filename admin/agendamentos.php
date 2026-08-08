<?php
/**
 * Agenda Escolar - Listagem e Filtro de Agendamentos
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../db.php';

// Garantir autenticação antes de qualquer ação
requireLogin();

$db = db();

// Processamento de Ação via POST Direto (Backup sem AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && isset($_POST['agendamento_id'])) {
    $agendamento_id = filter_input(INPUT_POST, 'agendamento_id', FILTER_VALIDATE_INT);
    $acao = sanitizar($_POST['acao']);
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (validarCSRFToken($csrf_token) && $agendamento_id) {
        $novo_status = '';
        if ($acao === 'confirmar') $novo_status = 'confirmado';
        elseif ($acao === 'cancelar') $novo_status = 'cancelado';
        elseif ($acao === 'realizar') $novo_status = 'realizado';
        
        if (!empty($novo_status)) {
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
                
                $params[] = $agendamento_id;
                $stmtUpdate = $db->prepare("UPDATE agendamentos SET $sql_campos WHERE id = ?");
                $stmtUpdate->execute($params);
                flash('status_sucesso', 'Status do agendamento atualizado para "' . ucfirst($novo_status) . '".', 'success');
            } catch (Exception $e) {
                flash('status_erro', 'Erro ao atualizar status do agendamento.', 'danger');
            }
            redirect(BASE_URL . '/admin/agendamentos.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
        }
    }
}

// Carrega o cabeçalho visual apenas após processar as ações (evitando "headers already sent" ao redirecionar)
require_once __DIR__ . '/includes/admin-header.php';

// Parâmetros de Filtro e Busca
$busca = sanitizar($_GET['busca'] ?? '');
$status_filtro = sanitizar($_GET['status'] ?? '');
$data_filtro = sanitizar($_GET['data'] ?? '');
$pagina = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT) ?: 1;
$limite = 10; // Itens por página
$offset = ($pagina - 1) * $limite;

// Construção da query com filtros
$where_clauses = [];
$params = [];

if (!empty($busca)) {
    $where_clauses[] = "(a.nome_responsavel LIKE ? OR a.nome_crianca LIKE ? OR a.telefone LIKE ? OR a.token LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

if (!empty($status_filtro)) {
    $where_clauses[] = "a.status = ?";
    $params[] = $status_filtro;
}

if (!empty($data_filtro)) {
    $where_clauses[] = "h.data = ?";
    $params[] = $data_filtro;
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
}

// Contar total de registros para paginação
$total_registros = 0;
try {
    $stmtCount = $db->prepare("
        SELECT COUNT(*) 
        FROM agendamentos a
        JOIN horarios_disponiveis h ON a.horario_id = h.id
        $where_sql
    ");
    $stmtCount->execute($params);
    $total_registros = (int)$stmtCount->fetchColumn();
} catch (Exception $e) {
    // Silenciar
}

$total_paginas = ceil($total_registros / $limite);

// Buscar agendamentos com paginação
$agendamentos = [];
try {
    $stmt = $db->prepare("
        SELECT a.*, h.data, h.hora_inicio, h.hora_fim, s.nome as nome_segmento,
               adm.nome as nome_admin_atualizador
        FROM agendamentos a
        JOIN horarios_disponiveis h ON a.horario_id = h.id
        JOIN segmentos s ON a.segmento_id = s.id
        LEFT JOIN administradores adm ON a.atualizado_por_admin_id = adm.id
        $where_sql
        ORDER BY h.data DESC, h.hora_inicio DESC
        LIMIT ? OFFSET ?
    ");
    $params[] = $limite;
    $params[] = $offset;
    $stmt->execute($params);
    $agendamentos = $stmt->fetchAll();
} catch (Exception $e) {
    // Silenciar
}
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h2 style="font-size: 24px; font-weight: 700; margin-bottom: 6px;">Agendamentos de Visitas</h2>
        <p style="color: var(--text-muted); font-size: 14px;">Gerencie, filtre e atualize o status das visitas agendadas.</p>
    </div>
</div>

<!-- Filtros e Busca -->
<div class="card" style="margin-bottom: 24px; padding: 20px;">
    <form method="GET" action="agendamentos.php" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap;">
        <!-- Campo de Pesquisa Geral com ícone interno -->
        <div class="form-group" style="margin: 0; flex: 2; min-width: 250px; position: relative;">
            <label class="form-label" for="busca" style="margin-bottom: 6px; display: block; font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Pesquisa</label>
            <div style="position: relative;">
                <i data-lucide="search" size="16" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                <input type="text" id="busca" name="busca" class="form-control" placeholder="Digite responsável, criança, telefone..." value="<?php echo htmlspecialchars($busca); ?>" style="padding-left: 40px; height: 42px; width: 100%;">
            </div>
        </div>
        
        <!-- Filtro de Status -->
        <div class="form-group" style="margin: 0; flex: 1; min-width: 180px;">
            <label class="form-label" for="status" style="margin-bottom: 6px; display: block; font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Status</label>
            <select id="status" name="status" class="form-control" style="height: 42px; width: 100%;">
                <option value="">Todos os status</option>
                <option value="pendente" <?php echo $status_filtro === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                <option value="confirmado" <?php echo $status_filtro === 'confirmado' ? 'selected' : ''; ?>>Confirmado</option>
                <option value="realizado" <?php echo $status_filtro === 'realizado' ? 'selected' : ''; ?>>Realizado</option>
                <option value="cancelado" <?php echo $status_filtro === 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
            </select>
        </div>
        
        <!-- Filtro de Data -->
        <div class="form-group" style="margin: 0; flex: 1; min-width: 180px;">
            <label class="form-label" for="data" style="margin-bottom: 6px; display: block; font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Data da Visita</label>
            <input type="date" id="data" name="data" class="form-control" value="<?php echo htmlspecialchars($data_filtro); ?>" style="height: 42px; width: 100%;">
        </div>
        
        <!-- Botões de Ação -->
        <div style="display: flex; gap: 8px; height: 42px;">
            <button type="submit" class="btn btn-primary" style="height: 42px; display: inline-flex; align-items: center; gap: 6px; padding: 0 16px;">
                <i data-lucide="filter" size="16"></i> Filtrar
            </button>
            <?php if (!empty($busca) || !empty($status_filtro) || !empty($data_filtro)): ?>
                <a href="agendamentos.php" class="btn btn-secondary" style="height: 42px; display: inline-flex; align-items: center; justify-content: center; padding: 0 16px; text-decoration: none;">
                    <i data-lucide="rotate-ccw" size="16" style="margin-right: 4px;"></i> Limpar
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Listagem em Tabela -->
<div class="card" style="padding: 0; overflow: hidden;">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Data & Hora</th>
                    <th>Responsável</th>
                    <th>Criança / Série</th>
                    <th>Contato</th>
                    <th>Status</th>
                    <th style="text-align: right;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($agendamentos)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px 0; color: var(--text-muted);">
                            Nenhum agendamento encontrado para os filtros selecionados.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($agendamentos as $row): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 700; color: var(--text-main);"><?php echo formatarData($row['data']); ?></div>
                                <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                                    <?php echo formatarHora($row['hora_inicio']); ?> às <?php echo formatarHora($row['hora_fim']); ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 600;"><?php echo $row['nome_responsavel']; ?></div>
                                <div style="font-size: 11px; color: var(--text-muted); font-family: monospace; margin-top: 2px;"><?php echo $row['token']; ?></div>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--text-main);"><?php echo $row['nome_crianca']; ?></div>
                                <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                                    <?php echo $row['nome_segmento']; ?> (<?php echo $row['serie_interesse']; ?>)
                                </div>
                            </td>
                            <td>
                                <div><?php echo formatarTelefone($row['telefone']); ?></div>
                                <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;"><?php echo $row['email']; ?></div>
                            </td>
                            <td>
                                <?php 
                                $badgeClass = 'pending';
                                if ($row['status'] === 'confirmado') $badgeClass = 'confirmed';
                                elseif ($row['status'] === 'realizado') $badgeClass = 'completed';
                                elseif ($row['status'] === 'cancelado') $badgeClass = 'cancelled';
                                ?>
                                <span class="badge badge-<?php echo $badgeClass; ?>">
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                                <?php if (!empty($row['nome_admin_atualizador'])): ?>
                                    <div style="font-size: 10px; color: var(--text-muted); margin-top: 4px;" title="Alterado por: <?php echo htmlspecialchars($row['nome_admin_atualizador']); ?>">
                                        <i data-lucide="user-check" size="10" style="vertical-align: middle; margin-right: 1px; color: var(--primary);"></i>
                                        <?php 
                                        $partes = explode(' ', $row['nome_admin_atualizador']);
                                        echo htmlspecialchars($partes[0]); 
                                        ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 8px; justify-content: flex-end; align-items: center;">
                                    <!-- Ação Visualizar Detalhes -->
                                    <a href="agendamento-detalhes.php?id=<?php echo $row['id']; ?>" class="btn btn-secondary btn-sm" title="Visualizar Detalhes">
                                        <i data-lucide="eye" size="14"></i> Detalhes
                                    </a>
                                    
                                    <!-- Formulário rápido para ações rápidas de status -->
                                    <form method="POST" action="agendamentos.php<?php echo !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''; ?>" style="display: inline-flex; gap: 4px;">
                                        <input type="hidden" name="csrf_token" value="<?php echo gerarCSRFToken(); ?>">
                                        <input type="hidden" name="agendamento_id" value="<?php echo $row['id']; ?>">
                                        
                                        <?php if ($row['status'] === 'pendente'): ?>
                                            <button type="submit" name="acao" value="confirmar" class="btn btn-accent btn-sm" style="background-color: var(--primary); color: white;" title="Confirmar Visita">
                                                <i data-lucide="check" size="14"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($row['status'] === 'confirmado'): ?>
                                            <button type="submit" name="acao" value="realizar" class="btn btn-success btn-sm" title="Marcar como Realizada">
                                                <i data-lucide="smile" size="14"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($row['status'] !== 'cancelado' && $row['status'] !== 'realizado'): ?>
                                            <button type="submit" name="acao" value="cancelar" class="btn btn-danger btn-sm" onclick="return confirm('Deseja realmente cancelar este agendamento?');" title="Cancelar Visita">
                                                <i data-lucide="x" size="14"></i>
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Paginação -->
<?php if ($total_paginas > 1): ?>
    <div style="display: flex; justify-content: center; gap: 8px; margin-top: 24px;">
        <?php 
        $query_params = $_GET;
        
        // Botão Anterior
        if ($pagina > 1) {
            $query_params['pagina'] = $pagina - 1;
            echo '<a href="agendamentos.php?' . http_build_query($query_params) . '" class="btn btn-secondary btn-sm"><i data-lucide="chevron-left" size="14"></i> Anterior</a>';
        }
        
        // Páginas Numéricas
        for ($i = 1; $i <= $total_paginas; $i++) {
            $query_params['pagina'] = $i;
            $activeClass = ($i === $pagina) ? 'btn-primary' : 'btn-secondary';
            echo '<a href="agendamentos.php?' . http_build_query($query_params) . '" class="btn ' . $activeClass . ' btn-sm">' . $i . '</a>';
        }
        
        // Botão Próximo
        if ($pagina < $total_paginas) {
            $query_params['pagina'] = $pagina + 1;
            echo '<a href="agendamentos.php?' . http_build_query($query_params) . '" class="btn btn-secondary btn-sm">Próximo <i data-lucide="chevron-right" size="14"></i></a>';
        }
        ?>
    </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/admin-footer.php';
?>
