<?php
/**
 * Painel principal.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_can('ver_dashboard');

$q = fn(string $sql): int => (int) db()->query($sql)->fetchColumn();

$totalExpedientes = $q('SELECT COUNT(*) FROM expedientes');
$totalSaidas      = $q('SELECT COUNT(*) FROM saida_expedientes');
$totalRequeridos  = $q('SELECT COUNT(*) FROM requeridos');
$totalBenef       = $q('SELECT COUNT(*) FROM beneficiarios');
$totalInst        = $q('SELECT COUNT(*) FROM instituicao');
$totalUsers       = $q('SELECT COUNT(*) FROM usuarios');

$porStatus = ['encurso' => 0, 'finalizado' => 0, 'pendente' => 0, 'arquivado' => 0];
foreach (db()->query('SELECT status, COUNT(*) AS c FROM expedientes GROUP BY status') as $r) {
    $k = strtolower((string) $r['status']);
    if (isset($porStatus[$k])) {
        $porStatus[$k] = (int) $r['c'];
    }
}

$hoje = $q("SELECT COUNT(*) FROM expedientes WHERE DATE(data_entrada) = CURDATE()");
$mes  = $q("SELECT COUNT(*) FROM expedientes WHERE YEAR(data_entrada) = YEAR(CURDATE()) AND MONTH(data_entrada) = MONTH(CURDATE())");
$saidasMes = $q("SELECT COUNT(*) FROM saida_expedientes WHERE YEAR(data_saida) = YEAR(CURDATE()) AND MONTH(data_saida) = MONTH(CURDATE())");

$recentes = db()->query(
    'SELECT e.idexpediente, e.referencia, e.nrm_processo, e.Assunto, e.data_entrada, e.status, e.proveniencia,
            u.nume AS registado_por
     FROM expedientes e
     LEFT JOIN usuarios u ON u.idusuario = e.idusuario
     ORDER BY e.data_entrada DESC, e.idexpediente DESC
     LIMIT 6'
)->fetchAll();

$ultimasSaidas = db()->query(
    'SELECT s.idsaida, s.referencia, s.destino, s.data_saida, s.assunto, e.referencia AS expediente_ref
     FROM saida_expedientes s
     LEFT JOIN expedientes e ON e.idexpediente = s.idexpediente
     ORDER BY s.data_saida DESC, s.idsaida DESC
     LIMIT 5'
)->fetchAll();

$atividade = [];
if (can('ver_logs')) {
    $atividade = db()->query('SELECT usuario, accao, data FROM logs ORDER BY data DESC, idlog DESC LIMIT 7')->fetchAll();
}

$maxStatus = max(1, ...array_values($porStatus));
$cores = ['encurso' => 'blue', 'finalizado' => '', 'pendente' => 'amber', 'arquivado' => 'slate'];

$page_title = 'Painel';
$page_subtitle = 'Visão geral do movimento de expediente · ' . date('d/m/Y');
$active = 'dashboard.php';
$page_actions = can('criar_expedientes')
    ? '<a class="btn btn-primary btn-sm" href="' . e(url('/expediente_form.php')) . '">' . svg_icon('plus', 'icon icon-sm') . ' Novo expediente</a>'
    : '';
require __DIR__ . '/includes/header.php';
?>

<div class="grid grid-4 mb-2">
    <div class="stat">
        <div class="stat-icon"><?= svg_icon('inbox') ?></div>
        <div class="stat-body">
            <div class="stat-value"><?= numero_pt($totalExpedientes) ?></div>
            <div class="stat-label">Expedientes</div>
            <div class="stat-hint"><?= numero_pt($mes) ?> este mês · <?= numero_pt($hoje) ?> hoje</div>
        </div>
    </div>
    <div class="stat">
        <div class="stat-icon amber"><?= svg_icon('clock') ?></div>
        <div class="stat-body">
            <div class="stat-value"><?= numero_pt($porStatus['pendente']) ?></div>
            <div class="stat-label">Pendentes</div>
            <div class="stat-hint">A aguardar tratamento</div>
        </div>
    </div>
    <div class="stat">
        <div class="stat-icon blue"><?= svg_icon('history') ?></div>
        <div class="stat-body">
            <div class="stat-value"><?= numero_pt($porStatus['encurso']) ?></div>
            <div class="stat-label">Em curso</div>
            <div class="stat-hint">Em tramitação</div>
        </div>
    </div>
    <div class="stat">
        <div class="stat-icon"><?= svg_icon('check') ?></div>
        <div class="stat-body">
            <div class="stat-value"><?= numero_pt($porStatus['finalizado']) ?></div>
            <div class="stat-label">Finalizados</div>
            <div class="stat-hint"><?= numero_pt($porStatus['arquivado']) ?> arquivados</div>
        </div>
    </div>
</div>

<div class="grid" style="grid-template-columns: 1.55fr 1fr;">
    <div class="card">
        <div class="card-head">
            <div>
                <h3><?= svg_icon('inbox', 'icon icon-sm') ?> Expedientes recentes</h3>
                <span class="sub">Últimas entradas registadas</span>
            </div>
            <a class="btn btn-outline btn-sm" href="<?= e(url('/expedientes.php')) ?>">Ver todos</a>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Referência</th>
                        <th>Assunto</th>
                        <th>Proveniência</th>
                        <th>Entrada</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$recentes): ?>
                    <tr><td colspan="5">
                        <div class="empty" style="padding:30px 10px;">
                            <?= svg_icon('inbox') ?>
                            <h4>Sem expedientes registados</h4>
                            <p>Comece por registar a primeira entrada de expediente.</p>
                        </div>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($recentes as $r): ?>
                        <tr class="is-clickable" data-href="<?= e(url('/expediente_view.php?id=' . (int) $r['idexpediente'])) ?>">
                            <td>
                                <span class="cell-strong cell-mono"><?= e($r['referencia']) ?></span>
                                <span class="cell-sub"><?= processo_e_interno($r['nrm_processo'] ?? null) ? 'Expediente interno' : 'Nº ' . e($r['nrm_processo']) ?></span>
                            </td>
                            <td><?= e($r['Assunto']) ?></td>
                            <td><?= e($r['proveniencia']) ?></td>
                            <td class="nowrap"><?= e(data_pt($r['data_entrada'])) ?></td>
                            <td><?= status_badge($r['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div>
                <h3><?= svg_icon('report', 'icon icon-sm') ?> Distribuição por estado</h3>
                <span class="sub">Total de <?= numero_pt($totalExpedientes) ?> expedientes</span>
            </div>
        </div>
        <div class="card-body">
            <div class="bar-chart">
                <?php foreach ($porStatus as $status => $total): ?>
                    <div class="bar-row">
                        <span><?= e(STATUS_EXPEDIENTE[$status]) ?></span>
                        <div class="bar-track">
                            <div class="bar-fill <?= e($cores[$status]) ?>" style="width: <?= $total > 0 ? max(3, round($total / $maxStatus * 100)) : 0 ?>%"></div>
                        </div>
                        <span class="bar-value"><?= numero_pt($total) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="divider"></div>

            <div class="grid grid-2" style="gap:10px;">
                <div class="stat" style="padding:14px;">
                    <div class="stat-icon gold"><?= svg_icon('outbox') ?></div>
                    <div class="stat-body">
                        <div class="stat-value" style="font-size:1.25rem;"><?= numero_pt($totalSaidas) ?></div>
                        <div class="stat-label" style="font-size:.66rem;">Saídas</div>
                    </div>
                </div>
                <div class="stat" style="padding:14px;">
                    <div class="stat-icon violet"><?= svg_icon('users') ?></div>
                    <div class="stat-body">
                        <div class="stat-value" style="font-size:1.25rem;"><?= numero_pt($totalRequeridos) ?></div>
                        <div class="stat-label" style="font-size:.66rem;">Requeridos</div>
                    </div>
                </div>
                <div class="stat" style="padding:14px;">
                    <div class="stat-icon blue"><?= svg_icon('handshake') ?></div>
                    <div class="stat-body">
                        <div class="stat-value" style="font-size:1.25rem;"><?= numero_pt($totalBenef) ?></div>
                        <div class="stat-label" style="font-size:.66rem;">Beneficiários</div>
                    </div>
                </div>
                <div class="stat" style="padding:14px;">
                    <div class="stat-icon amber"><?= svg_icon('building') ?></div>
                    <div class="stat-body">
                        <div class="stat-value" style="font-size:1.25rem;"><?= numero_pt($totalInst) ?></div>
                        <div class="stat-label" style="font-size:.66rem;">Instituições</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="grid mt-2" style="grid-template-columns: 1fr 1fr;">
    <div class="card">
        <div class="card-head">
            <div>
                <h3><?= svg_icon('outbox', 'icon icon-sm') ?> Últimas saídas</h3>
                <span class="sub"><?= numero_pt($saidasMes) ?> este mês</span>
            </div>
            <a class="btn btn-outline btn-sm" href="<?= e(url('/saidas.php')) ?>">Ver todas</a>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Referência</th><th>Destino</th><th>Saída</th></tr></thead>
                <tbody>
                <?php if (!$ultimasSaidas): ?>
                    <tr><td colspan="3"><div class="empty" style="padding:26px 10px;"><?= svg_icon('outbox') ?><p>Nenhuma saída registada.</p></div></td></tr>
                <?php else: ?>
                    <?php foreach ($ultimasSaidas as $s): ?>
                        <tr>
                            <td class="cell-mono"><?= e($s['referencia']) ?></td>
                            <td><?= e($s['destino']) ?><span class="cell-sub"><?= e($s['assunto']) ?></span></td>
                            <td class="nowrap"><?= e(data_pt($s['data_saida'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (can('ver_logs')): ?>
    <div class="card">
        <div class="card-head">
            <div>
                <h3><?= svg_icon('history', 'icon icon-sm') ?> Actividade recente</h3>
                <span class="sub">Últimas operações no sistema</span>
            </div>
            <a class="btn btn-outline btn-sm" href="<?= e(url('/logs.php')) ?>">Histórico</a>
        </div>
        <div class="card-body">
            <?php if (!$atividade): ?>
                <div class="empty" style="padding:20px;"><?= svg_icon('history') ?><p>Sem actividade registada.</p></div>
            <?php else: ?>
                <div class="timeline">
                    <?php foreach ($atividade as $a): ?>
                        <div class="timeline-item">
                            <strong><?= e($a['accao']) ?></strong>
                            <span><?= e($a['usuario']) ?> · <?= e(data_hora_pt($a['data'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-head">
            <div>
                <h3><?= svg_icon('shield', 'icon icon-sm') ?> Resumo do sistema</h3>
                <span class="sub">Informação geral</span>
            </div>
        </div>
        <div class="card-body">
            <div class="detail-grid">
                <div class="detail-item"><div class="dt">Utilizadores</div><div class="dd"><?= numero_pt($totalUsers) ?></div></div>
                <div class="detail-item"><div class="dt">Instituições</div><div class="dd"><?= numero_pt($totalInst) ?></div></div>
                <div class="detail-item"><div class="dt">Expedientes arquivados</div><div class="dd"><?= numero_pt($porStatus['arquivado']) ?></div></div>
                <div class="detail-item"><div class="dt">Saídas este mês</div><div class="dd"><?= numero_pt($saidasMes) ?></div></div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
