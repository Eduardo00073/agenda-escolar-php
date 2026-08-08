<?php
/**
 * Agenda Escolar - Script de Limpeza do Banco de Dados (Reset de Testes)
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/db.php';

// Garantir que apenas administradores logados possam acessar e executar
requireLogin();

$db = db();
$sucesso = false;
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['executar_limpeza'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (validarCSRFToken($csrf_token)) {
        try {
            // Desativar checks temporariamente por segurança
            $db->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            // 1. Limpar agendamentos
            $db->exec("DELETE FROM agendamentos");
            $db->exec("ALTER TABLE agendamentos AUTO_INCREMENT = 1");
            
            // 2. Limpar horários disponíveis
            $db->exec("DELETE FROM horarios_disponiveis");
            $db->exec("ALTER TABLE horarios_disponiveis AUTO_INCREMENT = 1");
            
            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
            $sucesso = true;
        } catch (Exception $e) {
            $erro = 'Erro ao realizar a limpeza do banco de dados: ' . $e->getMessage();
        }
    } else {
        $erro = 'Falha na validação de segurança (CSRF). Tente novamente.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Limpeza do Sistema - Agenda Escolar</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --primary: #ff5000;
            --primary-hover: #e04600;
            --primary-bg: #fff3ed;
            --danger: #ef4444;
            --danger-bg: #fef2f2;
            --success: #10b981;
            --success-bg: #ecfdf5;
            --bg-main: #f4f4f5;
            --bg-card: #ffffff;
            --text-main: #18181b;
            --text-muted: #71717a;
            --border: #e4e4e7;
            --radius: 12px;
            --shadow: 0 10px 15px -3px rgba(0,0,0,0.05), 0 4px 6px -2px rgba(0,0,0,0.02);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        
        body {
            background-color: var(--bg-main);
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 500px;
            width: 100%;
        }
        
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 32px;
            box-shadow: var(--shadow);
            text-align: center;
        }
        
        .icon-box {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .icon-box-warning {
            background-color: #fef3c7;
            color: #d97706;
        }
        
        .icon-box-success {
            background-color: var(--success-bg);
            color: var(--success);
        }
        
        h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 12px;
            color: var(--text-main);
        }
        
        p {
            font-size: 14px;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 24px;
        }
        
        .alert-box {
            background-color: var(--danger-bg);
            border: 1.5px dashed var(--danger);
            border-radius: 8px;
            padding: 16px;
            text-align: left;
            margin-bottom: 24px;
        }
        
        .alert-box h2 {
            font-size: 13px;
            font-weight: 700;
            color: var(--danger);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .alert-box ul {
            font-size: 12px;
            color: #7f1d1d;
            list-style: none;
        }
        
        .alert-box li {
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .alert-box li::before {
            content: "•";
            color: var(--danger);
            font-weight: bold;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        .btn-danger {
            background-color: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #dc2626;
            box-shadow: 0 4px 12px rgba(239,68,68,0.25);
        }
        
        .btn-secondary {
            background-color: #f4f4f5;
            color: var(--text-muted);
            border: 1px solid var(--border);
            margin-top: 10px;
        }
        
        .btn-secondary:hover {
            background-color: #e4e4e7;
            color: var(--text-main);
        }
        
        .btn-success {
            background-color: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #059669;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <?php if ($sucesso): ?>
            <!-- Tela de Sucesso -->
            <div class="icon-box icon-box-success">
                <i data-lucide="check-circle" size="32"></i>
            </div>
            <h1>Limpeza Concluída!</h1>
            <p>O banco de dados foi resetado com sucesso para o início da operação.</p>
            
            <div class="alert-box" style="background-color: var(--success-bg); border-color: var(--success); text-align: center;">
                <span style="font-size: 13px; font-weight: 600; color: #065f46;">
                    Todos os agendamentos e horários foram deletados. Os administradores e segmentos de ensino foram mantidos.
                </span>
            </div>
            
            <a href="admin/index.php" class="btn btn-success">
                <i data-lucide="layout-dashboard" size="16"></i> Ir para o Painel
            </a>
        <?php else: ?>
            <!-- Tela de Confirmação -->
            <div class="icon-box icon-box-warning">
                <i data-lucide="alert-triangle" size="32"></i>
            </div>
            <h1>Resetar Dados de Teste</h1>
            <p>Esta ação irá limpar os registros acumulados durante os testes e preparar o sistema para o uso real.</p>
            
            <?php if (!empty($erro)): ?>
                <div style="background-color: var(--danger-bg); color: var(--danger); border: 1.5px solid #fecaca; padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 20px; text-align: left; display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="alert-circle" size="16" style="flex-shrink:0;"></i>
                    <span><?php echo $erro; ?></span>
                </div>
            <?php endif; ?>

            <div class="alert-box">
                <h2>O que será APAGADO?</h2>
                <ul>
                    <li>Todos os agendamentos realizados</li>
                    <li>Todos os horários de visitas criados</li>
                </ul>
                <h2 style="color: var(--success); margin-top: 12px;">O que será MANTIDO?</h2>
                <ul style="color: #065f46;">
                    <li>Configurações gerais da escola</li>
                    <li>Regras de agendamento (antecedência mínima)</li>
                    <li>Todos os segmentos de ensino</li>
                    <li>Todos os administradores (Jéssica, Suellen, etc.)</li>
                </ul>
            </div>
            
            <form method="POST" action="limpeza.php">
                <input type="hidden" name="csrf_token" value="<?php echo gerarCSRFToken(); ?>">
                <button type="submit" name="executar_limpeza" value="1" class="btn btn-danger" onclick="return confirm('ATENÇÃO: Deseja realmente apagar todos os agendamentos e horários disponíveis? Esta ação é irreversível.');">
                    <i data-lucide="trash-2" size="16"></i> Confirmar Limpeza e Reset
                </button>
            </form>
            
            <a href="admin/index.php" class="btn btn-secondary">
                <i data-lucide="arrow-left" size="16"></i> Cancelar e Voltar
            </a>
        <?php endif; ?>
    </div>
</div>

<script>
    // Inicializar os ícones do Lucide
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>
</body>
</html>
