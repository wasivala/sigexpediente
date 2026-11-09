<?php
/**
 * Gestão de utilizadores (administração).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_can('gerir_usuarios');

$nome = get('nome');
$email = get('email');
$grupo = get('grupo');

$where = [];
$params = [];
if ($nome !== '') {
    $where[] = 'nume LIKE :nome';
    $params[':nome'] = '%' . $nome . '%';
}
if ($email !== '') {
    $where[] = 'email LIKE :email';
    $params[':email'] = '%' . $email . '%';
}
if ($grupo !== '' && array_key_exists($grupo, GRUPOS_ACESSO)) {
    $where[] = 'grupo_acesso = :grupo';
    $params[':grupo'] = $grupo;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$count = db()->prepare("SELECT COUNT(*) FROM usuarios {$whereSql}");
$count->execute($params);
$total = (int) $count->fetchColumn();

$porPagina = max(5, (int) config_get('itens_por_pagina', '15'));
$p = pagination($total, $porPagina, get_int('pagina', 1), ['nome' => $nome, 'email' => $email, 'grupo' => $grupo]);

$stmt = db()->prepare("SELECT idusuario, nume, email, grupo_acesso, estado, criado_em FROM usuarios {$whereSql} ORDER BY nume ASC LIMIT {$p['por_pagina']} OFFSET {$p['offset']}");
$stmt->execute($params);
$linhas = $stmt->fetchAll();

$temFiltros = tem_filtros([$nome, $email, $grupo]);

$page_title = 'Utilizadores';
$page_subtitle = 'Contas de acesso ao sistema e respectivos perfis';
$active = 'usuarios.php';
$page_actions = '<a class="btn btn-primary btn-sm" href="' . e(url('/usuario_form.php')) . '">' . svg_icon('user-plus', 'icon icon-sm') . ' Novo utilizador</a>';
require __DIR__ . '/includes/header.php';
?>

<div class="note mb-2">
    <?= svg_icon('shield') ?>
    <span>As palavras-passe são guardadas exclusivamente na forma de <strong>hash bcrypt</strong>. Nem os administradores conseguem ler a palavra-passe original.</span>
</div>

<div class="filters">
    <form method="get" action="<?= e(url('/usuarios.php')) ?>">
        <div class="field">
            <label for="f_nome">Nome</label>
            <input type="search" id="f_nome" name="nome" value="<?= e($nome) ?>" placeholder="Nome do utilizador">
        </div>
        <div class="field">
            <label for="f_email">E-mail</label>
            <input type="search" id="f_email" name="email" value="<?= e($email) ?>" placeholder="nome@instituicao">
        </div>
        <div class="field">
            <label for="f_grupo">Grupo</label>
            <select id="f_grupo" name="grupo" data-auto-submit>
                <option value="">Todos</option>
                <?php foreach (GRUPOS_ACESSO as $k => $label): ?>
                    <option value="<?= e($k) ?>"<?= $grupo === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary"><?= svg_icon('search', 'icon icon-sm') ?> Filtrar</button>
            <a href="<?= e(url('/usuarios.php')) ?>" class="btn btn-outline">Limpar</a>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-head">
        <div><h3>Contas registadas</h3><span class="sub"><?= numero_pt($total) ?> resultado(s)</span></div>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Grupo de acesso</th>
                    <th>Estado</th>
                    <th>Criado em</th>
                    <th class="text-right">Acções</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$linhas): ?>
                <tr><td colspan="6"><div class="empty"><?= svg_icon('user') ?><h4>Nenhum utilizador encontrado</h4><p>Ajuste os filtros ou crie uma nova conta.</p></div></td></tr>
            <?php else: ?>
                <?php foreach ($linhas as $l): ?>
                    <tr>
                        <td>
                            <div class="flex-center">
                                <span class="avatar avatar-sm"><?= e(iniciais((string) $l['nume'])) ?></span>
                                <span class="cell-strong"><?= e($l['nume']) ?></span>
                            </div>
                        </td>
                        <td><?= e($l['email']) ?></td>
                        <td><span class="badge badge-<?= strtolower($l['grupo_acesso']) === 'administrador' ? 'gold' : 'neutral' ?>"><?= e($l['grupo_acesso']) ?></span></td>
                        <td><span class="badge badge-<?= strtolower($l['estado']) === 'ativo' ? 'ativo' : 'inativo' ?>"><?= e($l['estado']) ?></span></td>
                        <td class="nowrap"><?= e(data_pt((string) $l['criado_em'])) ?></td>
                        <td>
                            <div class="actions">
                                <a class="btn-icon" href="<?= e(url('/usuario_form.php?id=' . (int) $l['idusuario'])) ?>" title="Editar"><?= svg_icon('edit') ?></a>
                                <?php if ((int) $l['idusuario'] !== (int) current_user()['idusuario']): ?>
                                    <form method="post" action="<?= e(url('/usuario_delete.php')) ?>" style="display:inline" data-confirm="Eliminar a conta de <?= e($l['nume']) ?>?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $l['idusuario'] ?>">
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

<div class="card mt-2">
    <div class="card-head"><h3>Perfis de acesso</h3></div>
    <div class="card-body">
        <div class="grid grid-4" style="gap:14px;">
            <div>
                <span class="badge badge-gold">Administrador</span>
                <p class="text-sm text-muted mt-1 mb-0">Acesso completo: gestão, configurações, utilizadores, backup e histórico.</p>
            </div>
            <div>
                <span class="badge badge-neutral">Gestor</span>
                <p class="text-sm text-muted mt-1 mb-0">Gestão de expedientes, saídas, requeridos, beneficiários e relatórios.</p>
            </div>
            <div>
                <span class="badge badge-neutral">Operador</span>
                <p class="text-sm text-muted mt-1 mb-0">Registo e actualização de expedientes, saídas e entidades associadas.</p>
            </div>
            <div>
                <span class="badge badge-neutral">Consulta</span>
                <p class="text-sm text-muted mt-1 mb-0">Apenas leitura e emissão de relatórios.</p>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
