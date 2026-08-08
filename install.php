<?php
/**
 * Agenda Escolar - Instalador Automático do Banco de Dados
 */

require_once __DIR__ . '/config.php';

$step_results = [];
$install_success = true;

// 1. Tentar conectar ao servidor MySQL
try {
    // Conexão temporária sem especificar o banco de dados para poder criá-lo se necessário
    $dsn_temp = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
    $pdo_temp = new PDO($dsn_temp, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    // Tenta criar o banco de dados
    $pdo_temp->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $step_results['db_creation'] = [
        'status' => 'success',
        'message' => 'Banco de dados criado ou já existente.'
    ];
} catch (PDOException $e) {
    // Se falhar a criação geral, tentamos prosseguir caso o banco já esteja criado e o usuário só tenha permissão para o banco específico
    $step_results['db_creation'] = [
        'status' => 'warning',
        'message' => 'Não foi possível criar o banco automaticamente (comum em hospedagens compartilhadas). Tentando conectar ao banco diretamente: ' . $e->getMessage()
    ];
}

// 2. Conectar ao banco de dados específico
$db = null;
if ($install_success) {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $db = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $step_results['db_connection'] = [
            'status' => 'success',
            'message' => 'Conexão estabelecida com sucesso com o banco de dados `' . DB_NAME . '`.'
        ];
    } catch (PDOException $e) {
        $step_results['db_connection'] = [
            'status' => 'error',
            'message' => 'Falha ao conectar ao banco de dados: ' . $e->getMessage()
        ];
        $install_success = false;
    }
}

