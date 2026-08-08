<?php
/**
 * Agenda Escolar - Header Administrativo (Sidebar + Topbar)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../db.php';

// Garantir que o usuário está autenticado
requireLogin();

// Determinar página atual para aplicar class "active" nos links
$pagina_atual = basename($_SERVER['SCRIPT_NAME']);

// Obter contagem de agendamentos com status "pendente" para exibir badge de alerta
$db = db();
$total_pendentes = 0;
try {
    $stmtCount = $db->query("SELECT COUNT(*) FROM agendamentos WHERE status = 'pendente'");
    $total_pendentes = (int)$stmtCount->fetchColumn();
} catch (Exception $e) {
    // Silencia erros
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo | <?php echo SCHOOL_NAME; ?></title>
    <!-- Favicons / Ícone do Navegador -->
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>/assets/img/favicon.png?v=canva">
    <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>/assets/img/favicon.png?v=canva">
    
    <!-- Google Fonts (Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- CSS Principal Compartilhado -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../../assets/css/style.css'); ?>">
    
    <!-- Se for a página de calendário, incluir estilos do FullCalendar -->
    <?php if ($pagina_atual === 'calendario.php'): ?>
        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
        <style>
            /* Ajustes finos do FullCalendar para seguir o design system */
            .fc {
                font-family: inherit;
                --fc-border-color: var(--border);
                --fc-today-bg-color: rgba(255, 80, 0, 0.05);
                --fc-button-bg-color: var(--primary);
                --fc-button-border-color: var(--primary);
                --fc-button-hover-bg-color: var(--primary-dark);
                --fc-button-hover-border-color: var(--primary-dark);
                --fc-button-active-bg-color: var(--primary-dark);
                --fc-button-active-border-color: var(--primary-dark);
            }
            .fc-event {
                cursor: pointer;
                border: none !important;
                padding: 2px 6px;
                border-radius: var(--radius-sm);
                font-size: 12px;
                font-weight: 500;
            }
            .fc-event-pending { background-color: var(--warning) !important; color: white !important; }
            .fc-event-confirmed { background-color: var(--primary) !important; color: white !important; }
            .fc-event-realizado { background-color: var(--success) !important; color: white !important; }
            .fc-event-cancelado { background-color: var(--danger) !important; color: white !important; text-decoration: line-through; }
        </style>
    <?php endif; ?>
</head>
<body>

<div class="admin-layout">
    
    <!-- Sidebar Administrativa -->
    <aside class="admin-sidebar">
        <div class="admin-sidebar-logo">
            <img src="<?php echo BASE_URL; ?>/assets/img/logo.png" alt="<?php echo SCHOOL_NAME; ?>">
        </div>
        
        <nav style="flex-grow: 1;">
            <ul class="admin-nav">
                <li class="admin-nav-item">
                    <a href="<?php echo BASE_URL; ?>/admin/index.php" class="admin-nav-link <?php echo $pagina_atual === 'index.php' ? 'active' : ''; ?>">
                        <i data-lucide="layout-dashboard" size="18"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <li class="admin-nav-item">
                    <a href="<?php echo BASE_URL; ?>/admin/agendamentos.php" class="admin-nav-link <?php echo ($pagina_atual === 'agendamentos.php' || $pagina_atual === 'agendamento-detalhes.php') ? 'active' : ''; ?>">
                        <i data-lucide="clipboard-list" size="18"></i>
                        <span>Agendamentos</span>
                        <?php if ($total_pendentes > 0): ?>
                            <span style="background-color: var(--warning); color: white; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 50px; margin-left: auto;">
                                <?php echo $total_pendentes; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                
                <li class="admin-nav-item">
                    <a href="<?php echo BASE_URL; ?>/admin/calendario.php" class="admin-nav-link <?php echo $pagina_atual === 'calendario.php' ? 'active' : ''; ?>">
                        <i data-lucide="calendar" size="18"></i>
                        <span>Calendário de Visitas</span>
                    </a>
                </li>

                <li class="admin-nav-item">
                    <a href="<?php echo BASE_URL; ?>/admin/horarios.php" class="admin-nav-link <?php echo $pagina_atual === 'horarios.php' ? 'active' : ''; ?>">
                        <i data-lucide="calendar-clock" size="18"></i>
                        <span>Horários Disponíveis</span>
                    </a>
                </li>
                
                <li class="admin-nav-item">
                    <a href="<?php echo BASE_URL; ?>/admin/segmentos.php" class="admin-nav-link <?php echo $pagina_atual === 'segmentos.php' ? 'active' : ''; ?>">
                        <i data-lucide="layers" size="18"></i>
                        <span>Segmentos de Ensino</span>
                    </a>
                </li>
                
                <li class="admin-nav-item">
                    <a href="<?php echo BASE_URL; ?>/admin/administradores.php" class="admin-nav-link <?php echo $pagina_atual === 'administradores.php' ? 'active' : ''; ?>">
                        <i data-lucide="users" size="18"></i>
                        <span>Gerenciar Acessos</span>
                    </a>
                </li>

                <li class="admin-nav-item">
                    <a href="<?php echo BASE_URL; ?>/admin/configuracoes.php" class="admin-nav-link <?php echo $pagina_atual === 'configuracoes.php' ? 'active' : ''; ?>">
                        <i data-lucide="settings" size="18"></i>
                        <span>Configurações</span>
                    </a>
                </li>
            </ul>
        </nav>
        
        <!-- Área Inferior do Menu (Ajuda + Logout) -->
        <div style="padding: 16px 12px 20px; border-top: 1px solid rgba(255,255,255,0.08); display: flex; flex-direction: column; gap: 4px;">
            <a href="<?php echo BASE_URL; ?>/admin/ajuda.php" class="admin-nav-link <?php echo $pagina_atual === 'ajuda.php' ? 'active' : ''; ?>" style="color: rgba(255,255,255,0.6);">
                <i data-lucide="bot" size="18"></i>
                <span>Assistente do Prof. Edu. [IA]</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/logout.php" class="admin-nav-link" style="color: rgba(239, 68, 68, 0.8);">
                <i data-lucide="log-out" size="18"></i>
                <span>Sair do Painel</span>
            </a>
        </div>
    </aside>
    
    <!-- Lado Direito (Topbar + Conteúdo) -->
    <div class="admin-main">
        
        <!-- Topbar Administrativa -->
        <header class="admin-topbar">
            <!-- Botão Hamburguer no Mobile -->
            <button class="admin-menu-toggle" id="adminSidebarToggle">
                <i data-lucide="menu" size="24"></i>
            </button>
            
            <div style="font-weight: 500; font-size: 14px; color: var(--text-muted);">
                Painel Administrativo
            </div>
            
            <div style="display: flex; align-items: center; gap: 16px;">
                <div style="text-align: right; line-height: 1.3;">
                    <div style="font-weight: 600; font-size: 14px; color: var(--text-main);"><?php echo getAdminNome(); ?></div>
                    <div style="font-size: 11px; color: var(--text-muted);">Administrador</div>
                </div>
                
                <div style="width: 36px; height: 36px; border-radius: 50%; background-color: var(--primary-bg); color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">
                    <?php 
                    $iniciais = '';
                    $nomes = explode(' ', getAdminNome());
                    foreach (array_slice($nomes, 0, 2) as $n) {
                        $iniciais .= strtoupper(substr($n, 0, 1));
                    }
                    echo $iniciais;
                    ?>
                </div>
            </div>
        </header>
        
        <!-- Área de Toasts Administrativos -->
        <?php exibirMensagensFlash(); ?>
        
        <!-- Conteúdo Interno do Painel -->
        <main class="admin-content">
