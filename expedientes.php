<?php
/**
 * Listagem de expedientes com filtragem por período, referência e estado.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_can('ver_expedientes');

$referencia = get('referencia');
$processo   = get('processo');
$status     = get('status');
$dataIni    = get('data_inicio');
$dataFim    = get('data_fim');
$busca      = get('q');

$status = array_key_exists($status, STATUS_EXPEDIENTE) ? $status : '';

if ($dataIni !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataIni)) { $dataIni = ''; }
if ($dataFim !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim)) { $dataFim = ''; }
if ($dataIni !== '' && $dataFim !== '' && $dataIni > $dataFim) {
    flash_warning('A data inicial não pode ser posterior à data final. Os filtros de data foram ignorados.');
    $dataIni = $dataFim = '';
}

$where = [];
$params = [];

if ($referencia !== '') {
    $where[] = '(e.referencia LIKE :referencia OR e.nrm_processo LIKE :referencia2)';
    $params[':referencia'] = '%' . $referencia . '%';
    $params[':referencia2'] = '%' . $referencia . '%';
}
if ($processo !== '') {
    $where[] = 'e.nrm_processo LIKE :processo';
    $params[':processo'] = '%' . $processo . '%';
}
if ($status !== '') {
    $where[] = 'e.status = :status';
    $params[':status'] = $status;
}
if ($dataIni !== '') {
    $where[] = 'DATE(e.data_entrada) >= :data_ini';
    $params[':data_ini'] = $dataIni;
}
if ($dataFim !== '') {
    $where[] = 'DATE(e.data_entrada) <= :data_fim';
    $params[':data_fim'] = $dataFim;
}
if ($busca !== '') {
    $where[] = '(e.Assunto LIKE :busca OR e.descricao LIKE :busca2 OR e.proveniencia LIKE :busca3)';
    $params[':busca'] = '%' . $busca . '%';
    $params[':busca2'] = '%' . $busca . '%';
    $params[':busca3'] = '%' . $busca . '%';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = db()->prepare("SELECT COUNT(*) FROM expedientes e {$whereSql}");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$porPagina = max(5, (int) config_get('itens_por_pagina', '15'));
$p = pagination($total, $porPagina, get_int('pagina', 1), [
    'referencia' => $referencia,
    'processo'   => $processo,
    'status'     => $status,
    'data_inicio'=> $dataIni,
    'data_fim'   => $dataFim,
    'q'          => $busca,
]);

$sql = "SELECT e.idexpediente, e.referencia, e.nrm_processo, e.Assunto, e.proveniencia,
               e.data_entrada, e.status, e.descricao, e.scan_documento,
               u.nume AS registado_por
        FROM expedientes e
        LEFT JOIN usuarios u ON u.idusuario = e.idusuario
        {$whereSql}
        ORDER BY e.data_entrada DESC, e.idexpediente DESC
        LIMIT {$p['por_pagina']} OFFSET {$p['offset']}";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$linhas = $stmt->fetchAll();

$temFiltros = tem_filtros([$referencia, $processo, $status, $dataIni, $dataFim, $busca]);

$page_title = 'Entrada de Expedientes';
$page_subtitle = 'Entradas de expediente, triagem e estado de tramitação';
$active = 'expedientes.php';
$page_actions = can('criar_expedientes')
    ? '<a class="btn btn-primary btn-sm" href="' . e(url('/expediente_form.php')) . '">' . svg_icon('plus', 'icon icon-sm') . ' Novo expediente</a>'
    : '';
require __DIR__ . '/includes/header.php';
?>

<div class="filters">
    <form method="get" action="<?= e(url('/expedientes.php')) ?>">
        <div class="field">
            <label for="f_ref">Nº / Referência</label>
            <input type="search" id="f_ref" name="referencia" value="<?= e($referencia) ?>" placeholder="Ex.: REF-2026/001">
        </div>
        <div class="field">
            <label for="f_proc">Nº do processo</label>
            <input type="search" id="f_proc" name="processo" value="<?= e($processo) ?>" placeholder="Ex.: 123/2026">
        </div>
        <div class="field">
            <label for="f_status">Estado</label>
            <select id="f_status" name="status" data-auto-submit>
                <option value="">Todos</option>
                <?php foreach (STATUS_EXPEDIENTE as $k => $label): ?>
                    <option value="<?= e($k) ?>"<?= $status === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
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
            <label for="f_q">Assunto / Proveniência</label>
            <input type="search" id="f_q" name="q" value="<?= e($busca) ?>" placeholder="Pesquisar...">
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary"><?= svg_icon('search', 'icon icon-sm') ?> Filtrar</button>
            <a href="<?= e(url('/expedientes.php')) ?>" class="btn btn-outline">Limpar</a>
        </div>
    </form>

    <?php if ($temFiltros): ?>
        <div class="filters-summary">
            <?php if ($referencia !== ''): ?><span class="chip">Referência: <?= e($referencia) ?><a href="?<?= e(http_build_query(array_filter(['processo'=>$processo,'status'=>$status,'data_inicio'=>$dataIni,'data_fim'=>$dataFim,'q'=>$busca]))) ?>" aria-label="Remover"><?= svg_icon('x') ?></a></span><?php endif; ?>
            <?php if ($processo !== ''): ?><span class="chip">Processo: <?= e($processo) ?><a href="?<?= e(http_build_query(array_filter(['referencia'=>$referencia,'status'=>$status,'data_inicio'=>$dataIni,'data_fim'=>$dataFim,'q'=>$busca]))) ?>" aria-label="Remover"><?= svg_icon('x') ?></a></span><?php endif; ?>
            <?php if ($status !== ''): ?><span class="chip">Estado: <?= e(STATUS_EXPEDIENTE[$status]) ?><a href="?<?= e(http_build_query(array_filter(['referencia'=>$referencia,'processo'=>$processo,'data_inicio'=>$dataIni,'data_fim'=>$dataFim,'q'=>$busca]))) ?>" aria-label="Remover"><?= svg_icon('x') ?></a></span><?php endif; ?>
            <?php if ($dataIni !== ''): ?><span class="chip">De: <?= e(data_pt($dataIni)) ?><a href="?<?= e(http_build_query(array_filter(['referencia'=>$referencia,'processo'=>$processo,'status'=>$status,'data_fim'=>$dataFim,'q'=>$busca]))) ?>" aria-label="Remover"><?= svg_icon('x') ?></a></span><?php endif; ?>
            <?php if ($dataFim !== ''): ?><span class="chip">Até: <?= e(data_pt($dataFim)) ?><a href="?<?= e(http_build_query(array_filter(['referencia'=>$referencia,'processo'=>$processo,'status'=>$status,'data_inicio'=>$dataIni,'q'=>$busca]))) ?>" aria-label="Remover"><?= svg_icon('x') ?></a></span><?php endif; ?>
            <?php if ($busca !== ''): ?><span class="chip">Busca: <?= e($busca) ?><a href="?<?= e(http_build_query(array_filter(['referencia'=>$referencia,'processo'=>$processo,'status'=>$status,'data_inicio'=>$dataIni,'data_fim'=>$dataFim]))) ?>" aria-label="Remover"><?= svg_icon('x') ?></a></span><?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-head">
        <div>
            <h3>Registos de expediente</h3>
            <span class="sub"><?= numero_pt($total) ?> resultado(s) encontrado(s)</span>
        </div>
    </div>

    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>Referência</th>
                    <th>Nº Processo</th>
                    <th>Assunto</th>
                    <th>Proveniência</th>
                    <th>Entrada</th>
                    <th>Estado</th>
                    <th>Registado por</th>
                    <th class="text-right">Acções</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$linhas): ?>
                <tr><td colspan="8">
                    <div class="empty">
                        <?= svg_icon('inbox') ?>
                        <h4>Nenhum expediente encontrado</h4>
                        <p><?= $temFiltros ? 'Ajuste os filtros de pesquisa e tente novamente.' : 'Ainda não existem expedientes registados.' ?></p>
                        <?php if (!$temFiltros && can('criar_expedientes')): ?>
                            <a class="btn btn-primary btn-sm" href="<?= e(url('/expediente_form.php')) ?>"><?= svg_icon('plus', 'icon icon-sm') ?> Registar expediente</a>
                        <?php endif; ?>
                    </div>
                </td></tr>
            <?php else: ?>
                <?php foreach ($linhas as $l): ?>
                    <tr class="is-clickable" data-href="<?= e(url('/expediente_view.php?id=' . (int) $l['idexpediente'])) ?>">
                        <td class="cell-mono cell-strong"><?= e($l['referencia']) ?></td>
                        <td class="cell-mono"><?= e(processo_rotulo($l['nrm_processo'] ?? null)) ?></td>
                        <td>
                            <?= e($l['Assunto']) ?>
                            <span class="cell-sub"><?= e($l['descricao']) ?></span>
                        </td>
                        <td><?= e($l['proveniencia']) ?></td>
                        <td class="nowrap"><?= e(data_pt($l['data_entrada'])) ?></td>
                        <td><?= status_badge($l['status']) ?></td>
                        <td><?= e($l['registado_por'] ?? '—') ?></td>
                        <td>
                            <div class="actions">
                                <a class="btn-icon" href="<?= e(url('/expediente_view.php?id=' . (int) $l['idexpediente'])) ?>" title="Ver detalhes"><?= svg_icon('eye') ?></a>
                                <?php if (can('editar_expedientes')): ?>
                                    <a class="btn-icon" href="<?= e(url('/expediente_form.php?id=' . (int) $l['idexpediente'])) ?>" title="Editar"><?= svg_icon('edit') ?></a>
                                <?php endif; ?>
                                <?php if (can('apagar_expedientes')): ?>
                                    <form method="post" action="<?= e(url('/expediente_delete.php')) ?>" style="display:inline" data-confirm="Eliminar o expediente <?= e($l['referencia']) ?>? Esta acção não pode ser desfeita.">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $l['idexpediente'] ?>">
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
