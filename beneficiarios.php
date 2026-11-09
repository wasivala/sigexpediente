<?php
/**
 * Listagem de beneficiários (consulta por nome).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_can('ver_beneficiarios');

$nome = get('nome');
$expRef = get('expediente');
$banco = get('banco');

$where = [];
$params = [];
if ($nome !== '') {
    $where[] = 'b.nome LIKE :nome';
    $params[':nome'] = '%' . $nome . '%';
}
if ($expRef !== '') {
    $where[] = 'e.referencia LIKE :exp';
    $params[':exp'] = '%' . $expRef . '%';
}
if ($banco !== '') {
    $where[] = 'b.banco LIKE :banco';
    $params[':banco'] = '%' . $banco . '%';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$count = db()->prepare("SELECT COUNT(*) FROM beneficiarios b LEFT JOIN expedientes e ON e.idexpediente = b.idexpediente {$whereSql}");
$count->execute($params);
$total = (int) $count->fetchColumn();

$porPagina = max(5, (int) config_get('itens_por_pagina', '15'));
$p = pagination($total, $porPagina, get_int('pagina', 1), ['nome' => $nome, 'expediente' => $expRef, 'banco' => $banco]);

$sql = "SELECT b.*, e.referencia AS expediente_ref
        FROM beneficiarios b
        LEFT JOIN expedientes e ON e.idexpediente = b.idexpediente
        {$whereSql}
        ORDER BY b.nome ASC
        LIMIT {$p['por_pagina']} OFFSET {$p['offset']}";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$linhas = $stmt->fetchAll();

$temFiltros = tem_filtros([$nome, $expRef, $banco]);

$page_title = 'Beneficiários';
$page_subtitle = 'Beneficiários associados aos expedientes';
$active = 'beneficiarios.php';
$page_actions = can('criar_beneficiarios')
    ? '<a class="btn btn-primary btn-sm" href="' . e(url('/beneficiario_form.php')) . '">' . svg_icon('plus', 'icon icon-sm') . ' Novo beneficiário</a>'
    : '';
require __DIR__ . '/includes/header.php';
?>

<div class="filters">
    <form method="get" action="<?= e(url('/beneficiarios.php')) ?>">
        <div class="field">
            <label for="f_nome">Nome</label>
            <input type="search" id="f_nome" name="nome" value="<?= e($nome) ?>" placeholder="Nome do beneficiário">
        </div>
        <div class="field">
            <label for="f_banco">Banco</label>
            <input type="search" id="f_banco" name="banco" value="<?= e($banco) ?>" placeholder="Instituição bancária">
        </div>
        <div class="field">
            <label for="f_exp">Expediente</label>
            <input type="search" id="f_exp" name="expediente" value="<?= e($expRef) ?>" placeholder="Referência">
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary"><?= svg_icon('search', 'icon icon-sm') ?> Filtrar</button>
            <a href="<?= e(url('/beneficiarios.php')) ?>" class="btn btn-outline">Limpar</a>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-head">
        <div><h3>Beneficiários registados</h3><span class="sub"><?= numero_pt($total) ?> resultado(s)</span></div>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Contacto</th>
                    <th>Endereço</th>
                    <th>Banco / Conta</th>
                    <th class="num">Quantidade</th>
                    <th>Expediente</th>
                    <th class="text-right">Acções</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$linhas): ?>
                <tr><td colspan="7">
                    <div class="empty">
                        <?= svg_icon('handshake') ?>
                        <h4>Nenhum beneficiário encontrado</h4>
                        <p><?= $temFiltros ? 'Ajuste os filtros de pesquisa.' : 'Ainda não existem beneficiários registados.' ?></p>
                    </div>
                </td></tr>
            <?php else: ?>
                <?php foreach ($linhas as $l): ?>
                    <tr>
                        <td class="cell-strong"><?= e($l['nome']) ?></td>
                        <td><?= e($l['contacto']) ?></td>
                        <td><?= e($l['endereco']) ?></td>
                        <td><?= e($l['banco']) ?><span class="cell-sub cell-mono"><?= e((string) $l['conta']) ?></span></td>
                        <td class="num"><?= numero_pt($l['qtd_beneficiario']) ?></td>
                        <td>
                            <?php if ($l['expediente_ref']): ?>
                                <a href="<?= e(url('/expediente_view.php?id=' . (int) $l['idexpediente'])) ?>" class="cell-mono"><?= e($l['expediente_ref']) ?></a>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td>
                            <div class="actions">
                                <?php if (can('editar_beneficiarios')): ?>
                                    <a class="btn-icon" href="<?= e(url('/beneficiario_form.php?id=' . (int) $l['idbeneficiario'])) ?>" title="Editar"><?= svg_icon('edit') ?></a>
                                <?php endif; ?>
                                <?php if (can('apagar_beneficiarios')): ?>
                                    <form method="post" action="<?= e(url('/beneficiario_delete.php')) ?>" style="display:inline" data-confirm="Eliminar o beneficiário <?= e($l['nome']) ?>?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $l['idbeneficiario'] ?>">
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
