<?php
/**
 * Colégio Exemplo Modelo
 * Página Inicial - Formulário de Agendamento Direto com Calendário Interativo
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/phpmailer/Exception.php';
require_once __DIR__ . '/includes/phpmailer/PHPMailer.php';
require_once __DIR__ . '/includes/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$db = db();
$mensagem_erro = '';

// ── Leitura da antecedência mínima configurada ──
$antecedencia_horas = 2; // Valor padrão de segurança caso a config não exista
try {
    $stmtAnt = $db->prepare("SELECT valor FROM configuracoes WHERE chave = 'antecedencia_minima_horas'");
    $stmtAnt->execute();
    $val_ant = $stmtAnt->fetchColumn();
    if ($val_ant !== false && is_numeric($val_ant)) {
        $antecedencia_horas = max(0, (float)$val_ant);
    }
} catch (Exception $e) { /* Usa o padrão */ }

// Calcular o datetime mínimo: agora + antecedência
$dt_minimo = new DateTime();
if ($antecedencia_horas > 0) {
    $minutos_totais = (int)round($antecedencia_horas * 60);
    $dt_minimo->modify("+{$minutos_totais} minutes");
}
$data_minima   = $dt_minimo->format('Y-m-d');
$hora_minima   = $dt_minimo->format('H:i:s');

