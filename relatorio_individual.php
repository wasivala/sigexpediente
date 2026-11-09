<?php
/**
 * Relatório individual de um expediente.
 * Layout conforme o modelo de relatório em anexo.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/relatorio.php';
require_can('ver_relatorios');

$id = get_int('id', 0);
if ($id <= 0) {
    // Compatibilidade com ligações antigas que usam ?expediente=
    $id = get_int('expediente', 0);
}
if ($id <= 0) {
    flash_warning('Selecione o expediente para o qual pretende emitir o relatório.');
    redirect('/relatorios.php');
}

$stmt = db()->prepare(
    'SELECT e.*, u.nume AS registado_por
     FROM expedientes e
     LEFT JOIN usuarios u ON u.idusuario = e.idusuario
     WHERE e.idexpediente = :id
     LIMIT 1'
);
$stmt->execute([':id' => $id]);
$exp = $stmt->fetch();

if (!$exp) {
    flash_error('Expediente não encontrado.');
    redirect('/relatorios.php');
}

$stmt = db()->prepare('SELECT * FROM requeridos WHERE idexpediente = :id ORDER BY nome ASC');
$stmt->execute([':id' => $id]);
$requeridos = $stmt->fetchAll();

$stmt = db()->prepare('SELECT * FROM beneficiarios WHERE idexpediente = :id ORDER BY nome ASC');
$stmt->execute([':id' => $id]);
$beneficiarios = $stmt->fetchAll();

$stmt = db()->prepare('SELECT * FROM saida_expedientes WHERE idexpediente = :id ORDER BY data_saida ASC');
$stmt->execute([':id' => $id]);
$saidas = $stmt->fetchAll();

$assunto = (string) config_get('relatorio_assunto', 'Relatório de Pedido de Declaração de Vencimento');
$dataEntrada = (string) $exp['data_entrada'];

$page_title = 'Relatório individual';
$page_subtitle = 'Expediente ' . $exp['referencia'];
$active = 'relatorios.php';
$page_actions = can('exportar_relatorios')
    ? '<a class="btn btn-outline btn-sm" href="' . e(url('/exportar.php?tipo=individual&id=' . $id)) . '">' . svg_icon('download', 'icon icon-sm') . ' CSV</a>'
    : '';
require __DIR__ . '/includes/header.php';
?>

<div class="report-toolbar no-print">
    <div class="flex gap-2 wrap">
        <a class="btn btn-outline btn-sm" href="<?= e(url('/expediente_view.php?id=' . $id)) ?>"><?= svg_icon('arrow-left', 'icon icon-sm') ?> Ver expediente</a>
        <a class="btn btn-outline btn-sm" href="<?= e(url('/relatorios.php')) ?>"><?= svg_icon('report', 'icon icon-sm') ?> Relatórios</a>
        <span class="badge badge-<?= e(strtolower((string) $exp['status'])) ?>"><?= e(STATUS_EXPEDIENTE[$exp['status']] ?? $exp['status']) ?></span>
    </div>
    <button type="button" class="btn btn-primary btn-sm" onclick="window.print()"><?= svg_icon('print', 'icon icon-sm') ?> Imprimir / PDF</button>
</div>

<div class="report-doc">
    <?= relatorio_cabecalho($assunto, $dataEntrada, $dataEntrada) ?>

    <div class="report-title">Relatório Individual de Expediente</div>
    <div class="report-sub">Expediente Nº <?= e($exp['referencia']) ?></div>

    <div class="report-section">
        <h4>Identificação do expediente</h4>
        <table class="report-kv">
            <tr>
                <td class="k">Referência</td>
                <td><?= e($exp['referencia']) ?></td>
                <td class="k">Nº do processo</td>
                <td><?= e(processo_rotulo($exp['nrm_processo'] ?? null)) ?></td>
            </tr>
            <tr>
                <td class="k">Data de entrada</td>
                <td><?= e(data_hora_pt((string) $exp['data_entrada'])) ?></td>
                <td class="k">Estado</td>
                <td><?= e(STATUS_EXPEDIENTE[$exp['status']] ?? $exp['status']) ?></td>
            </tr>
            <tr>
                <td class="k">Proveniência</td>
                <td><?= e($exp['proveniencia']) ?></td>
                <td class="k">Registado por</td>
                <td><?= e($exp['registado_por'] ?? $exp['usuario']) ?></td>
            </tr>
            <tr>
                <td class="k">Assunto</td>
                <td colspan="3"><?= e($exp['Assunto']) ?></td>
            </tr>
            <tr>
                <td class="k">Descrição</td>
                <td colspan="3"><?= e($exp['descricao']) ?></td>
            </tr>
        </table>
    </div>

    <div class="report-section">
        <h4>Requeridos</h4>
        <?php if (!$requeridos): ?>
            <p class="text-sm" style="margin:4px 0;">Nenhum requerido associado a este expediente.</p>
        <?php else: ?>
            <table class="report-table">
                <thead>
                    <tr>
                        <th class="c" style="width:38px">N/O</th>
                        <th>Nome</th>
                        <th>Patente</th>
                        <th>Ramo</th>
                        <th>Unidade</th>
                        <th>Função</th>
                        <th>Contacto</th>
                        <th>NUIT</th>
                        <th>Banco</th>
                        <th>Conta</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($requeridos as $i => $r): ?>
                    <tr>
                        <td class="c"><?= $i + 1 ?></td>
                        <td><?= e($r['nome']) ?></td>
                        <td><?= e($r['patente']) ?></td>
                        <td><?= e($r['ramo']) ?></td>
                        <td><?= e($r['unidade']) ?></td>
                        <td><?= e($r['funcao']) ?></td>
                        <td><?= e($r['contacto']) ?></td>
                        <td><?= e((string) $r['nuit']) ?></td>
                        <td><?= e($r['banco']) ?></td>
                        <td><?= e((string) $r['conta']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="report-section">
        <h4>Beneficiários</h4>
        <?php if (!$beneficiarios): ?>
            <p class="text-sm" style="margin:4px 0;">Nenhum beneficiário associado a este expediente.</p>
        <?php else: ?>
            <table class="report-table">
                <thead>
                    <tr>
                        <th class="c" style="width:38px">N/O</th>
                        <th>Nome</th>
                        <th>Contacto</th>
                        <th>Endereço</th>
                        <th>Banco</th>
                        <th>Conta</th>
                        <th class="c">Quantidade</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($beneficiarios as $i => $b): ?>
                    <tr>
                        <td class="c"><?= $i + 1 ?></td>
                        <td><?= e($b['nome']) ?></td>
                        <td><?= e($b['contacto']) ?></td>
                        <td><?= e($b['endereco']) ?></td>
                        <td><?= e($b['banco']) ?></td>
                        <td><?= e((string) $b['conta']) ?></td>
                        <td class="c"><?= e((string) $b['qtd_beneficiario']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="report-section">
        <h4>Saídas associadas</h4>
        <?php if (!$saidas): ?>
            <p class="text-sm" style="margin:4px 0;">Nenhuma saída registada para este expediente.</p>
        <?php else: ?>
            <table class="report-table">
                <thead>
                    <tr>
                        <th class="c" style="width:38px">N/O</th>
                        <th>Referência</th>
                        <th>Destino</th>
                        <th>Data de saída</th>
                        <th>Assunto</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($saidas as $i => $s): ?>
                    <tr>
                        <td class="c"><?= $i + 1 ?></td>
                        <td><?= e($s['referencia']) ?></td>
                        <td><?= e($s['destino']) ?></td>
                        <td><?= e(data_hora_pt((string) $s['data_saida'])) ?></td>
                        <td><?= e($s['assunto']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?= relatorio_assinaturas() ?>

    <div class="report-foot">
        <span><?= e((string) config_get('app_nome', 'SIGExpediente')) ?> · Relatório individual</span>
        <span>Emitido em <?= e(date('d/m/Y H:i')) ?></span>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
