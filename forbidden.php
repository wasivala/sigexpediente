<?php
/**
 * Página 403 - acesso negado.
 */

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    require_once __DIR__ . '/includes/auth.php';
}

$page_title = 'Acesso negado';
$page_subtitle = 'Não tem permissão para acessar este recurso.';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <div class="card-body">
        <div class="empty">
            <?= svg_icon('shield') ?>
            <h4>Acesso negado</h4>
            <p>O seu grupo de acesso (<strong><?= e($u['grupo_acesso'] ?? '—') ?></strong>) não permite visualizar esta página.</p>
            <a class="btn btn-primary" href="<?= e(url('/dashboard.php')) ?>"><?= svg_icon('arrow-left') ?> Voltar ao painel</a>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
