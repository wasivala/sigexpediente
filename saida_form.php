<?php
/**
 * Registo / edição de saída de expediente.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$id = get_int('id', 0);
$editando = $id > 0;
require_can($editando ? 'editar_saidas' : 'criar_saidas');

$expedientePre = get_int('expediente', 0);
$erros = [];
$reg = [
    'referencia' => '',
    'descricao'  => '',
    'destino'    => '',
    'data_saida' => date('Y-m-d\TH:i'),
    'assunto'    => '',
    'idexpediente' => $expedientePre,
];
$dataOriginal = null;
$temDocumento = false;

if ($editando) {
    $stmt = db()->prepare('SELECT * FROM saida_expedientes WHERE idsaida = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        flash_error('Saída não encontrada.');
        redirect('/saidas.php');
    }
    $reg = [
        'referencia'   => (string) $row['referencia'],
        'descricao'    => (string) $row['descricao'],
        'destino'      => (string) $row['destino'],
        'data_saida'   => data_input((string) $row['data_saida'], true),
        'assunto'      => (string) $row['assunto'],
        'idexpediente' => (int) $row['idexpediente'],
    ];
    $dataOriginal = (string) $row['data_saida'];
    $temDocumento = $row['scan_documento'] !== null && $row['scan_documento'] !== '';
}

$expedientes = db()->query('SELECT idexpediente, referencia, nrm_processo, Assunto FROM expedientes ORDER BY data_entrada DESC')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $reg = [
        'referencia'   => post('referencia'),
        'descricao'    => post('descricao'),
        'destino'      => post('destino'),
        'data_saida'   => post('data_saida'),
        'assunto'      => post('assunto'),
        'idexpediente' => post_int('idexpediente'),
    ];

    if (!validar_texto($reg['referencia'], 'Referência', 2, 50, $e)) { $erros['referencia'] = $e; }
    if (!validar_texto($reg['descricao'], 'Descrição', 3, 100, $e)) { $erros['descricao'] = $e; }
    if (!validar_texto($reg['destino'], 'Destino', 2, 150, $e)) { $erros['destino'] = $e; }
    if (!validar_texto($reg['assunto'], 'Assunto', 3, 50, $e)) { $erros['assunto'] = $e; }
    if (!validar_data_nao_passada($reg['data_saida'], 'Data de saída', $editando ? $dataOriginal : null, $e)) { $erros['data_saida'] = $e; }

    $expSelecionado = null;
    foreach ($expedientes as $ex) {
        if ((int) $ex['idexpediente'] === $reg['idexpediente']) {
            $expSelecionado = $ex;
            break;
        }
    }
    if (!$expSelecionado) {
        $erros['idexpediente'] = 'Selecione o expediente de origem.';
    }

    $novoDocumento = null;
    try {
        $novoDocumento = ler_upload($_FILES['scan_documento'] ?? []);
    } catch (RuntimeException $ex) {
        $erros['scan_documento'] = $ex->getMessage();
    }
    $removerDocumento = isset($_POST['remover_documento']) && $_POST['remover_documento'] === '1';

    if (!$erros) {
        $dataSql = date('Y-m-d H:i:s', strtotime($reg['data_saida']));
        $u = current_user();

        if ($editando) {
            $sets = [
                'descricao = :descricao', 'referencia = :referencia', 'destino = :destino',
                'data_saida = :data', 'assunto = :assunto', 'expediente = :expediente',
                'idexpediente = :idexpediente',
            ];
            $params = [
                ':descricao' => $reg['descricao'], ':referencia' => $reg['referencia'],
                ':destino' => $reg['destino'], ':data' => $dataSql, ':assunto' => $reg['assunto'],
                ':expediente' => $expSelecionado['referencia'],
                ':idexpediente' => $reg['idexpediente'], ':id' => $id,
            ];
            if ($novoDocumento !== null) {
                $sets[] = 'scan_documento = :scan';
                $params[':scan'] = $novoDocumento['blob'];
            } elseif ($removerDocumento) {
                $sets[] = 'scan_documento = NULL';
            }
            $stmt = db()->prepare('UPDATE saida_expedientes SET ' . implode(', ', $sets) . ' WHERE idsaida = :id');
            $stmt->execute($params);
            log_action('Saída actualizada: ' . $reg['referencia'], ['idsaida' => $id, 'idexpediente' => $reg['idexpediente']]);
            flash_success('Saída actualizada com sucesso.');
        } else {
            $stmt = db()->prepare(
                'INSERT INTO saida_expedientes (descricao, referencia, destino, data_saida, assunto, usuario, expediente, scan_documento, idexpediente, idusuario)
                 VALUES (:descricao, :referencia, :destino, :data, :assunto, :usuario, :expediente, :scan, :idexpediente, :idusuario)'
            );
            $stmt->execute([
                ':descricao' => $reg['descricao'], ':referencia' => $reg['referencia'],
                ':destino' => $reg['destino'], ':data' => $dataSql, ':assunto' => $reg['assunto'],
                ':usuario' => $u['nume'], ':expediente' => $expSelecionado['referencia'],
                ':scan' => $novoDocumento['blob'] ?? null,
                ':idexpediente' => $reg['idexpediente'], ':idusuario' => (int) $u['idusuario'],
            ]);
            $novoId = (int) db()->lastInsertId();
            log_action('Saída registada: ' . $reg['referencia'], ['idsaida' => $novoId, 'idexpediente' => $reg['idexpediente']]);
            flash_success('Saída registada com sucesso.');
        }
        redirect('/saidas.php');
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
$destinoAtualForaLista = $reg['destino'] !== '' && !in_array($reg['destino'], $nomesInstituicoes, true);

$page_title = $editando ? 'Editar saída' : 'Nova saída de expediente';
$page_subtitle = 'Encaminhamento de documento para outra entidade';
$active = 'saidas.php';
require __DIR__ . '/includes/header.php';
?>

<form method="post" enctype="multipart/form-data" action="<?= e(url('/saida_form.php' . ($editando ? '?id=' . $id : ''))) ?>" novalidate>
    <?= csrf_field() ?>

    <?php if ($erros): ?>
        <div class="flash flash-danger mb-2" role="alert"><?= svg_icon('alert') ?><span>Corrija os campos assinalados antes de guardar.</span></div>
    <?php endif; ?>

    <div class="grid" style="grid-template-columns: minmax(0, 1fr) 320px;">
        <div class="card">
            <div class="card-head"><div><h3>Dados da saída</h3><span class="sub">Campos com * são obrigatórios</span></div></div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="field">
                        <label for="referencia">Referência <span class="req">*</span></label>
                        <input type="text" id="referencia" name="referencia" maxlength="50" required
                               value="<?= e($reg['referencia']) ?>" placeholder="Ex.: SAIDA-2026/001"
                               class="<?= isset($erros['referencia']) ? 'is-invalid' : '' ?>">
                        <?php if (isset($erros['referencia'])): ?><span class="field-error"><?= e($erros['referencia']) ?></span><?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="destino">Destino <span class="req">*</span></label>
                        <?php if ($instituicoes): ?>
                            <select id="destino" name="destino" required
                                    class="<?= isset($erros['destino']) ? 'is-invalid' : '' ?>">
                                <option value="">— Selecionar instituição ou sector —</option>
                                <?php foreach ($instPorTipo as $tipoInst => $nomesInst): ?>
                                    <optgroup label="<?= e($tipoInst) ?>">
                                        <?php foreach ($nomesInst as $nomeInst): ?>
                                            <option value="<?= e($nomeInst) ?>"<?= $reg['destino'] === $nomeInst ? ' selected' : '' ?>><?= e($nomeInst) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                                <?php if ($destinoAtualForaLista): ?>
                                    <option value="<?= e($reg['destino']) ?>" selected><?= e($reg['destino']) ?> (valor actual)</option>
                                <?php endif; ?>
                            </select>
                            <span class="hint">Instituições e sectores activos registados no sistema.
                                <?php if (can('ver_instituicoes')): ?><a href="<?= e(url('/instituicoes.php')) ?>">Gerir instituições</a>.<?php endif; ?>
                            </span>
                        <?php else: ?>
                            <input type="text" id="destino" name="destino" maxlength="150" required
                                   value="<?= e($reg['destino']) ?>" placeholder="Ex.: Direcção de Finanças"
                                   class="<?= isset($erros['destino']) ? 'is-invalid' : '' ?>">
                            <span class="hint">Ainda não existem instituições registadas.
                                <?php if (can('criar_instituicoes')): ?><a href="<?= e(url('/instituicao_form.php')) ?>">Registar instituição</a>.<?php endif; ?>
                            </span>
                        <?php endif; ?>
                        <?php if (isset($erros['destino'])): ?><span class="field-error"><?= e($erros['destino']) ?></span><?php endif; ?>
                    </div>

                    <div class="field full">
                        <label for="idexpediente">Expediente de origem <span class="req">*</span></label>
                        <select id="idexpediente" name="idexpediente" required class="<?= isset($erros['idexpediente']) ? 'is-invalid' : '' ?>">
                            <option value="">— Selecionar expediente —</option>
                            <?php foreach ($expedientes as $ex): ?>
                                <option value="<?= (int) $ex['idexpediente'] ?>"<?= (int) $reg['idexpediente'] === (int) $ex['idexpediente'] ? ' selected' : '' ?>>
                                    <?= e($ex['referencia']) ?> · <?= e($ex['Assunto']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$expedientes): ?><span class="hint">Ainda não existem expedientes. Registe primeiro um expediente.</span><?php endif; ?>
                        <?php if (isset($erros['idexpediente'])): ?><span class="field-error"><?= e($erros['idexpediente']) ?></span><?php endif; ?>
                    </div>

                    <div class="field full">
                        <label for="descricao">Descrição <span class="req">*</span></label>
                        <input type="text" id="descricao" name="descricao" maxlength="100" required
                               value="<?= e($reg['descricao']) ?>" placeholder="Conteúdo encaminhado"
                               class="<?= isset($erros['descricao']) ? 'is-invalid' : '' ?>">
                        <?php if (isset($erros['descricao'])): ?><span class="field-error"><?= e($erros['descricao']) ?></span><?php endif; ?>
                    </div>

                    <div class="field full">
                        <label for="assunto">Assunto <span class="req">*</span></label>
                        <input type="text" id="assunto" name="assunto" maxlength="50" required
                               value="<?= e($reg['assunto']) ?>" placeholder="Ex.: Envio de declaração de vencimento"
                               class="<?= isset($erros['assunto']) ? 'is-invalid' : '' ?>">
                        <?php if (isset($erros['assunto'])): ?><span class="field-error"><?= e($erros['assunto']) ?></span><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid" style="gap:18px; align-content:start;">
            <div class="card">
                <div class="card-head"><h3>Data</h3></div>
                <div class="card-body">
                    <div class="field">
                        <label for="data_saida">Data e hora de saída <span class="req">*</span></label>
                        <input type="datetime-local" id="data_saida" name="data_saida" required
                               value="<?= e($reg['data_saida']) ?>" <?= $editando ? '' : 'data-min-hoje' ?>
                               class="<?= isset($erros['data_saida']) ? 'is-invalid' : '' ?>">
                        <span class="hint">Não são permitidas datas anteriores a hoje.</span>
                        <?php if (isset($erros['data_saida'])): ?><span class="field-error"><?= e($erros['data_saida']) ?></span><?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h3>Documento digitalizado</h3></div>
                <div class="card-body">
                    <div class="field">
                        <label for="scan_documento">Ficheiro (PDF ou imagem)</label>
                        <input type="file" id="scan_documento" name="scan_documento" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp">
                        <span class="hint">Tamanho máximo: 5 MB.</span>
                        <?php if (isset($erros['scan_documento'])): ?><span class="field-error"><?= e($erros['scan_documento']) ?></span><?php endif; ?>
                    </div>
                    <?php if ($editando && $temDocumento): ?>
                        <label class="flex-center mt-2" style="font-size:.8rem;color:var(--muted);">
                            <input type="checkbox" name="remover_documento" value="1" style="width:auto;min-height:auto;"> Remover documento actual
                        </label>
                        <a class="btn btn-outline btn-sm mt-1" target="_blank" href="<?= e(url('/documento.php?tipo=saida&id=' . $id)) ?>"><?= svg_icon('file', 'icon icon-sm') ?> Ver documento actual</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <a class="btn btn-outline" href="<?= e(url('/saidas.php')) ?>">Cancelar</a>
        <button type="submit" class="btn btn-primary"><?= svg_icon('check', 'icon icon-sm') ?> <?= $editando ? 'Guardar alterações' : 'Registar saída' ?></button>
    </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
