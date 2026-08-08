<?php
/**
 * Agenda Escolar - API JSON para o FullCalendar
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../db.php';

// Validar se o usuário está logado
if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado.']);
    exit;
}

// Capturar intervalo de datas enviado pelo FullCalendar (start e end em formato ISO)
$start = sanitizar($_GET['start'] ?? '');
$end = sanitizar($_GET['end'] ?? '');

if (empty($start) || empty($end)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parâmetros start e end são obrigatórios.']);
    exit;
}

// Converter datas capturadas para Y-m-d para consulta no MySQL
$start_date = date('Y-m-d', strtotime($start));
$end_date = date('Y-m-d', strtotime($end));

$db = db();
$events = [];

try {
    // Buscar agendamentos no período
    $stmt = $db->prepare("
        SELECT a.*, 
               h.data, h.hora_inicio, h.hora_fim, 
               s.nome as nome_segmento
        FROM agendamentos a
        JOIN horarios_disponiveis h ON a.horario_id = h.id
        JOIN segmentos s ON a.segmento_id = s.id
        WHERE h.data BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        // Formatar data/hora no padrão ISO 8601 exigido pelo FullCalendar (YYYY-MM-DDTHH:MM:SS)
        $start_iso = $row['data'] . 'T' . $row['hora_inicio'];
        $end_iso = $row['data'] . 'T' . $row['hora_fim'];
        
        // Atribuir classe CSS correspondente ao status do agendamento
        $class = 'fc-event-pending';
        if ($row['status'] === 'confirmado') $class = 'fc-event-confirmed';
        elseif ($row['status'] === 'realizado') $class = 'fc-event-realizado';
        elseif ($row['status'] === 'cancelado') $class = 'fc-event-cancelado';

        $events[] = [
            'id' => $row['id'],
            'title' => 'Visita: ' . $row['nome_crianca'],
            'start' => $start_iso,
            'end' => $end_iso,
            'className' => $class,
            
            // Dados extras que serão exibidos no modal de clique do calendário
            'extendedProps' => [
                'nome_crianca' => $row['nome_crianca'],
                'nome_responsavel' => $row['nome_responsavel'],
                'nome_segmento' => $row['nome_segmento'],
                'serie_interesse' => $row['serie_interesse'],
                'status' => $row['status'],
                'telefone' => formatarTelefone($row['telefone']),
                'whatsapp' => formatarTelefone($row['whatsapp']),
                'email' => $row['email']
            ]
        ];
    }

    echo json_encode($events);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno ao consultar agendamentos: ' . $e->getMessage()]);
}
