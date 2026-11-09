<?php
/**
 * Início de sessão.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (auth_check()) {
    redirect('/dashboard.php');
}

$erro = '';
$login = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $login = post('login');
    $senha = (string) ($_POST['senha'] ?? '');

    // Limitação de tentativas (5 em 10 minutos)
    $agora = time();
    $tentativas = array_values(array_filter(
        $_SESSION['login_attempts'] ?? [],
        fn($t) => ($agora - (int) $t) < 600
    ));
    $_SESSION['login_attempts'] = $tentativas;

    if (count($tentativas) >= 5) {
        $erro = 'Demasiadas tentativas falhadas. Aguarde alguns minutos antes de tentar novamente.';
    } elseif ($login === '' || $senha === '') {
        $erro = 'Informe o e-mail (ou nome de utilizador) e a palavra-passe.';
    } else {
        $stmt = db()->prepare('SELECT * FROM usuarios WHERE email = :l1 OR nume = :l2 LIMIT 1');
        $stmt->execute([':l1' => $login, ':l2' => $login]);
        $user = $stmt->fetch();

        $valido = false;
        if ($user) {
            $info = password_get_info((string) $user['senha']);
            if (!empty($info['algo'])) {
                $valido = password_verify($senha, (string) $user['senha']);
            } elseif (hash_equals((string) $user['senha'], $senha) && $senha !== '') {
                // Compatibilidade com registos antigos em texto simples: actualiza para hash.
                $novo = password_hash($senha, PASSWORD_BCRYPT);
                $up = db()->prepare('UPDATE usuarios SET senha = :s WHERE idusuario = :id');
                $up->execute([':s' => $novo, ':id' => (int) $user['idusuario']]);
                $valido = true;
                log_action('Palavra-passe migrada para hash seguro', [], (string) $user['nume'], (int) $user['idusuario']);
            }
        }

        if ($valido && strtolower((string) $user['estado']) === 'ativo') {
            unset($_SESSION['login_attempts']);
            login_user($user);
            $intended = $_SESSION['intended'] ?? null;
            unset($_SESSION['intended']);
            flash_success('Bem-vindo(a), ' . $user['nume'] . '.');
            if ($intended && str_starts_with((string) $intended, '/')) {
                header('Location: ' . $intended);
                exit;
            }
            redirect('/dashboard.php');
        }

        if ($valido) {
            $erro = 'A sua conta está inactiva. Contacte o administrador do sistema.';
        } else {
            $_SESSION['login_attempts'][] = $agora;
            $erro = 'Credenciais inválidas. Verifique o e-mail e a palavra-passe.';
        }
    }
}

$auth_title = 'Departamento de Pessoal';
$auth_subtitle = 'Repartição de Base de Dados e Arquivo';

require __DIR__ . '/includes/auth_header.php';
?>
<?php if ($erro !== ''): ?>
    <div class="flash flash-danger mb-2" role="alert">
        <?= svg_icon('alert') ?><span><?= e($erro) ?></span>
    </div>
<?php endif; ?>

<form method="post" action="<?= e(url('/login.php')) ?>" autocomplete="on" novalidate>
    <?= csrf_field() ?>

    <div class="field mb-2">
        <label for="login">E-mail ou nome de utilizador</label>
        <input type="text" id="login" name="login" value="<?= e($login) ?>"
               placeholder="ex.: admin@sigexpediente.local" maxlength="50"
               autocomplete="username" required autofocus>
    </div>

    <div class="field mb-2">
        <label for="senha">Palavra-passe</label>
        <div class="input-group">
            <input type="password" id="senha" name="senha" placeholder="••••••••"
                   autocomplete="current-password" required>
            <button type="button" class="toggle-pass" aria-label="Mostrar palavra-passe"><?= svg_icon('eye', 'icon icon-sm') ?></button>
        </div>
    </div>

    <div class="auth-links mb-2">
        <span></span>
        <a href="<?= e(url('/forgot-password.php')) ?>">Esqueceu a palavra-passe?</a>
    </div>

    <button type="submit" class="btn btn-primary btn-lg btn-block">
        Entrar <?= svg_icon('logout') ?>
    </button>
</form>

<p class="text-center text-sm text-muted mt-2" style="margin-bottom:0">
    Acesso restrito a utilizadores autorizados.
</p>
<?php require __DIR__ . '/includes/auth_footer.php'; ?>
