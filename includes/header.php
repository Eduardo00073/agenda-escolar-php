<?php
/**
 * Agenda Escolar - Header Comum (Área Pública)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';

// Determina qual é a página atual para aplicar classe "active" no menu
$pagina_atual = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($titulo_pagina) ? $titulo_pagina . ' | ' . SCHOOL_NAME : SCHOOL_NAME . ' - Agendamento de Visitas'; ?></title>
    
    <!-- Favicons / Ícone do Navegador e Buscadores -->
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>/assets/img/favicon.png?v=canva">
    <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>/assets/img/favicon.png?v=canva">
    
    <!-- Meta tags SEO -->
    <meta name="description" content="Agende uma visita ao Colégio Exemplo Modelo. Conheça nossa proposta pedagógica, estrutura física e corpo docente. Escolha o melhor dia para sua família!">
    <meta name="keywords" content="escola, agendamento de visita, visita guiada, matrícula escolar, educação infantil, ensino médio, jales">
    
    <!-- Open Graph / WhatsApp Link Preview Card -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo BASE_URL; ?>/index.php">
    <meta property="og:title" content="📅 Agende sua Visita | <?php echo SCHOOL_NAME; ?>">
    <meta property="og:description" content="Venha conhecer nossa proposta pedagógica, estrutura e ambiente acolhedor. Escolha o melhor dia e horário para sua família no nosso calendário interativo!">
    <meta property="og:image" content="<?php echo BASE_URL; ?>/assets/img/og_preview.png">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:site_name" content="<?php echo SCHOOL_NAME; ?>">
    
    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="📅 Agende sua Visita | <?php echo SCHOOL_NAME; ?>">
    <meta name="twitter:description" content="Escolha o melhor dia e horário para sua família conhecer o Colégio Exemplo Modelo no nosso calendário interativo!">
    <meta name="twitter:image" content="<?php echo BASE_URL; ?>/assets/img/og_preview.png">
    
    <!-- Google Fonts (Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <!-- Calendário customizado integrado (sem dependência externa) -->
    
    <!-- CSS Principal -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
</head>
<body>

    <!-- Header / Navbar -->
    <header class="site-header">
        <div class="container">
            <a href="<?php echo BASE_URL; ?>/index.php" class="logo">
                <img src="<?php echo BASE_URL; ?>/assets/img/logo.png" alt="<?php echo SCHOOL_NAME; ?>">
            </a>
            
            <nav>
                <ul class="nav-links">
                    <li>
                        <a href="<?php echo BASE_URL; ?>/admin/login.php" class="nav-link" style="opacity: 0.8; font-size: 13px; display: flex; align-items: center; gap: 4px;">
                            <i data-lucide="lock" size="14"></i> Área Restrita
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Área de Exibição das Mensagens Flash (Se houverem) -->
    <?php exibirMensagensFlash(); ?>
    
    <!-- Container Principal do Conteúdo -->
    <main style="min-height: calc(100vh - 212px); padding: 40px 0 0 0;">
