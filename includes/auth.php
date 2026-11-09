<?php
/**
 * Autenticação, sessão e controlo de acessos.
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/* =====================================================================
 * Sessão
 * ===================================================================== */

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL !== '' ? BASE_URL : '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    // Expiração por inactividade (configurável) e limite absoluto
    $minutos = (int) (config_get('sessao_minutos', '30') ?? 30);
    $minutos = $minutos > 0 ? $minutos : 30;
    $agora = time();

    if (isset($_SESSION['last_activity'])) {
        $inactivo = $agora - (int) $_SESSION['last_activity'];
        $absoluto = $agora - (int) ($_SESSION['created_at'] ?? $agora);
        if ($inactivo > $minutos * 60 || $absoluto > SESSION_LIFETIME || !isset($_SESSION['user_id'])) {
            if (isset($_SESSION['user_id'])) {
                $_SESSION = [];
                session_regenerate_id(true);
                flash_info('A sessão expirou. Inicie sessão novamente.');
            }
        }
    }
    $_SESSION['last_activity'] = $agora;
    $_SESSION['created_at'] = $_SESSION['created_at'] ?? $agora;
}

/* =====================================================================
 * Utilizador autenticado
 * ===================================================================== */

function current_user(bool $reload = false): ?array
{
    static $user = false;

    if ($reload) {
        $user = false;
    }
    if ($user !== false) {
        return $user;
    }
    if (empty($_SESSION['user_id'])) {
        $user = null;
        return null;
    }

    $stmt = db()->prepare('SELECT idusuario, nume, email, grupo_acesso, estado FROM usuarios WHERE idusuario = :id LIMIT 1');
    $stmt->execute([':id' => (int) $_SESSION['user_id']]);
    $row = $stmt->fetch();

    if (!$row || strtolower((string) $row['estado']) !== 'ativo') {
        unset($_SESSION['user_id']);
        $user = null;
        return null;
    }
    $user = $row;
    $GLOBALS['auth_user'] = $row;
    return $user;
}

function auth_check(): bool
{
    return current_user() !== null;
}

function login_user(array $row): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $row['idusuario'];
    $_SESSION['created_at'] = time();
    $_SESSION['last_activity'] = time();
    csrf_token();
    current_user(true);
    criar_notificacao_acesso($row);
    log_action('Início de sessão', [], $row['nume'], (int) $row['idusuario']);
}

/* =====================================================================
 * Notificações
 * ===================================================================== */

/**
 * Regista, para todos os administradores activos, um aviso de que um
 * utilizador acedeu ao sistema. Falhas de notificação nunca impedem o login.
 */
function criar_notificacao_acesso(array $user): void
{
    try {
        $stmt = db()->prepare(
            "SELECT idusuario FROM usuarios
             WHERE grupo_acesso = 'Administrador' AND estado = 'Ativo'"
        );
        $stmt->execute();
        $admins = $stmt->fetchAll();
        if (!$admins) {
            return;
        }

        $nome = trim((string) ($user['nume'] ?? 'Utilizador'));
        $grupo = trim((string) ($user['grupo_acesso'] ?? '—'));
        $mensagem = 'Acesso ao sistema: ' . $nome . ' (' . $grupo . ')';
        $referencia = 'login:' . date('Y-m-d H:i:s');

        $ins = db()->prepare(
            'INSERT INTO notificacoes (idusuario, tipo, mensagem, referencia, lida, criado_em)
             VALUES (:u, :t, :m, :r, 0, NOW())'
        );
        foreach ($admins as $admin) {
            $ins->execute([
                ':u' => (int) $admin['idusuario'],
                ':t' => 'acesso',
                ':m' => mb_substr($mensagem, 0, 255),
                ':r' => mb_substr($referencia, 0, 100),
            ]);
        }
    } catch (Throwable $e) {
        error_log('Falha ao criar notificação de acesso: ' . $e->getMessage());
    }
}

