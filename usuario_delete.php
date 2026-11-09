<?php
/**
 * Eliminação de utilizador (POST).
 *
 * Regras de segurança:
 *  - ninguém pode eliminar a própria conta;
 *  - o último administrador activo não pode ser eliminado;
 *  - contas com registos associados devem ser desactivadas em vez de eliminadas.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_can('gerir_usuarios');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/usuarios.php');
}
verify_csrf();

$id = post_int('id');
$stmt = db()->prepare('SELECT idusuario, nume, grupo_acesso, estado FROM usuarios WHERE idusuario = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$alvo = $stmt->fetch();

if (!$alvo) {
    flash_error('Utilizador não encontrado.');
    redirect('/usuarios.php');
}

$atual = current_user();

if ((int) $atual['idusuario'] === $id) {
    flash_warning('Não pode eliminar a sua própria conta. Altere a palavra-passe ou peça a outro administrador para o fazer.');
    redirect('/usuarios.php');
}

if ($alvo['grupo_acesso'] === 'Administrador' && strtolower((string) $alvo['estado']) === 'ativo') {
    $chk = db()->prepare("SELECT COUNT(*) FROM usuarios WHERE grupo_acesso = 'Administrador' AND estado = 'Ativo' AND idusuario <> :id");
    $chk->execute([':id' => $id]);
    if ((int) $chk->fetchColumn() === 0) {
        flash_warning('Não é possível eliminar o último administrador activo do sistema.');
        redirect('/usuarios.php');
    }
}

// Contas com registos associados não podem ser eliminadas (integridade referencial).
$deps = 0;
foreach (['expedientes', 'saida_expedientes', 'requeridos', 'beneficiarios'] as $tabela) {
    $s = db()->prepare("SELECT COUNT(*) FROM {$tabela} WHERE idusuario = :id");
    $s->execute([':id' => $id]);
    $deps += (int) $s->fetchColumn();
}

if ($deps > 0) {
    flash_warning(
        'A conta de ' . $alvo['nume'] . ' tem ' . $deps . ' registo(s) associado(s) e não pode ser eliminada. '
        . 'Defina o estado como Inativo no formulário de edição para bloquear o acesso sem perder o histórico.'
    );
    redirect('/usuarios.php');
}

try {
    $del = db()->prepare('DELETE FROM usuarios WHERE idusuario = :id');
    $del->execute([':id' => $id]);
    log_action('Utilizador eliminado: ' . $alvo['nume']);
    flash_success('Utilizador ' . $alvo['nume'] . ' eliminado com sucesso.');
} catch (Throwable $e) {
    error_log('Erro ao eliminar utilizador: ' . $e->getMessage());
    flash_error('Não foi possível eliminar o utilizador. Desactive a conta em vez de a eliminar.');
}

redirect('/usuarios.php');
