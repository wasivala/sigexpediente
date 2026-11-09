<?php
/**
 * Definição de nova palavra-passe a partir do link de recuperação.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (auth_check()) {
    redirect('/dashboard.php');
}

$token = get('token');
$email = get('email');
$erro = '';
$mensagem = '';
$valido = false;
$user = null;

/**
 * Valida o par (email, token) e devolve o utilizador ou null.
 */
function validar_token_recuperacao(string $email, string $token): ?array
{
    if ($email === '' || $token === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    $stmt = db()->prepare(
        'SELECT idusuario, nume, email, estado FROM usuarios
         WHERE email = :e AND reset_token = :t AND reset_expires IS NOT NULL AND reset_expires > NOW() LIMIT 1'
    );
    $stmt->execute([':e' => $email, ':t' => hash('sha256', $token)]);
    $row = $stmt->fetch();
    return $row ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $token = post('token');
    $email = post('email');
    $user = validar_token_recuperacao($email, $token);

    if (!$user) {
        $erro = 'O link de recuperação é inválido ou já expirou. Solicite um novo.';
    } else {
        $senha = (string) ($_POST['senha'] ?? '');
        $confirmar = (string) ($_POST['confirmar'] ?? '');

        if (!validar_senha($senha, $e1)) {
            $erro = $e1;
        } elseif ($senha !== $confirmar) {
            $erro = 'A confirmação não coincide com a nova palavra-passe.';
        } else {
            $stmt = db()->prepare('UPDATE usuarios SET senha = :s, reset_token = NULL, reset_expires = NULL WHERE idusuario = :id');
            $stmt->execute([':s' => password_hash($senha, PASSWORD_BCRYPT), ':id' => (int) $user['idusuario']]);

            log_action('Palavra-passe redefinida via e-mail', ['idusuario' => (int) $user['idusuario']], (string) $user['nume'], (int) $user['idusuario']);
            flash_success('Palavra-passe redefinida com sucesso. Já pode iniciar sessão.');
            redirect('/login.php');
        }
    }
} else {
    $user = validar_token_recuperacao($email, $token);
    $valido = $user !== null;
    if (!$valido) {
        $erro = 'O link de recuperação é inválido ou já expirou. Solicite um novo.';
    }
}

$auth_title = 'Nova palavra-passe';
$auth_subtitle = $user ? 'Defina uma nova palavra-passe para ' . $user['email'] . '.' : '';

require __DIR__ . '/includes/auth_header.php';
?>
<?php if ($erro !== ''): ?>
    <div class="flash flash-danger mb-2" role="alert">
        <?= svg_icon('alert') ?><span><?= e($erro) ?></span>
    </div>
<?php endif; ?>

<?php if ($user): ?>
<form method="post" action="<?= e(url('/reset-password.php')) ?>" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <input type="hidden" name="email" value="<?= e($email) ?>">

    <div class="field mb-2">
        <label for="senha">Nova palavra-passe</label>
        <div class="input-group">
            <input type="password" id="senha" name="senha" placeholder="Mínimo 8 caracteres"
                   autocomplete="new-password" required minlength="8">
            <button type="button" class="toggle-pass" aria-label="Mostrar palavra-passe"><?= svg_icon('eye', 'icon icon-sm') ?></button>
        </div>
        <span class="hint">Use pelo menos 8 caracteres, incluindo letras e números.</span>
    </div>

    <div class="field mb-2">
        <label for="confirmar">Confirmar palavra-passe</label>
        <div class="input-group">
            <input type="password" id="confirmar" name="confirmar" placeholder="Repita a palavra-passe"
                   autocomplete="new-password" required minlength="8">
            <button type="button" class="toggle-pass" aria-label="Mostrar palavra-passe"><?= svg_icon('eye', 'icon icon-sm') ?></button>
        </div>
    </div>

    <button type="submit" class="btn btn-primary btn-lg btn-block">
        Guardar nova palavra-passe <?= svg_icon('check') ?>
    </button>
</form>
<?php else: ?>
    <div class="note warning mb-2">
        <?= svg_icon('alert') ?>
        <span>Os links de recuperação são válidos durante 60 minutos e apenas uma vez.</span>
    </div>
    <a class="btn btn-primary btn-block" href="<?= e(url('/forgot-password.php')) ?>">Solicitar novo link</a>
<?php endif; ?>

<div class="auth-links mt-2">
    <a href="<?= e(url('/login.php')) ?>"><?= svg_icon('arrow-left', 'icon icon-sm') ?> Voltar ao início de sessão</a>
</div>
<?php require __DIR__ . '/includes/auth_footer.php'; ?>
