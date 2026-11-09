<?php
/**
 * Download de uma cópia de segurança.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/backup.php';
require_can('gerir_backup');

$nome = get('f');
$caminho = backup_caminho($nome);

if ($caminho === null) {
    flash_error('A cópia de segurança indicada não foi encontrada.');
    redirect('/backup.php');
}

$tamanho = (int) filesize($caminho);
log_action('Backup descarregado: ' . basename($caminho));

header('Content-Type: application/sql; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . basename($caminho) . '"');
header('Content-Length: ' . $tamanho);
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

readfile($caminho);
exit;
