<?php
/**
 * Perfil do utilizador autenticado: dados da conta e alteração de palavra-passe.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$u = current_user();
$id = (int) $u['idusuario'];

$stmt = db()->prepare('SELECT idusuario, nume, email, senha, grupo_acesso, estado, criado_em FROM usuarios WHERE idusuario = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$conta = $stmt->fetch();
if (!$conta) {
    logout_user();
    redirect('/login.php');
}

$erros = [];
$errosSenha = [];
$reg = ['nume' => (string) $conta['nume'], 'email' => (string) $conta['email']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $acao = post('acao');

    if ($acao === 'perfil') {
        $reg = ['nume' => post('nume'), 'email' => post('email')];

        if (!validar_nome($reg['nume'], 'Nome', 50, $e)) { $erros['nume'] = $e; }
        if (!validar_email($reg['email'], $e)) { $erros['email'] = $e; }

        if (!isset($erros['email'])) {
            $chk = db()->prepare('SELECT COUNT(*) FROM usuarios WHERE email = :email AND idusuario <> :id');
            $chk->execute([':email' => $reg['email'], ':id' => $id]);
            if ((int) $chk->fetchColumn() > 0) {
                $erros['email'] = 'Já existe outra conta com este e-mail.';
            }
        }

        if (!$erros) {
            $upd = db()->prepare('UPDATE usuarios SET nume = :nume, email = :email WHERE idusuario = :id');
            $upd->execute([':nume' => $reg['nume'], ':email' => $reg['email'], ':id' => $id]);
            log_action('Perfil actualizado', ['idusuario' => $id]);
            current_user(true);
            flash_success('Dados do perfil actualizados com sucesso.');
            redirect('/perfil.php');
        }
    } elseif ($acao === 'senha') {
        $atual = (string) ($_POST['senha_atual'] ?? '');
        $nova = (string) ($_POST['senha_nova'] ?? '');
        $confirmar = (string) ($_POST['senha_confirmar'] ?? '');

        if ($atual === '' || !password_verify($atual, (string) $conta['senha'])) {
            $errosSenha['senha_atual'] = 'A palavra-passe actual não está correcta.';
        }
        if (!validar_senha($nova, $e)) {
            $errosSenha['senha_nova'] = $e;
        } elseif ($nova === $atual) {
            $errosSenha['senha_nova'] = 'A nova palavra-passe deve ser diferente da actual.';
        } elseif ($nova !== $confirmar) {
            $errosSenha['senha_confirmar'] = 'A confirmação não coincide.';
        }

        if (!$errosSenha) {
            $upd = db()->prepare('UPDATE usuarios SET senha = :senha, reset_token = NULL, reset_expires = NULL WHERE idusuario = :id');
            $upd->execute([':senha' => password_hash($nova, PASSWORD_BCRYPT), ':id' => $id]);
            log_action('Palavra-passe alterada', ['idusuario' => $id]);
            flash_success('Palavra-passe alterada com sucesso.');
            redirect('/perfil.php');
        }
    }
}

$page_title = 'Meu perfil';
$page_subtitle = 'Dados pessoais, conta de acesso e segurança';
$active = 'perfil.php';
require __DIR__ . '/includes/header.php';
?>

<div class="grid grid-2">
    <div class="card">
        <div class="card-head"><div><h3>Dados da conta</h3><span class="sub">Informação visível no sistema</span></div></div>
        <div class="card-body">
            <?php if ($erros): ?>
                <div class="flash flash-danger mb-2" role="alert"><?= svg_icon('alert') ?><span>Corrija os campos assinalados.</span></div>
            <?php endif; ?>

            <div class="flex-center mb-2">
                <span class="avatar"><?= e(iniciais((string) $conta['nume'])) ?></span>
                <div>
                    <strong><?= e($conta['nume']) ?></strong><br>
                    <span class="badge badge-<?= strtolower((string) $conta['grupo_acesso']) === 'administrador' ? 'gold' : 'neutral' ?>"><?= e($conta['grupo_acesso']) ?></span>
                </div>
            </div>

            <form method="post" action="<?= e(url('/perfil.php')) ?>" novalidate autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="acao" value="perfil">

                <div class="form-grid">
                    <div class="field">
                        <label for="nume">Nome <span class="req">*</span></label>
                        <input type="text" id="nume" name="nume" maxlength="50" required data-letras
                               value="<?= e($reg['nume']) ?>"
                               class="<?= isset($erros['nume']) ? 'is-invalid' : '' ?>">
                        <?php if (isset($erros['nume'])): ?><span class="field-error"><?= e($erros['nume']) ?></span><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="email">E-mail <span class="req">*</span></label>
                        <input type="email" id="email" name="email" maxlength="50" required
                               value="<?= e($reg['email']) ?>"
                               class="<?= isset($erros['email']) ? 'is-invalid' : '' ?>">
                        <?php if (isset($erros['email'])): ?><span class="field-error"><?= e($erros['email']) ?></span><?php endif; ?>
                    </div>
                </div>

                <div class="detail-grid mt-2">
                    <div class="detail-item">
                        <div class="dt">Grupo de acesso</div>
                        <div class="dd"><?= e($conta['grupo_acesso']) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="dt">Estado</div>
                        <div class="dd"><?= e($conta['estado']) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="dt">Conta criada em</div>
                        <div class="dd"><?= e(data_pt((string) $conta['criado_em'])) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="dt">Última sessão</div>
                        <div class="dd"><?= e(date('d/m/Y H:i')) ?></div>
                    </div>
                </div>

                <div class="form-actions" style="justify-content:flex-end;padding:16px 0 0;border:0;margin:0;background:none;">
                    <button type="submit" class="btn btn-primary"><?= svg_icon('check', 'icon icon-sm') ?> Guardar dados</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><div><h3>Alterar palavra-passe</h3><span class="sub">Mínimo de 8 caracteres, com letras e números</span></div></div>
        <div class="card-body">
            <?php if ($errosSenha): ?>
                <div class="flash flash-danger mb-2" role="alert"><?= svg_icon('alert') ?><span>Não foi possível alterar a palavra-passe.</span></div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('/perfil.php')) ?>" novalidate autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="acao" value="senha">

                <div class="field mb-2">
                    <label for="senha_atual">Palavra-passe actual <span class="req">*</span></label>
                    <div class="input-group">
                        <input type="password" id="senha_atual" name="senha_atual" required autocomplete="current-password"
                               class="<?= isset($errosSenha['senha_atual']) ? 'is-invalid' : '' ?>">
                        <button type="button" class="toggle-pass" aria-label="Mostrar palavra-passe"><?= svg_icon('eye', 'icon icon-sm') ?></button>
                    </div>
                    <?php if (isset($errosSenha['senha_atual'])): ?><span class="field-error"><?= e($errosSenha['senha_atual']) ?></span><?php endif; ?>
                </div>

                <div class="field mb-2">
                    <label for="senha_nova">Nova palavra-passe <span class="req">*</span></label>
                    <div class="input-group">
                        <input type="password" id="senha_nova" name="senha_nova" minlength="8" required autocomplete="new-password"
                               class="<?= isset($errosSenha['senha_nova']) ? 'is-invalid' : '' ?>">
                        <button type="button" class="toggle-pass" aria-label="Mostrar palavra-passe"><?= svg_icon('eye', 'icon icon-sm') ?></button>
                    </div>
                    <?php if (isset($errosSenha['senha_nova'])): ?><span class="field-error"><?= e($errosSenha['senha_nova']) ?></span><?php endif; ?>
                </div>

                <div class="field mb-2">
                    <label for="senha_confirmar">Confirmar nova palavra-passe <span class="req">*</span></label>
                    <div class="input-group">
                        <input type="password" id="senha_confirmar" name="senha_confirmar" minlength="8" required autocomplete="new-password"
                               class="<?= isset($errosSenha['senha_confirmar']) ? 'is-invalid' : '' ?>">
                        <button type="button" class="toggle-pass" aria-label="Mostrar palavra-passe"><?= svg_icon('eye', 'icon icon-sm') ?></button>
                    </div>
                    <?php if (isset($errosSenha['senha_confirmar'])): ?><span class="field-error"><?= e($errosSenha['senha_confirmar']) ?></span><?php endif; ?>
                </div>

                <div class="note">
                    <?= svg_icon('shield') ?>
                    <span>A palavra-passe é guardada como <strong>hash bcrypt</strong>. Após a alteração, os tokens de recuperação pendentes são invalidados.</span>
                </div>

                <div class="form-actions" style="justify-content:flex-end;padding:16px 0 0;border:0;margin:0;background:none;">
                    <button type="submit" class="btn btn-primary"><?= svg_icon('shield', 'icon icon-sm') ?> Alterar palavra-passe</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