function notificacoes_nao_lidas(int $idusuario): int
{
    try {
        $stmt = db()->prepare('SELECT COUNT(*) FROM notificacoes WHERE idusuario = :u AND lida = 0');
        $stmt->execute([':u' => $idusuario]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function notificacoes_recentes(int $idusuario, int $limite = 8): array
{
    $limite = max(1, $limite);
    try {
        $stmt = db()->prepare(
            'SELECT * FROM notificacoes WHERE idusuario = :u ORDER BY lida ASC, criado_em DESC, idnotificacao DESC LIMIT ' . $limite
        );
        $stmt->execute([':u' => $idusuario]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function marcar_notificacoes_lidas(int $idusuario, ?int $idnotificacao = null): void
{
    try {
        if ($idnotificacao !== null && $idnotificacao > 0) {
            $stmt = db()->prepare(
                'UPDATE notificacoes SET lida = 1 WHERE idusuario = :u AND idnotificacao = :id'
            );
            $stmt->execute([':u' => $idusuario, ':id' => $idnotificacao]);
        } else {
            $stmt = db()->prepare('UPDATE notificacoes SET lida = 1 WHERE idusuario = :u AND lida = 0');
            $stmt->execute([':u' => $idusuario]);
        }
    } catch (Throwable $e) {
        error_log('Falha ao marcar notificações como lidas: ' . $e->getMessage());
    }
}

function logout_user(): void
{
    if (auth_check()) {
        log_action('Fim de sessão');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool) ($p['secure'] ?? false), (bool) ($p['httponly'] ?? true));
    }
    session_destroy();
}

/* =====================================================================
 * Permissões
 * ===================================================================== */

/**
 * Matriz de capacidades por grupo de acesso.
 */
function capabilities(?string $grupo = null): array
{
    $grupo = $grupo ?? (current_user()['grupo_acesso'] ?? '');

    $todas = [
        'ver_dashboard', 'ver_expedientes', 'criar_expedientes', 'editar_expedientes', 'apagar_expedientes',
        'ver_saidas', 'criar_saidas', 'editar_saidas', 'apagar_saidas',
        'ver_requeridos', 'criar_requeridos', 'editar_requeridos', 'apagar_requeridos',
        'ver_beneficiarios', 'criar_beneficiarios', 'editar_beneficiarios', 'apagar_beneficiarios',
        'ver_instituicoes', 'criar_instituicoes', 'editar_instituicoes', 'apagar_instituicoes',
        'ver_relatorios', 'exportar_relatorios',
        'ver_logs', 'gerir_usuarios', 'gerir_configuracoes', 'gerir_backup',
        'ver_documentos', 'ver_notificacoes',
    ];

    switch ($grupo) {
        case 'Administrador':
            return $todas;

        case 'Gestor':
            return [
                'ver_dashboard', 'ver_expedientes', 'criar_expedientes', 'editar_expedientes', 'apagar_expedientes',
                'ver_saidas', 'criar_saidas', 'editar_saidas', 'apagar_saidas',
                'ver_requeridos', 'criar_requeridos', 'editar_requeridos', 'apagar_requeridos',
                'ver_beneficiarios', 'criar_beneficiarios', 'editar_beneficiarios', 'apagar_beneficiarios',
                'ver_instituicoes', 'criar_instituicoes', 'editar_instituicoes', 'apagar_instituicoes',
                'ver_relatorios', 'exportar_relatorios', 'ver_documentos',
            ];

        case 'Operador':
            return [
                'ver_dashboard', 'ver_expedientes', 'criar_expedientes', 'editar_expedientes',
                'ver_saidas', 'criar_saidas', 'editar_saidas',
                'ver_requeridos', 'criar_requeridos', 'editar_requeridos',
                'ver_beneficiarios', 'criar_beneficiarios', 'editar_beneficiarios',
                'ver_instituicoes',
                'ver_relatorios', 'exportar_relatorios', 'ver_documentos',
            ];

        case 'Consulta':
        default:
            return [
                'ver_dashboard', 'ver_expedientes', 'ver_saidas', 'ver_requeridos',
                'ver_beneficiarios', 'ver_instituicoes',
                'ver_relatorios', 'ver_documentos',
            ];
    }
}

function can(string $capacidade): bool
{
    return in_array($capacidade, capabilities(), true);
}

/* =====================================================================
 * Guardas
 * ===================================================================== */

function require_login(): void
{
    if (!auth_check()) {
        $_SESSION['intended'] = $_SERVER['REQUEST_URI'] ?? null;
        flash_warning('É necessário iniciar sessão para continuar.');
        redirect('/login.php');
    }
}

function require_can(string $capacidade): void
{
    require_login();
    if (!can($capacidade)) {
        http_response_code(403);
        require __DIR__ . '/../forbidden.php';
        exit;
    }
}

function require_admin(): void
{
    require_login();
    if (!can('gerir_usuarios') && !can('gerir_configuracoes')) {
        http_response_code(403);
        require __DIR__ . '/../forbidden.php';
        exit;
    }
}

// A sessão é iniciada automaticamente ao carregar este ficheiro.
start_session();

// Os ícones são usados em várias páginas antes da inclusão do cabeçalho.
require_once __DIR__ . '/icons.php';
