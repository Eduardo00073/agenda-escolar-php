<?php
/**
 * Agenda Escolar - Middleware de Autenticação Administrativa
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';

// Iniciar a sessão se ainda não foi iniciada pelo config.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verifica se o administrador está devidamente logado.
 */
function isLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true && !empty($_SESSION['admin_id']);
}

/**
 * Garante que o usuário está autenticado. Caso contrário, redireciona para a tela de login do painel admin.
 */
function requireLogin() {
    if (!isLoggedIn()) {
        flash('login_error', 'Você precisa fazer login para acessar essa área.', 'danger');
        redirect(BASE_URL . '/admin/login.php');
    }
}

/**
 * Retorna o nome do administrador logado na sessão ativa.
 */
function getAdminNome() {
    return $_SESSION['admin_nome'] ?? 'Administrador';
}

/**
 * Retorna o ID do administrador logado na sessão ativa.
 */
function getAdminId() {
    return $_SESSION['admin_id'] ?? null;
}
