<?php
/**
 * Listagem de instituições (consulta por nome).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_can('ver_instituicoes');

$nome = get('nome');
$tipo = get('tipo');
$estado = get('estado');

$where = [];
$params = [];
if ($nome !== '') {
    $where[] = 'nome LIKE :nome';
    $params[':nome'] = '%' . $nome . '%';
}
if ($tipo !== '') {
    $where[] = 'tipo LIKE :tipo';
    $params[':tipo'] = '%' . $tipo . '%';
}
if ($estado !== '' && in_array($estado, ['Ativo', 'Inativo'], true)) {
    $where[] = 'estado = :estado';
    $params[':estado'] = $estado;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$count = db()->prepare("SELECT COUNT(*) FROM instituicao {$whereSql}");
$count->execute($params);
$total = (int) $count->fetchColumn();

$porPagina = max(5, (int) config_get('itens_por_pagina', '15'));
$p = pagination($total, $porPagina, get_int('pagina', 1), ['nome' => $nome, 'tipo' => $tipo, 'estado' => $estado]);

$stmt = db()->prepare("SELECT * FROM instituicao {$whereSql} ORDER BY nome ASC LIMIT {$p['por_pagina']} OFFSET {$p['offset']}");
$stmt->execute($params);
$linhas = $stmt->fetchAll();

$temFiltros = tem_filtros([$nome, $tipo, $estado]);

$page_title = 'Instituições';
$page_subtitle = 'Entidades que intervêm no circuito de expediente';
$active = 'instituicoes.php';
$page_actions = can('criar_instituicoes')
    ? '<a class="btn btn-primary btn-sm" href="' . e(url('/instituicao_form.php')) . '">' . svg_icon('plus', 'icon icon-sm') . ' Nova instituição</a>'
    : '';
require __DIR__ . '/includes/header.php';
?>

<div class="filters">
    <form method="get" action="<?= e(url('/instituicoes.php')) ?>">
        <div class="field">
            <label for="f_nome">Nome</label>
            <input type="search" id="f_nome" name="nome" value="<?= e($nome) ?>" placeholder="Nome da instituição">
        </div>
        <div class="field">
            <label for="f_tipo">Tipo</label>
            <input type="search" id="f_tipo" name="tipo" value="<?= e($tipo) ?>" placeholder="Ex.: Departamento">
        </div>
        <div class="field">
            <label for="f_estado">Estado</label>
            <select id="f_estado" name="estado" data-auto-submit>
                <option value="">Todos</option>
                <option value="Ativo"<?= $estado === 'Ativo' ? ' selected' : '' ?>>Ativo</option>
                <option value="Inativo"<?= $estado === 'Inativo' ? ' selected' : '' ?>>Inativo</option>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary"><?= svg_icon('search', 'icon icon-sm') ?> Filtrar</button>
            <a href="<?= e(url('/instituicoes.php')) ?>" class="btn btn-outline">Limpar</a>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-head">
        <div><h3>Instituições registadas</h3><span class="sub"><?= numero_pt($total) ?> resultado(s)</span></div>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Tipo</th>
                    <th>Estado</th>
                    <th class="text-right">Acções</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$linhas): ?>
                <tr><td colspan="4">
                    <div class="empty">
                        <?= svg_icon('building') ?>
                        <h4>Nenhuma instituição encontrada</h4>
                        <p><?= $temFiltros ? 'Ajuste os filtros de pesquisa.' : 'Ainda não existem instituições registadas.' ?></p>
                    </div>
                </td></tr>
            <?php else: ?>
                <?php foreach ($linhas as $l): ?>
                    <tr>
                        <td class="cell-strong"><?= e($l['nome']) ?></td>
                        <td><?= e($l['tipo']) ?></td>
                        <td><span class="badge badge-<?= strtolower($l['estado']) === 'ativo' ? 'ativo' : 'inativo' ?>"><?= e($l['estado']) ?></span></td>
                        <td>
                            <div class="actions">
                                <?php if (can('editar_instituicoes')): ?>
                                    <a class="btn-icon" href="<?= e(url('/instituicao_form.php?id=' . (int) $l['idinstituicao'])) ?>" title="Editar"><?= svg_icon('edit') ?></a>
                                <?php endif; ?>
                                <?php if (can('apagar_instituicoes')): ?>
                                    <form method="post" action="<?= e(url('/instituicao_delete.php')) ?>" style="display:inline" data-confirm="Eliminar a instituição <?= e($l['nome']) ?>?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $l['idinstituicao'] ?>">
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
