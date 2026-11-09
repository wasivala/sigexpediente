<?php
/**
 * Listagem de requeridos (consulta por NUIT e por nome).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_can('ver_requeridos');

$nuit = get('nuit');
$nome = get('nome');
$unidade = get('unidade');
$expRef = get('expediente');

$where = [];
$params = [];
if ($nuit !== '') {
    $where[] = 'CAST(r.nuit AS CHAR) LIKE :nuit';
    $params[':nuit'] = '%' . preg_replace('/\D/', '', $nuit) . '%';
}
if ($nome !== '') {
    $where[] = 'r.nome LIKE :nome';
    $params[':nome'] = '%' . $nome . '%';
}
if ($unidade !== '') {
    $where[] = '(r.unidade LIKE :unidade OR r.ramo LIKE :unidade2)';
    $params[':unidade'] = '%' . $unidade . '%';
    $params[':unidade2'] = '%' . $unidade . '%';
}
if ($expRef !== '') {
    $where[] = 'e.referencia LIKE :exp';
    $params[':exp'] = '%' . $expRef . '%';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$count = db()->prepare("SELECT COUNT(*) FROM requeridos r LEFT JOIN expedientes e ON e.idexpediente = r.idexpediente {$whereSql}");
$count->execute($params);
$total = (int) $count->fetchColumn();

$porPagina = max(5, (int) config_get('itens_por_pagina', '15'));
$p = pagination($total, $porPagina, get_int('pagina', 1), [
    'nuit' => $nuit, 'nome' => $nome, 'unidade' => $unidade, 'expediente' => $expRef,
]);

$sql = "SELECT r.*, e.referencia AS expediente_ref
        FROM requeridos r
        LEFT JOIN expedientes e ON e.idexpediente = r.idexpediente
        {$whereSql}
        ORDER BY r.nome ASC
        LIMIT {$p['por_pagina']} OFFSET {$p['offset']}";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$linhas = $stmt->fetchAll();

$temFiltros = tem_filtros([$nuit, $nome, $unidade, $expRef]);

$page_title = 'Requeridos';
$page_subtitle = 'Pessoas associadas aos expedientes · pesquisa por NUIT ou nome';
$active = 'requeridos.php';
$page_actions = can('criar_requeridos')
    ? '<a class="btn btn-primary btn-sm" href="' . e(url('/requerido_form.php')) . '">' . svg_icon('plus', 'icon icon-sm') . ' Novo requerido</a>'
    : '';
require __DIR__ . '/includes/header.php';
?>

<div class="filters">
    <form method="get" action="<?= e(url('/requeridos.php')) ?>">
        <div class="field">
            <label for="f_nuit">NUIT</label>
            <input type="search" id="f_nuit" name="nuit" value="<?= e($nuit) ?>" inputmode="numeric" placeholder="Número de identificação">
        </div>
        <div class="field">
            <label for="f_nome">Nome</label>
            <input type="search" id="f_nome" name="nome" value="<?= e($nome) ?>" placeholder="Nome completo">
        </div>
        <div class="field">
            <label for="f_unidade">Unidade / Ramo</label>
            <input type="search" id="f_unidade" name="unidade" value="<?= e($unidade) ?>" placeholder="Unidade ou ramo">
        </div>
        <div class="field">
            <label for="f_exp">Expediente</label>
            <input type="search" id="f_exp" name="expediente" value="<?= e($expRef) ?>" placeholder="Referência">
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary"><?= svg_icon('search', 'icon icon-sm') ?> Filtrar</button>
            <a href="<?= e(url('/requeridos.php')) ?>" class="btn btn-outline">Limpar</a>
        </div>
    </form>
    <?php if ($temFiltros): ?>
        <div class="filters-summary">
            <?php foreach (['NUIT' => $nuit, 'Nome' => $nome, 'Unidade' => $unidade, 'Expediente' => $expRef] as $label => $val): ?>
                <?php if ($val !== ''): ?><span class="chip"><?= e($label) ?>: <?= e($val) ?></span><?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-head">
        <div><h3>Requeridos registados</h3><span class="sub"><?= numero_pt($total) ?> resultado(s)</span></div>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Patente / Ramo</th>
                    <th>Unidade</th>
                    <th>Função</th>
                    <th>Contacto</th>
                    <th>NUIT</th>
                    <th>Banco / Conta</th>
                    <th>Expediente</th>
                    <th class="text-right">Acções</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$linhas): ?>
                <tr><td colspan="9">
                    <div class="empty">
                        <?= svg_icon('users') ?>
                        <h4>Nenhum requerido encontrado</h4>
                        <p><?= $temFiltros ? 'Ajuste os filtros de pesquisa.' : 'Ainda não existem requeridos registados.' ?></p>
                    </div>
                </td></tr>
            <?php else: ?>
                <?php foreach ($linhas as $l): ?>
                    <tr>
                        <td class="cell-strong"><?= e($l['nome']) ?></td>
                        <td><?= e($l['patente']) ?><span class="cell-sub"><?= e($l['ramo']) ?></span></td>
                        <td><?= e($l['unidade']) ?></td>
                        <td><?= e($l['funcao']) ?></td>
                        <td><?= e($l['contacto']) ?></td>
                        <td class="cell-mono"><?= e((string) $l['nuit']) ?></td>
                        <td><?= e($l['banco']) ?><span class="cell-sub cell-mono"><?= e((string) $l['conta']) ?></span></td>
                        <td>
                            <?php if ($l['expediente_ref']): ?>
                                <a href="<?= e(url('/expediente_view.php?id=' . (int) $l['idexpediente'])) ?>" class="cell-mono"><?= e($l['expediente_ref']) ?></a>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td>
                            <div class="actions">
                                <?php if (can('editar_requeridos')): ?>
                                    <a class="btn-icon" href="<?= e(url('/requerido_form.php?id=' . (int) $l['idrequerido'])) ?>" title="Editar"><?= svg_icon('edit') ?></a>
                                <?php endif; ?>
                                <?php if (can('apagar_requeridos')): ?>
                                    <form method="post" action="<?= e(url('/requerido_delete.php')) ?>" style="display:inline" data-confirm="Eliminar o requerido <?= e($l['nome']) ?>?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $l['idrequerido'] ?>">
                                        <button type="submit" class="btn-icon danger" title="Eliminar"><?= svg_icon('trash') ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($linhas): ?>
        <div class="table-foot">
            <span>A mostrar <?= numero_pt($p['inicio']) ?>–<?= numero_pt($p['fim']) ?> de <?= numero_pt($p['total']) ?></span>
            <?= pagination_links($p) ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
