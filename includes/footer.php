        </main>

        <footer class="app-footer">
            <span>&copy; <?= date('Y') ?> <?= e(config_get('app_nome', 'SIGExpediente')) ?> · <?= e(config_get('inst_linha4', 'Departamento de Pessoal')) ?></span>
            <span class="footer-dot">·</span>
            <span>Repartição de Base de Dados e Arquivo</span>
        </footer>
    </div>
</div>

<script src="<?= e(url('/assets/js/app.js')) ?>"></script>
<?php if (!empty($extra_scripts)): ?><?= $extra_scripts ?><?php endif; ?>
</body>
</html>
