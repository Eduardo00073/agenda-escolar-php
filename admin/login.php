<?php
/**
 * Agenda Escolar - Login Administrativo
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../db.php';

// ── Cadastro automático temporário de Jessica e Suellen ──
try {
    $db_temp = db();
    $usuarios_temp = [
        [
            'nome' => 'Jessica Bonfim de Araujo',
            'login' => 'jessica',
            'senha' => 'jessica@2026'
        ],
        [
            'nome' => 'Suellen Karla Pedro Silva',
            'login' => 'suellen',
            'senha' => 'suellen@2026'
        ]
    ];
    foreach ($usuarios_temp as $u) {
        $stmtCheck = $db_temp->prepare("SELECT COUNT(*) FROM administradores WHERE email = ?");
        $stmtCheck->execute([$u['login']]);
        if ($stmtCheck->fetchColumn() == 0) {
            $hash = password_hash($u['senha'], PASSWORD_BCRYPT);
            $stmtInsert = $db_temp->prepare("INSERT INTO administradores (nome, email, senha) VALUES (?, ?, ?)");
            $stmtInsert->execute([$u['nome'], $u['login'], $hash]);
        }
    }
} catch (Exception $e) {
    // Silencia se houver erro temporário
}

// Se já estiver logado, redireciona direto para o painel
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    redirect(BASE_URL . '/admin/index.php');
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizar($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    // Validar token de proteção contra CSRF
    if (!validarCSRFToken($csrf_token)) {
        $erro = 'Erro de validação de sessão. Tente novamente.';
    } elseif (empty($email) || empty($senha)) {
        $erro = 'Por favor, preencha todos os campos.';
    } else {
        try {
            $db = db();
            // Buscar administrador pelo e-mail
            $stmt = $db->prepare("SELECT * FROM administradores WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($senha, $admin['senha'])) {
                // Login realizado com sucesso
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_nome'] = $admin['nome'];
                $_SESSION['admin_email'] = $admin['email'];

                flash('login_sucesso', 'Bem-vindo ao painel administrativo!', 'success');
                redirect(BASE_URL . '/admin/index.php');
            } else {
                // Brute-force protection delay
                sleep(1);
                $erro = 'E-mail ou senha incorretos.';
            }
        } catch (PDOException $e) {
            $erro = 'Erro de banco de dados: ' . (DEV_MODE ? $e->getMessage() : 'Tente novamente mais tarde.');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Administrativo | <?php echo SCHOOL_NAME; ?></title>
    <!-- Favicons / Ícone do Navegador -->
    <link rel="icon" type="image/png" href="../assets/img/favicon.png?v=canva">
    <link rel="apple-touch-icon" href="../assets/img/favicon.png?v=canva">
    
    <!-- Google Fonts (Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- CSS Principal -->
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
    <style>
        :root {
            --primary: #ff5000;
            --primary-hover: #cc4000;
            --danger: #ef4444;
            --background: #fafafa;
            --text-main: #18181b;
            --text-muted: #71717a;
            --card-bg: #ffffff;
            --border: #e4e4e7;
            --radius: 12px;
            --shadow: 0 10px 15px -3px rgba(0,0,0,0.05), 0 4px 6px -2px rgba(0,0,0,0.02);
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background);
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            background-color: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            padding: 32px;
        }
        .logo-area {
            text-align: center;
            margin-bottom: 24px;
        }
        .logo-box {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: #18181b; /* Preto neutro da escola */
            padding: 12px 24px;
            border-radius: 8px;
            margin-bottom: 16px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .logo-box img {
            max-height: 44px;
            width: auto;
            display: block;
        }
        .logo-title {
            font-size: 20px;
            font-weight: 700;
        }
        .logo-subtitle {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 4px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-control {
            width: 100%;
            padding: 11px 14px;
            font-size: 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background-color: var(--card-bg);
            color: var(--text-main);
            transition: all 0.2s ease;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(255, 80, 0, 0.15);
        }
        .btn {
            width: 100%;
            padding: 12px;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn:hover {
            background-color: var(--primary-hover);
        }
        .error-message {
            background-color: #fef2f2;
            color: var(--danger);
            border: 1px solid #fee2e2;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 24px;
            font-size: 13px;
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.2s;
        }
        .back-link:hover {
            color: var(--primary);
        }
    </style>
</head>
<body>

<div class="login-wrapper" style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; width: 100%; max-width: 400px; padding: 20px 0;">
    <div class="login-card" style="width: 100%;">
        <div class="logo-area">
            <div class="logo-box">
                <img src="../assets/img/logo.png" alt="Logo <?php echo SCHOOL_NAME; ?>">
            </div>
            <div class="logo-title">Acesso Restrito</div>
            <div class="logo-subtitle">Insira suas credenciais administrativas</div>
        </div>

        <?php exibirMensagensFlash(); ?>

        <?php if (!empty($erro)): ?>
            <div class="error-message">
                <i data-lucide="alert-triangle" size="16" style="flex-shrink: 0;"></i>
                <span><?php echo $erro; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <input type="hidden" name="csrf_token" value="<?php echo gerarCSRFToken(); ?>">

            <div class="form-group">
                <label class="form-label" for="email">Usuário ou E-mail</label>
                <input type="text" id="email" name="email" class="form-control" placeholder="exemplo ou exemplo@escolaexemplo.com.br" required autofocus>
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label class="form-label" for="senha">Senha de Acesso</label>
                <input type="password" id="senha" name="senha" class="form-control" placeholder="Sua senha secreta" required>
            </div>

            <button type="submit" class="btn">
                <i data-lucide="log-in" size="16"></i> Entrar no Painel
            </button>
        </form>

        <a href="../index.php" class="back-link">
            <i data-lucide="arrow-left" size="14" style="vertical-align: middle; margin-right: 4px;"></i>
            Voltar ao site da escola
        </a>
    </div>

    <!-- Rodapé de Login -->
    <div style="text-align: center; margin-top: 24px; font-size: 12px; color: var(--text-muted); line-height: 1.5; width: 100%;">
        <p>&copy; <?php echo date('Y'); ?> <?php echo SCHOOL_NAME; ?>. Todos os direitos reservados.</p>
        <p style="margin-top: 4px;">
            <a href="https://www.prof-eduardo.com/" target="_blank" style="color: inherit; text-decoration: none; opacity: 0.85;">Desenvolvedor: Eduardo Junior Alcântara da Silva - Professor de TI e desenvolvedor full stack</a>
        </p>
    </div>
</div>

<script>
    // Inicializar ícones do Lucide
    lucide.createIcons();
</script>
</body>
</html>
