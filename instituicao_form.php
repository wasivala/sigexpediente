<?php
/**
 * Registo / edição de instituição.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$id = get_int('id', 0);
$editando = $id > 0;
require_can($editando ? 'editar_instituicoes' : 'criar_instituicoes');

$erros = [];
$reg = ['nome' => '', 'tipo' => '', 'estado' => 'Ativo'];
$tipoOriginal = '';

if ($editando) {
    $stmt = db()->prepare('SELECT * FROM instituicao WHERE idinstituicao = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        flash_error('Instituição não encontrada.');
        redirect('/instituicoes.php');
    }
    $tipoOriginal = (string) $row['tipo'];
    $reg = ['nome' => (string) $row['nome'], 'tipo' => $tipoOriginal, 'estado' => (string) $row['estado']];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $reg = ['nome' => post('nome'), 'tipo' => post('tipo'), 'estado' => post('estado')];

    $tiposValidos = TIPOS_INSTITUICAO;
    if ($tipoOriginal !== '' && !in_array($tipoOriginal, $tiposValidos, true)) {
        // Preserva tipos antigos ao editar registos já existentes.
        $tiposValidos[] = $tipoOriginal;
    }

    if (!validar_nome($reg['nome'], 'Nome', 100, $e)) { $erros['nome'] = $e; }
    if (!in_array($reg['tipo'], $tiposValidos, true)) { $erros['tipo'] = 'Selecione um tipo válido.'; }
    if (!in_array($reg['estado'], ['Ativo', 'Inativo'], true)) { $erros['estado'] = 'Selecione um estado válido.'; }

    // Nome único
    if (!isset($erros['nome'])) {
        $chk = db()->prepare('SELECT COUNT(*) FROM instituicao WHERE nome = :nome' . ($editando ? ' AND idinstituicao <> :id' : ''));
        $params = [':nome' => $reg['nome']];
        if ($editando) { $params[':id'] = $id; }
        $chk->execute($params);
        if ((int) $chk->fetchColumn() > 0) { $erros['nome'] = 'Já existe uma instituição com este nome.'; }
    }

    if (!$erros) {
        if ($editando) {
            $stmt = db()->prepare('UPDATE instituicao SET nome = :nome, tipo = :tipo, estado = :estado WHERE idinstituicao = :id');
            $stmt->execute([':nome' => $reg['nome'], ':tipo' => $reg['tipo'], ':estado' => $reg['estado'], ':id' => $id]);
            log_action('Instituição actualizada: ' . $reg['nome'], []);
            flash_success('Instituição actualizada com sucesso.');
        } else {
            $stmt = db()->prepare('INSERT INTO instituicao (nome, tipo, estado) VALUES (:nome, :tipo, :estado)');
            $stmt->execute([':nome' => $reg['nome'], ':tipo' => $reg['tipo'], ':estado' => $reg['estado']]);
            log_action('Instituição registada: ' . $reg['nome'], []);
            flash_success('Instituição registada com sucesso.');
        }
        redirect('/instituicoes.php');
    }
}

$page_title = $editando ? 'Editar instituição' : 'Nova instituição';
$page_subtitle = 'Identificação e estado da instituição';
$active = 'instituicoes.php';
require __DIR__ . '/includes/header.php';
?>

<form method="post" action="<?= e(url('/instituicao_form.php' . ($editando ? '?id=' . $id : ''))) ?>" novalidate>
    <?= csrf_field() ?>
    <?php if ($erros): ?>
        <div class="flash flash-danger mb-2" role="alert"><?= svg_icon('alert') ?><span>Corrija os campos assinalados.</span></div>
    <?php endif; ?>

    <div class="card" style="max-width:720px">
        <div class="card-head"><div><h3>Dados da instituição</h3><span class="sub">Campos com * são obrigatórios</span></div></div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field full">
                    <label for="nome">Nome <span class="req">*</span></label>
                    <input type="text" id="nome" name="nome" maxlength="100" required data-letras
                           value="<?= e($reg['nome']) ?>" placeholder="Ex.: Comando da Força Aérea"
                           class="<?= isset($erros['nome']) ? 'is-invalid' : '' ?>">
                    <span class="hint">Apenas letras e espaços (sem números).</span>
                    <?php if (isset($erros['nome'])): ?><span class="field-error"><?= e($erros['nome']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label for="tipo">Tipo <span class="req">*</span></label>
                    <select id="tipo" name="tipo" required
                            class="<?= isset($erros['tipo']) ? 'is-invalid' : '' ?>">
                        <option value="">— Selecionar tipo —</option>
                        <?php foreach (TIPOS_INSTITUICAO as $tipoOpcao): ?>
                            <option value="<?= e($tipoOpcao) ?>"<?= $reg['tipo'] === $tipoOpcao ? ' selected' : '' ?>><?= e($tipoOpcao) ?></option>
                        <?php endforeach; ?>
                        <?php if ($tipoOriginal !== '' && !in_array($tipoOriginal, TIPOS_INSTITUICAO, true)): ?>
                            <option value="<?= e($tipoOriginal) ?>"<?= $reg['tipo'] === $tipoOriginal ? ' selected' : '' ?>><?= e($tipoOriginal) ?> (valor actual)</option>
                        <?php endif; ?>
                    </select>
                    <?php if (isset($erros['tipo'])): ?><span class="field-error"><?= e($erros['tipo']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label for="estado">Estado <span class="req">*</span></label>
                    <select id="estado" name="estado" required>
                        <option value="Ativo"<?= $reg['estado'] === 'Ativo' ? ' selected' : '' ?>>Ativo</option>
                        <option value="Inativo"<?= $reg['estado'] === 'Inativo' ? ' selected' : '' ?>>Inativo</option>
                    </select>
                    <?php if (isset($erros['estado'])): ?><span class="field-error"><?= e($erros['estado']) ?></span><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="form-actions" style="max-width:720px">
        <a class="btn btn-outline" href="<?= e(url('/instituicoes.php')) ?>">Cancelar</a>
        <button type="submit" class="btn btn-primary"><?= svg_icon('check', 'icon icon-sm') ?> <?= $editando ? 'Guardar alterações' : 'Registar instituição' ?></button>
    </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
