<?php
/**
 * Eliminação de expediente (POST).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_can('apagar_expedientes');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/expedientes.php');
}
verify_csrf();

$id = post_int('id');
$stmt = db()->prepare('SELECT referencia FROM expedientes WHERE idexpediente = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$exp = $stmt->fetch();

if (!$exp) {
    flash_error('Expediente não encontrado.');
    redirect('/expedientes.php');
}

$deps = 0;
foreach (['saida_expedientes', 'requeridos', 'beneficiarios'] as $tabela) {
    $s = db()->prepare("SELECT COUNT(*) FROM {$tabela} WHERE idexpediente = :id");
    $s->execute([':id' => $id]);
    $deps += (int) $s->fetchColumn();
}

if ($deps > 0) {
    flash_warning('Não é possível eliminar o expediente ' . $exp['referencia'] . ' porque tem ' . $deps . ' registo(s) associado(s) (saídas, requeridos ou beneficiários). Elimine ou transfira esses registos primeiro.');
    redirect('/expediente_view.php?id=' . $id);
}

try {
    $stmt = db()->prepare('DELETE FROM expedientes WHERE idexpediente = :id');
    $stmt->execute([':id' => $id]);
    log_action('Expediente eliminado: ' . $exp['referencia']);
    flash_success('Expediente eliminado com sucesso.');
} catch (Throwable $e) {
    error_log('Erro ao eliminar expediente: ' . $e->getMessage());
    flash_error('Não foi possível eliminar o expediente.');
}
redirect('/expedientes.php');
