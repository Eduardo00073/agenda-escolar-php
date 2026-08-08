<?php
/**
 * Agenda Escolar - Conexão com o Banco de Dados via PDO (Singleton)
 */

require_once __DIR__ . '/config.php';

class DB {
    private static $instance = null;

    /**
     * Retorna a instância ativa da conexão PDO.
     */
    public static function getConnection() {
        if (self::$instance === null) {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ];
                
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
                
                // Verificar se as tabelas existem (evitar loop no próprio install.php)
                if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php') {
                    try {
                        self::$instance->query("SELECT 1 FROM `administradores` LIMIT 1");
                    } catch (PDOException $e) {
                        // SQLSTATE 42S02: Tabela não existe
                        if ($e->getCode() == '42S02' || strpos($e->getMessage(), '1146') !== false) {
                            $install_url = BASE_URL . '/install.php';
                            ?>
                            <!DOCTYPE html>
                            <html lang="pt-BR">
                            <head>
                                <meta charset="UTF-8">
                                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                                <title>Instalação Requerida | Colégio Exemplo Modelo</title>
                                <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
                                <style>
                                    body { font-family: 'Inter', sans-serif; background-color: #fafafa; color: #18181b; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; text-align: center; }
                                    .card { max-width: 450px; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); border: 1px solid #e4e4e7; }
                                    h1 { color: #ff5000; font-size: 22px; margin-bottom: 12px; font-weight: 700; }
                                    p { color: #71717a; font-size: 14px; line-height: 1.6; margin-bottom: 24px; }
                                    .btn { display: inline-block; padding: 12px 24px; background-color: #ff5000; color: white; text-decoration: none; font-weight: 600; border-radius: 8px; font-size: 14px; transition: background-color 0.2s; }
                                    .btn:hover { background-color: #cc4000; }
                                </style>
                            </head>
                            <body>
                                <div class="card">
                                    <h1>Instalação Necessária</h1>
                                    <p>A conexão com o banco de dados foi estabelecida com sucesso, mas as tabelas do sistema ainda não foram geradas.</p>
                                    <a href="<?php echo $install_url; ?>" class="btn">Executar Instalador do Sistema</a>
                                </div>
                            </body>
                            </html>
                            <?php
                            exit;
                        }
                    }
                }
            } catch (PDOException $e) {
                // Se der erro de conexão e estivermos em ambiente local/desenvolvimento,
                // vamos tentar dar um fallback amigável ou exibir o erro de forma elegante
                if (DEV_MODE) {
                    die("Erro de conexão ao banco de dados: " . $e->getMessage() . "<br>Verifique as credenciais no arquivo <code>config.php</code>.");
                } else {
                    die("Erro ao conectar ao banco de dados. Por favor, tente novamente mais tarde.");
                }
            }
        }
        return self::$instance;
    }
}

/**
 * Função utilitária para obter a conexão PDO diretamente.
 */
function db() {
    return DB::getConnection();
}