// Se o formulário foi enviado via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitização de entradas
    $horario_id = filter_input(INPUT_POST, 'horario_id', FILTER_VALIDATE_INT);
    $segmento_id = filter_input(INPUT_POST, 'segmento_id', FILTER_VALIDATE_INT);
    $nome_responsavel = sanitizar($_POST['nome_responsavel'] ?? '');
    $telefone = sanitizar($_POST['telefone'] ?? '');
    $whatsapp = sanitizar($_POST['whatsapp'] ?? '');
    if (empty($whatsapp)) {
        $whatsapp = $telefone;
    }
    
    // Tratando e-mail opcional
    $email_raw = sanitizar($_POST['email'] ?? '');
    $email = '';
    if (!empty($email_raw)) {
        $email = filter_var($email_raw, FILTER_VALIDATE_EMAIL) ?: '';
    }
    
    // Tratando dados da criança opcionais
    $nome_crianca = sanitizar($_POST['nome_crianca'] ?? '');
    if (empty($nome_crianca)) {
        $nome_crianca = 'Não informado';
    }
    $idade_crianca = sanitizar($_POST['idade_crianca'] ?? '');
    if (empty($idade_crianca)) {
        $idade_crianca = 'Não informada';
    }
    $serie_interesse = sanitizar($_POST['serie_interesse'] ?? '');
    if (empty($serie_interesse)) {
        $serie_interesse = 'Não especificada';
    }
    
    $observacoes = sanitizar($_POST['observacoes'] ?? '');
    $csrf_token = $_POST['csrf_token'] ?? '';

    // Buscar segmento padrão se não informado
    if (empty($segmento_id)) {
        try {
            $stmtSeg = $db->query("SELECT id FROM segmentos WHERE ativo = 1 ORDER BY ordem ASC, id ASC LIMIT 1");
            $segmento_id = $stmtSeg->fetchColumn();
        } catch (Exception $e) {
            $segmento_id = 0;
        }
    }

    // Validação de CSRF
    if (!validarCSRFToken($csrf_token)) {
        $mensagem_erro = 'Falha de validação de segurança (CSRF). Por favor, tente enviar novamente.';
    }
    // Validação dos campos obrigatórios (apenas horário, segmento, nome e telefone)
    elseif (!$horario_id || !$segmento_id || empty($nome_responsavel) || empty($telefone)) {
        $mensagem_erro = 'Por favor, preencha o Nome e o Telefone de contato.';
    } else {
        try {
            // ── Verificar se o horário respeita a antecedência mínima (proteção server-side) ──
            $stmtVerifHorario = $db->prepare("SELECT data, hora_inicio FROM horarios_disponiveis WHERE id = ? AND ativo = 1");
            $stmtVerifHorario->execute([$horario_id]);
            $horario_verif = $stmtVerifHorario->fetch();
            if (!$horario_verif) {
                $mensagem_erro = 'Horário inválido ou inativo. Por favor, escolha outro.';
            } elseif ($antecedencia_horas > 0) {
                $dt_horario_selecionado = new DateTime($horario_verif['data'] . ' ' . $horario_verif['hora_inicio']);
                $dt_limite_post = new DateTime();
                $minutos_limite = (int)round($antecedencia_horas * 60);
                $dt_limite_post->modify("+{$minutos_limite} minutes");
                if ($dt_horario_selecionado < $dt_limite_post) {
                    $mensagem_erro = 'Este horário não está mais disponível para agendamento. É necessário agendar com pelo menos ' . (number_format($antecedencia_horas, 0)) . 'h de antecedência. Por favor, escolha outro horário.';
                }
            }

            if (empty($mensagem_erro)) {
            // Verificar vagas disponíveis (Prevenir overbooking)
            $vagas = vagasDisponiveis($horario_id);
            if ($vagas <= 0) {
                $mensagem_erro = 'Desculpe, o horário selecionado acabou de ficar lotado. Por favor, escolha outra data.';
            } else {
                // Iniciar transação para garantir atomicidade do ID e do Token único
                $db->beginTransaction();

                // Gerar um token temporário único para respeitar a restrição UNIQUE
                $token_temp = uniqid('temp_', true);
                
                // Inserir agendamento no banco
                $stmtInsert = $db->prepare("
                    INSERT INTO agendamentos 
                    (horario_id, segmento_id, nome_responsavel, telefone, whatsapp, email, nome_crianca, idade_crianca, serie_interesse, observacoes, status, token) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente', ?)
                ");
                $stmtInsert->execute([
                    $horario_id,
                    $segmento_id,
                    $nome_responsavel,
                    $telefone,
                    $whatsapp,
                    $email,
                    $nome_crianca,
                    $idade_crianca,
                    $serie_interesse,
                    $observacoes,
                    $token_temp
                ]);

                // Obter o ID gerado e definir o token curto
                $agendamento_id = $db->lastInsertId();
                $token = 'AGD-' . $agendamento_id;

                // Atualizar o registro com o token curto e final
                $stmtUpdate = $db->prepare("UPDATE agendamentos SET token = ? WHERE id = ?");
                $stmtUpdate->execute([$token, $agendamento_id]);

                $db->commit();

                 // ── Enviar Notificação por E-mail para todos os Administradores cadastrados ──
                $admins_notificar = [];
                try {
                    $stmtAdmins = $db->query("
                        SELECT nome, email_notificacao 
                        FROM administradores 
                        WHERE email_notificacao IS NOT NULL AND email_notificacao != ''
                    ");
                    $admins_notificar = $stmtAdmins->fetchAll();
                } catch (Exception $ex) {
                    // Silencia erro
                }

                // Buscar detalhes do agendamento (usado tanto nas notificações de admin quanto do visitante)
                $data_visita = '';
                $hora_visita = '';
                $hora_inicio_visita = '';
                $nome_seg_email = '';
                try {
                    $stmtInfo = $db->prepare("
                        SELECT h.data, h.hora_inicio, h.hora_fim, s.nome as nome_segmento
                        FROM horarios_disponiveis h
                        JOIN segmentos s ON s.id = ?
                        WHERE h.id = ?
                    ");
                    $stmtInfo->execute([$segmento_id, $horario_id]);
                    $info = $stmtInfo->fetch();
                    if ($info) {
                        $data_visita = date('d/m/Y', strtotime($info['data']));
                        $hora_visita = formatarHora($info['hora_inicio']) . ' às ' . formatarHora($info['hora_fim']);
                        $hora_inicio_visita = formatarHora($info['hora_inicio']);
                        $nome_seg_email = $info['nome_segmento'];
                    }
                } catch (Exception $ex) {
                    // Silencia
                }

                // ── Enviar Notificação por E-mail para todos os Administradores cadastrados ──
                $admins_notificar = [];
                try {
                    $stmtAdmins = $db->query("
                        SELECT nome, email_notificacao 
                        FROM administradores 
                        WHERE email_notificacao IS NOT NULL AND email_notificacao != ''
                    ");
                    $admins_notificar = $stmtAdmins->fetchAll();
                } catch (Exception $ex) {
                    // Silencia erro
                }

                if (!empty($admins_notificar)) {
                    $mensagem_html = "
                    <html>
                    <head>
                        <meta charset='UTF-8'>
                        <style>
                            body { font-family: Arial, sans-serif; background-color: #f4f4f5; color: #18181b; padding: 20px; }
                            .container { max-width: 600px; background: white; padding: 24px; border-radius: 8px; border: 1px solid #e4e4e7; margin: 0 auto; }
                            h2 { color: #ff5000; font-size: 20px; margin-bottom: 16px; border-bottom: 2px solid #ff5000; padding-bottom: 8px; margin-top: 0; }
                            p { line-height: 1.5; font-size: 14px; color: #3f3f46; }
                            .info-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; margin: 16px 0; }
                            .info-line { margin-bottom: 8px; font-size: 14px; border-bottom: 1px solid #f1f5f9; padding-bottom: 6px; }
                            .info-line:last-child { border-bottom: none; padding-bottom: 0; margin-bottom: 0; }
                            .info-line strong { color: #475569; width: 140px; display: inline-block; }
                            .footer { font-size: 12px; color: #71717a; margin-top: 24px; text-align: center; border-top: 1px solid #e4e4e7; padding-top: 12px; }
                            .btn { display: inline-block; padding: 10px 20px; background-color: #ff5000; color: white !important; text-decoration: none; font-weight: bold; border-radius: 6px; font-size: 14px; margin-top: 12px; text-align: center; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <h2>Novo Agendamento Recebido!</h2>
                            <p>Olá, Administrador. Uma nova visita guiada foi agendada através do portal do Colégio Exemplo Modelo.</p>
                            
                            <div class='info-box'>
                                <div class='info-line'><strong>Responsável:</strong> " . htmlspecialchars($nome_responsavel) . "</div>
                                <div class='info-line'><strong>Telefone:</strong> " . htmlspecialchars($telefone) . "</div>
                                <div class='info-line'><strong>WhatsApp:</strong> " . htmlspecialchars($whatsapp) . "</div>
                                <div class='info-line'><strong>E-mail:</strong> " . (!empty($email) ? htmlspecialchars($email) : 'Não informado') . "</div>
                                <div class='info-line'><strong>Aluno(a):</strong> " . htmlspecialchars($nome_crianca) . "</div>
                                <div class='info-line'><strong>Série/Segmento:</strong> " . htmlspecialchars($nome_seg_email . ' (' . $serie_interesse . ')') . "</div>
                                <div class='info-line'><strong>Data da Visita:</strong> " . htmlspecialchars($data_visita) . "</div>
                                <div class='info-line'><strong>Horário da Visita:</strong> " . htmlspecialchars($hora_visita) . "</div>
                                <div class='info-line'><strong>Código Token:</strong> " . htmlspecialchars($token) . "</div>
                            </div>
                            
                            <p>Para ver os detalhes completos, gerenciar ou confirmar o agendamento, acesse o painel administrativo.</p>
                            <a href='" . BASE_URL . "/admin/agendamento-detalhes.php?id=" . $agendamento_id . "' class='btn'>Acessar Ficha do Agendamento</a>
                            
                            <div class='footer'>
                                Este é um e-mail automático gerado pelo sistema Agenda Escolar.<br>
                                &copy; " . date('Y') . " " . SCHOOL_NAME . ".
                            </div>
                        </div>
                    </body>
                    </html>
                    ";

                    // Disparar um e-mail individual para cada administrador cadastrado
                    foreach ($admins_notificar as $admin_dest) {
                        $mail = new PHPMailer(true);
                        try {
                            $mail->isSMTP();
                            $mail->Host       = SMTP_HOST;
                            $mail->SMTPAuth   = true;
                            $mail->Username   = SMTP_USER;
                            $mail->Password   = SMTP_PASS;
                            $mail->SMTPSecure = (SMTP_SECURE === 'ssl') ? 'ssl' : 'tls';
                            $mail->Port       = SMTP_PORT;
                            $mail->CharSet    = 'UTF-8';

                            $mail->setFrom(SMTP_USER, 'Agenda Escolar - ' . SCHOOL_NAME);
                            $mail->addAddress($admin_dest['email_notificacao'], $admin_dest['nome']);
                            
                            $mail->isHTML(true);
                            $mail->Subject = "Novo Agendamento Recebido - " . SCHOOL_NAME;
                            $mail->Body    = $mensagem_html;

                            $mail->send();
                        } catch (Exception $exMail) {
                            if (defined('DEV_MODE') && DEV_MODE) {
                                error_log("Erro PHPMailer para " . $admin_dest['email_notificacao'] . ": " . $mail->ErrorInfo);
                            }
                        }
                    }
                }

                // ── Enviar E-mail de Confirmação para o Visitante (se informou e-mail) ──
                if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $msg_visitante_html = "
                    <html>
                    <head>
                        <meta charset='UTF-8'>
                        <style>
                            body { font-family: Arial, sans-serif; background-color: #f4f4f5; color: #18181b; margin: 0; padding: 20px; }
                            .container { max-width: 600px; background: white; padding: 32px; border-radius: 10px; border: 1px solid #e4e4e7; margin: 0 auto; }
                            .header { text-align: center; margin-bottom: 28px; padding-bottom: 20px; border-bottom: 2px solid #ff5000; }
                            .header h1 { color: #ff5000; font-size: 22px; margin: 0 0 4px; }
                            .header p { color: #71717a; font-size: 13px; margin: 0; }
                            .success-badge { display: inline-block; background-color: #dcfce7; color: #16a34a; font-weight: 700; font-size: 13px; padding: 6px 16px; border-radius: 50px; margin-bottom: 20px; }
                            h2 { font-size: 18px; color: #18181b; margin-bottom: 6px; }
                            p { line-height: 1.6; font-size: 14px; color: #3f3f46; margin-top: 0; }
                            .info-box { background: #fff7f5; border: 1px solid #ffd5c8; border-radius: 8px; padding: 20px; margin: 20px 0; }
                            .info-box-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #ff5000; margin-bottom: 14px; }
                            .info-table { width: 100%; border-collapse: collapse; }
                            .info-table td { padding: 8px 0; border-bottom: 1px solid rgba(0,0,0,0.05); font-size: 14px; }
                            .info-table tr:last-child td { border-bottom: none; }
                            .info-table .label { color: #71717a; font-weight: 500; text-align: left; }
                            .info-table .value { font-weight: 700; color: #18181b; text-align: right; }
                            .token-box { text-align: center; background: #f4f4f5; border: 2px dashed #d4d4d8; border-radius: 8px; padding: 16px; margin: 20px 0; }
                            .token-box .token-label { font-size: 11px; color: #71717a; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 6px; }
                            .token-code { font-size: 26px; font-family: monospace; font-weight: 700; color: #ff5000; letter-spacing: 2px; }
                            .tips { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 16px; margin: 20px 0; }
                            .tips-title { font-weight: 700; font-size: 13px; color: #92400e; margin-bottom: 10px; }
                            .tips ul { margin: 0; padding-left: 18px; font-size: 13px; color: #78350f; line-height: 1.7; }
                            .footer { font-size: 11px; color: #a1a1aa; margin-top: 28px; text-align: center; border-top: 1px solid #e4e4e7; padding-top: 16px; line-height: 1.6; }
                            .btn { display: inline-block; padding: 12px 24px; background-color: #ff5000; color: white !important; text-decoration: none; font-weight: bold; border-radius: 8px; font-size: 14px; margin-top: 16px; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h1>" . SCHOOL_NAME . "</h1>
                                <p>Sistema de Agendamento de Visitas Guiadas</p>
                            </div>

                            <div style='text-align: center;'>
                                <span class='success-badge'>Agendamento Pré-Registrado com Sucesso!</span>
                            </div>

                            <h2>Olá, " . htmlspecialchars($nome_responsavel) . "!</h2>
                            <p>Recebemos o seu pedido de visita guiada ao <strong>" . SCHOOL_NAME . "</strong>. Nossa equipe analisará o seu agendamento e entrará em contato para confirmar em breve.</p>

                            <div class='info-box'>
                                <div class='info-box-title'>Resumo da Visita Agendada</div>
                                <table class='info-table'>
                                    <tr>
                                        <td class='label'>Data</td>
                                        <td class='value'>" . htmlspecialchars($data_visita) . "</td>
                                    </tr>
                                    <tr>
                                        <td class='label'>Horário de Chegada</td>
                                        <td class='value'>" . htmlspecialchars($hora_inicio_visita) . " <span style='font-size: 11px; color: #ff5000; font-weight: 600;'>(Favor chegar 10 minutos antes)</span></td>
                                    </tr>
                                    <tr>
                                        <td class='label'>Segmento</td>
                                        <td class='value'>" . htmlspecialchars($nome_seg_email) . "</td>
                                    </tr>
                                    <tr>
                                        <td class='label'>Série de Interesse</td>
                                        <td class='value'>" . htmlspecialchars($serie_interesse) . "</td>
                                    </tr>
                                    <tr>
                                        <td class='label'>Aluno(a)</td>
                                        <td class='value'>" . htmlspecialchars($nome_crianca) . "</td>
                                    </tr>
                                </table>
                            </div>

                            <div class='token-box'>
                                <div class='token-label'>Seu Código de Acompanhamento</div>
                                <div class='token-code'>" . htmlspecialchars($token) . "</div>
                                <p style='font-size: 12px; color: #71717a; margin: 8px 0 0;'>Guarde este código. Ele identifica seu agendamento e é necessário na recepção no dia da visita.</p>
                            </div>

                            <div class='tips'>
                                <div class='tips-title'>Orientações para o dia da visita:</div>
                                <ul>
                                    <li>Chegar com aproximadamente 10 minutos de antecedência.</li>
                                    <li>O ponto de encontro será na recepção principal do colégio.</li>
                                    <li>Se desejar, traga o aluno(a) para vivenciar o ambiente escolar.</li>
                                    <li>Caso precise cancelar ou reagendar, entre em contato informando o código acima.</li>
                                </ul>
                            </div>

                            <div style='text-align: center;'>
                                <a href='" . BASE_URL . "/confirmacao.php?token=" . htmlspecialchars($token) . "' class='btn'>Ver Comprovante Online</a>
                            </div>

                            <div class='footer'>
                                Você recebeu este e-mail porque realizou um agendamento de visita em nosso portal.<br>
                                Em caso de dúvidas, entre em contato diretamente com a secretaria da escola.<br><br>
                                &copy; " . date('Y') . " " . SCHOOL_NAME . " &mdash; Todos os direitos reservados.
                            </div>
                        </div>
                    </body>
                    </html>
                    ";
                    // Disparar o e-mail para o visitante
                    $mail_visitante = new PHPMailer(true);
                    try {
                        $mail_visitante->isSMTP();
                        $mail_visitante->Host       = SMTP_HOST;
                        $mail_visitante->SMTPAuth   = true;
                        $mail_visitante->Username   = SMTP_USER;
                        $mail_visitante->Password   = SMTP_PASS;
                        $mail_visitante->SMTPSecure = (SMTP_SECURE === 'ssl') ? 'ssl' : 'tls';
                        $mail_visitante->Port       = SMTP_PORT;
                        $mail_visitante->CharSet    = 'UTF-8';

                        $mail_visitante->setFrom(SMTP_USER, SCHOOL_NAME);
                        $mail_visitante->addAddress($email, $nome_responsavel);

                        $mail_visitante->isHTML(true);
                        $mail_visitante->Subject = "Agendamento Pré-Registrado com Sucesso! - " . SCHOOL_NAME;
                        $mail_visitante->Body    = $msg_visitante_html;

                        $mail_visitante->send();
                    } catch (Exception $exVisitante) {
                        if (defined('DEV_MODE') && DEV_MODE) {
                            error_log("Erro PHPMailer (visitante) para " . $email . ": " . $mail_visitante->ErrorInfo);
                        }
                    }
                }

                // Buscar mensagem de sucesso customizada do banco de dados (se houver)
                $stmtMsg = $db->prepare("SELECT valor FROM configuracoes WHERE chave = 'mensagem_sucesso'");
                $stmtMsg->execute();
                $msg_sucesso = $stmtMsg->fetchColumn();
                if (!$msg_sucesso) {
                    $msg_sucesso = 'Agendamento pré-registrado com sucesso!';
                }

                flash('agendamento_sucesso', $msg_sucesso, 'success');
                redirect(BASE_URL . '/confirmacao.php?token=' . $token);
            }
            } // fim if empty($mensagem_erro) — validação antecedência
        } catch (PDOException $e) {
            $mensagem_erro = 'Erro interno ao processar o agendamento. Detalhes: ' . (DEV_MODE ? $e->getMessage() : 'Tente novamente mais tarde.');
        }
    }
}

// Buscar horários disponíveis futuros com cálculo correto de vagas
// Aplica o filtro de antecedência mínima: só mostra horários >= datetime_minimo
$hoje = date('Y-m-d');
try {
    $stmtHorarios = $db->prepare("
        SELECT h.*, 
               (h.max_vagas - (SELECT COUNT(*) FROM agendamentos a WHERE a.horario_id = h.id AND a.status != 'cancelado')) as vagas_restantes
        FROM horarios_disponiveis h
        WHERE (
            h.data > ?
            OR (h.data = ? AND h.hora_inicio >= ?)
          )
          AND h.ativo = 1 
          AND (h.max_vagas - (SELECT COUNT(*) FROM agendamentos a WHERE a.horario_id = h.id AND a.status != 'cancelado')) > 0
        ORDER BY h.data ASC, h.hora_inicio ASC
    ");
    $stmtHorarios->execute([$data_minima, $data_minima, $hora_minima]);
    $horarios_db = $stmtHorarios->fetchAll();
} catch (PDOException $e) {
    $horarios_db = [];
}

// Buscar TODOS os horários futuros (incluindo lotados) para mostrar indicadores visuais no calendário
// Também aplica filtro de antecedência para não mostrar dias já bloqueados
$horarios_todos = [];
try {
    $stmtTodos = $db->prepare("
        SELECT h.data, h.max_vagas,
               (SELECT COUNT(*) FROM agendamentos a WHERE a.horario_id = h.id AND a.status != 'cancelado') as total_agendados
        FROM horarios_disponiveis h
        WHERE (
            h.data > ?
            OR (h.data = ? AND h.hora_inicio >= ?)
          )
          AND h.ativo = 1
        ORDER BY h.data ASC
    ");
    $stmtTodos->execute([$data_minima, $data_minima, $hora_minima]);
    $horarios_todos = $stmtTodos->fetchAll();
} catch (PDOException $e) {
    $horarios_todos = [];
}

// Agrupar horários disponíveis por data para o seletor de slots
$horarios_js = [];
foreach ($horarios_db as $h) {
    $horarios_js[$h['data']][] = [
        'id' => $h['id'],
        'hora_inicio' => formatarHora($h['hora_inicio']),
        'hora_fim' => formatarHora($h['hora_fim']),
        'vagas_restantes' => $h['vagas_restantes']
    ];
}

// Calcular ocupação por dia para os indicadores visuais do calendário
// Resultado: ['2026-07-01' => ['total_vagas' => 8, 'vagas_livres' => 5, 'percentual_livre' => 62.5], ...]
$ocupacao_por_dia = [];
foreach ($horarios_todos as $ht) {
    $data = $ht['data'];
    if (!isset($ocupacao_por_dia[$data])) {
        $ocupacao_por_dia[$data] = ['total_vagas' => 0, 'vagas_livres' => 0];
    }
    $vagas_livres = max(0, (int)$ht['max_vagas'] - (int)$ht['total_agendados']);
    $ocupacao_por_dia[$data]['total_vagas'] += (int)$ht['max_vagas'];
    $ocupacao_por_dia[$data]['vagas_livres'] += $vagas_livres;
}
// Calcular percentual livre
foreach ($ocupacao_por_dia as &$info) {
    $info['percentual_livre'] = $info['total_vagas'] > 0 
        ? round(($info['vagas_livres'] / $info['total_vagas']) * 100) 
        : 0;
}
unset($info);

// Buscar segmentos disponíveis e ativos
try {
    $stmtSegmentos = $db->prepare("SELECT * FROM segmentos WHERE ativo = 1 ORDER BY ordem ASC");
    $stmtSegmentos->execute();
    $segmentos = $stmtSegmentos->fetchAll();
} catch (PDOException $e) {
    $segmentos = [];
}

$titulo_pagina = 'Agendar Visita';
require_once __DIR__ . '/includes/header.php';
?>


<?php
// ===== CALENDÁRIO CUSTOMIZADO: Calcular mês atual para exibição =====
$cal_ano = (int)date('Y');
$cal_mes = (int)date('n');
// Montar nome do mês em pt-BR
$meses_pt = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

// Calcular meses disponíveis para navegação (mês atual + próximos 2 meses)
$meses_nav = [];
for ($m = 0; $m < 3; $m++) {
    $nav_mes = (int)date('n', mktime(0, 0, 0, $cal_mes + $m, 1, $cal_ano));
    $nav_ano = (int)date('Y', mktime(0, 0, 0, $cal_mes + $m, 1, $cal_ano));
    $meses_nav[] = ['mes' => $nav_mes, 'ano' => $nav_ano];
}

// Preparar dados de todos os meses navegáveis para JSON
$calendario_dados = [];
foreach ($meses_nav as $mn) {
    $total_dias = cal_days_in_month(CAL_GREGORIAN, $mn['mes'], $mn['ano']);
    $primeiro_dia_semana = (int)date('w', mktime(0, 0, 0, $mn['mes'], 1, $mn['ano']));
    $chave_mes = $mn['ano'] . '-' . str_pad($mn['mes'], 2, '0', STR_PAD_LEFT);
    
    $dias = [];
    for ($d = 1; $d <= $total_dias; $d++) {
        $data_str = $mn['ano'] . '-' . str_pad($mn['mes'], 2, '0', STR_PAD_LEFT) . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
        // Considera "passado" qualquer data/dia que já não tem horários disponíveis dado a antecedência mínima
        $eh_passado = $data_str < $data_minima;
        $eh_hoje = $data_str === $hoje;
        
        $status = 'no-slots'; // Padrão
        $badge_text = '';
        $badge_class = '';
        $clicavel = false;
        
        if ($eh_passado) {
            $status = 'past';
        } elseif (isset($ocupacao_por_dia[$data_str])) {
            $oc = $ocupacao_por_dia[$data_str];
            if ($oc['vagas_livres'] <= 0) {
                $status = 'full';
                $badge_text = 'Lotado';
                $badge_class = 'cal-badge-full';
            } elseif ($oc['percentual_livre'] <= 50) {
                $status = 'almost-full';
                $badge_text = 'Últimas';
                $badge_class = 'cal-badge-almost';
                $clicavel = true;
            } else {
                $status = 'available';
                $badge_text = 'Livre';
                $badge_class = 'cal-badge-available';
                $clicavel = true;
            }
        }
        
        $dias[] = [
            'dia' => $d,
            'data' => $data_str,
            'status' => $status,
            'eh_hoje' => $eh_hoje,
            'clicavel' => $clicavel,
            'badge_text' => $badge_text,
            'badge_class' => $badge_class,
        ];
    }
    
    $calendario_dados[$chave_mes] = [
        'titulo' => $meses_pt[$mn['mes']] . ' ' . $mn['ano'],
        'primeiro_dia_semana' => $primeiro_dia_semana,
        'dias' => $dias,
    ];
}
?>

<div class="container" style="max-width: 800px; margin-bottom: 60px; padding-top: 20px;">
    
    <div style="text-align: center; margin-bottom: 40px;">
        <h1 style="font-size: 32px; margin-bottom: 8px;">Agendamento de Visita</h1>
        <p style="color: var(--text-muted);">Selecione o dia e horário de sua preferência no calendário abaixo.</p>
    </div>

    <!-- Barra de Progresso do Wizard -->
    <div class="wizard-progress">
        <div class="wizard-progress-bar" id="progressBar"></div>
        <div class="wizard-step active" id="stepIndicator1">1</div>
        <div class="wizard-step" id="stepIndicator2">2</div>
        <div class="wizard-step" id="stepIndicator3">3</div>
    </div>

    <?php if (!empty($mensagem_erro)): ?>
        <div style="background-color: var(--danger-bg); color: var(--danger); border: 1px solid #fecaca; padding: 16px; border-radius: var(--radius-md); margin-bottom: 24px; font-size: 14px;">
            <i data-lucide="alert-circle" size="18" style="vertical-align: middle; margin-right: 8px;"></i>
            <?php echo $mensagem_erro; ?>
        </div>
    <?php endif; ?>

    <form id="agendamentoForm" method="POST" action="index.php">
        <!-- Token CSRF para segurança -->
        <input type="hidden" name="csrf_token" value="<?php echo gerarCSRFToken(); ?>">

        <!-- ETAPA 1: CALENDÁRIO INTERATIVO CUSTOMIZADO -->
        <div class="step-content active" id="step1">
            <div class="card" style="margin-bottom: 24px; padding: 0; overflow: visible;">
                <div style="padding: 20px 24px; border-bottom: 1px solid var(--border);">
                    <h3 style="font-size: 16px; display: flex; align-items: center; gap: 8px; margin: 0;">
                        <i data-lucide="calendar" size="18" style="color: var(--primary);"></i>
                        1. Selecione um Dia Disponível
                    </h3>
                </div>
                
                <?php if (empty($ocupacao_por_dia)): ?>
                    <div style="text-align: center; padding: 40px 24px;">
                        <i data-lucide="calendar-off" size="48" style="color: var(--text-muted); margin-bottom: 16px; opacity: 0.5;"></i>
                        <p style="color: var(--text-muted); font-size: 16px;">
                            Não há horários de visita abertos no momento. Por favor, tente novamente mais tarde ou entre em contato pelo telefone do colégio.
                        </p>
                    </div>
                <?php else: ?>
                    <!-- Dica Orientativa para o Visitante -->
                    <div style="background-color: #eff6ff; color: #1e40af; padding: 12px 20px; font-size: 13px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #dbeafe;">
                        <i data-lucide="help-circle" size="18" style="flex-shrink: 0; color: #3b82f6;"></i>
                        <span><strong>Como agendar:</strong> Clique em qualquer dia com vagas (destacado em <strong>verde</strong> ou <strong>laranja</strong>) para visualizar os horários disponíveis.</span>
                    </div>

                    <!-- Calendário Customizado -->
                    <div class="custom-calendar" id="customCalendar" style="border: none; border-radius: 0; box-shadow: none;">
                        <!-- Header com navegação -->
                        <div class="cal-header">
                            <button type="button" class="cal-nav-btn" id="calPrev" title="Mês anterior">
                                <i data-lucide="chevron-left" size="18"></i>
                            </button>
                            <span class="cal-header-title" id="calTitle"></span>
                            <button type="button" class="cal-nav-btn" id="calNext" title="Próximo mês">
                                <i data-lucide="chevron-right" size="18"></i>
                            </button>
                        </div>
                        
                        <!-- Dias da semana -->
                        <div class="cal-weekdays">
                            <div class="cal-weekday">Dom</div>
                            <div class="cal-weekday">Seg</div>
                            <div class="cal-weekday">Ter</div>
                            <div class="cal-weekday">Qua</div>
                            <div class="cal-weekday">Qui</div>
                            <div class="cal-weekday">Sex</div>
                            <div class="cal-weekday">Sáb</div>
                        </div>
                        
                        <!-- Grid de dias (gerado via JS) -->
                        <div class="cal-grid" id="calGrid"></div>
                        
                        <!-- Legenda -->
                        <div class="cal-legend">
                            <div class="cal-legend-item">
                                <div class="cal-legend-dot green"></div>
                                <span>Disponível</span>
                            </div>
                            <div class="cal-legend-item">
                                <div class="cal-legend-dot orange"></div>
                                <span>Últimas vagas</span>
                            </div>
                            <div class="cal-legend-item">
                                <div class="cal-legend-dot red"></div>
                                <span>Lotado</span>
                            </div>
                            <div class="cal-legend-item">
                                <div class="cal-legend-dot gray"></div>
                                <span>Indisponível</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Div de Revelação de Horários -->
                    <div id="horariosContainer" style="display: none; border-top: 1px dashed var(--border); padding: 24px; animation: slideUp 0.3s ease;">
                        <h4 style="font-size: 14px; font-weight: 700; margin-bottom: 16px; color: var(--text-main);">
                            Escolha um horário para o dia <span id="dataSelecionadaLabel" style="color: var(--primary);"></span>:
                        </h4>
                        <div id="slotsGrid" class="options-grid"></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($ocupacao_por_dia)): ?>
                    <div style="display: flex; justify-content: flex-end; padding: 16px 24px; border-top: 1px solid var(--border);">
                        <button type="button" class="btn btn-primary btn-next" id="btnAvancarStep1" disabled style="opacity: 0.5; cursor: not-allowed;">
                            Avançar <i data-lucide="chevron-right" size="16"></i>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ETAPA 2: SELEÇÃO DO SEGMENTO -->
        <div class="step-content" id="step2">
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <h3 style="font-size: 16px; display: flex; align-items: center; gap: 8px;">
                        <i data-lucide="layers" size="18" style="color: var(--primary);"></i>
                        2. Selecione o Segmento de Interesse
                    </h3>
                </div>

                <?php if (empty($segmentos)): ?>
                    <p style="color: var(--text-muted);">Nenhum segmento configurado no sistema. Contate a administração do colégio.</p>
                <?php else: ?>
                    <div class="options-grid" style="grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));">
                        <?php $seg_idx = 0; foreach ($segmentos as $seg): ?>
                            <div class="option-card <?php echo $seg_idx === 0 ? 'selected' : ''; ?>" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 24px; min-height: 120px;">
                                <input type="radio" name="segmento_id" value="<?php echo $seg['id']; ?>" <?php echo $seg_idx === 0 ? 'checked' : ''; ?>>
                                <i data-lucide="check-circle" size="24" style="color: var(--primary); margin-bottom: 12px; opacity: <?php echo $seg_idx === 0 ? '1' : '0.3'; ?>;"></i>
                                <span style="font-weight: 600; font-size: 15px;"><?php echo $seg['nome']; ?></span>
                            </div>
                        <?php $seg_idx++; endforeach; ?>
                    </div>
                <?php endif; ?>

                <div style="display: flex; justify-content: space-between; margin-top: 24px;">
                    <button type="button" class="btn btn-secondary btn-prev">
                        <i data-lucide="chevron-left" size="16"></i> Voltar
                    </button>
                    <button type="button" class="btn btn-primary btn-next" id="btnAvancarStep2" style="cursor: pointer;">
                        Avançar <i data-lucide="chevron-right" size="16"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ETAPA 3: DADOS DE CONTATO E DA CRIANÇA -->
        <div class="step-content" id="step3">
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <h3 style="font-size: 16px; display: flex; align-items: center; gap: 8px;">
                        <i data-lucide="user-check" size="18" style="color: var(--primary);"></i>
                        3. Preencha seus Dados de Contato e do Aluno
                    </h3>
                </div>

                <!-- Dados do Responsável -->
                <h4 style="font-size: 14px; font-weight: 700; margin-bottom: 16px; color: var(--primary); border-bottom: 1px solid var(--border); padding-bottom: 8px; text-transform: uppercase;">
                    Dados do Responsável
                </h4>
                
                <div class="form-group">
                    <label class="form-label" for="nome_responsavel">Nome do Responsável *</label>
                    <input type="text" id="nome_responsavel" name="nome_responsavel" class="form-control" placeholder="Digite seu nome completo" required>
                </div>

                <div class="grid-2col">
                    <div class="form-group">
                        <label class="form-label" for="telefone">Telefone *</label>
                        <input type="tel" id="telefone" name="telefone" class="form-control" placeholder="(00) 0000-0000" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="whatsapp">WhatsApp (Opcional)</label>
                        <input type="tel" id="whatsapp" name="whatsapp" class="form-control" placeholder="(00) 00000-0000">
                    </div>
                </div>

                <div style="margin-bottom: 20px; display: flex; align-items: center; gap: 8px; margin-top: -10px;">
                    <input type="checkbox" id="mesmo_whatsapp" style="width: 16px; height: 16px; cursor: pointer;">
                    <label for="mesmo_whatsapp" style="font-size: 13px; color: var(--text-muted); cursor: pointer; user-select: none;">
                        O número do WhatsApp é o mesmo do Telefone
                    </label>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">E-mail (Opcional)</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="exemplo@email.com">
                </div>

                <!-- Dados do Aluno -->
                <h4 style="font-size: 14px; font-weight: 700; margin-bottom: 16px; color: var(--primary); border-bottom: 1px solid var(--border); padding-bottom: 8px; margin-top: 32px; text-transform: uppercase;">
                    Dados da Criança
                </h4>

                <div class="form-group">
                    <label class="form-label" for="nome_crianca">Nome do Aluno(a) (Opcional)</label>
                    <input type="text" id="nome_crianca" name="nome_crianca" class="form-control" placeholder="Digite o nome completo da criança">
                </div>

                <div class="grid-2col">
                    <div class="form-group">
                        <label class="form-label" for="idade_crianca">Idade (Opcional)</label>
                        <input type="text" id="idade_crianca" name="idade_crianca" class="form-control" placeholder="Ex: 6 anos">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="serie_interesse">Série de Interesse (Opcional)</label>
                        <input type="text" id="serie_interesse" name="serie_interesse" class="form-control" placeholder="Ex: 1º Ano do Ensino Fundamental">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="observacoes">Observações (Opcional)</label>
                    <textarea id="observacoes" name="observacoes" class="form-control" rows="3" placeholder="Escreva observações adicionais caso queira"></textarea>
                </div>

                <div style="display: flex; justify-content: space-between; margin-top: 32px;">
                    <button type="button" class="btn btn-secondary btn-prev">
                        <i data-lucide="chevron-left" size="16"></i> Voltar
                    </button>
                    <button type="submit" class="btn btn-accent btn-lg" style="background-color: var(--accent); color: white;">
                        <i data-lucide="check-circle" size="18"></i> Confirmar Visita
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Script do Calendário Customizado e Lógica Frontend -->
<?php if (!empty($ocupacao_por_dia)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ====== Dados do PHP ======
    const calendarioDados = <?php echo json_encode($calendario_dados); ?>;
    const horariosData = <?php echo json_encode($horarios_js); ?>;
    // Antecedência mínima: data e hora a partir dos quais os slots são exibidos
    const dataMinima = <?php echo json_encode($data_minima); ?>;
    const horaMinima = <?php echo json_encode(substr($hora_minima, 0, 5)); ?>; // HH:MM
    const mesesKeys = Object.keys(calendarioDados);
    let mesAtualIdx = 0;

    const grid = document.getElementById('calGrid');
    const titleEl = document.getElementById('calTitle');
    const prevBtn = document.getElementById('calPrev');
    const nextBtn = document.getElementById('calNext');

    // ====== Renderizar calendário de um mês ======
    function renderCalendar(mesIdx) {
        const mesKey = mesesKeys[mesIdx];
        const mesData = calendarioDados[mesKey];
        
        titleEl.textContent = mesData.titulo;
        grid.innerHTML = '';

        // Atualizar estado dos botões de navegação
        prevBtn.style.opacity = mesIdx === 0 ? '0.3' : '1';
        prevBtn.style.pointerEvents = mesIdx === 0 ? 'none' : 'auto';
        nextBtn.style.opacity = mesIdx >= mesesKeys.length - 1 ? '0.3' : '1';
        nextBtn.style.pointerEvents = mesIdx >= mesesKeys.length - 1 ? 'none' : 'auto';

        // Células vazias antes do primeiro dia
        for (let i = 0; i < mesData.primeiro_dia_semana; i++) {
            const emptyEl = document.createElement('div');
            emptyEl.className = 'cal-day cal-day-empty';
            grid.appendChild(emptyEl);
        }

        // Dias do mês
        mesData.dias.forEach(dia => {
            const dayEl = document.createElement('div');
            dayEl.className = 'cal-day cal-day-' + dia.status;
            if (dia.eh_hoje) dayEl.classList.add('cal-day-today');
            dayEl.dataset.date = dia.data;

            // Número do dia
            const numEl = document.createElement('div');
            numEl.className = 'cal-day-number';
            numEl.textContent = dia.dia;
            dayEl.appendChild(numEl);

            // Badge de status
            if (dia.badge_text) {
                const badgeEl = document.createElement('span');
                badgeEl.className = 'cal-day-badge ' + dia.badge_class;
                badgeEl.textContent = dia.badge_text;
                dayEl.appendChild(badgeEl);
            }

            // Clique nos dias disponíveis
            if (dia.clicavel) {
                dayEl.addEventListener('click', function() {
                    handleDayClick(dia.data, this);
                });
            }

            grid.appendChild(dayEl);
        });

        // Recriar ícones Lucide
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    // ====== Handler de clique no dia ======
    function handleDayClick(dateStr, dayEl) {
        const slots = horariosData[dateStr];

        // Remover seleção anterior
        document.querySelectorAll('.cal-day').forEach(el => {
            el.classList.remove('cal-day-selected');
        });
        dayEl.classList.add('cal-day-selected');

        if (slots && slots.length > 0) {
            // Formatar data pt-BR sem bug de fuso (Bug 2/4 corrigidos)
            const parts = dateStr.split('-');
            const dataFormatada = parts[2] + '/' + parts[1] + '/' + parts[0];
            document.getElementById('dataSelecionadaLabel').textContent = dataFormatada;

            // Montar cards de horários
            const slotsGrid = document.getElementById('slotsGrid');
            slotsGrid.innerHTML = '';

            // Filtro client-side: garante que slots já expirados não sejam clicáveis
            // (reforço visual, o backend já filtra)
            const slotsVisiveis = slots.filter(slot => {
                if (dateStr > dataMinima) return true; // Dia futuro: todos os slots OK
                if (dateStr < dataMinima) return false; // Dia passado: nenhum
                // Mesmo dia da data mínima: comparar hora
                return slot.hora_inicio >= horaMinima;
            });

            if (slotsVisiveis.length === 0) {
                slotsGrid.innerHTML = '<p style="color: var(--text-muted); font-size: 14px; text-align: center; padding: 12px 0;">Nenhum horário disponível para este dia com a antecedência necessária.</p>';
            } else {
                slotsVisiveis.forEach(slot => {
                    const card = document.createElement('div');
                    card.className = 'option-card';
                    card.innerHTML = `
                        <input type="radio" name="horario_id" value="${slot.id}">
                        <div class="time">${slot.hora_inicio}</div>
                        <div style="font-size: 11px; margin-top: 4px; opacity: 0.8;">Até ${slot.hora_fim}</div>
                        <div class="info" style="margin-top: 8px;">${slot.vagas_restantes} ${slot.vagas_restantes == 1 ? 'vaga' : 'vagas'}</div>
                    `;

                    card.addEventListener('click', function() {
                        document.querySelectorAll('#slotsGrid .option-card').forEach(c => c.classList.remove('selected'));
                        this.classList.add('selected');
                        this.querySelector('input[type="radio"]').checked = true;

                        const btn = document.getElementById('btnAvancarStep1');
                        btn.disabled = false;
                        btn.style.opacity = '1';
                        btn.style.cursor = 'pointer';
                    });

                    slotsGrid.appendChild(card);
                });
            }

            // Mostrar container e scroll suave
            document.getElementById('horariosContainer').style.display = 'block';
            setTimeout(() => {
                document.getElementById('horariosContainer').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }, 50);
        } else {
            document.getElementById('horariosContainer').style.display = 'none';
            const btn = document.getElementById('btnAvancarStep1');
            btn.disabled = true;
            btn.style.opacity = '0.5';
            btn.style.cursor = 'not-allowed';
        }
    }

    // ====== Navegação de meses ======
    prevBtn.addEventListener('click', function() {
        if (mesAtualIdx > 0) {
            mesAtualIdx--;
            renderCalendar(mesAtualIdx);
            // Limpar seleção ao trocar de mês
            document.getElementById('horariosContainer').style.display = 'none';
        }
    });

    nextBtn.addEventListener('click', function() {
        if (mesAtualIdx < mesesKeys.length - 1) {
            mesAtualIdx++;
            renderCalendar(mesAtualIdx);
            document.getElementById('horariosContainer').style.display = 'none';
        }
    });

    // ====== Render inicial ======
    renderCalendar(0);

    // ====== Lógica para habilitar Avançar do Step 2 (Segmento) ======
    document.querySelectorAll('input[name="segmento_id"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const btn = document.getElementById('btnAvancarStep2');
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.style.cursor = 'pointer';
        });
    });
});
</script>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
