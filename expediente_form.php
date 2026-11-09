<?php
/**
 * Registo / edição de expediente.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$id = get_int('id', 0);
$editando = $id > 0;
require_can($editando ? 'editar_expedientes' : 'criar_expedientes');

$erros = [];
$reg = [
    'referencia'     => '',
    'nrm_processo'   => '',
    'interno'        => 0,
    'descricao'      => '',
    'data_entrada'   => date('Y-m-d\TH:i'),
    'proveniencia'   => '',
    'Assunto'        => '',
    'status'         => 'pendente',
];
$dataOriginal = null;
$temDocumento = false;

if ($editando) {
    $stmt = db()->prepare('SELECT * FROM expedientes WHERE idexpediente = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        flash_error('Expediente não encontrado.');
        redirect('/expedientes.php');
    }
    $reg = [
        'referencia'   => (string) $row['referencia'],
        'nrm_processo' => (string) ($row['nrm_processo'] ?? ''),
        'interno'      => (int) ($row['interno'] ?? 0),
        'descricao'    => (string) $row['descricao'],
        'data_entrada' => data_input((string) $row['data_entrada'], true),
        'proveniencia' => (string) $row['proveniencia'],
        'Assunto'      => (string) $row['Assunto'],
        'status'       => (string) $row['status'],
    ];
    $dataOriginal = (string) $row['data_entrada'];
    $temDocumento = $row['scan_documento'] !== null && $row['scan_documento'] !== '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $reg = [
        'referencia'   => post('referencia'),
        'nrm_processo' => post('nrm_processo'),
        'interno'      => isset($_POST['interno']) ? 1 : 0,
        'descricao'    => post('descricao'),
        'data_entrada' => post('data_entrada'),
        'proveniencia' => post('proveniencia'),
        'Assunto'      => post('Assunto'),
        'status'       => post('status'),
    ];

    if ($reg['interno'] === 1) {
        $reg['nrm_processo'] = '';
    } elseif (!validar_texto($reg['nrm_processo'], 'Número do processo', 1, 50, $e)) {
        $erros['nrm_processo'] = $e;
    }
    if (!validar_texto($reg['referencia'], 'Referência', 2, 50, $e)) { $erros['referencia'] = $e; }
    if (!validar_texto($reg['descricao'], 'Descrição', 3, 100, $e)) { $erros['descricao'] = $e; }
    if (!validar_texto($reg['Assunto'], 'Assunto', 3, 50, $e)) { $erros['Assunto'] = $e; }
    if (!validar_texto($reg['proveniencia'], 'Proveniência', 2, 150, $e)) { $erros['proveniencia'] = $e; }
    if (!validar_data_nao_passada($reg['data_entrada'], 'Data de entrada', $editando ? $dataOriginal : null, $e)) { $erros['data_entrada'] = $e; }
    if (!validar_opcao($reg['status'], STATUS_EXPEDIENTE, 'Estado', $e)) { $erros['status'] = $e; }

    $novoDocumento = null;
    try {
        $novoDocumento = ler_upload($_FILES['scan_documento'] ?? []);
    } catch (RuntimeException $ex) {
        $erros['scan_documento'] = $ex->getMessage();
    }
    $removerDocumento = isset($_POST['remover_documento']) && $_POST['remover_documento'] === '1';

    if (!$erros) {
        $dataSql = date('Y-m-d H:i:s', strtotime($reg['data_entrada']));
        $u = current_user();
        $nrmProcesso = $reg['nrm_processo'] !== '' ? $reg['nrm_processo'] : null;

        if ($editando) {
            $sets = [
                'referencia = :referencia',
                'nrm_processo = :nrm_processo',
                'interno = :interno',
                'descricao = :descricao',
                'data_entrada = :data_entrada',
                'proveniencia = :proveniencia',
                'Assunto = :assunto',
                'status = :status',
            ];
            $params = [
                ':referencia'   => $reg['referencia'],
                ':nrm_processo' => $nrmProcesso,
                ':interno'      => $reg['interno'],
                ':descricao'    => $reg['descricao'],
                ':data_entrada' => $dataSql,
                ':proveniencia' => $reg['proveniencia'],
                ':assunto'      => $reg['Assunto'],
                ':status'       => $reg['status'],
                ':id'           => $id,
            ];
            if ($novoDocumento !== null) {
                $sets[] = 'scan_documento = :scan';
                $params[':scan'] = $novoDocumento['blob'];
                $temDocumento = true;
            } elseif ($removerDocumento) {
                $sets[] = 'scan_documento = NULL';
                $temDocumento = false;
            }
            $stmt = db()->prepare('UPDATE expedientes SET ' . implode(', ', $sets) . ' WHERE idexpediente = :id');
            $stmt->execute($params);
            log_action('Expediente actualizado: ' . $reg['referencia'], ['idexpediente' => $id]);
            flash_success('Expediente actualizado com sucesso.');
        } else {
            $stmt = db()->prepare(
                'INSERT INTO expedientes (descricao, nrm_processo, interno, referencia, data_entrada, proveniencia, Assunto, usuario, scan_documento, status, idusuario)
                 VALUES (:descricao, :nrm_processo, :interno, :referencia, :data_entrada, :proveniencia, :assunto, :usuario, :scan, :status, :idusuario)'
            );
            $stmt->execute([
                ':descricao'    => $reg['descricao'],
                ':nrm_processo' => $nrmProcesso,
                ':interno'      => $reg['interno'],
                ':referencia'   => $reg['referencia'],
                ':data_entrada' => $dataSql,
                ':proveniencia' => $reg['proveniencia'],
                ':assunto'      => $reg['Assunto'],
                ':usuario'      => $u['nume'],
                ':scan'         => $novoDocumento['blob'] ?? null,
                ':status'       => $reg['status'],
                ':idusuario'    => (int) $u['idusuario'],
            ]);
            $id = (int) db()->lastInsertId();
            log_action('Expediente registado: ' . $reg['referencia'], ['idexpediente' => $id]);
            flash_success('Expediente registado com sucesso.');
        }
        redirect('/expediente_view.php?id=' . $id);
    }
}

$instituicoes = [];
try {
    $instituicoes = db()->query(
        "SELECT nome, tipo FROM instituicao WHERE estado = 'Ativo' ORDER BY tipo ASC, nome ASC"
    )->fetchAll();
} catch (Throwable $e) {
    $instituicoes = [];
}
$instPorTipo = [];
$nomesInstituicoes = [];
foreach ($instituicoes as $inst) {
    $tipoInst = trim((string) ($inst['tipo'] ?? ''));
    $nomeInst = trim((string) ($inst['nome'] ?? ''));
    if ($nomeInst === '') {
        continue;
    }
    $instPorTipo[$tipoInst !== '' ? $tipoInst : 'Outros'][] = $nomeInst;
    $nomesInstituicoes[] = $nomeInst;
}
$provAtualForaLista = $reg['proveniencia'] !== '' && !in_array($reg['proveniencia'], $nomesInstituicoes, true);

$page_title = $editando ? 'Editar expediente' : 'Novo expediente';
$page_subtitle = $editando ? 'Actualize os dados do expediente ' . $reg['referencia'] : 'Registe a entrada de um novo expediente';
$active = 'expedientes.php';
require __DIR__ . '/includes/header.php';
?>

<form method="post" enctype="multipart/form-data" action="<?= e(url('/expediente_form.php' . ($editando ? '?id=' . $id : ''))) ?>" novalidate>
    <?= csrf_field() ?>

    <?php if ($erros): ?>
        <div class="flash flash-danger mb-2" role="alert">
            <?= svg_icon('alert') ?>
            <span>Corrija os campos assinalados antes de guardar.</span>
        </div>
    <?php endif; ?>

    <div class="grid" style="grid-template-columns: minmax(0, 1fr) 320px;">
        <div class="card">
            <div class="card-head">
                <div>
                    <h3>Identificação do expediente</h3>
                    <span class="sub">Os campos marcados com * são obrigatórios</span>
                </div>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="field">
                        <label for="referencia">Referência <span class="req">*</span></label>
                        <input type="text" id="referencia" name="referencia" maxlength="50" required
                               value="<?= e($reg['referencia']) ?>" placeholder="Ex.: REF-2026/001"
                               class="<?= isset($erros['referencia']) ? 'is-invalid' : '' ?>">
                        <?php if (isset($erros['referencia'])): ?><span class="field-error"><?= e($erros['referencia']) ?></span><?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="nrm_processo">Número do processo <span class="req" id="nrm_processo_req"<?= $reg['interno'] ? ' style="display:none"' : '' ?>>*</span></label>
                        <input type="text" id="nrm_processo" name="nrm_processo" maxlength="50"
                               value="<?= e($reg['nrm_processo']) ?>" placeholder="Ex.: 123/DP/2026"
                               <?= $reg['interno'] ? 'disabled' : 'required' ?>
                               class="<?= isset($erros['nrm_processo']) ? 'is-invalid' : '' ?>">
                        <label class="check-inline mt-1" for="interno">
                            <input type="checkbox" id="interno" name="interno" value="1" data-interno-toggle
                                   <?= $reg['interno'] ? 'checked' : '' ?>>
                            <span>Expediente interno (sem número de processo)</span>
                        </label>
                        <span class="hint">Marque a opção quando o expediente não tiver número de processo.</span>
                        <?php if (isset($erros['nrm_processo'])): ?><span class="field-error"><?= e($erros['nrm_processo']) ?></span><?php endif; ?>
                    </div>

                    <div class="field full">
                        <label for="descricao">Descrição <span class="req">*</span></label>
                        <input type="text" id="descricao" name="descricao" maxlength="100" required
                               value="<?= e($reg['descricao']) ?>" placeholder="Breve descrição do conteúdo do expediente"
                               class="<?= isset($erros['descricao']) ? 'is-invalid' : '' ?>">
                        <?php if (isset($erros['descricao'])): ?><span class="field-error"><?= e($erros['descricao']) ?></span><?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="Assunto">Assunto <span class="req">*</span></label>
                        <input type="text" id="Assunto" name="Assunto" maxlength="50" required
                               value="<?= e($reg['Assunto']) ?>" placeholder="Ex.: Pedido de declaração de vencimento"
                               class="<?= isset($erros['Assunto']) ? 'is-invalid' : '' ?>">
                        <?php if (isset($erros['Assunto'])): ?><span class="field-error"><?= e($erros['Assunto']) ?></span><?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="proveniencia">Proveniência <span class="req">*</span></label>
                        <?php if ($instituicoes): ?>
                            <select id="proveniencia" name="proveniencia" required
                                    class="<?= isset($erros['proveniencia']) ? 'is-invalid' : '' ?>">
                                <option value="">— Selecionar instituição ou sector —</option>
                                <?php foreach ($instPorTipo as $tipoInst => $nomesInst): ?>
                                    <optgroup label="<?= e($tipoInst) ?>">
                                        <?php foreach ($nomesInst as $nomeInst): ?>
                                            <option value="<?= e($nomeInst) ?>"<?= $reg['proveniencia'] === $nomeInst ? ' selected' : '' ?>><?= e($nomeInst) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                                <?php if ($provAtualForaLista): ?>
                                    <option value="<?= e($reg['proveniencia']) ?>" selected><?= e($reg['proveniencia']) ?> (valor actual)</option>
                                <?php endif; ?>
                            </select>
                            <span class="hint">Instituições e sectores activos registados no sistema.
                                <?php if (can('ver_instituicoes')): ?><a href="<?= e(url('/instituicoes.php')) ?>">Gerir instituições</a>.<?php endif; ?>
                            </span>
                        <?php else: ?>
                            <input type="text" id="proveniencia" name="proveniencia" maxlength="150" required
                                   value="<?= e($reg['proveniencia']) ?>" placeholder="Ex.: Comando da Força Aérea"
                                   class="<?= isset($erros['proveniencia']) ? 'is-invalid' : '' ?>">
                            <span class="hint">Ainda não existem instituições registadas.
                                <?php if (can('criar_instituicoes')): ?><a href="<?= e(url('/instituicao_form.php')) ?>">Registar instituição</a>.<?php endif; ?>
                            </span>
                        <?php endif; ?>
                        <?php if (isset($erros['proveniencia'])): ?><span class="field-error"><?= e($erros['proveniencia']) ?></span><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid" style="gap:18px; align-content:start;">
            <div class="card">
                <div class="card-head"><h3>Entrada e estado</h3></div>
                <div class="card-body">
                    <div class="field mb-2">
                        <label for="data_entrada">Data e hora de entrada <span class="req">*</span></label>
                        <input type="datetime-local" id="data_entrada" name="data_entrada" required
                               value="<?= e($reg['data_entrada']) ?>"
                               <?= $editando ? '' : 'data-min-hoje' ?>
                               class="<?= isset($erros['data_entrada']) ? 'is-invalid' : '' ?>">
                        <span class="hint">Não são permitidas datas anteriores a hoje.</span>
                        <?php if (isset($erros['data_entrada'])): ?><span class="field-error"><?= e($erros['data_entrada']) ?></span><?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="status">Estado <span class="req">*</span></label>
                        <select id="status" name="status" required class="<?= isset($erros['status']) ? 'is-invalid' : '' ?>">
                            <?php foreach (STATUS_EXPEDIENTE as $k => $label): ?>
                                <option value="<?= e($k) ?>"<?= $reg['status'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($erros['status'])): ?><span class="field-error"><?= e($erros['status']) ?></span><?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h3>Documento digitalizado</h3></div>
                <div class="card-body">
                    <div class="field">
                        <label for="scan_documento">Ficheiro (PDF ou imagem)</label>
                        <input type="file" id="scan_documento" name="scan_documento"
                               accept=".pdf,.jpg,.jpeg,.png,.gif,.webp">
                        <span class="hint">Tamanho máximo: 5 MB.</span>
                        <?php if (isset($erros['scan_documento'])): ?><span class="field-error"><?= e($erros['scan_documento']) ?></span><?php endif; ?>
                    </div>
                    <?php if ($editando && $temDocumento): ?>
                        <label class="flex-center mt-2" style="font-size:.8rem;color:var(--muted);">
                            <input type="checkbox" name="remover_documento" value="1" style="width:auto;min-height:auto;">
                            Remover documento actual
                        </label>
                        <a class="btn btn-outline btn-sm mt-1" target="_blank" href="<?= e(url('/documento.php?tipo=expediente&id=' . $id)) ?>">
                            <?= svg_icon('file', 'icon icon-sm') ?> Ver documento actual
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <a class="btn btn-outline" href="<?= e(url($editando ? '/expediente_view.php?id=' . $id : '/expedientes.php')) ?>">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            <?= svg_icon('check', 'icon icon-sm') ?> <?= $editando ? 'Guardar alterações' : 'Registar expediente' ?>
        </button>
    </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
