<?php
/**
 * Agenda Escolar - API para alteração rápida de status via AJAX
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../db.php';

// Validar permissão
if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acesso negado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método de requisição não permitido. Use POST.']);
    exit;
}

// Obter dados do body em JSON ou POST form urlencoded
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$novo_status = sanitizar($_POST['status'] ?? '');
$csrf_token = $_POST['csrf_token'] ?? '';

// Tentar ler JSON se o POST regular vier vazio (comum em chamadas fetch com headers application/json)
if (!$id && !$novo_status) {
    $input_json = json_decode(file_get_contents('php://input'), true);
    if ($input_json) {
        $id = filter_var($input_json['id'] ?? null, FILTER_VALIDATE_INT);
        $novo_status = sanitizar($input_json['status'] ?? '');
        $csrf_token = $input_json['csrf_token'] ?? '';
    }
}

// Validar CSRF
if (!validarCSRFToken($csrf_token)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Token CSRF inválido.']);
    exit;
}

// Validar parâmetros
$status_permitidos = ['pendente', 'confirmado', 'realizado', 'cancelado'];
if (!$id || !in_array($novo_status, $status_permitidos)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos ou incompletos.']);
    exit;
}

try {
    $db = db();
    
    // Determina os campos de data/hora e responsável adicionais com base no novo status
    $admin_id = getAdminId();
    $sql_campos = "status = ?, atualizado_por_admin_id = ?, atualizado_em = CURRENT_TIMESTAMP";
    $params = [$novo_status, $admin_id];
    
    if ($novo_status === 'confirmado') {
        $sql_campos .= ", confirmado_em = CURRENT_TIMESTAMP, confirmado_por_admin_id = ?";
        $params[] = $admin_id;
    } elseif ($novo_status === 'realizado') {
        $sql_campos .= ", realizado_em = CURRENT_TIMESTAMP, realizado_por_admin_id = ?";
        $params[] = $admin_id;
    }
    
    $params[] = $id;
    $stmt = $db->prepare("UPDATE agendamentos SET $sql_campos WHERE id = ?");
    $stmt->execute($params);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Status do agendamento atualizado para "' . ucfirst($novo_status) . '" com sucesso.',
        'status' => $novo_status
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno ao atualizar status: ' . $e->getMessage()]);
}
