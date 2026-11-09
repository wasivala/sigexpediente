<?php
/**
 * Registo / edição de utilizador.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_can('gerir_usuarios');

$id = get_int('id', 0);
$editando = $id > 0;

$erros = [];
$reg = ['nume' => '', 'email' => '', 'grupo_acesso' => 'Consulta', 'estado' => 'Ativo'];

if ($editando) {
    $stmt = db()->prepare('SELECT idusuario, nume, email, grupo_acesso, estado FROM usuarios WHERE idusuario = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        flash_error('Utilizador não encontrado.');
        redirect('/usuarios.php');
    }
    $reg = [
        'nume' => (string) $row['nume'], 'email' => (string) $row['email'],
        'grupo_acesso' => (string) $row['grupo_acesso'], 'estado' => (string) $row['estado'],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $reg = [
        'nume' => post('nume'), 'email' => post('email'),
        'grupo_acesso' => post('grupo_acesso'), 'estado' => post('estado'),
    ];
    $senha = (string) ($_POST['senha'] ?? '');
    $confirmar = (string) ($_POST['confirmar'] ?? '');

    if (!validar_nome($reg['nume'], 'Nome', 50, $e)) { $erros['nume'] = $e; }
    if (!validar_email($reg['email'], $e)) { $erros['email'] = $e; }
    if (!array_key_exists($reg['grupo_acesso'], GRUPOS_ACESSO)) { $erros['grupo_acesso'] = 'Selecione um grupo válido.'; }
    if (!in_array($reg['estado'], ['Ativo', 'Inativo'], true)) { $erros['estado'] = 'Selecione um estado válido.'; }

    if (!$editando || $senha !== '') {
        if (!validar_senha($senha, $e)) { $erros['senha'] = $e; }
        elseif ($senha !== $confirmar) { $erros['confirmar'] = 'A confirmação não coincide.'; }
    }

    // E-mail único
    if (!isset($erros['email'])) {
        $chk = db()->prepare('SELECT COUNT(*) FROM usuarios WHERE email = :email' . ($editando ? ' AND idusuario <> :id' : ''));
        $params = [':email' => $reg['email']];
        if ($editando) { $params[':id'] = $id; }
        $chk->execute($params);
        if ((int) $chk->fetchColumn() > 0) { $erros['email'] = 'Já existe uma conta com este e-mail.'; }
    }

    // Impede remover o último administrador activo
    if ($editando && !$erros) {
        $atual = current_user();
        if ((int) $atual['idusuario'] === $id) {
            // não pode desactivar-se nem retirar o próprio perfil de administrador
            if ($reg['grupo_acesso'] !== 'Administrador' || $reg['estado'] !== 'Ativo') {
                $erros['grupo_acesso'] = 'Não pode remover o seu próprio acesso de administrador.';
            }
        }
    }

    if (!$erros) {
        if ($editando) {
            $sets = 'nume = :nume, email = :email, grupo_acesso = :grupo, estado = :estado';
            $params = [
                ':nume' => $reg['nume'], ':email' => $reg['email'],
                ':grupo' => $reg['grupo_acesso'], ':estado' => $reg['estado'], ':id' => $id,
            ];
            if ($senha !== '') {
                $sets .= ', senha = :senha, reset_token = NULL, reset_expires = NULL';
                $params[':senha'] = password_hash($senha, PASSWORD_BCRYPT);
            }
            $stmt = db()->prepare("UPDATE usuarios SET {$sets} WHERE idusuario = :id");
            $stmt->execute($params);
            log_action('Utilizador actualizado: ' . $reg['nume'], ['idusuario' => $id]);
            flash_success('Utilizador actualizado com sucesso.');
        } else {
            $stmt = db()->prepare(
                'INSERT INTO usuarios (nume, email, senha, grupo_acesso, estado) VALUES (:nume, :email, :senha, :grupo, :estado)'
            );
            $stmt->execute([
                ':nume' => $reg['nume'], ':email' => $reg['email'],
                ':senha' => password_hash($senha, PASSWORD_BCRYPT),
                ':grupo' => $reg['grupo_acesso'], ':estado' => $reg['estado'],
            ]);
            $novoId = (int) db()->lastInsertId();
            log_action('Utilizador criado: ' . $reg['nume'], ['idusuario' => $novoId]);
            flash_success('Utilizador criado com sucesso.');
        }
        redirect('/usuarios.php');
    }
}

$page_title = $editando ? 'Editar utilizador' : 'Novo utilizador';
$page_subtitle = 'Dados da conta e permissões de acesso';
$active = 'usuarios.php';
require __DIR__ . '/includes/header.php';
?>

<form method="post" action="<?= e(url('/usuario_form.php' . ($editando ? '?id=' . $id : ''))) ?>" novalidate autocomplete="off">
    <?= csrf_field() ?>
    <?php if ($erros): ?>
        <div class="flash flash-danger mb-2" role="alert"><?= svg_icon('alert') ?><span>Corrija os campos assinalados.</span></div>
    <?php endif; ?>

    <div class="card" style="max-width:820px">
        <div class="card-head"><div><h3>Dados da conta</h3><span class="sub">Campos com * são obrigatórios</span></div></div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field">
                    <label for="nume">Nome <span class="req">*</span></label>
                    <input type="text" id="nume" name="nume" maxlength="50" required data-letras
                           value="<?= e($reg['nume']) ?>" placeholder="Nome do utilizador"
                           class="<?= isset($erros['nume']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($erros['nume'])): ?><span class="field-error"><?= e($erros['nume']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label for="email">E-mail <span class="req">*</span></label>
                    <input type="email" id="email" name="email" maxlength="50" required
                           value="<?= e($reg['email']) ?>" placeholder="nome@instituicao.gov.mz"
                           class="<?= isset($erros['email']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($erros['email'])): ?><span class="field-error"><?= e($erros['email']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label for="grupo_acesso">Grupo de acesso <span class="req">*</span></label>
                    <select id="grupo_acesso" name="grupo_acesso" required class="<?= isset($erros['grupo_acesso']) ? 'is-invalid' : '' ?>">
                        <?php foreach (GRUPOS_ACESSO as $k => $label): ?>
                            <option value="<?= e($k) ?>"<?= $reg['grupo_acesso'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($erros['grupo_acesso'])): ?><span class="field-error"><?= e($erros['grupo_acesso']) ?></span><?php endif; ?>
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

            <div class="divider"></div>
            <p class="section-title"><?= svg_icon('shield', 'icon icon-sm') ?> Palavra-passe</p>

            <?php if ($editando): ?>
                <div class="note mb-2"><?= svg_icon('info') ?><span>Deixe os campos em branco para manter a palavra-passe actual.</span></div>
            <?php endif; ?>

            <div class="form-grid">
                <div class="field">
                    <label for="senha"><?= $editando ? 'Nova palavra-passe' : 'Palavra-passe' ?><?= $editando ? '' : ' <span class="req">*</span>' ?></label>
                    <div class="input-group">
                        <input type="password" id="senha" name="senha" minlength="8" autocomplete="new-password"
                               placeholder="Mínimo 8 caracteres" <?= $editando ? '' : 'required' ?>
                               class="<?= isset($erros['senha']) ? 'is-invalid' : '' ?>">
                        <button type="button" class="toggle-pass" aria-label="Mostrar palavra-passe"><?= svg_icon('eye', 'icon icon-sm') ?></button>
                    </div>
                    <span class="hint">Pelo menos 8 caracteres, com letras e números. Guardada como hash bcrypt.</span>
                    <?php if (isset($erros['senha'])): ?><span class="field-error"><?= e($erros['senha']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label for="confirmar">Confirmar palavra-passe<?= $editando ? '' : ' <span class="req">*</span>' ?></label>
                    <div class="input-group">
                        <input type="password" id="confirmar" name="confirmar" minlength="8" autocomplete="new-password"
                               placeholder="Repita a palavra-passe" <?= $editando ? '' : 'required' ?>
                               class="<?= isset($erros['confirmar']) ? 'is-invalid' : '' ?>">
                        <button type="button" class="toggle-pass" aria-label="Mostrar palavra-passe"><?= svg_icon('eye', 'icon icon-sm') ?></button>
                    </div>
                    <?php if (isset($erros['confirmar'])): ?><span class="field-error"><?= e($erros['confirmar']) ?></span><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="form-actions" style="max-width:820px">
        <a class="btn btn-outline" href="<?= e(url('/usuarios.php')) ?>">Cancelar</a>
        <button type="submit" class="btn btn-primary"><?= svg_icon('check', 'icon icon-sm') ?> <?= $editando ? 'Guardar alterações' : 'Criar utilizador' ?></button>
    </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
