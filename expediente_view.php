<?php
/**
 * Ficha de expediente: dados, requeridos, beneficiários, saídas e histórico.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_can('ver_expedientes');

$id = get_int('id', 0);
$stmt = db()->prepare(
    'SELECT e.*, u.nume AS registado_por
     FROM expedientes e
     LEFT JOIN usuarios u ON u.idusuario = e.idusuario
     WHERE e.idexpediente = :id LIMIT 1'
);
$stmt->execute([':id' => $id]);
$exp = $stmt->fetch();

if (!$exp) {
    flash_error('Expediente não encontrado.');
    redirect('/expedientes.php');
}

$stmt = db()->prepare('SELECT * FROM requeridos WHERE idexpediente = :id ORDER BY nome');
$stmt->execute([':id' => $id]);
$requeridos = $stmt->fetchAll();

$stmt = db()->prepare('SELECT * FROM beneficiarios WHERE idexpediente = :id ORDER BY nome');
$stmt->execute([':id' => $id]);
$beneficiarios = $stmt->fetchAll();

$stmt = db()->prepare('SELECT * FROM saida_expedientes WHERE idexpediente = :id ORDER BY data_saida DESC');
$stmt->execute([':id' => $id]);
$saidas = $stmt->fetchAll();

$stmt = db()->prepare("SELECT * FROM logs WHERE idexpediente = :id ORDER BY data DESC, idlog DESC LIMIT 20");
$stmt->execute([':id' => $id]);
$historico = $stmt->fetchAll();

$acoes = [];
if (can('editar_expedientes')) {
    $acoes[] = '<a class="btn btn-outline btn-sm" href="' . e(url('/expediente_form.php?id=' . $id)) . '">' . svg_icon('edit', 'icon icon-sm') . ' Editar</a>';
}
if (can('ver_relatorios')) {
    $acoes[] = '<a class="btn btn-gold btn-sm" target="_blank" href="' . e(url('/relatorio_individual.php?expediente=' . $id)) . '">' . svg_icon('report', 'icon icon-sm') . ' Relatório individual</a>';
}
if (can('apagar_expedientes')) {
    $acoes[] = '<form method="post" action="' . e(url('/expediente_delete.php')) . '" style="display:inline" data-confirm="Eliminar este expediente? Esta acção não pode ser desfeita.">'
        . csrf_field() . '<input type="hidden" name="id" value="' . $id . '">'
        . '<button type="submit" class="btn btn-danger-soft btn-sm">' . svg_icon('trash', 'icon icon-sm') . ' Eliminar</button></form>';
}

$page_title = 'Expediente ' . $exp['referencia'];
$page_subtitle = (processo_e_interno($exp['nrm_processo'] ?? null)
        ? 'Expediente interno'
        : 'Nº do processo ' . $exp['nrm_processo'])
    . ' · Entrada em ' . data_hora_pt((string) $exp['data_entrada']);
$active = 'expedientes.php';
$page_actions = implode(' ', $acoes);
require __DIR__ . '/includes/header.php';
?>

<div class="grid" style="grid-template-columns: 1.5fr 1fr;">
    <div class="card">
        <div class="card-head">
            <div class="flex-center">
                <h3><?= e($exp['referencia']) ?></h3>
                <?= status_badge($exp['status']) ?>
            </div>
            <span class="text-sm text-muted">Registado por <?= e($exp['registado_por'] ?? '—') ?></span>
        </div>
        <div class="card-body">
            <div class="detail-grid">
                <div class="detail-item"><div class="dt">Número do processo</div><div class="dd"><?= e(processo_rotulo($exp['nrm_processo'] ?? null)) ?></div></div>
                <div class="detail-item"><div class="dt">Referência</div><div class="dd"><?= e($exp['referencia']) ?></div></div>
                <div class="detail-item"><div class="dt">Assunto</div><div class="dd"><?= e($exp['Assunto']) ?></div></div>
                <div class="detail-item"><div class="dt">Proveniência</div><div class="dd"><?= e($exp['proveniencia']) ?></div></div>
                <div class="detail-item"><div class="dt">Data de entrada</div><div class="dd"><?= e(data_hora_pt((string) $exp['data_entrada'])) ?></div></div>
                <div class="detail-item"><div class="dt">Estado</div><div class="dd"><?= status_badge($exp['status']) ?></div></div>
                <div class="detail-item" style="grid-column:1/-1"><div class="dt">Descrição</div><div class="dd"><?= nl2br(e($exp['descricao'])) ?></div></div>
            </div>

            <?php if ($exp['scan_documento'] !== null && $exp['scan_documento'] !== ''): ?>
                <div class="divider"></div>
                <a class="btn btn-outline btn-sm" target="_blank" href="<?= e(url('/documento.php?tipo=expediente&id=' . $id)) ?>">
                    <?= svg_icon('file', 'icon icon-sm') ?> Abrir documento digitalizado
                </a>
            <?php else: ?>
                <p class="text-sm text-muted mt-2 mb-0">Sem documento digitalizado associado.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h3>Resumo</h3></div>
        <div class="card-body">
            <div class="grid grid-2" style="gap:10px;">
                <div class="stat" style="padding:14px;">
                    <div class="stat-icon violet"><?= svg_icon('users') ?></div>
                    <div class="stat-body"><div class="stat-value" style="font-size:1.3rem;"><?= count($requeridos) ?></div><div class="stat-label" style="font-size:.64rem;">Requeridos</div></div>
                </div>
                <div class="stat" style="padding:14px;">
                    <div class="stat-icon blue"><?= svg_icon('handshake') ?></div>
                    <div class="stat-body"><div class="stat-value" style="font-size:1.3rem;"><?= count($beneficiarios) ?></div><div class="stat-label" style="font-size:.64rem;">Beneficiários</div></div>
                </div>
                <div class="stat" style="padding:14px;">
                    <div class="stat-icon gold"><?= svg_icon('outbox') ?></div>
                    <div class="stat-body"><div class="stat-value" style="font-size:1.3rem;"><?= count($saidas) ?></div><div class="stat-label" style="font-size:.64rem;">Saídas</div></div>
                </div>
                <div class="stat" style="padding:14px;">
                    <div class="stat-icon amber"><?= svg_icon('history') ?></div>
                    <div class="stat-body"><div class="stat-value" style="font-size:1.3rem;"><?= count($historico) ?></div><div class="stat-label" style="font-size:.64rem;">Acções</div></div>
                </div>
            </div>

            <div class="divider"></div>
            <?php if (can('criar_requeridos')): ?>
                <a class="btn btn-outline btn-sm btn-block mb-2" href="<?= e(url('/requerido_form.php?expediente=' . $id)) ?>"><?= svg_icon('user-plus', 'icon icon-sm') ?> Adicionar requerido</a>
            <?php endif; ?>
            <?php if (can('criar_beneficiarios')): ?>
                <a class="btn btn-outline btn-sm btn-block mb-2" href="<?= e(url('/beneficiario_form.php?expediente=' . $id)) ?>"><?= svg_icon('handshake', 'icon icon-sm') ?> Adicionar beneficiário</a>
            <?php endif; ?>
            <?php if (can('criar_saidas')): ?>
                <a class="btn btn-outline btn-sm btn-block" href="<?= e(url('/saida_form.php?expediente=' . $id)) ?>"><?= svg_icon('outbox', 'icon icon-sm') ?> Registar saída</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card mt-2">
    <div class="card-head">
        <div><h3>Requeridos</h3><span class="sub">Pessoas associadas a este expediente</span></div>
        <?php if (can('criar_requeridos')): ?>
            <a class="btn btn-outline btn-sm" href="<?= e(url('/requerido_form.php?expediente=' . $id)) ?>"><?= svg_icon('plus', 'icon icon-sm') ?> Adicionar</a>
        <?php endif; ?>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Nome</th><th>Patente / Ramo</th><th>Unidade</th><th>Função</th><th>Contacto</th><th>NUIT</th><th>Banco / Conta</th></tr></thead>
            <tbody>
            <?php if (!$requeridos): ?>
                <tr><td colspan="7"><div class="empty" style="padding:26px;"><?= svg_icon('users') ?><p>Nenhum requerido associado.</p></div></td></tr>
            <?php else: ?>
                <?php foreach ($requeridos as $r): ?>
                    <tr>
                        <td class="cell-strong"><?= e($r['nome']) ?></td>
                        <td><?= e($r['patente']) ?><span class="cell-sub"><?= e($r['ramo']) ?></span></td>
                        <td><?= e($r['unidade']) ?></td>
                        <td><?= e($r['funcao']) ?></td>
                        <td><?= e($r['contacto']) ?></td>
                        <td class="cell-mono"><?= e((string) $r['nuit']) ?></td>
                        <td><?= e($r['banco']) ?><span class="cell-sub cell-mono"><?= e((string) $r['conta']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-2">
    <div class="card-head">
        <div><h3>Beneficiários</h3><span class="sub">Beneficiários do expediente</span></div>
        <?php if (can('criar_beneficiarios')): ?>
            <a class="btn btn-outline btn-sm" href="<?= e(url('/beneficiario_form.php?expediente=' . $id)) ?>"><?= svg_icon('plus', 'icon icon-sm') ?> Adicionar</a>
        <?php endif; ?>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Nome</th><th>Contacto</th><th>Endereço</th><th>Banco / Conta</th><th class="num">Quantidade</th></tr></thead>
            <tbody>
            <?php if (!$beneficiarios): ?>
                <tr><td colspan="5"><div class="empty" style="padding:26px;"><?= svg_icon('handshake') ?><p>Nenhum beneficiário associado.</p></div></td></tr>
            <?php else: ?>
                <?php foreach ($beneficiarios as $b): ?>
                    <tr>
                        <td class="cell-strong"><?= e($b['nome']) ?></td>
                        <td><?= e($b['contacto']) ?></td>
                        <td><?= e($b['endereco']) ?></td>
                        <td><?= e($b['banco']) ?><span class="cell-sub cell-mono"><?= e((string) $b['conta']) ?></span></td>
                        <td class="num"><?= numero_pt($b['qtd_beneficiario']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="grid mt-2" style="grid-template-columns: 1.5fr 1fr;">
    <div class="card">
        <div class="card-head">
            <div><h3>Saídas do expediente</h3><span class="sub">Encaminhamentos registados</span></div>
            <?php if (can('criar_saidas')): ?>
                <a class="btn btn-outline btn-sm" href="<?= e(url('/saida_form.php?expediente=' . $id)) ?>"><?= svg_icon('plus', 'icon icon-sm') ?> Nova</a>
            <?php endif; ?>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Referência</th><th>Destino</th><th>Assunto</th><th>Data</th></tr></thead>
                <tbody>
                <?php if (!$saidas): ?>
                    <tr><td colspan="4"><div class="empty" style="padding:26px;"><?= svg_icon('outbox') ?><p>Nenhuma saída registada.</p></div></td></tr>
                <?php else: ?>
                    <?php foreach ($saidas as $s): ?>
                        <tr>
                            <td class="cell-mono"><?= e($s['referencia']) ?></td>
                            <td><?= e($s['destino']) ?></td>
                            <td><?= e($s['assunto']) ?></td>
                            <td class="nowrap"><?= e(data_pt($s['data_saida'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h3>Histórico do expediente</h3></div>
        <div class="card-body">
            <?php if (!$historico): ?>
                <div class="empty" style="padding:20px;"><?= svg_icon('history') ?><p>Sem acções registadas.</p></div>
            <?php else: ?>
                <div class="timeline">
                    <?php foreach ($historico as $h): ?>
                        <div class="timeline-item">
                            <strong><?= e($h['accao']) ?></strong>
                            <span><?= e($h['usuario']) ?> · <?= e(data_hora_pt($h['data'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
