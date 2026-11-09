<?php
/**
 * SIGExpediente - Configuração geral da aplicação
 */

declare(strict_types=1);

// Raiz absoluta do projecto
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// ---------------------------------------------------------------------
// Ambiente / erros
// ---------------------------------------------------------------------
define('APP_ENV', getenv('APP_ENV') ?: 'development');

if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', APP_ROOT . '/storage/logs/php-error.log');
}

// ---------------------------------------------------------------------
// Fuso horário
// ---------------------------------------------------------------------
date_default_timezone_set('Africa/Maputo');

// ---------------------------------------------------------------------
// Base de dados
// ---------------------------------------------------------------------
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'sigexpediente');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
// utf8 (utf8mb3) para coincidir com a collation utf8_general_ci das tabelas.
define('DB_CHARSET', 'utf8');

// ---------------------------------------------------------------------
// Sessão
// ---------------------------------------------------------------------
define('SESSION_NAME', 'sigexpediente_sid');
define('SESSION_LIFETIME', 60 * 60 * 2); // 2 horas (máximo absoluto)

// ---------------------------------------------------------------------
// Uploads / documentos
// ---------------------------------------------------------------------
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024); // 5 MB
define('ALLOWED_UPLOAD_EXT', ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_UPLOAD_MIME', ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// ---------------------------------------------------------------------
// Directórios de armazenamento
// ---------------------------------------------------------------------
define('STORAGE_DIR', APP_ROOT . '/storage');
define('BACKUP_DIR', STORAGE_DIR . '/backups');
define('LOG_DIR', STORAGE_DIR . '/logs');

// ---------------------------------------------------------------------
// URL base (detectada automaticamente)
// ---------------------------------------------------------------------
if (!defined('BASE_URL')) {
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'], '/')) : '';
    $appRoot = str_replace('\\', '/', rtrim(APP_ROOT, '/'));
    $base = '';
    if ($docRoot !== '' && str_starts_with($appRoot, $docRoot)) {
        $base = substr($appRoot, strlen($docRoot));
    }
    define('BASE_URL', rtrim($base, '/'));
}

// ---------------------------------------------------------------------
// Constantes de domínio
// ---------------------------------------------------------------------
define('STATUS_EXPEDIENTE', [
    'encurso'     => 'Encurso',
    'finalizado'  => 'Finalizado',
    'pendente'    => 'Pendente',
    'arquivado'   => 'Arquivado',
]);

define('GRUPOS_ACESSO', [
    'Administrador' => 'Administrador',
    'Gestor'        => 'Gestor',
    'Operador'      => 'Operador',
    'Consulta'      => 'Consulta',
]);

// Patentes militares (Exército/Marinha), pela ordem hierárquica.
define('PATENTES', [
    'General do Exército/Almirante',
    'Tenente-General/Vice-Almirante',
    'Major General/Contra Almirante',
    'Brigadeiro/Comodoro',
    'Coronel/Capitão de Mar e Guerra',
    'Tenente-Coronel/Capitão de Fragata',
    'Major/Capitão Tenente',
    'Capitão/Primeiro Tenente',
    'Tenente/Segundo Tenente',
    'Alferes/Guarda Marinha',
    'Intendente',
    'Sub-Intendente',
    'Primeiro Sargento',
    'Segundo Sargento',
    'Terceiro Sargento',
    'Furriel/Subsargento',
    'Primeiro Cabo/Primeiro Marinheiro',
    'Segundo Cabo/Segundo Marinheiro',
    'Soldado/Grumete',
]);

// Valor gravado no campo `patente` quando o requerido é civil.
define('PATENTE_CIVIL', 'Civil');

// Tipos de instituição/sector usados na combobox do formulário.
define('TIPOS_INSTITUICAO', [
    'Instituição',
    'Departamento',
    'Repartição',
]);

// Garante que os directórios de armazenamento existem
foreach ([STORAGE_DIR, BACKUP_DIR, LOG_DIR] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}
