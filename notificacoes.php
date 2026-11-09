<?php
/**
 * Notificações do administrador (acessos ao sistema).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_can('ver_notificacoes');

$u = current_user();
$uid = (int) $u['idusuario'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $acao = post('acao');

    if ($acao === 'marcar_todas') {
        marcar_notificacoes_lidas($uid);
        flash_success('Todas as notificações foram marcadas como lidas.');
        redirect('/notificacoes.php');
    }

    if ($acao === 'marcar') {
        marcar_notificacoes_lidas($uid, post_int('id'));
        flash_success('Notificação marcada como lida.');
        redirect('/notificacoes.php');
    }

    flash_error('Acção inválida.');
    redirect('/notificacoes.php');
}

$total = 0;
try {
    $stmt = db()->prepare('SELECT COUNT(*) FROM notificacoes WHERE idusuario = :u');
    $stmt->execute([':u' => $uid]);
    $total = (int) $stmt->fetchColumn();
} catch (Throwable $e) {
    $total = 0;
}

$porPagina = max(5, (int) config_get('itens_por_pagina', '15'));
$p = pagination($total, $porPagina, get_int('pagina', 1));

$linhas = [];
try {
    $stmt = db()->prepare(
        'SELECT * FROM notificacoes WHERE idusuario = :u
         ORDER BY lida ASC, criado_em DESC, idnotificacao DESC
         LIMIT ' . $p['por_pagina'] . ' OFFSET ' . $p['offset']
    );
    $stmt->execute([':u' => $uid]);
    $linhas = $stmt->fetchAll();
} catch (Throwable $e) {
    $linhas = [];
}

$naoLidas = notificacoes_nao_lidas($uid);

$page_title = 'Notificações';
$page_subtitle = $naoLidas > 0
    ? $naoLidas . ' notificação(ões) não lida(s)'
    : 'Avisos de acesso ao sistema';
$active = 'notificacoes.php';
$page_actions = ($naoLidas > 0)
    ? '<form method="post" action="' . e(url('/notificacoes.php')) . '" style="display:inline">'
        . csrf_field()
        . '<input type="hidden" name="acao" value="marcar_todas">'
        . '<button type="submit" class="btn btn-primary btn-sm">' . svg_icon('check', 'icon icon-sm') . ' Marcar todas como lidas</button>'
        . '</form>'
    : '';
require __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-head">
        <div>
            <h3>Acessos ao sistema</h3>
            <span class="sub"><?= numero_pt($total) ?> notificação(ões)</span>
        </div>
    </div>

    <?php if (!$linhas): ?>
        <div class="card-body">
            <div class="empty">
                <?= svg_icon('bell') ?>
                <h4>Sem notificações</h4>
                <p>Ainda não existem avisos de acesso para mostrar.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="notif-list">
            <?php foreach ($linhas as $n): ?>
                <?php $lida = (int) $n['lida'] === 1; ?>
                <article class="notif-item<?= $lida ? ' is-read' : '' ?>">
                    <span class="notif-dot" aria-hidden="true"><?= svg_icon('bell', 'icon icon-sm') ?></span>
                    <div class="notif-body">
                        <div class="notif-top">
                            <strong><?= e($n['mensagem']) ?></strong>
                            <span class="badge <?= $lida ? 'badge-neutral' : 'badge-encurso' ?>">
                                <?= $lida ? 'Lida' : 'Nova' ?>
                            </span>
                        </div>
                        <div class="notif-meta">
                            <?= svg_icon('clock', 'icon icon-sm') ?>
                            <?= e(data_hora_pt((string) $n['criado_em'])) ?>
                            <?php if (!empty($n['referencia'])): ?>
                                <span class="notif-ref"><?= e($n['referencia']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!$lida): ?>
                        <form method="post" action="<?= e(url('/notificacoes.php')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="acao" value="marcar">
                            <input type="hidden" name="id" value="<?= (int) $n['idnotificacao'] ?>">
                            <button type="submit" class="btn btn-outline btn-sm">Marcar como lida</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="table-foot">
            <span>A mostrar <?= numero_pt($p['inicio']) ?>–<?= numero_pt($p['fim']) ?> de <?= numero_pt($p['total']) ?></span>
            <?= pagination_links($p) ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
