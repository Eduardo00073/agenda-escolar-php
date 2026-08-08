<?php
/**
 * Agenda Escolar - Logout do Administrador
 */
require_once __DIR__ . '/../includes/functions.php';

// Limpar e destruir a sessão do usuário
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Se a sessão foi iniciada de novo para o flash
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
flash('logout_sucesso', 'Você saiu do painel administrativo com sucesso.', 'success');

redirect(BASE_URL . '/admin/login.php');