// 3. Criar tabelas se a conexão deu certo
if ($install_success && $db) {
    try {
        // Tabela administradores
        $db->exec("CREATE TABLE IF NOT EXISTS `administradores` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `nome` VARCHAR(100) NOT NULL,
            `email` VARCHAR(150) NOT NULL UNIQUE,
            `senha` VARCHAR(255) NOT NULL,
            `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        $step_results['tbl_administradores'] = ['status' => 'success', 'message' => 'Tabela `administradores` criada/verificada.'];

        // Tabela segmentos
        $db->exec("CREATE TABLE IF NOT EXISTS `segmentos` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `nome` VARCHAR(100) NOT NULL UNIQUE,
            `ordem` INT DEFAULT 0,
            `ativo` TINYINT(1) DEFAULT 1,
            `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        $step_results['tbl_segmentos'] = ['status' => 'success', 'message' => 'Tabela `segmentos` criada/verificada.'];

        // Tabela horarios_disponiveis
        $db->exec("CREATE TABLE IF NOT EXISTS `horarios_disponiveis` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `data` DATE NOT NULL,
            `hora_inicio` TIME NOT NULL,
            `hora_fim` TIME NOT NULL,
            `max_vagas` INT DEFAULT 1,
            `ativo` TINYINT(1) DEFAULT 1,
            `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        $step_results['tbl_horarios'] = ['status' => 'success', 'message' => 'Tabela `horarios_disponiveis` criada/verificada.'];

        // Tabela agendamentos
        $db->exec("CREATE TABLE IF NOT EXISTS `agendamentos` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `horario_id` INT NOT NULL,
            `segmento_id` INT NOT NULL,
            `nome_responsavel` VARCHAR(150) NOT NULL,
            `telefone` VARCHAR(20) NOT NULL,
            `whatsapp` VARCHAR(20) NOT NULL,
            `email` VARCHAR(150) NOT NULL,
            `nome_crianca` VARCHAR(150) NOT NULL,
            `idade_crianca` VARCHAR(20) NOT NULL,
            `serie_interesse` VARCHAR(50) NOT NULL,
            `observacoes` TEXT NULL,
            `status` ENUM('pendente', 'confirmado', 'cancelado', 'realizado') DEFAULT 'pendente',
            `token` VARCHAR(64) NOT NULL UNIQUE,
            `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `atualizado_em` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`horario_id`) REFERENCES `horarios_disponiveis`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`segmento_id`) REFERENCES `segmentos`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        $step_results['tbl_agendamentos'] = ['status' => 'success', 'message' => 'Tabela `agendamentos` criada/verificada.'];

        // Tabela configuracoes
        $db->exec("CREATE TABLE IF NOT EXISTS `configuracoes` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `chave` VARCHAR(50) NOT NULL UNIQUE,
            `valor` TEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        $step_results['tbl_configuracoes'] = ['status' => 'success', 'message' => 'Tabela `configuracoes` criada/verificada.'];

    } catch (PDOException $e) {
        $step_results['tabelas'] = [
            'status' => 'error',
            'message' => 'Erro ao criar tabelas: ' . $e->getMessage()
        ];
        $install_success = false;
    }
}

// 4. Inserir dados padrão (administrador inicial, segmentos e configurações iniciais)
if ($install_success && $db) {
    try {
        // Verificar se já existe administrador cadastrado para não sobrescrever
        $stmt = $db->query("SELECT COUNT(*) FROM `administradores`");
        $countAdmin = $stmt->fetchColumn();
        
        if ($countAdmin == 0) {
            $senha_hash = password_hash('admin123', PASSWORD_BCRYPT);
            $stmtInsertAdmin = $db->prepare("INSERT INTO `administradores` (`nome`, `email`, `senha`) VALUES (?, ?, ?)");
            $stmtInsertAdmin->execute(['Administrador Geral', 'admin@escolaexemplo.com.br', $senha_hash]);
            $step_results['data_admin'] = ['status' => 'success', 'message' => 'Administrador padrão criado com sucesso (Login: <b>admin@escolaexemplo.com.br</b> / Senha: <b>admin123</b>).'];
        } else {
            $step_results['data_admin'] = ['status' => 'info', 'message' => 'Já existem administradores cadastrados. Cadastro padrão ignorado.'];
        }

        // Inserir segmentos padrão se não existirem
        $segmentos_padrao = [
            ['nome' => 'Educação Infantil', 'ordem' => 1],
            ['nome' => 'Pré', 'ordem' => 2],
            ['nome' => 'Anos Iniciais', 'ordem' => 3],
            ['nome' => 'Anos Finais', 'ordem' => 4],
            ['nome' => 'Ensino Médio', 'ordem' => 5]
        ];

        $stmtCheckSeg = $db->prepare("SELECT COUNT(*) FROM `segmentos` WHERE `nome` = ?");
        $stmtInsertSeg = $db->prepare("INSERT INTO `segmentos` (`nome`, `ordem`, `ativo`) VALUES (?, ?, 1)");
        
        $seg_criados = 0;
        foreach ($segmentos_padrao as $seg) {
            $stmtCheckSeg->execute([$seg['nome']]);
            if ($stmtCheckSeg->fetchColumn() == 0) {
                $stmtInsertSeg->execute([$seg['nome'], $seg['ordem']]);
                $seg_criados++;
            }
        }
        
        if ($seg_criados > 0) {
            $step_results['data_segmentos'] = ['status' => 'success', 'message' => "$seg_criados segmentos educacionais inseridos por padrão."];
        } else {
            $step_results['data_segmentos'] = ['status' => 'info', 'message' => "Os segmentos de ensino já estavam cadastrados."];
        }

        // Inserir configurações padrão se não existirem
        $configs_padrao = [
            'nome_escola' => 'Colégio Exemplo Modelo',
            'telefone_contato' => '(17) 98154-0011',
            'email_contato' => 'contato@escolaexemplo.com.br',
            'endereco_escola' => 'Av. Francisco Jalles, 1851 - Lot. Avenida, Jales - SP, 15703-200',
            'mensagem_sucesso' => 'Seu agendamento foi pré-registrado com sucesso! Nossa equipe entrará em contato em breve para confirmar a visita.',
            'antecedencia_minima_horas' => '2'
        ];

        $stmtCheckCfg = $db->prepare("SELECT COUNT(*) FROM `configuracoes` WHERE `chave` = ?");
        $stmtInsertCfg = $db->prepare("INSERT INTO `configuracoes` (`chave`, `valor`) VALUES (?, ?)");

        $cfg_criados = 0;
        foreach ($configs_padrao as $chave => $valor) {
            $stmtCheckCfg->execute([$chave]);
            if ($stmtCheckCfg->fetchColumn() == 0) {
                $stmtInsertCfg->execute([$chave, $valor]);
                $cfg_criados++;
            }
        }

        if ($cfg_criados > 0) {
            $step_results['data_configs'] = ['status' => 'success', 'message' => "$cfg_criados configurações básicas do sistema carregadas."];
        } else {
            $step_results['data_configs'] = ['status' => 'info', 'message' => "Configurações do sistema já existentes."];
        }

        // Inserir horários de demonstração por padrão se não existirem
        $stmtCheckHorarios = $db->query("SELECT COUNT(*) FROM `horarios_disponiveis`");
        $total_horarios_check = (int)$stmtCheckHorarios->fetchColumn();
        
        if ($total_horarios_check == 0) {
            $hoje_inst = new DateTime();
            $stmtInsertHorarioInst = $db->prepare("
                INSERT INTO `horarios_disponiveis` (data, hora_inicio, hora_fim, max_vagas, ativo) 
                VALUES (?, ?, ?, ?, 1)
            ");
            
            $horarios_gerados = 0;
            // Gerar para 14 dias corridos
            for ($k = 0; $k < 14; $k++) {
                $data_atual_inst = clone $hoje_inst;
                $data_atual_inst->modify("+$k day");
                $dia_semana_inst = (int)$data_atual_inst->format('w');
                
                if ($dia_semana_inst > 0 && $dia_semana_inst < 6) {
                    $dt = $data_atual_inst->format('Y-m-d');
                    // Manhã
                    $stmtInsertHorarioInst->execute([$dt, '09:00:00', '10:00:00', 2]);
                    $stmtInsertHorarioInst->execute([$dt, '10:30:00', '11:30:00', 2]);
                    // Tarde
                    $stmtInsertHorarioInst->execute([$dt, '14:00:00', '15:00:00', 2]);
                    $stmtInsertHorarioInst->execute([$dt, '15:30:00', '16:30:00', 2]);
                    $horarios_gerados += 4;
                }
            }
            $step_results['data_horarios'] = ['status' => 'success', 'message' => "$horarios_gerados horários de demonstração (Manhã e Tarde) criados nas próximas duas semanas."];
        } else {
            $step_results['data_horarios'] = ['status' => 'info', 'message' => "Os horários de visita já estavam cadastrados."];
        }

    } catch (PDOException $e) {
        $step_results['dados_iniciais'] = [
            'status' => 'error',
            'message' => 'Erro ao preencher dados iniciais: ' . $e->getMessage()
        ];
        $install_success = false;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador Agenda Escolar</title>
    <!-- Google Fonts (Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --primary: #ff5000;
            --primary-hover: #cc4000;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --background: #f8fafc;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --card-bg: #ffffff;
            --border: #e2e8f0;
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
        .container {
            width: 100%;
            max-width: 600px;
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, var(--primary), #6366f1);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .header p {
            font-size: 14px;
            opacity: 0.9;
        }
        .content {
            padding: 30px;
        }
        .step {
            display: flex;
            align-items: flex-start;
            padding: 16px;
            border-radius: 10px;
            margin-bottom: 12px;
            border: 1px solid var(--border);
            background-color: #fafafa;
        }
        .step-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            margin-right: 16px;
            flex-shrink: 0;
        }
        .step-details {
            flex-grow: 1;
            font-size: 14px;
            line-height: 1.5;
        }
        .step-details h3 {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        /* Status Colors */
        .step.success {
            border-color: #d1fae5;
            background-color: #ecfdf5;
        }
        .step.success .step-icon {
            background-color: #d1fae5;
            color: var(--success);
        }
        .step.success h3 { color: #065f46; }
        .step.success p { color: #047857; }

        .step.warning {
            border-color: #fef3c7;
            background-color: #fffbeb;
        }
        .step.warning .step-icon {
            background-color: #fef3c7;
            color: var(--warning);
        }
        .step.warning h3 { color: #92400e; }
        .step.warning p { color: #b45309; }

        .step.error {
            border-color: #fee2e2;
            background-color: #fef2f2;
        }
        .step.error .step-icon {
            background-color: #fee2e2;
            color: var(--danger);
        }
        .step.error h3 { color: #991b1b; }
        .step.error p { color: #b91c1c; }

        .step.info {
            border-color: #dbeafe;
            background-color: #eff6ff;
        }
        .step.info .step-icon {
            background-color: #dbeafe;
            color: var(--info);
        }
        .step.info h3 { color: #1e40af; }
        .step.info p { color: #1d4ed8; }

        .footer-action {
            margin-top: 30px;
            text-align: center;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            font-size: 15px;
            font-weight: 600;
            color: white;
            background-color: var(--primary);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.2s;
            gap: 8px;
        }
        .btn:hover {
            background-color: var(--primary-hover);
        }
        .btn-secondary {
            background-color: var(--text-muted);
        }
        .btn-secondary:hover {
            background-color: #475569;
        }
        .alert-security {
            margin-top: 20px;
            padding: 16px;
            border-radius: 8px;
            background-color: #fff7ed;
            border: 1px solid #ffedd5;
            color: #c2410c;
            font-size: 13px;
            line-height: 1.5;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .alert-security strong {
            display: block;
            margin-bottom: 2px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1 style="display: flex; align-items: center; justify-content: center; gap: 8px;">
            <i data-lucide="shield-check"></i> Agenda Escolar
        </h1>
        <p>Instalação e Configuração do Banco de Dados</p>
    </div>
    
    <div class="content">
        <?php foreach ($step_results as $key => $res): ?>
            <div class="step <?php echo $res['status']; ?>">
                <div class="step-icon">
                    <?php if ($res['status'] === 'success'): ?>
                        <i data-lucide="check" size="18"></i>
                    <?php elseif ($res['status'] === 'warning'): ?>
                        <i data-lucide="alert-triangle" size="18"></i>
                    <?php elseif ($res['status'] === 'error'): ?>
                        <i data-lucide="x" size="18"></i>
                    <?php else: ?>
                        <i data-lucide="info" size="18"></i>
                    <?php endif; ?>
                </div>
                <div class="step-details">
                    <h3>
                        <?php 
                        switch ($key) {
                            case 'db_creation': echo 'Criação do Banco de Dados'; break;
                            case 'db_connection': echo 'Conexão com Banco de Dados'; break;
                            case 'tbl_administradores': echo 'Tabela Administradores'; break;
                            case 'tbl_segmentos': echo 'Tabela Segmentos'; break;
                            case 'tbl_horarios': echo 'Tabela Horários'; break;
                            case 'tbl_agendamentos': echo 'Tabela Agendamentos'; break;
                            case 'tbl_configuracoes': echo 'Tabela Configurações'; break;
                            case 'data_admin': echo 'Conta do Administrador'; break;
                            case 'data_segmentos': echo 'Segmentos Iniciais'; break;
                            case 'data_configs': echo 'Definições do Sistema'; break;
                            case 'data_horarios': echo 'Horários de Demonstração'; break;
                            default: echo 'Instalação'; break;
                        }
                        ?>
                    </h3>
                    <p><?php echo $res['message']; ?></p>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($install_success): ?>
            <div class="alert-security">
                <i data-lucide="alert-octagon" style="flex-shrink: 0; color: #ea580c; margin-top: 2px;"></i>
                <div>
                    <strong>IMPORTANTE POR SEGURANÇA!</strong>
                    Exclua este arquivo (<code>install.php</code>) do seu servidor após concluir a instalação para evitar que outras pessoas reiniciem seu banco de dados.
                </div>
            </div>
            
            <div class="footer-action">
                <a href="index.php" class="btn">
                    <i data-lucide="arrow-right"></i> Ir para a Página Inicial
                </a>
            </div>
        <?php else: ?>
            <div class="footer-action">
                <button onclick="window.location.reload();" class="btn btn-secondary">
                    <i data-lucide="refresh-cw"></i> Tentar Novamente
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Inicializar ícones do Lucide
    lucide.createIcons();
</script>
</body>
</html>
