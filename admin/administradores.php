<?php
/**
 * Agenda Escolar - Gerenciamento de Administradores (Criação de Usuários)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../db.php';

// Garantir autenticação antes de qualquer ação
requireLogin();

$db = db();
$mensagem_sucesso = '';
$mensagem_erro = '';

// Processamento de Ações do Formulário (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $acao = sanitizar($_POST['acao']);
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (validarCSRFToken($csrf_token)) {
        
        // Ação: Cadastrar Novo Administrador
        if ($acao === 'cadastrar') {
            $nome = sanitizar($_POST['nome'] ?? '');
            $email = sanitizar($_POST['email'] ?? '');
            $senha = $_POST['senha'] ?? '';
            
            if (empty($nome) || empty($email) || empty($senha)) {
                $mensagem_erro = 'Por favor, preencha todos os campos corretamente.';
            } elseif (strlen($senha) < 6) {
                $mensagem_erro = 'A senha deve possuir pelo menos 6 caracteres.';
            } else {
                try {
                    // Verificar se o e-mail já existe
                    $stmtCheck = $db->prepare("SELECT COUNT(*) FROM administradores WHERE email = ?");
                    $stmtCheck->execute([$email]);
                    if ($stmtCheck->fetchColumn() > 0) {
                        $mensagem_erro = 'Já existe um administrador cadastrado com este e-mail.';
                    } else {
                        // Criptografar a senha com hash seguro bcrypt
                        $senha_hash = password_hash($senha, PASSWORD_BCRYPT);
                        
                        $stmtInsert = $db->prepare("INSERT INTO administradores (nome, email, senha) VALUES (?, ?, ?)");
                        $stmtInsert->execute([$nome, $email, $senha_hash]);
                        
                        flash('admin_msg', 'Novo administrador cadastrado com sucesso!', 'success');
                        redirect(BASE_URL . '/admin/administradores.php');
                    }
                } catch (PDOException $e) {
                    $mensagem_erro = 'Erro ao cadastrar administrador.';
                }
            }
        }
        
        // Ação: Excluir Administrador
        elseif ($acao === 'excluir') {
            $id_excluir = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $admin_logado_id = getAdminId();
            
            if ($id_excluir) {
                if ($id_excluir == $admin_logado_id) {
                    $mensagem_erro = 'Você não pode excluir o seu próprio login de acesso.';
                } else {
                    try {
                        // Garantir que restará pelo menos um administrador no sistema
                        $stmtCount = $db->query("SELECT COUNT(*) FROM administradores");
                        $total_admins = (int)$stmtCount->fetchColumn();
                        
                        if ($total_admins <= 1) {
                            $mensagem_erro = 'Não é possível excluir o último administrador cadastrado no sistema.';
                        } else {
                            $stmtDel = $db->prepare("DELETE FROM administradores WHERE id = ?");
                            $stmtDel->execute([$id_excluir]);
                            
                            flash('admin_msg', 'Administrador excluído com sucesso.', 'success');
                            redirect(BASE_URL . '/admin/administradores.php');
                        }
                    } catch (Exception $e) {
                        $mensagem_erro = 'Erro ao excluir administrador.';
                    }
                }
            }
        }
    }
}

// Carrega o cabeçalho visual apenas após processar as ações (evitando "headers already sent" ao redirecionar)
require_once __DIR__ . '/includes/admin-header.php';

// Buscar administradores existentes para a tabela
$administradores = [];
try {
    $stmt = $db->query("SELECT id, nome, email, criado_em FROM administradores ORDER BY nome ASC");
    $administradores = $stmt->fetchAll();
} catch (Exception $e) {
    // Silencia
}
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h2 style="font-size: 24px; font-weight: 700; margin-bottom: 6px;">Gerenciar Acessos</h2>
        <p style="color: var(--text-muted); font-size: 14px;">Cadastre novos usuários administradores para gerenciar os agendamentos da escola.</p>
    </div>
    
    <button onclick="document.getElementById('modalAdicionarAdmin').classList.add('open')" class="btn btn-primary">
        <i data-lucide="user-plus" size="16"></i> Adicionar Administrador
    </button>
</div>

<?php if (!empty($mensagem_erro)): ?>
    <div style="background-color: var(--danger-bg); color: var(--danger); border: 1px solid #fecaca; padding: 16px; border-radius: var(--radius-md); margin-bottom: 24px; font-size: 14px;">
        <i data-lucide="alert-circle" size="18" style="vertical-align: middle; margin-right: 8px;"></i>
        <?php echo $mensagem_erro; ?>
    </div>
<?php endif; ?>

<!-- Flash messages são exibidas pelo admin-header.php via exibirMensagensFlash() -->

<!-- Tabela de Usuários Cadastrados -->
<div class="card" style="padding: 0; overflow: hidden;">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Usuário / E-mail</th>
                    <th>Cadastrado em</th>
                    <th style="text-align: right;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($administradores as $admin): ?>
                    <tr>
                        <td style="font-weight: 700; color: var(--text-main);"><?php echo $admin['nome']; ?></td>
                        <td><?php echo $admin['email']; ?></td>
                        <td><?php echo formatarDataHora($admin['criado_em']); ?></td>
                        <td style="text-align: right;">
                            <!-- Impedir auto-exclusão visualmente -->
                            <?php if ($admin['id'] != getAdminId()): ?>
                                <form method="POST" action="administradores.php" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo gerarCSRFToken(); ?>">
                                    <input type="hidden" name="id" value="<?php echo $admin['id']; ?>">
                                    <button type="submit" name="acao" value="excluir" class="btn btn-danger btn-sm" onclick="return confirm('Deseja realmente remover o acesso deste administrador?');" title="Excluir Acesso">
                                        <i data-lucide="trash-2" size="14"></i> Remover
                                    </button>
                                </form>
                            <?php else: ?>
                                <span style="font-size: 12px; color: var(--text-muted); font-style: italic; font-weight: 500;">Sua Sessão Ativa</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL: ADICIONAR NOVO ADMINISTRADOR -->
<div class="modal" id="modalAdicionarAdmin">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="font-size: 16px;">Criar Login Administrativo</h3>
            <button onclick="document.getElementById('modalAdicionarAdmin').classList.remove('open')" class="close-btn">
                <i data-lucide="x" size="20"></i>
            </button>
        </div>
        <form method="POST" action="administradores.php">
            <input type="hidden" name="csrf_token" value="<?php echo gerarCSRFToken(); ?>">
            <input type="hidden" name="acao" value="cadastrar">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="nome">Nome Completo *</label>
                    <input type="text" id="nome" name="nome" class="form-control" placeholder="Ex: Maria Souza" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="email">Usuário ou E-mail *</label>
                    <input type="text" id="email" name="email" class="form-control" placeholder="Ex: jessica ou exemplo@escolaexemplo.com.br" required>
                    <small style="color: var(--text-muted); font-size: 11px; margin-top: 4px; display: block;">Este usuário ou e-mail será utilizado como login para o painel.</small>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="senha">Senha de Acesso *</label>
                    <input type="password" id="senha" name="senha" class="form-control" placeholder="Mínimo 6 caracteres" required>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" onclick="document.getElementById('modalAdicionarAdmin').classList.remove('open')" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-primary">Criar Usuário</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Fechar modal ao clicar fora
    document.getElementById('modalAdicionarAdmin').addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('open');
        }
    });
</script>

<?php
require_once __DIR__ . '/includes/admin-footer.php';
?>
