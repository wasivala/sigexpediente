<?php
/**
 * Relatório colectivo de expedientes.
 * Layout conforme o modelo de relatório em anexo.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/relatorio.php';
require_can('ver_relatorios');

$f = relatorio_filtros();
$total = relatorio_coletivo_total($f);
$linhas = relatorio_coletivo_dados($f);

$queryAtual = http_build_query(array_filter($f, static fn($v) => $v !== '' && $v !== null));
$assunto = (string) config_get('relatorio_assunto', 'Relatório de Pedido de Declaração de Vencimento');

$page_title = 'Relatório colectivo';
$page_subtitle = 'Listagem de expedientes conforme o modelo institucional';
$active = 'relatorios.php';
$page_actions = can('exportar_relatorios')
    ? '<a class="btn btn-outline btn-sm" href="' . e(url('/exportar.php?tipo=coletivo' . ($queryAtual !== '' ? '&' . $queryAtual : ''))) . '">' . svg_icon('download', 'icon icon-sm') . ' CSV</a>'
    : '';
require __DIR__ . '/includes/header.php';
?>

<div class="report-toolbar no-print">
    <div class="flex gap-2 wrap">
        <a class="btn btn-outline btn-sm" href="<?= e(url('/relatorios.php')) ?>"><?= svg_icon('arrow-left', 'icon icon-sm') ?> Voltar</a>
        <span class="text-sm text-muted" style="align-self:center;"><?= e(relatorio_resumo($f)) ?></span>
    </div>
    <button type="button" class="btn btn-primary btn-sm" onclick="window.print()"><?= svg_icon('print', 'icon icon-sm') ?> Imprimir / PDF</button>
</div>

<div class="report-doc">
    <?= relatorio_cabecalho($assunto, (string) $f['data_inicio'], (string) $f['data_fim']) ?>

    <div class="report-title">Relatório de Expedientes</div>

    <?php if (!$linhas): ?>
        <p style="text-align:center;margin:30px 0;">Nenhum expediente corresponde aos filtros seleccionados.</p>
    <?php else: ?>
        <table class="report-table">
            <thead>
                <tr>
                    <th class="c" style="width:42px">N/O</th>
                    <th>Referência</th>
                    <th>Requerente</th>
                    <th>Requerido</th>
                    <th>Assuntos</th>
                    <th>Contacto de Beneficiário</th>
                    <th>Proveniência</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($linhas as $i => $l): ?>
                <?php
                $requerente = trim((string) ($l['patente'] ?? '') . ' ' . (string) ($l['requerido_nome'] ?? ''));
                if ($requerente === '') { $requerente = (string) ($l['beneficiario_nome'] ?? ''); }

                $requerido = (string) ($l['unidade'] ?? '');
                if ($requerido === '') { $requerido = (string) ($l['ramo'] ?? ''); }

                $contacto = (string) ($l['beneficiario_contacto'] ?? '');
                if ($contacto === '') { $contacto = (string) ($l['requerido_contacto'] ?? ''); }
                ?>
                <tr>
                    <td class="c"><?= $i + 1 ?></td>
                    <td><?= e($l['referencia']) ?></td>
                    <td><?= e($requerente !== '' ? $requerente : '—') ?></td>
                    <td><?= e($requerido !== '' ? $requerido : '—') ?></td>
                    <td><?= e($l['Assunto']) ?></td>
                    <td><?= e($contacto !== '' ? $contacto : '—') ?></td>
                    <td><?= e($l['proveniencia']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="report-meta" style="margin-top:12px;">
            <div><strong>Total de registos:</strong> <?= numero_pt(count($linhas)) ?></div>
            <div><strong>Expedientes distintos:</strong> <?= numero_pt(count(array_unique(array_column($linhas, 'idexpediente')))) ?></div>
        </div>
    <?php endif; ?>

    <?= relatorio_assinaturas() ?>

    <div class="report-foot">
        <span><?= e((string) config_get('app_nome', 'SIGExpediente')) ?> · Relatório colectivo</span>
        <span>Emitido em <?= e(date('d/m/Y H:i')) ?></span>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
