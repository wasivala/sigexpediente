<?php
/**
 * Conexão PDO (singleton) à base de dados.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
    } catch (PDOException $e) {
        error_log('Falha de ligação à base de dados: ' . $e->getMessage());
        http_response_code(500);
        exit(
            '<!doctype html><html lang="pt"><head><meta charset="utf-8">'
            . '<title>Erro de ligação</title></head><body style="font-family:Segoe UI,sans-serif;padding:40px;">'
            . '<h1>Não foi possível ligar à base de dados</h1>'
            . '<p>Verifique se o servidor MySQL está em execução e se as credenciais em '
            . '<code>config/config.php</code> estão correctas.</p>'
            . (APP_ENV === 'development' ? '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>' : '')
            . '</body></html>'
        );
    }

    return $pdo;
}
