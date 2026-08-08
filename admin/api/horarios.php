<?php
/**
 * Agenda Escolar - API REST para manipulação de horários via AJAX
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../db.php';

// Validar login
if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acesso negado.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$db = db();

try {
    // 1. GET: Retorna horários ativos ou futuros
    if ($method === 'GET') {
        $hoje = date('Y-m-d');
        $stmt = $db->prepare("SELECT * FROM horarios_disponiveis WHERE data >= ? AND ativo = 1 ORDER BY data ASC, hora_inicio ASC");
        $stmt->execute([$hoje]);
        $rows = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }
    
    // 2. POST: Criar novo horário
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        $csrf_token = $input['csrf_token'] ?? '';
        if (!validarCSRFToken($csrf_token)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Token CSRF inválido.']);
            exit;
        }
        
        $data = sanitizar($input['data'] ?? '');
        $hora_inicio = sanitizar($input['hora_inicio'] ?? '');
        $hora_fim = sanitizar($input['hora_fim'] ?? '');
        $max_vagas = filter_var($input['max_vagas'] ?? 1, FILTER_VALIDATE_INT) ?: 1;
        
        $hoje_valid = date('Y-m-d');
        if (empty($data) || empty($hora_inicio) || empty($hora_fim)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Campos obrigatórios ausentes.']);
            exit;
        } elseif ($data < $hoje_valid) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Não é possível cadastrar horários em datas passadas.']);
            exit;
        } elseif ($hora_inicio >= $hora_fim) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'A hora de término deve ser posterior à hora de início.']);
            exit;
        }
        
        $stmt = $db->prepare("INSERT INTO horarios_disponiveis (data, hora_inicio, hora_fim, max_vagas, ativo) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$data, $hora_inicio, $hora_fim, $max_vagas]);
        $insert_id = $db->lastInsertId();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Horário adicionado com sucesso.', 
            'id' => $insert_id
        ]);
        exit;
    }
    
    // 3. DELETE: Excluir horário (se não houver agendamentos vinculados)
    elseif ($method === 'DELETE' || ($method === 'POST' && isset($_POST['_method']) && $_POST['_method'] === 'DELETE')) {
        // Obter ID do horário
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            $input = json_decode(file_get_contents('php://input'), true);
            $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        }
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID do horário não fornecido.']);
            exit;
        }
        
        // Verificar se existem agendamentos ativos
        $stmtCheck = $db->prepare("SELECT COUNT(*) FROM agendamentos WHERE horario_id = ?");
        $stmtCheck->execute([$id]);
        $vinculados = (int)$stmtCheck->fetchColumn();
        
        if ($vinculados > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Não é possível excluir: existem $vinculados agendamentos associados."]);
            exit;
        }
        
        $stmtDel = $db->prepare("DELETE FROM horarios_disponiveis WHERE id = ?");
        $stmtDel->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Horário excluído com sucesso.']);
        exit;
    }
    
    // Método não suportado
    else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método HTTP não suportado.']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno de processamento: ' . $e->getMessage()]);
}
