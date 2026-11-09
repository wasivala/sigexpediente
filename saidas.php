<?php
/**
 * Listagem de saídas de expediente.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_can('ver_saidas');

$referencia = get('referencia');
$destino    = get('destino');
$dataIni    = get('data_inicio');
$dataFim    = get('data_fim');
$busca      = get('q');

if ($dataIni !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataIni)) { $dataIni = ''; }
if ($dataFim !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim)) { $dataFim = ''; }
if ($dataIni !== '' && $dataFim !== '' && $dataIni > $dataFim) {
    flash_warning('A data inicial não pode ser posterior à data final. Os filtros de data foram ignorados.');
    $dataIni = $dataFim = '';
}

$where = [];
$params = [];
if ($referencia !== '') {
    $where[] = '(s.referencia LIKE :ref OR e.referencia LIKE :ref2)';
    $params[':ref'] = '%' . $referencia . '%';
    $params[':ref2'] = '%' . $referencia . '%';
}
if ($destino !== '') {
    $where[] = 's.destino LIKE :destino';
    $params[':destino'] = '%' . $destino . '%';
}
if ($dataIni !== '') {
    $where[] = 'DATE(s.data_saida) >= :ini';
    $params[':ini'] = $dataIni;
}
if ($dataFim !== '') {
    $where[] = 'DATE(s.data_saida) <= :fim';
    $params[':fim'] = $dataFim;
}
if ($busca !== '') {
    $where[] = '(s.assunto LIKE :q OR s.descricao LIKE :q2)';
    $params[':q'] = '%' . $busca . '%';
    $params[':q2'] = '%' . $busca . '%';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$count = db()->prepare("SELECT COUNT(*) FROM saida_expedientes s LEFT JOIN expedientes e ON e.idexpediente = s.idexpediente {$whereSql}");
$count->execute($params);
$total = (int) $count->fetchColumn();

$porPagina = max(5, (int) config_get('itens_por_pagina', '15'));
$p = pagination($total, $porPagina, get_int('pagina', 1), [
    'referencia' => $referencia, 'destino' => $destino,
    'data_inicio' => $dataIni, 'data_fim' => $dataFim, 'q' => $busca,
]);

$sql = "SELECT s.idsaida, s.referencia, s.destino, s.data_saida, s.assunto, s.descricao,
               s.scan_documento, s.usuario, e.referencia AS expediente_ref, e.idexpediente
        FROM saida_expedientes s
        LEFT JOIN expedientes e ON e.idexpediente = s.idexpediente
        {$whereSql}
        ORDER BY s.data_saida DESC, s.idsaida DESC
        LIMIT {$p['por_pagina']} OFFSET {$p['offset']}";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$linhas = $stmt->fetchAll();

$temFiltros = tem_filtros([$referencia, $destino, $dataIni, $dataFim, $busca]);

$page_title = 'Saídas de Expedientes';
$page_subtitle = 'Registo de encaminhamento e expedição de documentos';
$active = 'saidas.php';
$page_actions = can('criar_saidas')
    ? '<a class="btn btn-primary btn-sm" href="' . e(url('/saida_form.php')) . '">' . svg_icon('plus', 'icon icon-sm') . ' Nova saída</a>'
    : '';
require __DIR__ . '/includes/header.php';
?>

<div class="filters">
    <form method="get" action="<?= e(url('/saidas.php')) ?>">
        <div class="field">
            <label for="f_ref">Referência</label>
            <input type="search" id="f_ref" name="referencia" value="<?= e($referencia) ?>" placeholder="Saída ou expediente">
        </div>
        <div class="field">
            <label for="f_dest">Destino</label>
            <input type="search" id="f_dest" name="destino" value="<?= e($destino) ?>" placeholder="Destinatário">
        </div>
        <div class="field">
            <label for="f_ini">Data inicial</label>
            <input type="date" id="f_ini" name="data_inicio" value="<?= e($dataIni) ?>">
        </div>
        <div class="field">
            <label for="f_fim">Data final</label>
            <input type="date" id="f_fim" name="data_fim" value="<?= e($dataFim) ?>">
        </div>
        <div class="field">
            <label for="f_q">Assunto</label>
            <input type="search" id="f_q" name="q" value="<?= e($busca) ?>" placeholder="Pesquisar...">
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary"><?= svg_icon('search', 'icon icon-sm') ?> Filtrar</button>
            <a href="<?= e(url('/saidas.php')) ?>" class="btn btn-outline">Limpar</a>
        </div>
    </form>
    <?php if ($temFiltros): ?>
        <div class="filters-summary">
            <?php foreach (['Referência' => $referencia, 'Destino' => $destino, 'De' => $dataIni, 'Até' => $dataFim, 'Busca' => $busca] as $label => $val): ?>
                <?php if ($val !== ''): ?><span class="chip"><?= e($label) ?>: <?= e($label === 'De' || $label === 'Até' ? data_pt($val) : $val) ?></span><?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-head">
        <div><h3>Registos de saída</h3><span class="sub"><?= numero_pt($total) ?> resultado(s)</span></div>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>Referência</th>
                    <th>Expediente</th>
                    <th>Destino</th>
                    <th>Assunto</th>
                    <th>Data de saída</th>
                    <th>Registado por</th>
                    <th class="text-right">Acções</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$linhas): ?>
                <tr><td colspan="7">
                    <div class="empty">
                        <?= svg_icon('outbox') ?>
                        <h4>Nenhuma saída encontrada</h4>
                        <p><?= $temFiltros ? 'Ajuste os filtros de pesquisa.' : 'Ainda não existem saídas registadas.' ?></p>
                    </div>
                </td></tr>
            <?php else: ?>
                <?php foreach ($linhas as $l): ?>
                    <tr>
                        <td class="cell-mono cell-strong"><?= e($l['referencia']) ?></td>
                        <td>
                            <?php if ($l['expediente_ref']): ?>
                                <a href="<?= e(url('/expediente_view.php?id=' . (int) $l['idexpediente'])) ?>"><?= e($l['expediente_ref']) ?></a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($l['destino']) ?></td>
                        <td><?= e($l['assunto']) ?><span class="cell-sub"><?= e($l['descricao']) ?></span></td>
                        <td class="nowrap"><?= e(data_pt($l['data_saida'])) ?></td>
                        <td><?= e($l['usuario']) ?></td>
                        <td>
                            <div class="actions">
                                <?php if ($l['scan_documento'] !== null && $l['scan_documento'] !== ''): ?>
                                    <a class="btn-icon" target="_blank" href="<?= e(url('/documento.php?tipo=saida&id=' . (int) $l['idsaida'])) ?>" title="Ver documento"><?= svg_icon('file') ?></a>
                                <?php endif; ?>
                                <?php if (can('editar_saidas')): ?>
                                    <a class="btn-icon" href="<?= e(url('/saida_form.php?id=' . (int) $l['idsaida'])) ?>" title="Editar"><?= svg_icon('edit') ?></a>
                                <?php endif; ?>
                                <?php if (can('apagar_saidas')): ?>
                                    <form method="post" action="<?= e(url('/saida_delete.php')) ?>" style="display:inline" data-confirm="Eliminar a saída <?= e($l['referencia']) ?>?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $l['idsaida'] ?>">
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
