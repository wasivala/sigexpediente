<?php
/**
 * Histórico de actividades do sistema.
 * Filtros: período, utilizador e acção.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_can('ver_logs');

$dataIni = get('data_inicio');
$dataFim = get('data_fim');
$usuario = get('usuario');
$accao   = get('accao');

if ($dataIni !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataIni)) { $dataIni = ''; }
if ($dataFim !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim)) { $dataFim = ''; }
if ($dataIni !== '' && $dataFim !== '' && $dataIni > $dataFim) {
    flash_warning('A data inicial não pode ser posterior à data final. Os filtros de data foram ignorados.');
    $dataIni = $dataFim = '';
}

$where = [];
$params = [];

if ($usuario !== '') {
    $where[] = 'l.usuario LIKE :usuario';
    $params[':usuario'] = '%' . $usuario . '%';
}
if ($accao !== '') {
    $where[] = 'l.accao LIKE :accao';
    $params[':accao'] = '%' . $accao . '%';
}
if ($dataIni !== '') {
    $where[] = 'DATE(l.data) >= :data_ini';
    $params[':data_ini'] = $dataIni;
}
if ($dataFim !== '') {
    $where[] = 'DATE(l.data) <= :data_fim';
    $params[':data_fim'] = $dataFim;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = db()->prepare("SELECT COUNT(*) FROM logs l {$whereSql}");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$porPagina = max(5, (int) config_get('itens_por_pagina', '15'));
$p = pagination($total, $porPagina, get_int('pagina', 1), [
    'data_inicio' => $dataIni,
    'data_fim'    => $dataFim,
    'usuario'     => $usuario,
    'accao'       => $accao,
]);

$stmt = db()->prepare(
    "SELECT l.idlog, l.usuario, l.accao, l.data
     FROM logs l
     {$whereSql}
     ORDER BY l.data DESC, l.idlog DESC
     LIMIT {$p['por_pagina']} OFFSET {$p['offset']}"
);
$stmt->execute($params);
$linhas = $stmt->fetchAll();

$temFiltros = tem_filtros([$usuario, $accao, $dataIni, $dataFim]);

$page_title = 'Histórico / Logs';
$page_subtitle = 'Registo cronológico das operações efectuadas no sistema';
$active = 'logs.php';
require __DIR__ . '/includes/header.php';
?>

<div class="filters">
    <form method="get" action="<?= e(url('/logs.php')) ?>">
        <div class="field">
            <label for="f_usuario">Utilizador</label>
            <input type="search" id="f_usuario" name="usuario" value="<?= e($usuario) ?>" placeholder="Nome do utilizador">
        </div>
        <div class="field">
            <label for="f_accao">Acção</label>
            <input type="search" id="f_accao" name="accao" value="<?= e($accao) ?>" placeholder="Ex.: criado, actualizado...">
        </div>
        <div class="field">
            <label for="f_ini">Data inicial</label>
            <input type="date" id="f_ini" name="data_inicio" value="<?= e($dataIni) ?>">
        </div>
        <div class="field">
            <label for="f_fim">Data final</label>
            <input type="date" id="f_fim" name="data_fim" value="<?= e($dataFim) ?>">
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary"><?= svg_icon('search', 'icon icon-sm') ?> Filtrar</button>
            <a href="<?= e(url('/logs.php')) ?>" class="btn btn-outline">Limpar</a>
        </div>
    </form>

    <?php if ($temFiltros): ?>
        <div class="filters-summary">
            <?php if ($usuario !== ''): ?><span class="chip">Utilizador: <?= e($usuario) ?><a href="?<?= e(http_build_query(array_filter(['accao' => $accao, 'data_inicio' => $dataIni, 'data_fim' => $dataFim]))) ?>" aria-label="Remover"><?= svg_icon('x') ?></a></span><?php endif; ?>
            <?php if ($accao !== ''): ?><span class="chip">Acção: <?= e($accao) ?><a href="?<?= e(http_build_query(array_filter(['usuario' => $usuario, 'data_inicio' => $dataIni, 'data_fim' => $dataFim]))) ?>" aria-label="Remover"><?= svg_icon('x') ?></a></span><?php endif; ?>
            <?php if ($dataIni !== ''): ?><span class="chip">De: <?= e(data_pt($dataIni)) ?><a href="?<?= e(http_build_query(array_filter(['usuario' => $usuario, 'accao' => $accao, 'data_fim' => $dataFim]))) ?>" aria-label="Remover"><?= svg_icon('x') ?></a></span><?php endif; ?>
            <?php if ($dataFim !== ''): ?><span class="chip">Até: <?= e(data_pt($dataFim)) ?><a href="?<?= e(http_build_query(array_filter(['usuario' => $usuario, 'accao' => $accao, 'data_inicio' => $dataIni]))) ?>" aria-label="Remover"><?= svg_icon('x') ?></a></span><?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-head">
        <div>
            <h3>Actividades registadas</h3>
            <span class="sub"><?= numero_pt($total) ?> registo(s) encontrado(s)</span>
        </div>
    </div>

    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>Data e hora</th>
                    <th>Utilizador</th>
                    <th>Acção</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$linhas): ?>
                <tr><td colspan="3">
                    <div class="empty">
                        <?= svg_icon('history') ?>
                        <h4>Nenhuma actividade encontrada</h4>
                        <p><?= $temFiltros ? 'Ajuste os filtros de pesquisa e tente novamente.' : 'Ainda não existem registos no histórico.' ?></p>
                    </div>
                </td></tr>
            <?php else: ?>
                <?php foreach ($linhas as $l): ?>
                    <tr>
                        <td class="nowrap cell-mono"><?= e(data_hora_pt((string) $l['data'])) ?></td>
                        <td>
                            <div class="flex-center">
                                <span class="avatar avatar-sm"><?= e(iniciais((string) $l['usuario'])) ?></span>
                                <span class="cell-strong"><?= e($l['usuario']) ?></span>
                            </div>
                        </td>
                        <td><?= e($l['accao']) ?></td>
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

<div class="note mt-2">
    <?= svg_icon('shield') ?>
    <span>O histórico é gerado automaticamente e não pode ser alterado pela interface. Os registos de início e fim de sessão, criação, edição e eliminação de dados são sempre guardados.</span>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
