<?php
/**
 * Recuperação de palavra-passe (envio de link por e-mail).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

if (auth_check()) {
    redirect('/dashboard.php');
}

$mensagem = '';
$tipo = 'info';
$email = '';
$linkDev = '';
$recuperacaoAtiva = config_get('recuperacao_ativa', '1') === '1';
$modoDev = config_get('modo_desenvolvimento', '0') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = post('email');

    if (!$recuperacaoAtiva) {
        $mensagem = 'A recuperação de palavra-passe está temporariamente desactivada. Contacte o administrador.';
        $tipo = 'warning';
    } elseif (!validar_email($email, $erro)) {
        $mensagem = $erro;
        $tipo = 'danger';
    } else {
        // Limitação: 3 pedidos por 15 minutos
        $agora = time();
        $pedidos = array_values(array_filter($_SESSION['reset_requests'] ?? [], fn($t) => ($agora - (int) $t) < 900));
        $_SESSION['reset_requests'] = $pedidos;

        if (count($pedidos) >= 3) {
            $mensagem = 'Foram efectuados demasiados pedidos. Aguarde 15 minutos antes de tentar novamente.';
            $tipo = 'warning';
        } else {
            $_SESSION['reset_requests'][] = $agora;

            $stmt = db()->prepare('SELECT idusuario, nume, email, estado FROM usuarios WHERE email = :e LIMIT 1');
            $stmt->execute([':e' => $email]);
            $user = $stmt->fetch();

            // Resposta genérica (não revela se o e-mail existe)
            $mensagem = 'Se o e-mail indicado estiver registado, receberá em breve as instruções de recuperação.';
            $tipo = 'success';

            if ($user && strtolower((string) $user['estado']) === 'ativo') {
                $token = bin2hex(random_bytes(32));
                $hash = hash('sha256', $token);
                $expira = date('Y-m-d H:i:s', time() + 3600);

                $up = db()->prepare('UPDATE usuarios SET reset_token = :t, reset_expires = :x WHERE idusuario = :id');
                $up->execute([':t' => $hash, ':x' => $expira, ':id' => (int) $user['idusuario']]);

                $link = absolute_url('/reset-password.php?token=' . urlencode($token) . '&email=' . urlencode((string) $user['email']));
                $corpo = '<p>Olá, <strong>' . e((string) $user['nume']) . '</strong>.</p>'
                    . '<p>Recebemos um pedido para redefinir a palavra-passe da sua conta no ' . e(config_get('app_nome', 'SIGExpediente')) . '.</p>'
                    . '<p style="margin:22px 0;text-align:center;">'
                    . '<a href="' . e($link) . '" style="display:inline-block;background:#14532d;color:#fff;padding:12px 22px;border-radius:8px;text-decoration:none;font-weight:600;">Redefinir palavra-passe</a>'
                    . '</p>'
                    . '<p style="font-size:12px;color:#64748b;">Este link é válido durante 60 minutos e só pode ser utilizado uma vez. '
                    . 'Se o botão não funcionar, copie e cole este endereço no navegador:<br><span style="word-break:break-all;">' . e($link) . '</span></p>';

                $enviado = enviar_email((string) $user['email'], 'Recuperação de palavra-passe · ' . config_get('app_nome', 'SIGExpediente'), email_template('Recuperação de palavra-passe', $corpo));

                log_action('Pedido de recuperação de palavra-passe', ['idusuario' => (int) $user['idusuario']], (string) $user['nume'], (int) $user['idusuario']);

                if (!$enviado && $modoDev) {
                    $linkDev = $link;
                    $mensagem = 'O envio de e-mail não está configurado. Em modo de desenvolvimento, utilize o link abaixo.';
                    $tipo = 'warning';
                }
            }
        }
    }
}

$auth_title = 'Recuperar palavra-passe';
$auth_subtitle = 'Indique o e-mail associado à sua conta.';

require __DIR__ . '/includes/auth_header.php';
?>
<?php if ($mensagem !== ''): ?>
    <div class="flash flash-<?= e($tipo) ?> mb-2" role="alert">
        <?= svg_icon($tipo === 'success' ? 'check' : ($tipo === 'danger' ? 'alert' : 'info')) ?>
        <span><?= e($mensagem) ?></span>
    </div>
<?php endif; ?>

<?php if ($linkDev !== ''): ?>
    <div class="note warning mb-2" style="flex-direction:column;align-items:flex-start;gap:6px;">
        <span style="font-weight:700;font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;">Link de recuperação (dev)</span>
        <a href="<?= e($linkDev) ?>" style="word-break:break-all;font-size:.76rem;"><?= e($linkDev) ?></a>
    </div>
<?php endif; ?>

<?php if ($recuperacaoAtiva): ?>
<form method="post" action="<?= e(url('/forgot-password.php')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="field mb-2">
        <label for="email">E-mail registado</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>"
               placeholder="nome@instituicao.gov.mz" maxlength="50" required autofocus>
    </div>
    <button type="submit" class="btn btn-primary btn-lg btn-block">
        Enviar instruções <?= svg_icon('mail') ?>
    </button>
</form>
<?php else: ?>
    <div class="note warning">
        <?= svg_icon('alert') ?>
        <span>A recuperação por e-mail está desactivada. Contacte o administrador do sistema para redefinir a sua palavra-passe.</span>
    </div>
<?php endif; ?>

<div class="auth-links mt-2">
    <a href="<?= e(url('/login.php')) ?>"><?= svg_icon('arrow-left', 'icon icon-sm') ?> Voltar ao início de sessão</a>
</div>
<?php require __DIR__ . '/includes/auth_footer.php'; ?>
