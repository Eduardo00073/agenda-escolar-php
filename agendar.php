<?php
/**
 * Colégio Exemplo Modelo
 * Redirecionador para a página inicial de agendamentos
 */
require_once __DIR__ . '/config.php';
header("Location: " . BASE_URL . "/index.php" . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
exit;
