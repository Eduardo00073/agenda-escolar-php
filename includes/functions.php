<?php
/**
 * Agenda Escolar - Funções Utilitárias Gerais
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

/**
 * Sanitiza valores de entrada para evitar XSS e outras injeções de dados no HTML.
 */
function sanitizar($data) {
    if (is_array($data)) {
        return array_map('sanitizar', $data);
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

/**
 * Gera um token CSRF e guarda na sessão para proteger formulários contra requisições falsificadas.
 */
function gerarCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valida o token CSRF recebido em formulários do tipo POST.
 */
function validarCSRFToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/**
 * Formata data no formato brasileiro (DD/MM/AAAA) a partir de AAAA-MM-DD.
 */
function formatarData($data) {
    if (empty($data)) return '';
    $timestamp = strtotime($data);
    return date('d/m/Y', $timestamp);
}

/**
 * Formata data e hora no formato brasileiro (DD/MM/AAAA às HH:MM).
 */
function formatarDataHora($dataHora) {
    if (empty($dataHora)) return '';
    $timestamp = strtotime($dataHora);
    return date('d/m/Y \à\s H:i', $timestamp);
}

/**
 * Formata hora no formato brasileiro (HH:MM) a partir de HH:MM:SS ou similar.
 */
function formatarHora($hora) {
    if (empty($hora)) return '';
    return date('H:i', strtotime($hora));
}

/**
 * Aplica máscara de telefone brasileiro dinamicamente.
 */
function formatarTelefone($tel) {
    // Remove tudo o que não for número
    $tel = preg_replace('/[^0-9]/', '', $tel);
    $qtd = strlen($tel);
    
    if ($qtd === 11) {
        return '(' . substr($tel, 0, 2) . ') ' . substr($tel, 2, 5) . '-' . substr($tel, 7);
    } elseif ($qtd === 10) {
        return '(' . substr($tel, 0, 2) . ') ' . substr($tel, 2, 4) . '-' . substr($tel, 6);
    }
    return $tel; // Retorna sem máscara se não se encaixar no padrão
}

/**
 * Gera uma string aleatória segura para tokens de agendamento.
 */
function gerarToken($tamanho = 32) {
    return bin2hex(random_bytes($tamanho));
}

/**
 * Retorna a quantidade de vagas disponíveis restantes para um determinado horário.
 */
function vagasDisponiveis($horario_id) {
    $db = db();
    
    // Buscar capacidade total do horário
    $stmtCapacidade = $db->prepare("SELECT max_vagas FROM horarios_disponiveis WHERE id = ? AND ativo = 1");
    $stmtCapacidade->execute([$horario_id]);
    $capacidade = $stmtCapacidade->fetchColumn();
    
    if ($capacidade === false) {
        return 0; // Horário não encontrado ou inativo
    }
    
    // Contar agendamentos ativos para esse horário (desconsidera cancelados)
    $stmtAgendados = $db->prepare("SELECT COUNT(*) FROM agendamentos WHERE horario_id = ? AND status != 'cancelado'");
    $stmtAgendados->execute([$horario_id]);
    $agendados = $stmtAgendados->fetchColumn();
    
    $restantes = (int)$capacidade - (int)$agendados;
    return max(0, $restantes);
}

/**
 * Redireciona de forma segura para uma URL específica e encerra o script.
 */
function redirect($url) {
    header("Location: " . $url);
    exit;
}

/**
 * Define ou obtém uma mensagem de alerta flash utilizando a sessão do usuário.
 */
function flash($nome = '', $mensagem = '', $tipo = 'success') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!empty($nome)) {
        if (!empty($mensagem)) {
            // Define a mensagem flash
            $_SESSION['flash'][$nome] = [
                'mensagem' => $mensagem,
                'tipo' => $tipo
            ];
        } else {
            // Retorna e deleta a mensagem flash
            if (isset($_SESSION['flash'][$nome])) {
                $flash = $_SESSION['flash'][$nome];
                unset($_SESSION['flash'][$nome]);
                return $flash;
            }
        }
    }
    return null;
}

/**
 * Exibe todas as mensagens flash em formato de Toasts.
 */
function exibirMensagensFlash() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!empty($_SESSION['flash'])) {
        echo '<div class="toast-container" id="toastContainer">';
        foreach ($_SESSION['flash'] as $nome => $flash) {
            $tipoClass = '';
            $icon = 'info';
            
            switch ($flash['tipo']) {
                case 'success':
                    $tipoClass = 'toast-success';
                    $icon = 'check-circle';
                    break;
                case 'danger':
                    $tipoClass = 'toast-danger';
                    $icon = 'alert-triangle';
                    break;
                case 'warning':
                    $tipoClass = 'toast-warning';
                    $icon = 'alert-circle';
                    break;
            }
            
            echo '<div class="toast ' . $tipoClass . '">';
            echo '  <i data-lucide="' . $icon . '"></i>';
            echo '  <div class="toast-message">' . $flash['mensagem'] . '</div>';
            echo '</div>';
        }
        echo '</div>';
        unset($_SESSION['flash']);
    }
}
