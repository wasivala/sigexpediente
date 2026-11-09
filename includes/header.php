<?php
/**
 * Cabeçalho comum: <head>, barra lateral e topo.
 * Variáveis esperadas (opcionais): $page_title, $page_subtitle, $page_actions, $active.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/icons.php';

require_login();

$u = current_user();
$appNome = config_get('app_nome', 'SIGExpediente');
$appSub  = config_get('app_subtitulo', 'Gestão de Entrada e Saída de Expedientes');
$active  = $active ?? current_path();
$page_title = $page_title ?? 'Painel';
$page_subtitle = $page_subtitle ?? '';
$page_actions = $page_actions ?? '';
$flashes = get_flashes();
$notifNaoLidas = can('ver_notificacoes') ? notificacoes_nao_lidas((int) $u['idusuario']) : 0;

$gruposNav = [
    'Gestão' => [
        ['dashboard.php', 'Painel', 'dashboard', 'ver_dashboard'],
        ['expedientes.php', 'Entrada de Expedientes', 'inbox', 'ver_expedientes'],
        ['saidas.php', 'Saídas de Expedientes', 'outbox', 'ver_saidas'],
        ['requeridos.php', 'Requeridos', 'users', 'ver_requeridos'],
        ['beneficiarios.php', 'Beneficiários', 'handshake', 'ver_beneficiarios'],
        ['instituicoes.php', 'Instituições', 'building', 'ver_instituicoes'],
    ],
    'Análise' => [
        ['relatorios.php', 'Relatórios', 'report', 'ver_relatorios'],
    ],
    'Sistema' => [
        ['notificacoes.php', 'Notificações', 'bell', 'ver_notificacoes'],
        ['logs.php', 'Histórico / Logs', 'history', 'ver_logs'],
        ['usuarios.php', 'Utilizadores', 'user', 'gerir_usuarios'],
        ['configuracoes.php', 'Configurações', 'settings', 'gerir_configuracoes'],
        ['backup.php', 'Backup do Sistema', 'database', 'gerir_backup'],
    ],
];
?>
<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#14532d">
    <title><?= e($page_title) ?> · <?= e($appNome) ?></title>
    <link rel="icon" type="image/png" href="<?= e(url('/assets/img/emblema-republica.png')) ?>">
    <link rel="apple-touch-icon" href="<?= e(url('/assets/img/emblema-republica.png')) ?>">
    <link rel="stylesheet" href="<?= e(url('/assets/css/style.css')) ?>">
    <?php if (!empty($extra_head)): ?><?= $extra_head ?><?php endif; ?>
</head>
<body>
<a class="skip-link" href="#conteudo">Saltar para o conteúdo</a>

<div class="app-shell">
    <aside class="sidebar" id="sidebar" aria-label="Navegação principal">
        <div class="brand">
            <div class="brand-mark">
                <img src="<?= e(url('/assets/img/emblema-republica.png')) ?>"
                     alt="Emblema da República de Moçambique" width="64" height="64">
            </div>
            <div class="brand-text">
                <strong>RBDA</strong>
                <span><?= e($appNome) ?></span>
            </div>
            <button class="sidebar-close" id="sidebarClose" type="button" aria-label="Fechar menu">
                <?= svg_icon('x') ?>
            </button>
        </div>

        <nav class="nav">
            <?php foreach ($gruposNav as $grupo => $itens): ?>
                <?php
                $visiveis = array_values(array_filter($itens, fn($i) => can($i[3])));
                if (!$visiveis) {
                    continue;
                }
                ?>
                <div class="nav-group">
                    <span class="nav-label"><?= e($grupo) ?></span>
                    <?php foreach ($visiveis as [$href, $label, $icone, $cap]): ?>
                        <a class="nav-link<?= $active === $href ? ' is-active' : '' ?>" href="<?= e(url('/' . $href)) ?>">
                            <?= svg_icon($icone) ?>
                            <span><?= e($label) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <span class="avatar"><?= e(iniciais((string) $u['nume'])) ?></span>
                <div>
                    <strong><?= e($u['nume']) ?></strong>
                    <span><?= e($u['grupo_acesso']) ?></span>
                </div>
            </div>
        </div>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="main-area">
        <header class="topbar">
            <button class="icon-btn menu-toggle" id="menuToggle" type="button" aria-label="Abrir menu">
                <span></span><span></span><span></span>
            </button>

            <div class="topbar-title">
                <h1><?= e($page_title) ?></h1>
                <?php if ($page_subtitle !== ''): ?>
                    <p><?= e($page_subtitle) ?></p>
                <?php endif; ?>
            </div>

            <div class="topbar-actions">
                <?php if ($page_actions !== ''): ?>
                    <div class="page-actions"><?= $page_actions ?></div>
                <?php endif; ?>

                <?php if (can('ver_notificacoes')): ?>
                    <a class="icon-btn notif-btn" href="<?= e(url('/notificacoes.php')) ?>"
                       title="Notificações" aria-label="Notificações<?= $notifNaoLidas > 0 ? ' (' . $notifNaoLidas . ' não lidas)' : '' ?>">
                        <?= svg_icon('bell') ?>
                        <?php if ($notifNaoLidas > 0): ?>
                            <span class="notif-badge"><?= $notifNaoLidas > 99 ? '99+' : $notifNaoLidas ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>

                <details class="user-menu">
                    <summary>
                        <span class="avatar avatar-sm"><?= e(iniciais((string) $u['nume'])) ?></span>
                        <span class="user-menu-name"><?= e($u['nume']) ?></span>
                        <?= svg_icon('chevron', 'icon icon-sm') ?>
                    </summary>
                    <div class="user-menu-panel">
                        <div class="user-menu-head">
                            <strong><?= e($u['nume']) ?></strong>
                            <span><?= e($u['email']) ?></span>
                            <span class="badge badge-neutral"><?= e($u['grupo_acesso']) ?></span>
                        </div>
                        <a href="<?= e(url('/perfil.php')) ?>"><?= svg_icon('user', 'icon icon-sm') ?> Meu perfil</a>
                        <a href="<?= e(url('/logout.php')) ?>"><?= svg_icon('logout', 'icon icon-sm') ?> Terminar sessão</a>
                    </div>
                </details>
            </div>
        </header>

        <main class="content" id="conteudo">
            <?php if ($flashes): ?>
                <div class="flash-stack">
                    <?php foreach ($flashes as $flash): ?>
                        <div class="flash flash-<?= e($flash['type']) ?>" role="alert">
                            <?= svg_icon($flash['type'] === 'success' ? 'check' : ($flash['type'] === 'danger' ? 'alert' : 'info')) ?>
                            <span><?= e($flash['message']) ?></span>
                            <button type="button" class="flash-close" aria-label="Fechar"><?= svg_icon('x', 'icon icon-sm') ?></button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
