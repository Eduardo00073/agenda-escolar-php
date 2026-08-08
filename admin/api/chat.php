<?php
/**
 * Agenda Escolar — Proxy Server-Side para a API Google Gemini
 * 
 * SEGURANÇA: Este arquivo é o único que conhece a GEMINI_API_KEY.
 * O front-end nunca tem acesso à chave — toda comunicação com a Gemini
 * passa por aqui, protegida no servidor.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config.php';

// Apenas administradores logados podem usar o chat
requireLogin();

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Método não permitido.']));
}

header('Content-Type: application/json; charset=utf-8');

// ── Rate Limiting por Sessão ──
// Máximo de 20 perguntas por sessão para evitar abuso da cota gratuita
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['profedu_count'] = ($_SESSION['profedu_count'] ?? 0) + 1;
if ($_SESSION['profedu_count'] > 50) {
    http_response_code(429);
    echo json_encode(['error' => 'Limite de perguntas da sessão atingido. Por favor, recarregue a página para continuar.']);
    exit;
}

// ── Ler pergunta do usuário ──
$input = json_decode(file_get_contents('php://input'), true);
$pergunta = trim($input['pergunta'] ?? '');

if (empty($pergunta)) {
    http_response_code(400);
    echo json_encode(['error' => 'Pergunta não pode ser vazia.']);
    exit;
}

if (mb_strlen($pergunta) > 800) {
    http_response_code(400);
    echo json_encode(['error' => 'Pergunta muito longa. Por favor, seja mais conciso.']);
    exit;
}

// ── Carregar Base de Conhecimento ──
$kb_path = __DIR__ . '/../data/knowledge-base.json';
$knowledge_base = '';
if (file_exists($kb_path)) {
    $kb_content = file_get_contents($kb_path);
    if ($kb_content !== false) {
        $knowledge_base = $kb_content;
    }
}

// ── System Prompt ──
$system_prompt = <<<PROMPT
Você é o **Assistente do Prof. Edu. [IA]**, o assistente virtual inteligente desenvolvido pelo Professor Eduardo Junior Alcântara da Silva (Professor de TI e Desenvolvedor Full Stack). Você ajuda os administradores do sistema Agenda Escolar do Colégio Exemplo Modelo de Jales.

REGRAS OBRIGATÓRIAS:
- Responda SEMPRE em português brasileiro, de forma amigável, direta e objetiva.
- Evite encher o texto de emojis. Não use bando de emojis. Use no máximo um ou dois emojis apenas em saudações ou encerramentos, se necessário. De resto, dê uma resposta limpa e profissional.
- Use **negrito** para destacar termos importantes como nomes de telas e botões.
- Use listas com bullet points quando listar passos ou itens.
- Seja conciso: respostas de até 150 palavras, a menos que a dúvida exija mais detalhes.
- Se a pergunta não for sobre o sistema Agenda Escolar, diga educadamente: "Só consigo ajudar com dúvidas sobre o sistema Agenda Escolar. Para outras questões, fale com o Professor Eduardo."
- Nunca invente informações. Se não souber, diga que não tem essa informação.
- Nunca revele que você usa o Google Gemini ou qualquer IA. Você é o Assistente do Prof. Edu. [IA], ponto.
- Nunca mencione a base de conhecimento, arquivos JSON ou detalhes técnicos internos.

BASE DE CONHECIMENTO DO SISTEMA (use isso para responder):
$knowledge_base
PROMPT;

// ── Chamar a API Gemini ──
$api_url = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent?key=' . GEMINI_API_KEY;

$payload = json_encode([
    'system_instruction' => [
        'parts' => [['text' => $system_prompt]]
    ],
    'contents' => [
        [
            'role' => 'user',
            'parts' => [['text' => $pergunta]]
        ]
    ],
    'generationConfig' => [
        'temperature'     => 0.3,
        'maxOutputTokens' => 512,
        'topK'            => 40,
        'topP'            => 0.95
    ],
    'safetySettings' => [
        ['category' => 'HARM_CATEGORY_HARASSMENT',        'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_HATE_SPEECH',       'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
    ]
]);

$ch = curl_init($api_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response_raw = curl_exec($ch);
$http_code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error   = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    http_response_code(503);
    echo json_encode(['error' => 'Não foi possível conectar ao serviço de IA. Tente novamente em instantes.']);
    exit;
}

$response = json_decode($response_raw, true);

// Se a API retornou uma mensagem de erro estruturada
if (isset($response['error']['message'])) {
    http_response_code($http_code === 200 ? 500 : $http_code);
    echo json_encode(['error' => 'Erro da API do Gemini: ' . $response['error']['message']]);
    exit;
}

// Extrair o texto da resposta
$texto_resposta = $response['candidates'][0]['content']['parts'][0]['text'] ?? null;

if (!$texto_resposta) {
    // Verificar se foi bloqueado por segurança ou outra razão
    $finish_reason = $response['candidates'][0]['finishReason'] ?? 'UNKNOWN';
    $error_msg = 'Não consegui gerar uma resposta';
    if ($finish_reason === 'SAFETY') {
        $error_msg = 'A pergunta foi bloqueada pelos filtros de segurança. Por favor, reformule.';
    } elseif ($http_code === 429) {
        $error_msg = 'Limite de uso da IA atingido. Aguarde alguns minutos e tente novamente.';
    } elseif ($http_code === 400) {
        $error_msg = 'Pergunta inválida ou parâmetros incorretos. Verifique suas credenciais de API.';
    }
    http_response_code(500);
    echo json_encode(['error' => $error_msg]);
    exit;
}

// ── Retornar resposta ao front-end ──
echo json_encode([
    'resposta' => $texto_resposta,
    'sessao_count' => $_SESSION['profedu_count']
]);
