<?php
/**
 * Gestão de cópias de segurança: criação, download, restauro e eliminação.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/backup.php';
require_can('gerir_backup');

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $acao = post('acao');

    if ($acao === 'criar') {
        try {
            $info = backup_criar();
            log_action('Backup criado: ' . $info['nome']);
            flash_success('Cópia de segurança criada: ' . $info['nome'] . ' (' . backup_tamanho($info['tamanho']) . ', ' . numero_pt($info['registos']) . ' registo(s)).');
        } catch (Throwable $e) {
            error_log('Erro ao criar backup: ' . $e->getMessage());
            flash_error('Não foi possível criar a cópia de segurança: ' . $e->getMessage());
        }
        redirect('/backup.php');
    }

    if ($acao === 'eliminar') {
        $nome = post('nome');
        if (backup_apagar($nome)) {
            log_action('Backup eliminado: ' . $nome);
            flash_success('Cópia de segurança eliminada.');
        } else {
            flash_error('A cópia de segurança indicada não foi encontrada.');
        }
        redirect('/backup.php');
    }

    flash_warning('Acção inválida.');
    redirect('/backup.php');
}

$backups = backup_listar();

$tamanhoBd = 0.0;
try {
    $stmt = db()->query(
        'SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2)
         FROM information_schema.TABLES
         WHERE table_schema = DATABASE()'
    );
    $tamanhoBd = (float) $stmt->fetchColumn();
} catch (Throwable $e) {
    $tamanhoBd = 0.0;
}

$totalTabelas = 0;
try {
    $totalTabelas = count(db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM));
} catch (Throwable $e) {
    $totalTabelas = 0;
}

$ultimo = $backups[0] ?? null;

$page_title = 'Backup do Sistema';
$page_subtitle = 'Cópias de segurança e reposição da base de dados';
$active = 'backup.php';
require __DIR__ . '/includes/header.php';
?>

<div class="grid grid-4 mb-2">
    <div class="stat">
        <div class="stat-icon blue"><?= svg_icon('database') ?></div>
        <div class="stat-body">
            <span class="stat-label">Base de dados</span>
            <div class="stat-value"><?= e(numero_pt($tamanhoBd, 2)) ?> <span style="font-size:.8rem;font-weight:600">MB</span></div>
            <div class="stat-hint"><?= numero_pt($totalTabelas) ?> tabela(s)</div>
        </div>
    </div>
    <div class="stat">
        <div class="stat-icon gold"><?= svg_icon('download') ?></div>
        <div class="stat-body">
            <span class="stat-label">Cópias guardadas</span>
            <div class="stat-value"><?= numero_pt(count($backups)) ?></div>
            <div class="stat-hint">Ficheiros .sql em storage/backups</div>
        </div>
    </div>
    <div class="stat">
        <div class="stat-icon violet"><?= svg_icon('clock') ?></div>
        <div class="stat-body">
            <span class="stat-label">Última cópia</span>
            <div class="stat-value" style="font-size:1.05rem">
                <?= $ultimo ? e(data_hora_pt(date('Y-m-d H:i:s', $ultimo['data']))) : '—' ?>
            </div>
            <div class="stat-hint"><?= $ultimo ? e($ultimo['nome']) : 'Nenhuma cópia criada' ?></div>
        </div>
    </div>
    <div class="stat">
        <div class="stat-icon red"><?= svg_icon('alert') ?></div>
        <div class="stat-body">
            <span class="stat-label">Reposição</span>
            <div class="stat-value" style="font-size:1.05rem">Atenção</div>
            <div class="stat-hint">Substitui os dados actuais</div>
        </div>
    </div>
</div>

<div class="flex-between mb-2 wrap gap-2">
    <span class="text-sm text-muted">Os ficheiros ficam guardados no servidor, na pasta <code>storage/backups</code>.</span>
    <form method="post" action="<?= e(url('/backup.php')) ?>" data-confirm="Criar uma nova cópia de segurança completa da base de dados?">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="criar">
        <button type="submit" class="btn btn-primary"><?= svg_icon('database', 'icon icon-sm') ?> Criar cópia agora</button>
    </form>
</div>

<div class="card">
    <div class="card-head">
        <div><h3>Cópias de segurança disponíveis</h3><span class="sub"><?= numero_pt(count($backups)) ?> ficheiro(s)</span></div>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>Ficheiro</th>
                    <th>Data</th>
                    <th>Tamanho</th>
                    <th class="text-right">Acções</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$backups): ?>
                <tr><td colspan="4">
                    <div class="empty">
                        <?= svg_icon('database') ?>
                        <h4>Nenhuma cópia de segurança</h4>
                        <p>Crie a primeira cópia para proteger os dados do sistema.</p>
                    </div>
                </td></tr>
            <?php else: ?>
                <?php foreach ($backups as $b): ?>
                    <tr>
                        <td class="cell-mono cell-strong"><?= e($b['nome']) ?></td>
                        <td class="nowrap"><?= e(data_hora_pt(date('Y-m-d H:i:s', $b['data']))) ?></td>
                        <td class="nowrap"><?= e(backup_tamanho($b['tamanho'])) ?></td>
                        <td>
                            <div class="actions" style="justify-content:flex-end">
                                <a class="btn-icon" href="<?= e(url('/backup_download.php?f=' . urlencode($b['nome']))) ?>" title="Descarregar"><?= svg_icon('download') ?></a>
                                <form method="post" action="<?= e(url('/backup_restore.php')) ?>" style="display:inline"
                                      data-confirm="Restaurar a base de dados a partir de <?= e($b['nome']) ?>? Todos os dados actuais serão substituídos.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="ficheiro" value="<?= e($b['nome']) ?>">
                                    <input type="hidden" name="criar_antes" value="1">
                                    <button type="submit" class="btn-icon" title="Restaurar"><?= svg_icon('history') ?></button>
                                </form>
                                <form method="post" action="<?= e(url('/backup.php')) ?>" style="display:inline"
                                      data-confirm="Eliminar o ficheiro <?= e($b['nome']) ?>?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="acao" value="eliminar">
                                    <input type="hidden" name="nome" value="<?= e($b['nome']) ?>">
                                    <button type="submit" class="btn-icon danger" title="Eliminar"><?= svg_icon('trash') ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="grid grid-2 mt-2">
    <div class="card">
        <div class="card-head"><div><h3><?= svg_icon('upload', 'icon icon-sm') ?> Restaurar a partir de ficheiro</h3><span class="sub">Carregue uma cópia .sql gerada por este sistema</span></div></div>
        <div class="card-body">
            <form method="post" action="<?= e(url('/backup_restore.php')) ?>" enctype="multipart/form-data"
                  data-confirm="Restaurar a base de dados a partir do ficheiro carregado? Todos os dados actuais serão substituídos.">
                <?= csrf_field() ?>
                <div class="field mb-2">
                    <label for="backup_file">Ficheiro de backup (.sql)</label>
                    <input type="file" id="backup_file" name="backup_file" accept=".sql" required>
                    <span class="hint">Tamanho máximo: 50 MB.</span>
                </div>
                <label class="flex-center mb-2" style="font-size:.8rem;color:var(--muted)">
                    <input type="checkbox" name="criar_antes" value="1" checked style="width:auto;min-height:auto;">
                    Criar automaticamente uma cópia de segurança antes de restaurar
                </label>
                <button type="submit" class="btn btn-danger-soft"><?= svg_icon('upload', 'icon icon-sm') ?> Restaurar base de dados</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h3>Recomendações</h3></div>
        <div class="card-body">
            <ul class="text-sm text-muted" style="margin:0;padding-left:18px;line-height:1.8">
                <li>Guarde as cópias também fora do servidor (disco externo ou nuvem institucional).</li>
                <li>Crie uma cópia antes e depois de alterações importantes na estrutura de dados.</li>
                <li>O restauro substitui <strong>todas</strong> as tabelas existentes; confirme sempre o ficheiro escolhido.</li>
                <li>Os ficheiros em <code>storage/backups</code> devem estar protegidos de acesso público pelo servidor web.</li>
            </ul>
            <div class="note mt-2">
                <?= svg_icon('info') ?>
                <span>O backup inclui estrutura, dados, configurações e documentos digitalizados (guardados na base de dados).</span>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
