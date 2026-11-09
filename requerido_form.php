<?php
/**
 * Registo / edição de requerido.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$id = get_int('id', 0);
$editando = $id > 0;
require_can($editando ? 'editar_requeridos' : 'criar_requeridos');

$expedientePre = get_int('expediente', 0);
$erros = [];
$reg = [
    'nome' => '', 'patente' => '', 'civil' => 0, 'ramo' => '', 'unidade' => '', 'funcao' => '',
    'contacto' => '', 'nuit' => '', 'conta' => '', 'banco' => '', 'idexpediente' => $expedientePre,
];
$patenteOriginal = '';

if ($editando) {
    $stmt = db()->prepare('SELECT * FROM requeridos WHERE idrequerido = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        flash_error('Requerido não encontrado.');
        redirect('/requeridos.php');
    }
    $patenteOriginal = (string) $row['patente'];
    $reg = [
        'nome' => (string) $row['nome'], 'patente' => $patenteOriginal,
        'civil' => $patenteOriginal === PATENTE_CIVIL ? 1 : 0,
        'ramo' => (string) $row['ramo'], 'unidade' => (string) $row['unidade'],
        'funcao' => (string) $row['funcao'], 'contacto' => (string) $row['contacto'],
        'nuit' => (string) $row['nuit'], 'conta' => (string) $row['conta'],
        'banco' => (string) $row['banco'], 'idexpediente' => (int) $row['idexpediente'],
    ];
}

$expedientes = db()->query('SELECT idexpediente, referencia, Assunto FROM expedientes ORDER BY data_entrada DESC')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $reg = [
        'nome' => post('nome'), 'patente' => post('patente'),
        'civil' => isset($_POST['civil']) ? 1 : 0,
        'ramo' => post('ramo'),
        'unidade' => post('unidade'), 'funcao' => post('funcao'), 'contacto' => post('contacto'),
        'nuit' => post('nuit'), 'conta' => post('conta'), 'banco' => post('banco'),
        'idexpediente' => post_int('idexpediente'),
    ];

    if (!validar_nome($reg['nome'], 'Nome', 100, $e)) { $erros['nome'] = $e; }
    if ($reg['civil'] === 1) {
        $reg['patente'] = PATENTE_CIVIL;
    } else {
        $patentesValidas = PATENTES;
        if ($editando && $patenteOriginal !== '' && !in_array($patenteOriginal, $patentesValidas, true)) {
            $patentesValidas[] = $patenteOriginal;
        }
        if (!in_array($reg['patente'], $patentesValidas, true)) {
            $erros['patente'] = 'Selecione uma patente válida.';
        }
    }
    if (!validar_nome($reg['ramo'], 'Ramo', 20, $e)) { $erros['ramo'] = $e; }
    if (!validar_texto($reg['unidade'], 'Unidade', 2, 150, $e)) { $erros['unidade'] = $e; }
    if (!validar_texto($reg['funcao'], 'Função', 2, 200, $e)) { $erros['funcao'] = $e; }
    if (!validar_digitos($reg['contacto'], 'Contacto', 7, 15, $e)) { $erros['contacto'] = $e; }
    if (!validar_digitos($reg['nuit'], 'NUIT', 9, 9, $e)) { $erros['nuit'] = $e; }
    elseif ((float) $reg['nuit'] > 2147483647) { $erros['nuit'] = 'O NUIT indicado excede o limite suportado.'; }
    if (!validar_digitos($reg['conta'], 'Número de conta', 5, 10, $e)) { $erros['conta'] = $e; }
    elseif ((float) $reg['conta'] > 2147483647) { $erros['conta'] = 'O número de conta excede o limite suportado (10 dígitos).'; }
    if (!validar_nome($reg['banco'], 'Banco', 10, $e)) { $erros['banco'] = $e; }

    $expSelecionado = null;
    foreach ($expedientes as $ex) {
        if ((int) $ex['idexpediente'] === $reg['idexpediente']) { $expSelecionado = $ex; break; }
    }
    if (!$expSelecionado) { $erros['idexpediente'] = 'Selecione o expediente associado.'; }

    if (!$erros) {
        $u = current_user();
        if ($editando) {
            $stmt = db()->prepare(
                'UPDATE requeridos SET nome = :nome, patente = :patente, ramo = :ramo, unidade = :unidade,
                 funcao = :funcao, contacto = :contacto, nuit = :nuit, conta = :conta, banco = :banco,
                 expediente = :expediente, idexpediente = :idexpediente WHERE idrequerido = :id'
            );
            $stmt->execute([
                ':nome' => $reg['nome'], ':patente' => $reg['patente'], ':ramo' => $reg['ramo'],
                ':unidade' => $reg['unidade'], ':funcao' => $reg['funcao'], ':contacto' => $reg['contacto'],
                ':nuit' => (int) $reg['nuit'], ':conta' => (int) $reg['conta'], ':banco' => $reg['banco'],
                ':expediente' => $expSelecionado['referencia'], ':idexpediente' => $reg['idexpediente'],
                ':id' => $id,
            ]);
            log_action('Requerido actualizado: ' . $reg['nome'], ['idrequerido' => $id, 'idexpediente' => $reg['idexpediente']]);
            flash_success('Requerido actualizado com sucesso.');
        } else {
            $stmt = db()->prepare(
                'INSERT INTO requeridos (nome, patente, ramo, unidade, funcao, contacto, nuit, conta, banco, expediente, usurio, idexpediente, idusuario)
                 VALUES (:nome, :patente, :ramo, :unidade, :funcao, :contacto, :nuit, :conta, :banco, :expediente, :usurio, :idexpediente, :idusuario)'
            );
            $stmt->execute([
                ':nome' => $reg['nome'], ':patente' => $reg['patente'], ':ramo' => $reg['ramo'],
                ':unidade' => $reg['unidade'], ':funcao' => $reg['funcao'], ':contacto' => $reg['contacto'],
                ':nuit' => (int) $reg['nuit'], ':conta' => (int) $reg['conta'], ':banco' => $reg['banco'],
                ':expediente' => $expSelecionado['referencia'], ':usurio' => $u['nume'],
                ':idexpediente' => $reg['idexpediente'], ':idusuario' => (int) $u['idusuario'],
            ]);
            $novoId = (int) db()->lastInsertId();
            log_action('Requerido registado: ' . $reg['nome'], ['idrequerido' => $novoId, 'idexpediente' => $reg['idexpediente']]);
            flash_success('Requerido registado com sucesso.');
        }
        redirect('/requeridos.php');
    }
}

$page_title = $editando ? 'Editar requerido' : 'Novo requerido';
$page_subtitle = 'Dados pessoais, contacto e informação bancária';
$active = 'requeridos.php';
require __DIR__ . '/includes/header.php';
?>

<form method="post" action="<?= e(url('/requerido_form.php' . ($editando ? '?id=' . $id : ''))) ?>" novalidate>
    <?= csrf_field() ?>
    <?php if ($erros): ?>
        <div class="flash flash-danger mb-2" role="alert"><?= svg_icon('alert') ?><span>Corrija os campos assinalados antes de guardar.</span></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-head"><div><h3>Identificação</h3><span class="sub">Campos com * são obrigatórios</span></div></div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field full">
                    <label for="nome">Nome completo <span class="req">*</span></label>
                    <input type="text" id="nome" name="nome" maxlength="100" required data-letras
                           value="<?= e($reg['nome']) ?>" placeholder="Ex.: João Carlos Macuácua"
                           class="<?= isset($erros['nome']) ? 'is-invalid' : '' ?>">
                    <span class="hint">Apenas letras e espaços (sem números).</span>
                    <?php if (isset($erros['nome'])): ?><span class="field-error"><?= e($erros['nome']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label for="patente">Patente <span class="req" id="patente_req"<?= $reg['civil'] ? ' style="display:none"' : '' ?>>*</span></label>
                    <select id="patente" name="patente" <?= $reg['civil'] ? 'disabled' : 'required' ?>
                            class="<?= isset($erros['patente']) ? 'is-invalid' : '' ?>">
                        <option value="">— Selecionar patente —</option>
                        <?php foreach (PATENTES as $patenteOpcao): ?>
                            <option value="<?= e($patenteOpcao) ?>"<?= $reg['patente'] === $patenteOpcao ? ' selected' : '' ?>><?= e($patenteOpcao) ?></option>
                        <?php endforeach; ?>
                        <?php if ($editando && $patenteOriginal !== '' && $patenteOriginal !== PATENTE_CIVIL && !in_array($patenteOriginal, PATENTES, true)): ?>
                            <option value="<?= e($patenteOriginal) ?>" selected><?= e($patenteOriginal) ?> (valor actual)</option>
                        <?php endif; ?>
                    </select>
                    <label class="check-inline mt-1" for="civil">
                        <input type="checkbox" id="civil" name="civil" value="1" data-civil-toggle
                               <?= $reg['civil'] ? 'checked' : '' ?>>
                        <span>Requerido civil (sem patente militar)</span>
                    </label>
                    <span class="hint">Ao marcar “civil”, o campo Patente grava <strong>Civil</strong>.</span>
                    <?php if (isset($erros['patente'])): ?><span class="field-error"><?= e($erros['patente']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label for="ramo">Ramo <span class="req">*</span></label>
                    <input type="text" id="ramo" name="ramo" maxlength="20" required data-letras
                           value="<?= e($reg['ramo']) ?>" placeholder="Ex.: Exército"
                           class="<?= isset($erros['ramo']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($erros['ramo'])): ?><span class="field-error"><?= e($erros['ramo']) ?></span><?php endif; ?>
                </div>
                <div class="field full">
                    <label for="unidade">Unidade <span class="req">*</span></label>
                    <input type="text" id="unidade" name="unidade" maxlength="150" required
                           value="<?= e($reg['unidade']) ?>" placeholder="Ex.: 1ª Brigada de Infantaria"
                           class="<?= isset($erros['unidade']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($erros['unidade'])): ?><span class="field-error"><?= e($erros['unidade']) ?></span><?php endif; ?>
                </div>
                <div class="field full">
                    <label for="funcao">Função <span class="req">*</span></label>
                    <input type="text" id="funcao" name="funcao" maxlength="200" required
                           value="<?= e($reg['funcao']) ?>" placeholder="Ex.: Chefe da Secção de Pessoal"
                           class="<?= isset($erros['funcao']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($erros['funcao'])): ?><span class="field-error"><?= e($erros['funcao']) ?></span><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-2">
        <div class="card-head"><h3>Contacto e dados bancários</h3></div>
        <div class="card-body">
            <div class="form-grid cols-3">
                <div class="field">
                    <label for="contacto">Contacto telefónico <span class="req">*</span></label>
                    <input type="tel" id="contacto" name="contacto" maxlength="15" required data-digitos
                           value="<?= e($reg['contacto']) ?>" placeholder="Ex.: 841234567"
                           class="<?= isset($erros['contacto']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($erros['contacto'])): ?><span class="field-error"><?= e($erros['contacto']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label for="nuit">NUIT <span class="req">*</span></label>
                    <input type="text" id="nuit" name="nuit" maxlength="9" required inputmode="numeric" data-digitos
                           value="<?= e($reg['nuit']) ?>" placeholder="9 dígitos"
                           class="<?= isset($erros['nuit']) ? 'is-invalid' : '' ?>">
                    <span class="hint">Exactamente 9 dígitos.</span>
                    <?php if (isset($erros['nuit'])): ?><span class="field-error"><?= e($erros['nuit']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label for="banco">Banco <span class="req">*</span></label>
                    <input type="text" id="banco" name="banco" maxlength="10" required data-letras
                           value="<?= e($reg['banco']) ?>" placeholder="Ex.: BCI"
                           class="<?= isset($erros['banco']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($erros['banco'])): ?><span class="field-error"><?= e($erros['banco']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label for="conta">Número de conta <span class="req">*</span></label>
                    <input type="text" id="conta" name="conta" maxlength="10" required inputmode="numeric" data-digitos
                           value="<?= e($reg['conta']) ?>" placeholder="Até 10 dígitos"
                           class="<?= isset($erros['conta']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($erros['conta'])): ?><span class="field-error"><?= e($erros['conta']) ?></span><?php endif; ?>
                </div>
                <div class="field full">
                    <label for="idexpediente">Expediente associado <span class="req">*</span></label>
                    <select id="idexpediente" name="idexpediente" required class="<?= isset($erros['idexpediente']) ? 'is-invalid' : '' ?>">
                        <option value="">— Selecionar expediente —</option>
                        <?php foreach ($expedientes as $ex): ?>
                            <option value="<?= (int) $ex['idexpediente'] ?>"<?= (int) $reg['idexpediente'] === (int) $ex['idexpediente'] ? ' selected' : '' ?>>
                                <?= e($ex['referencia']) ?> · <?= e($ex['Assunto']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($erros['idexpediente'])): ?><span class="field-error"><?= e($erros['idexpediente']) ?></span><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <a class="btn btn-outline" href="<?= e(url('/requeridos.php')) ?>">Cancelar</a>
        <button type="submit" class="btn btn-primary"><?= svg_icon('check', 'icon icon-sm') ?> <?= $editando ? 'Guardar alterações' : 'Registar requerido' ?></button>
    </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
