<?php
/**
 * Agenda Escolar - Gerenciamento de Segmentos Escolares (CRUD)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../db.php';

// Garantir autenticação antes de qualquer ação
requireLogin();

$db = db();
$mensagem_erro = '';

// Processamento de Ações do Formulário (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $acao = sanitizar($_POST['acao']);
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (validarCSRFToken($csrf_token)) {
        
        // Ação: Cadastrar Novo Segmento
        if ($acao === 'cadastrar') {
            $nome = sanitizar($_POST['nome'] ?? '');
            $ordem = filter_input(INPUT_POST, 'ordem', FILTER_VALIDATE_INT) ?: 0;
            
            if (empty($nome)) {
                $mensagem_erro = 'O nome do segmento é obrigatório.';
            } else {
                try {
                    $stmt = $db->prepare("INSERT INTO segmentos (nome, ordem, ativo) VALUES (?, ?, 1)");
                    $stmt->execute([$nome, $ordem]);
                    
                    flash('seg_msg', 'Segmento cadastrado com sucesso!', 'success');
                    redirect(BASE_URL . '/admin/segmentos.php');
                } catch (PDOException $e) {
                    $mensagem_erro = 'Erro ao cadastrar segmento: ' . (str_contains($e->getMessage(), 'Duplicate entry') ? 'Este segmento já existe.' : $e->getMessage());
                }
            }
        }
        
        // Ação: Editar Segmento Existente
        elseif ($acao === 'editar') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $nome = sanitizar($_POST['nome'] ?? '');
            $ordem = filter_input(INPUT_POST, 'ordem', FILTER_VALIDATE_INT) ?: 0;
            
            if (!$id || empty($nome)) {
                $mensagem_erro = 'Por favor, preencha todos os dados da edição.';
            } else {
                try {
                    $stmt = $db->prepare("UPDATE segmentos SET nome = ?, ordem = ? WHERE id = ?");
                    $stmt->execute([$nome, $ordem, $id]);
                    
                    flash('seg_msg', 'Segmento atualizado com sucesso!', 'success');
                    redirect(BASE_URL . '/admin/segmentos.php');
                } catch (PDOException $e) {
                    $mensagem_erro = 'Erro ao atualizar segmento: ' . (str_contains($e->getMessage(), 'Duplicate entry') ? 'Este nome de segmento já existe.' : $e->getMessage());
                }
            }
        }
        
        // Ação: Alternar Status Ativo
        elseif ($acao === 'alternar_status') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if ($id) {
                try {
                    $stmt = $db->prepare("UPDATE segmentos SET ativo = NOT ativo WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    flash('seg_msg', 'Status do segmento alterado com sucesso.', 'success');
                    redirect(BASE_URL . '/admin/segmentos.php');
                } catch (Exception $e) {
                    $mensagem_erro = 'Erro ao alterar status.';
                }
            }
        }
        
        // Ação: Excluir Segmento
        elseif ($acao === 'excluir') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if ($id) {
                try {
                    // Verificar se há agendamentos associados
                    $stmtCheck = $db->prepare("SELECT COUNT(*) FROM agendamentos WHERE segmento_id = ?");
                    $stmtCheck->execute([$id]);
                    $agendamentos_associados = (int)$stmtCheck->fetchColumn();
                    
                    if ($agendamentos_associados > 0) {
                        $mensagem_erro = "Não é possível excluir o segmento pois há $agendamentos_associados agendamento(s) associado(s) a ele. Considere desativá-lo.";
                    } else {
                        $stmtDel = $db->prepare("DELETE FROM segmentos WHERE id = ?");
                        $stmtDel->execute([$id]);
                        
                        flash('seg_msg', 'Segmento excluído permanentemente.', 'success');
                        redirect(BASE_URL . '/admin/segmentos.php');
                    }
                } catch (Exception $e) {
                    $mensagem_erro = 'Erro ao excluir segmento.';
                }
            }
        }
    }
}

// Carrega o cabeçalho visual apenas após processar as ações (evitando "headers already sent" ao redirecionar)
require_once __DIR__ . '/includes/admin-header.php';

// Buscar segmentos para listagem
$segmentos = [];
try {
    $stmt = $db->query("SELECT * FROM segmentos ORDER BY ordem ASC, nome ASC");
    $segmentos = $stmt->fetchAll();
} catch (Exception $e) {
    // Silencia
}
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h2 style="font-size: 24px; font-weight: 700; margin-bottom: 6px;">Segmentos de Ensino</h2>
        <p style="color: var(--text-muted); font-size: 14px;">Cadastre e gerencie as séries/segmentos disponíveis na seleção do formulário.</p>
    </div>
    
    <button onclick="document.getElementById('modalAdicionar').classList.add('open')" class="btn btn-primary">
        <i data-lucide="plus" size="16"></i> Adicionar Segmento
    </button>
</div>

<?php if (!empty($mensagem_erro)): ?>
    <div style="background-color: var(--danger-bg); color: var(--danger); border: 1px solid #fecaca; padding: 16px; border-radius: var(--radius-md); margin-bottom: 24px; font-size: 14px;">
        <i data-lucide="alert-circle" size="18" style="vertical-align: middle; margin-right: 8px;"></i>
        <?php echo $mensagem_erro; ?>
    </div>
<?php endif; ?>

<!-- Flash messages são exibidas pelo admin-header.php via exibirMensagensFlash() -->

<!-- Tabela de Segmentos -->
<div class="card" style="padding: 0; overflow: hidden;">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Ordem de Exibição</th>
                    <th>Nome do Segmento</th>
                    <th>Status</th>
                    <th style="text-align: right;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($segmentos)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 40px 0; color: var(--text-muted);">
                            Nenhum segmento cadastrado. Clique no botão acima para cadastrar o primeiro.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($segmentos as $seg): ?>
                        <tr style="<?php echo !$seg['ativo'] ? 'opacity: 0.6;' : ''; ?>">
                            <td style="font-weight: 600; width: 180px; color: var(--text-muted);">
                                #<?php echo $seg['ordem']; ?>
                            </td>
                            <td style="font-weight: 700; color: var(--text-main);"><?php echo $seg['nome']; ?></td>
                            <td>
                                <?php if ($seg['ativo']): ?>
                                    <span class="badge badge-completed">Ativo</span>
                                <?php else: ?>
                                    <span class="badge badge-cancelled">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 8px; justify-content: flex-end;">
                                    
                                    <!-- Ação Editar (Carrega modal com dados) -->
                                    <button onclick="abrirModalEdicao(<?php echo $seg['id']; ?>, <?php echo htmlspecialchars(json_encode($seg['nome']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo $seg['ordem']; ?>)" class="btn btn-secondary btn-sm" title="Editar Segmento">
                                        <i data-lucide="edit-3" size="14"></i>
                                    </button>
                                    
                                    <!-- Ação Alternar Status Ativo — toggle switch -->
                                    <form method="POST" action="segmentos.php" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo gerarCSRFToken(); ?>">
                                        <input type="hidden" name="id" value="<?php echo $seg['id']; ?>">
                                        <button type="submit" name="acao" value="alternar_status"
                                                class="slot-toggle <?php echo $seg['ativo'] ? 'slot-toggle-on' : 'slot-toggle-off'; ?>"
                                                title="<?php echo $seg['ativo'] ? 'Clique para desativar este segmento' : 'Clique para ativar este segmento'; ?>">
                                            <span class="slot-toggle-knob"></span>
                                            <span class="slot-toggle-label"><?php echo $seg['ativo'] ? 'Ativo' : 'Inativo'; ?></span>
                                        </button>
                                    </form>
                                    
                                    <!-- Ação Excluir Segmento -->
                                    <form method="POST" action="segmentos.php" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo gerarCSRFToken(); ?>">
                                        <input type="hidden" name="id" value="<?php echo $seg['id']; ?>">
                                        <button type="submit" name="acao" value="excluir" class="btn btn-danger btn-sm" onclick="return confirm('Deseja realmente excluir este segmento? Esta ação é definitiva.');" title="Excluir Segmento">
                                            <i data-lucide="trash-2" size="14"></i>
                                        </button>
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

<!-- MODAL 1: ADICIONAR SEGMENTO -->
<div class="modal" id="modalAdicionar">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="font-size: 16px;">Adicionar Segmento Escolar</h3>
            <button onclick="document.getElementById('modalAdicionar').classList.remove('open')" class="close-btn">
                <i data-lucide="x" size="20"></i>
            </button>
        </div>
        <form method="POST" action="segmentos.php">
            <input type="hidden" name="csrf_token" value="<?php echo gerarCSRFToken(); ?>">
            <input type="hidden" name="acao" value="cadastrar">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="nome">Nome do Segmento *</label>
                    <input type="text" id="nome" name="nome" class="form-control" placeholder="Ex: Berçário, Anos Finais, Pré" required>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="ordem">Ordem de Exibição</label>
                    <input type="number" id="ordem" name="ordem" class="form-control" value="0" min="0">
                    <small style="color: var(--text-muted); font-size: 11px; margin-top: 4px; display: block;">Define em qual ordem o segmento aparecerá na área pública (menor para maior).</small>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" onclick="document.getElementById('modalAdicionar').classList.remove('open')" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar Segmento</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 2: EDITAR SEGMENTO -->
<div class="modal" id="modalEditar">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="font-size: 16px;">Editar Segmento Escolar</h3>
            <button onclick="document.getElementById('modalEditar').classList.remove('open')" class="close-btn">
                <i data-lucide="x" size="20"></i>
            </button>
        </div>
        <form method="POST" action="segmentos.php">
            <input type="hidden" name="csrf_token" value="<?php echo gerarCSRFToken(); ?>">
            <input type="hidden" name="acao" value="editar">
            <input type="hidden" name="id" id="edit_id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="edit_nome">Nome do Segmento *</label>
                    <input type="text" id="edit_nome" name="nome" class="form-control" required>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="edit_ordem">Ordem de Exibição</label>
                    <input type="number" id="edit_ordem" name="ordem" class="form-control" min="0">
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" onclick="document.getElementById('modalEditar').classList.remove('open')" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-primary">Atualizar</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Função para abrir o modal de edição carregando as variáveis nos inputs
    function abrirModalEdicao(id, nome, ordem) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_nome').value = nome;
        document.getElementById('edit_ordem').value = ordem;
        
        document.getElementById('modalEditar').classList.add('open');
    }

    // Fechar modais ao clicar fora
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('open');
            }
        });
    });
</script>

<?php
require_once __DIR__ . '/includes/admin-footer.php';
?>
