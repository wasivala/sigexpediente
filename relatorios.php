<?php
/**
 * Central de relatórios: relatório individual e relatório colectivo.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_can('ver_relatorios');

$expedientes = db()->query(
    'SELECT idexpediente, referencia, nrm_processo, Assunto, data_entrada
     FROM expedientes
     ORDER BY data_entrada DESC, idexpediente DESC
     LIMIT 300'
)->fetchAll();

$totalExpedientes = (int) db()->query('SELECT COUNT(*) FROM expedientes')->fetchColumn();
$totalRequeridos   = (int) db()->query('SELECT COUNT(*) FROM requeridos')->fetchColumn();
$totalSaidas       = (int) db()->query('SELECT COUNT(*) FROM saida_expedientes')->fetchColumn();

$page_title = 'Relatórios';
$page_subtitle = 'Emissão de relatórios individuais e colectivos';
$active = 'relatorios.php';
require __DIR__ . '/includes/header.php';
?>

<div class="grid grid-3 mb-2">
    <div class="stat">
        <div class="stat-icon blue"><?= svg_icon('inbox') ?></div>
        <div class="stat-body">
            <span class="stat-label">Expedientes</span>
            <div class="stat-value"><?= numero_pt($totalExpedientes) ?></div>
            <div class="stat-hint">Registos de entrada</div>
        </div>
    </div>
    <div class="stat">
        <div class="stat-icon violet"><?= svg_icon('users') ?></div>
        <div class="stat-body">
            <span class="stat-label">Requeridos</span>
            <div class="stat-value"><?= numero_pt($totalRequeridos) ?></div>
            <div class="stat-hint">Pessoas associadas</div>
        </div>
    </div>
    <div class="stat">
        <div class="stat-icon gold"><?= svg_icon('outbox') ?></div>
        <div class="stat-body">
            <span class="stat-label">Saídas</span>
            <div class="stat-value"><?= numero_pt($totalSaidas) ?></div>
            <div class="stat-hint">Encaminhamentos registados</div>
        </div>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-head">
            <div>
                <h3><?= svg_icon('file', 'icon icon-sm') ?> Relatório individual</h3>
                <span class="sub">Detalhe completo de um expediente</span>
            </div>
        </div>
        <div class="card-body">
            <p class="text-sm text-muted mt-0">
                Emite a ficha do expediente com identificação, requeridos, beneficiários e saídas associadas,
                no formato do modelo institucional.
            </p>

            <form method="get" action="<?= e(url('/relatorio_individual.php')) ?>">
                <div class="field mb-2">
                    <label for="expediente">Expediente <span class="req">*</span></label>
                    <select id="expediente" name="id" required>
                        <option value="">— Selecionar expediente —</option>
                        <?php foreach ($expedientes as $ex): ?>
                            <option value="<?= (int) $ex['idexpediente'] ?>">
                                <?= e($ex['referencia']) ?> · <?= e($ex['Assunto']) ?> · <?= e(data_pt((string) $ex['data_entrada'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$expedientes): ?>
                        <span class="hint">Ainda não existem expedientes registados.</span>
                    <?php endif; ?>
                </div>
                <div class="flex gap-2 wrap">
                    <button type="submit" class="btn btn-primary" <?= $expedientes ? '' : 'disabled' ?>>
                        <?= svg_icon('report', 'icon icon-sm') ?> Gerar relatório
                    </button>
                </div>
            </form>

            <?php if ($expedientes): ?>
                <div class="divider"></div>
                <p class="text-sm text-muted mt-0 mb-2">Acesso rápido aos últimos registos:</p>
                <div class="flex wrap gap-1">
                    <?php foreach (array_slice($expedientes, 0, 6) as $ex): ?>
                        <a class="btn btn-outline btn-sm" href="<?= e(url('/relatorio_individual.php?id=' . (int) $ex['idexpediente'])) ?>">
                            <?= e($ex['referencia']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div>
                <h3><?= svg_icon('report', 'icon icon-sm') ?> Relatório colectivo</h3>
                <span class="sub">Listagem por período, estado e NUIT</span>
            </div>
        </div>
        <div class="card-body">
            <p class="text-sm text-muted mt-0">
                Emite a listagem de expedientes no modelo institucional, com requerente, requerido,
                assuntos, contacto do beneficiário e proveniência.
            </p>

            <form method="get" action="<?= e(url('/relatorio_coletivo.php')) ?>">
                <div class="form-grid">
                    <div class="field">
                        <label for="data_inicio">Data inicial</label>
                        <input type="date" id="data_inicio" name="data_inicio">
                    </div>
                    <div class="field">
                        <label for="data_fim">Data final</label>
                        <input type="date" id="data_fim" name="data_fim">
                    </div>
                    <div class="field">
                        <label for="status">Estado</label>
                        <select id="status" name="status">
                            <option value="">Todos</option>
                            <?php foreach (STATUS_EXPEDIENTE as $k => $label): ?>
                                <option value="<?= e($k) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="nuit">NUIT do requerido</label>
                        <input type="text" id="nuit" name="nuit" maxlength="9" inputmode="numeric" data-digitos
                               placeholder="9 dígitos">
                    </div>
                    <div class="field full">
                        <label for="q">Pesquisa livre</label>
                        <input type="search" id="q" name="q" placeholder="Referência, processo, assunto, proveniência ou nome">
                    </div>
                </div>

                <div class="flex gap-2 wrap mt-2">
                    <button type="submit" class="btn btn-primary">
                        <?= svg_icon('report', 'icon icon-sm') ?> Gerar relatório
                    </button>
                    <?php if (can('exportar_relatorios')): ?>
                        <button type="submit" class="btn btn-outline" formaction="<?= e(url('/exportar.php')) ?>" formmethod="get">
                            <?= svg_icon('download', 'icon icon-sm') ?> Exportar CSV
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="note mt-2">
    <?= svg_icon('info') ?>
    <span>Os relatórios respeitam o modelo institucional em anexo (República de Moçambique · Ministério da Defesa Nacional · Estado-Maior General · Departamento de Pessoal · Repartição de Base de Dados e Arquivo). O cabeçalho e os assinantes podem ser configurados em <a href="<?= e(url('/configuracoes.php')) ?>">Configurações</a>.</span>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
