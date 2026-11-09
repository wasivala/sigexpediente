<?php
/**
 * Eliminação de beneficiário (POST).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_can('apagar_beneficiarios');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/beneficiarios.php');
}
verify_csrf();

$id = post_int('id');
$stmt = db()->prepare('SELECT nome FROM beneficiarios WHERE idbeneficiario = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();

if (!$row) {
    flash_error('Beneficiário não encontrado.');
    redirect('/beneficiarios.php');
}

try {
    $stmt = db()->prepare('DELETE FROM beneficiarios WHERE idbeneficiario = :id');
    $stmt->execute([':id' => $id]);
    log_action('Beneficiário eliminado: ' . $row['nome']);
    flash_success('Beneficiário eliminado com sucesso.');
} catch (Throwable $e) {
    error_log('Erro ao eliminar beneficiário: ' . $e->getMessage());
    flash_error('Não foi possível eliminar o beneficiário.');
}
redirect('/beneficiarios.php');
