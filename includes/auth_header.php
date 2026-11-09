<?php
/**
 * Layout de autenticação (lado esquerdo informativo + cartão).
 * Variáveis: $auth_title, $auth_subtitle (opcional)
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/icons.php';

$appNome = config_get('app_nome', 'SIGExpediente');
$appSub  = config_get('app_subtitulo', 'Gestao de Entrada e Saida de Expedientes');
$auth_title = $auth_title ?? 'Departamento de Pessoal';
$auth_subtitle = $auth_subtitle ?? '';
$flashes = get_flashes();
?>
<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0a3f6b">
    <title><?= e($auth_title) ?> · <?= e($appNome) ?></title>
    <link rel="icon" type="image/png" href="<?= e(url('/assets/img/emblema-republica.png')) ?>">
    <link rel="apple-touch-icon" href="<?= e(url('/assets/img/emblema-republica.png')) ?>">
    <link rel="stylesheet" href="<?= e(url('/assets/css/style.css')) ?>">
</head>
<body>
<div class="auth-wrap">
    <aside class="auth-aside">
        <div class="auth-brand">
            <div class="brand-mark">
                <img src="<?= e(url('/assets/img/emblema-republica.png')) ?>"
                     alt="Emblema da República de Moçambique" width="54" height="54">
            </div>
            <div>
                <strong><?= e($appNome) ?></strong>
                <span><?= e($appSub) ?></span>
            </div>
        </div>

        <div class="auth-hero">
            <h2>Controlo rigoroso de entrada e saída de expediente.</h2>
            <p>Registo, triagem, encaminhamento e arquivo de documentos num único sistema, com histórico completo e relatórios institucionais.</p>
        </div>

        <div class="auth-points">
            <div class="auth-point"><?= svg_icon('inbox') ?><span>Registo de expedientes com referência, proveniência, assunto e documento digitalizado.</span></div>
            <div class="auth-point"><?= svg_icon('outbox') ?><span>Controlo de saídas, destinos e prazos de encaminhamento.</span></div>
            <div class="auth-point"><?= svg_icon('report') ?><span>Relatórios individuais e colectivos prontos para impressão.</span></div>
            <div class="auth-point"><?= svg_icon('history') ?><span>Histórico de todas as operações e recuperação segura de credenciais.</span></div>
        </div>

        <div class="auth-meta">
            <?= e(config_get('inst_linha4', 'Departamento de Pessoal')) ?> · <?= e(config_get('inst_linha5', 'Repartição de Base de Dados e Arquivo')) ?>
        </div>
    </aside>

    <main class="auth-main">
        <div class="auth-card">
            <?php if ($flashes): ?>
                <div class="flash-stack">
                    <?php foreach ($flashes as $flash): ?>
                        <div class="flash flash-<?= e($flash['type']) ?>" role="alert">
                            <?= svg_icon($flash['type'] === 'success' ? 'check' : ($flash['type'] === 'danger' ? 'alert' : 'info')) ?>
                            <span><?= e($flash['message']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="auth-head">
                <div class="auth-logo">
                    <img src="<?= e(url('/assets/img/emblema-republica.png')) ?>"
                         alt="Emblema da República de Moçambique" width="76" height="76">
                </div>
                <h1><?= e($auth_title) ?></h1>
                <?php if ($auth_subtitle !== ''): ?><p><?= e($auth_subtitle) ?></p><?php endif; ?>
            </div>

            <div class="card">
                <div class="card-body">
