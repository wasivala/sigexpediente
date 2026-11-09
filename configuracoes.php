<?php
/**
 * Configurações do sistema: identidade, cabeçalho institucional,
 * assinantes de relatório, SMTP e segurança.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_can('gerir_configuracoes');

$chaves = [
    'app_nome', 'app_subtitulo',
    'inst_linha1', 'inst_linha2', 'inst_linha3', 'inst_linha4', 'inst_linha5',
    'relatorio_assunto', 'relatorio_assinante1', 'relatorio_nome1', 'relatorio_posto1',
    'relatorio_assinante2', 'relatorio_nome2', 'relatorio_posto2',
    'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_secure',
    'mail_from', 'mail_from_nome', 'recuperacao_ativa',
    'sessao_minutos', 'itens_por_pagina', 'modo_desenvolvimento',
];

$cfg = [];
foreach ($chaves as $chave) {
    $cfg[$chave] = (string) config_get($chave, '');
}
$cfg['smtp_pass'] = '';

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    foreach (array_keys($cfg) as $chave) {
        if ($chave === 'smtp_pass') {
            continue;
        }
        $cfg[$chave] = post($chave);
    }

    // Identidade da aplicação
    if (!validar_texto($cfg['app_nome'], 'Nome da aplicação', 2, 50, $e)) { $erros['app_nome'] = $e; }
    if (mb_strlen($cfg['app_subtitulo']) > 100) { $erros['app_subtitulo'] = 'O subtítulo não pode exceder 100 caracteres.'; }

    // Cabeçalho institucional
    for ($i = 1; $i <= 5; $i++) {
        if (mb_strlen($cfg['inst_linha' . $i]) > 100) {
            $erros['inst_linha' . $i] = 'A linha não pode exceder 100 caracteres.';
        }
    }
    if (!validar_texto($cfg['relatorio_assunto'], 'Assunto do relatório', 3, 150, $e)) { $erros['relatorio_assunto'] = $e; }

    if (!validar_texto($cfg['relatorio_assinante1'], 'Assinante 1', 2, 100, $e)) { $erros['relatorio_assinante1'] = $e; }
    if (!validar_texto($cfg['relatorio_assinante2'], 'Assinante 2', 2, 100, $e)) { $erros['relatorio_assinante2'] = $e; }
    if (!validar_nome($cfg['relatorio_nome1'], 'Nome do assinante 1', 100, $e)) { $erros['relatorio_nome1'] = $e; }
    if (!validar_nome($cfg['relatorio_nome2'], 'Nome do assinante 2', 100, $e)) { $erros['relatorio_nome2'] = $e; }
    if ($cfg['relatorio_posto1'] !== '' && !validar_nome($cfg['relatorio_posto1'], 'Posto do assinante 1', 50, $e)) { $erros['relatorio_posto1'] = $e; }
    if ($cfg['relatorio_posto2'] !== '' && !validar_nome($cfg['relatorio_posto2'], 'Posto do assinante 2', 50, $e)) { $erros['relatorio_posto2'] = $e; }

    // SMTP / e-mail
    if ($cfg['smtp_host'] !== '' && !preg_match('/^[A-Za-z0-9\.\-]+$/', $cfg['smtp_host'])) {
        $erros['smtp_host'] = 'Indique um servidor SMTP válido (ex.: smtp.instituicao.gov.mz).';
    }
    $porta = (int) $cfg['smtp_port'];
    if ($porta < 1 || $porta > 65535) { $erros['smtp_port'] = 'A porta deve estar entre 1 e 65535.'; }
    if (!in_array($cfg['smtp_secure'], ['tls', 'ssl', 'none'], true)) { $erros['smtp_secure'] = 'Selecione um modo de segurança válido.'; }
    if ($cfg['mail_from'] !== '' && !filter_var($cfg['mail_from'], FILTER_VALIDATE_EMAIL)) { $erros['mail_from'] = 'Indique um e-mail de remetente válido.'; }
    if (mb_strlen($cfg['mail_from_nome']) > 50) { $erros['mail_from_nome'] = 'O nome do remetente não pode exceder 50 caracteres.'; }
    if (!in_array($cfg['recuperacao_ativa'], ['0', '1'], true)) { $erros['recuperacao_ativa'] = 'Selecione uma opção válida.'; }

    // Segurança / listagens
    $sessao = (int) $cfg['sessao_minutos'];
    if ($sessao < 5 || $sessao > 240) { $erros['sessao_minutos'] = 'A sessão deve durar entre 5 e 240 minutos.'; }
    $porPagina = (int) $cfg['itens_por_pagina'];
    if ($porPagina < 5 || $porPagina > 100) { $erros['itens_por_pagina'] = 'A listagem deve mostrar entre 5 e 100 registos.'; }
    if (!in_array($cfg['modo_desenvolvimento'], ['0', '1'], true)) { $erros['modo_desenvolvimento'] = 'Selecione uma opção válida.'; }

    if (!$erros) {
        foreach ($chaves as $chave) {
            if ($chave === 'smtp_pass') {
                continue;
            }
            config_set($chave, $cfg[$chave]);
        }

        $novaPass = (string) ($_POST['smtp_pass'] ?? '');
        $limparPass = isset($_POST['limpar_smtp_pass']) && $_POST['limpar_smtp_pass'] === '1';
        if ($novaPass !== '') {
            config_set('smtp_pass', $novaPass);
        } elseif ($limparPass) {
            config_set('smtp_pass', '');
        }

        log_action('Configurações do sistema actualizadas');
        flash_success('Configurações guardadas com sucesso.');
        redirect('/configuracoes.php');
    }
}

$page_title = 'Configurações';
$page_subtitle = 'Identidade institucional, relatórios, e-mail e segurança';
$active = 'configuracoes.php';
require __DIR__ . '/includes/header.php';
?>

<?php if ($erros): ?>
    <div class="flash flash-danger mb-2" role="alert"><?= svg_icon('alert') ?><span>Corrija os campos assinalados antes de guardar.</span></div>
<?php endif; ?>

<form method="post" action="<?= e(url('/configuracoes.php')) ?>" novalidate autocomplete="off">
    <?= csrf_field() ?>

    <div class="grid grid-2">
        <div class="card">
            <div class="card-head"><div><h3><?= svg_icon('settings', 'icon icon-sm') ?> Identidade da aplicação</h3><span class="sub">Nome e apresentação geral</span></div></div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="field">
                        <label for="app_nome">Nome da aplicação <span class="req">*</span></label>
                        <input type="text" id="app_nome" name="app_nome" maxlength="50" required
                               value="<?= e($cfg['app_nome']) ?>"
                               class="<?= isset($erros['app_nome']) ? 'is-invalid' : '' ?>">
                        <?php if (isset($erros['app_nome'])): ?><span class="field-error"><?= e($erros['app_nome']) ?></span><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="app_subtitulo">Subtítulo</label>
                        <input type="text" id="app_subtitulo" name="app_subtitulo" maxlength="100"
                               value="<?= e($cfg['app_subtitulo']) ?>"
                               class="<?= isset($erros['app_subtitulo']) ? 'is-invalid' : '' ?>">
                        <?php if (isset($erros['app_subtitulo'])): ?><span class="field-error"><?= e($erros['app_subtitulo']) ?></span><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="itens_por_pagina">Registos por página</label>
                        <input type="number" id="itens_por_pagina" name="itens_por_pagina" min="5" max="100" required
                               value="<?= e($cfg['itens_por_pagina']) ?>"
                               class="<?= isset($erros['itens_por_pagina']) ? 'is-invalid' : '' ?>">
                        <?php if (isset($erros['itens_por_pagina'])): ?><span class="field-error"><?= e($erros['itens_por_pagina']) ?></span><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="modo_desenvolvimento">Modo de desenvolvimento</label>
                        <select id="modo_desenvolvimento" name="modo_desenvolvimento">
                            <option value="0"<?= $cfg['modo_desenvolvimento'] === '0' ? ' selected' : '' ?>>Não</option>
                            <option value="1"<?= $cfg['modo_desenvolvimento'] === '1' ? ' selected' : '' ?>>Sim</option>
                        </select>
                        <span class="hint">Quando activo e sem SMTP configurado, o link de recuperação é mostrado no ecrã.</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><div><h3><?= svg_icon('shield', 'icon icon-sm') ?> Segurança da sessão</h3><span class="sub">Controlo de acesso</span></div></div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="field">
                        <label for="sessao_minutos">Expiração por inactividade (minutos)</label>
                        <input type="number" id="sessao_minutos" name="sessao_minutos" min="5" max="240" required
                               value="<?= e($cfg['sessao_minutos']) ?>"
                               class="<?= isset($erros['sessao_minutos']) ? 'is-invalid' : '' ?>">
                        <span class="hint">Entre 5 e 240 minutos. O limite absoluto da sessão é de 2 horas.</span>
                        <?php if (isset($erros['sessao_minutos'])): ?><span class="field-error"><?= e($erros['sessao_minutos']) ?></span><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="recuperacao_ativa">Recuperação de palavra-passe por e-mail</label>
                        <select id="recuperacao_ativa" name="recuperacao_ativa">
                            <option value="1"<?= $cfg['recuperacao_ativa'] === '1' ? ' selected' : '' ?>>Activa</option>
                            <option value="0"<?= $cfg['recuperacao_ativa'] === '0' ? ' selected' : '' ?>>Desactivada</option>
                        </select>
                        <?php if (isset($erros['recuperacao_ativa'])): ?><span class="field-error"><?= e($erros['recuperacao_ativa']) ?></span><?php endif; ?>
                    </div>
                </div>

                <div class="note mt-2">
                    <?= svg_icon('info') ?>
                    <span>As palavras-passe são sempre guardadas como <strong>hash bcrypt</strong>. Os tokens de recuperação são guardados com <strong>SHA-256</strong> e expiram em 60 minutos.</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-2">
        <div class="card-head"><div><h3><?= svg_icon('building', 'icon icon-sm') ?> Cabeçalho institucional dos relatórios</h3><span class="sub">Linhas apresentadas no topo do modelo de relatório</span></div></div>
        <div class="card-body">
            <div class="form-grid">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <div class="field">
                        <label for="inst_linha<?= $i ?>">Linha <?= $i ?></label>
                        <input type="text" id="inst_linha<?= $i ?>" name="inst_linha<?= $i ?>" maxlength="100"
                               value="<?= e($cfg['inst_linha' . $i]) ?>"
                               class="<?= isset($erros['inst_linha' . $i]) ? 'is-invalid' : '' ?>">
                        <?php if (isset($erros['inst_linha' . $i])): ?><span class="field-error"><?= e($erros['inst_linha' . $i]) ?></span><?php endif; ?>
                    </div>
                <?php endfor; ?>
                <div class="field full">
                    <label for="relatorio_assunto">Assunto padrão do relatório</label>
                    <input type="text" id="relatorio_assunto" name="relatorio_assunto" maxlength="150"
                           value="<?= e($cfg['relatorio_assunto']) ?>"
                           class="<?= isset($erros['relatorio_assunto']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($erros['relatorio_assunto'])): ?><span class="field-error"><?= e($erros['relatorio_assunto']) ?></span><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-2">
        <div class="card-head"><div><h3><?= svg_icon('user', 'icon icon-sm') ?> Assinantes do relatório</h3><span class="sub">Blocos de assinatura impressos no fim do relatório</span></div></div>
        <div class="card-body">
            <div class="form-grid cols-3">
                <div class="field">
                    <label for="relatorio_assinante1">Cargo do 1.º assinante</label>
                    <input type="text" id="relatorio_assinante1" name="relatorio_assinante1" maxlength="100"
                           value="<?= e($cfg['relatorio_assinante1']) ?>"
                           class="<?= isset($erros['relatorio_assinante1']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($erros['relatorio_assinante1'])): ?><span class="field-error"><?= e($erros['relatorio_assinante1']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label for="relatorio_nome1">Nome do 1.º assinante</label>
                    <input type="text" id="relatorio_nome1" name="relatorio_nome1" maxlength="100" data-letras
                           value="<?= e($cfg['relatorio_nome1']) ?>"
                           class="<?= isset($erros['relatorio_nome1']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($erros['relatorio_nome1'])): ?><span class="field-error"><?= e($erros['relatorio_nome1']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label for="relatorio_posto1">Posto do 1.º assinante</label>
                    <input type="text" id="relatorio_posto1" name="relatorio_posto1" maxlength="50" data-letras
                           value="<?= e($cfg['relatorio_posto1']) ?>"
                           class="<?= isset($erros['relatorio_posto1']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($erros['relatorio_posto1'])): ?><span class="field-error"><?= e($erros['relatorio_posto1']) ?></span><?php endif; ?>
                </div>

                <div class="field">
                    <label for="relatorio_assinante2">Cargo do 2.º assinante</label>
                    <input type="text" id="relatorio_assinante2" name="relatorio_assinante2" maxlength="100"
                           value="<?= e($cfg['relatorio_assinante2']) ?>"
                           class="<?= isset($erros['relatorio_assinante2']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($erros['relatorio_assinante2'])): ?><span class="field-error"><?= e($erros['relatorio_assinante2']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label for="relatorio_nome2">Nome do 2.º assinante</label>
                    <input type="text" id="relatorio_nome2" name="relatorio_nome2" maxlength="100" data-letras
                           value="<?= e($cfg['relatorio_nome2']) ?>"
                           class="<?= isset($erros['relatorio_nome2']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($erros['relatorio_nome2'])): ?><span class="field-error"><?= e($erros['relatorio_nome2']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label for="relatorio_posto2">Posto do 2.º assinante</label>
                    <input type="text" id="relatorio_posto2" name="relatorio_posto2" maxlength="50" data-letras
                           value="<?= e($cfg['relatorio_posto2']) ?>"
                           class="<?= isset($erros['relatorio_posto2']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($erros['relatorio_posto2'])): ?><span class="field-error"><?= e($erros['relatorio_posto2']) ?></span><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-2">
        <div class="card-head"><div><h3><?= svg_icon('mail', 'icon icon-sm') ?> Servidor de e-mail (SMTP)</h3><span class="sub">Necessário para a recuperação de palavra-passe por e-mail</span></div></div>
        <div class="card-body">
            <div class="note mb-2">
                <?= svg_icon('info') ?>
                <span>Se o servidor SMTP estiver em branco, o sistema tenta usar a função <code>mail()</code> do PHP. Em ambiente de desenvolvimento, sem SMTP, o link de recuperação é apresentado directamente no ecrã.</span>
            </div>

            <div class="form-grid cols-3">
                <div class="field">
                    <label for="smtp_host">Servidor SMTP</label>
                    <input type="text" id="smtp_host" name="smtp_host" maxlength="100"
                           value="<?= e($cfg['smtp_host']) ?>" placeholder="smtp.instituicao.gov.mz"
                           class="<?= isset($erros['smtp_host']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($erros['smtp_host'])): ?><span class="field-error"><?= e($erros['smtp_host']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label for="smtp_port">Porta</label>
                    <input type="number" id="smtp_port" name="smtp_port" min="1" max="65535"
                           value="<?= e($cfg['smtp_port']) ?>"
                           class="<?= isset($erros['smtp_port']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($erros['smtp_port'])): ?><span class="field-error"><?= e($erros['smtp_port']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label for="smtp_secure">Segurança</label>
                    <select id="smtp_secure" name="smtp_secure">
                        <option value="tls"<?= $cfg['smtp_secure'] === 'tls' ? ' selected' : '' ?>>TLS (recomendado)</option>
                        <option value="ssl"<?= $cfg['smtp_secure'] === 'ssl' ? ' selected' : '' ?>>SSL</option>
                        <option value="none"<?= $cfg['smtp_secure'] === 'none' ? ' selected' : '' ?>>Sem encriptação</option>
                    </select>
                    <?php if (isset($erros['smtp_secure'])): ?><span class="field-error"><?= e($erros['smtp_secure']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label for="smtp_user">Utilizador SMTP</label>
                    <input type="text" id="smtp_user" name="smtp_user" maxlength="100" autocomplete="off"
                           value="<?= e($cfg['smtp_user']) ?>">
                </div>
                <div class="field">
                    <label for="smtp_pass">Palavra-passe SMTP</label>
                    <div class="input-group">
                        <input type="password" id="smtp_pass" name="smtp_pass" autocomplete="new-password"
                               placeholder="<?= config_get('smtp_pass', '') !== '' ? '•••••••• (manter actual)' : 'Sem palavra-passe' ?>">
                        <button type="button" class="toggle-pass" aria-label="Mostrar palavra-passe"><?= svg_icon('eye', 'icon icon-sm') ?></button>
                    </div>
                    <label class="flex-center" style="font-size:.74rem;color:var(--muted);">
                        <input type="checkbox" name="limpar_smtp_pass" value="1" style="width:auto;min-height:auto;"> Limpar palavra-passe guardada
                    </label>
                </div>
                <div class="field">
                    <label for="mail_from_nome">Nome do remetente</label>
                    <input type="text" id="mail_from_nome" name="mail_from_nome" maxlength="50"
                           value="<?= e($cfg['mail_from_nome']) ?>"
                           class="<?= isset($erros['mail_from_nome']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($erros['mail_from_nome'])): ?><span class="field-error"><?= e($erros['mail_from_nome']) ?></span><?php endif; ?>
                </div>
                <div class="field full">
                    <label for="mail_from">E-mail do remetente</label>
                    <input type="email" id="mail_from" name="mail_from" maxlength="100"
                           value="<?= e($cfg['mail_from']) ?>" placeholder="nao-responder@instituicao.gov.mz"
                           class="<?= isset($erros['mail_from']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($erros['mail_from'])): ?><span class="field-error"><?= e($erros['mail_from']) ?></span><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <a class="btn btn-outline" href="<?= e(url('/dashboard.php')) ?>">Cancelar</a>
        <button type="submit" class="btn btn-primary"><?= svg_icon('check', 'icon icon-sm') ?> Guardar configurações</button>
    </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
