<?php
/**
 * Agenda Escolar - Arquivo de Configuração Geral
 */

// Iniciar a sessão de forma segura se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    // Configurações de cookie de sessão seguras
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    
    // Se estiver usando HTTPS, habilitar cookies seguros
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    
    session_start();
}

// Configuração de fuso horário brasileiro
date_default_timezone_set('America/Sao_Paulo');

// Configurações do Banco de Dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'u178632972_agenda_escolar');
define('DB_USER', 'seu_usuario_db');
define('DB_PASS', 'sua_senha_db');

// URL Base do Sistema (Ajuste conforme necessário na hospedagem)
// Detecta automaticamente o protocolo e host
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
// Caminho do script atual para determinar a pasta base
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$dir = str_replace('\\', '/', dirname($scriptName));
// Remove "admin" ou "admin/api" se estivermos dentro do subdiretório admin
$dir = preg_replace('/\/admin(\/api)?$/', '', rtrim($dir, '/'));
$dir = rtrim($dir, '/');

define('BASE_URL', $protocol . $host . ($dir === '' ? '' : $dir));

// Configurações Gerais da Escola
define('SCHOOL_NAME', 'Colégio Exemplo Modelo');
define('ADMIN_EMAIL', 'contato@escolaexemplo.com.br');

// Modo de desenvolvimento (exibe erros se ativado)
define('DEV_MODE', true);

if (DEV_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// ── Chave da API Gemini (NUNCA exposta ao front-end) ──
// Utilizada exclusivamente pelo proxy server-side admin/api/chat.php
define('GEMINI_API_KEY', 'SUA_API_KEY_DO_GEMINI_AQUI');

// ── Configurações de SMTP do E-mail de Envio (Notificações) ──
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 465);
define('SMTP_USER', 'agenda_escolar@escolaexemplo.com.br');
define('SMTP_PASS', 'SUA_SENHA_SMTP_AQUI');
define('SMTP_SECURE', 'ssl');
define('GEMINI_MODEL', 'gemini-2.5-flash');
