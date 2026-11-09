<?php
/**
 * Eliminação de saída (POST).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_can('apagar_saidas');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/saidas.php');
}
verify_csrf();

$id = post_int('id');
$stmt = db()->prepare('SELECT referencia FROM saida_expedientes WHERE idsaida = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();

if (!$row) {
    flash_error('Saída não encontrada.');
    redirect('/saidas.php');
}

try {
    $stmt = db()->prepare('DELETE FROM saida_expedientes WHERE idsaida = :id');
    $stmt->execute([':id' => $id]);
    log_action('Saída eliminada: ' . $row['referencia']);
    flash_success('Saída eliminada com sucesso.');
} catch (Throwable $e) {
    error_log('Erro ao eliminar saída: ' . $e->getMessage());
    flash_error('Não foi possível eliminar a saída.');
}
redirect('/saidas.php');
