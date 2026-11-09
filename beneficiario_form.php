<?php
/**
 * Registo / edição de beneficiário.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$id = get_int('id', 0);
$editando = $id > 0;
require_can($editando ? 'editar_beneficiarios' : 'criar_beneficiarios');

$expedientePre = get_int('expediente', 0);
$erros = [];
$reg = [
    'nome' => '', 'contacto' => '', 'endereco' => '', 'conta' => '', 'banco' => '',
    'qtd_beneficiario' => '1', 'idexpediente' => $expedientePre,
];

if ($editando) {
    $stmt = db()->prepare('SELECT * FROM beneficiarios WHERE idbeneficiario = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        flash_error('Beneficiário não encontrado.');
        redirect('/beneficiarios.php');
    }
    $reg = [
        'nome' => (string) $row['nome'], 'contacto' => (string) $row['contacto'],
        'endereco' => (string) $row['endereco'], 'conta' => (string) $row['conta'],
        'banco' => (string) $row['banco'], 'qtd_beneficiario' => (string) $row['qtd_beneficiario'],
        'idexpediente' => (int) $row['idexpediente'],
    ];
}

$expedientes = db()->query('SELECT idexpediente, referencia, Assunto FROM expedientes ORDER BY data_entrada DESC')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $reg = [
        'nome' => post('nome'), 'contacto' => post('contacto'), 'endereco' => post('endereco'),
        'conta' => post('conta'), 'banco' => post('banco'),
        'qtd_beneficiario' => post('qtd_beneficiario'), 'idexpediente' => post_int('idexpediente'),
    ];

    if (!validar_nome($reg['nome'], 'Nome', 100, $e)) { $erros['nome'] = $e; }
    if (!validar_digitos($reg['contacto'], 'Contacto', 7, 20, $e)) { $erros['contacto'] = $e; }
    if (!validar_texto($reg['endereco'], 'Endereço', 2, 50, $e)) { $erros['endereco'] = $e; }
    if (!validar_digitos($reg['conta'], 'Número de conta', 5, 10, $e)) { $erros['conta'] = $e; }
    elseif ((float) $reg['conta'] > 2147483647) { $erros['conta'] = 'O número de conta excede o limite suportado (10 dígitos).'; }
    if (!validar_nome($reg['banco'], 'Banco', 10, $e)) { $erros['banco'] = $e; }
    if (!validar_digitos($reg['qtd_beneficiario'], 'Quantidade', 1, 4, $e)) { $erros['qtd_beneficiario'] = $e; }

    $expSelecionado = null;
    foreach ($expedientes as $ex) {
        if ((int) $ex['idexpediente'] === $reg['idexpediente']) { $expSelecionado = $ex; break; }
    }
    if (!$expSelecionado) { $erros['idexpediente'] = 'Selecione o expediente associado.'; }

    if (!$erros) {
        $u = current_user();
        if ($editando) {
            $stmt = db()->prepare(
                'UPDATE beneficiarios SET nome = :nome, contacto = :contacto, endereco = :endereco,
                 conta = :conta, banco = :banco, qtd_beneficiario = :qtd, expediente = :expediente,
                 idexpediente = :idexpediente WHERE idbeneficiario = :id'
            );
            $stmt->execute([
                ':nome' => $reg['nome'], ':contacto' => $reg['contacto'], ':endereco' => $reg['endereco'],
                ':conta' => (int) $reg['conta'], ':banco' => $reg['banco'],
                ':qtd' => (int) $reg['qtd_beneficiario'], ':expediente' => $expSelecionado['referencia'],
                ':idexpediente' => $reg['idexpediente'], ':id' => $id,
            ]);
            log_action('Beneficiário actualizado: ' . $reg['nome'], ['idbeneficiario' => $id, 'idexpediente' => $reg['idexpediente']]);
            flash_success('Beneficiário actualizado com sucesso.');
        } else {
            $stmt = db()->prepare(
                'INSERT INTO beneficiarios (nome, contacto, endereco, conta, banco, qtd_beneficiario, expediente, usuario, idusuario, idexpediente)
                 VALUES (:nome, :contacto, :endereco, :conta, :banco, :qtd, :expediente, :usuario, :idusuario, :idexpediente)'
            );
            $stmt->execute([
                ':nome' => $reg['nome'], ':contacto' => $reg['contacto'], ':endereco' => $reg['endereco'],
                ':conta' => (int) $reg['conta'], ':banco' => $reg['banco'],
                ':qtd' => (int) $reg['qtd_beneficiario'], ':expediente' => $expSelecionado['referencia'],
                ':usuario' => $u['nume'], ':idusuario' => (int) $u['idusuario'],
                ':idexpediente' => $reg['idexpediente'],
            ]);
            $novoId = (int) db()->lastInsertId();
            log_action('Beneficiário registado: ' . $reg['nome'], ['idbeneficiario' => $novoId, 'idexpediente' => $reg['idexpediente']]);
            flash_success('Beneficiário registado com sucesso.');
        }
        redirect('/beneficiarios.php');
    }
}

$page_title = $editando ? 'Editar beneficiário' : 'Novo beneficiário';
$page_subtitle = 'Dados de contacto e informação bancária do beneficiário';
$active = 'beneficiarios.php';
require __DIR__ . '/includes/header.php';
?>

<form method="post" action="<?= e(url('/beneficiario_form.php' . ($editando ? '?id=' . $id : ''))) ?>" novalidate>
    <?= csrf_field() ?>
    <?php if ($erros): ?>
        <div class="flash flash-danger mb-2" role="alert"><?= svg_icon('alert') ?><span>Corrija os campos assinalados antes de guardar.</span></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-head"><div><h3>Dados do beneficiário</h3><span class="sub">Campos com * são obrigatórios</span></div></div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field full">
                    <label for="nome">Nome completo <span class="req">*</span></label>
                    <input type="text" id="nome" name="nome" maxlength="100" required data-letras
                           value="<?= e($reg['nome']) ?>" placeholder="Ex.: Maria da Conceição Silva"
                           class="<?= isset($erros['nome']) ? 'is-invalid' : '' ?>">
                    <span class="hint">Apenas letras e espaços (sem números).</span>
                    <?php if (isset($erros['nome'])): ?><span class="field-error"><?= e($erros['nome']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label for="contacto">Contacto <span class="req">*</span></label>
                    <input type="tel" id="contacto" name="contacto" maxlength="20" required inputmode="numeric" data-digitos
                           value="<?= e($reg['contacto']) ?>" placeholder="Ex.: 841234567"
                           class="<?= isset($erros['contacto']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($erros['contacto'])): ?><span class="field-error"><?= e($erros['contacto']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label for="qtd_beneficiario">Quantidade de beneficiários <span class="req">*</span></label>
                    <input type="number" id="qtd_beneficiario" name="qtd_beneficiario" min="1" max="9999" required
                           value="<?= e($reg['qtd_beneficiario']) ?>"
                           class="<?= isset($erros['qtd_beneficiario']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($erros['qtd_beneficiario'])): ?><span class="field-error"><?= e($erros['qtd_beneficiario']) ?></span><?php endif; ?>
                </div>
                <div class="field full">
                    <label for="endereco">Endereço <span class="req">*</span></label>
                    <input type="text" id="endereco" name="endereco" maxlength="50" required
                           value="<?= e($reg['endereco']) ?>" placeholder="Bairro, rua, cidade"
                           class="<?= isset($erros['endereco']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($erros['endereco'])): ?><span class="field-error"><?= e($erros['endereco']) ?></span><?php endif; ?>
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
        <a class="btn btn-outline" href="<?= e(url('/beneficiarios.php')) ?>">Cancelar</a>
        <button type="submit" class="btn btn-primary"><?= svg_icon('check', 'icon icon-sm') ?> <?= $editando ? 'Guardar alterações' : 'Registar beneficiário' ?></button>
    </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
