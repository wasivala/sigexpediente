<?php
/**
 * Eliminação de instituição (POST).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_can('apagar_instituicoes');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/instituicoes.php');
}
verify_csrf();

$id = post_int('id');
$stmt = db()->prepare('SELECT nome FROM instituicao WHERE idinstituicao = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();

if (!$row) {
    flash_error('Instituição não encontrada.');
    redirect('/instituicoes.php');
}

try {
    $stmt = db()->prepare('DELETE FROM instituicao WHERE idinstituicao = :id');
    $stmt->execute([':id' => $id]);
    log_action('Instituição eliminada: ' . $row['nome']);
    flash_success('Instituição eliminada com sucesso.');
} catch (Throwable $e) {
    error_log('Erro ao eliminar instituição: ' . $e->getMessage());
    flash_error('Não foi possível eliminar a instituição.');
}
redirect('/instituicoes.php');
