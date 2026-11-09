<?php
/**
 * Eliminação de requerido (POST).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_can('apagar_requeridos');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/requeridos.php');
}
verify_csrf();

$id = post_int('id');
$stmt = db()->prepare('SELECT nome FROM requeridos WHERE idrequerido = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();

if (!$row) {
    flash_error('Requerido não encontrado.');
    redirect('/requeridos.php');
}

try {
    $stmt = db()->prepare('DELETE FROM requeridos WHERE idrequerido = :id');
    $stmt->execute([':id' => $id]);
    log_action('Requerido eliminado: ' . $row['nome']);
    flash_success('Requerido eliminado com sucesso.');
} catch (Throwable $e) {
    error_log('Erro ao eliminar requerido: ' . $e->getMessage());
    flash_error('Não foi possível eliminar o requerido. Verifique se existem registos associados.');
}
redirect('/requeridos.php');
