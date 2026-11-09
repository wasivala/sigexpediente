-- =====================================================================
-- Migração SIGExpediente
--   * reconcilia nomes de tabelas legadas;
--   * collation utf8_general_ci;
--   * número de processo opcional + expediente interno;
--   * patente, proveniência e destino alargados;
--   * tabela de notificações de acesso.
--
-- Pode ser executada mais de uma vez (é idempotente).
-- =====================================================================

USE `sigexpediente`;

-- ---------------------------------------------------------------------
-- 1. Renomear tabelas legadas, apenas se existirem e se o nome novo
--    ainda não existir (preserva os dados existentes).
-- ---------------------------------------------------------------------
SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.tables
      WHERE table_schema = DATABASE() AND table_name = 'entrada_expedientes') > 0
    AND
    (SELECT COUNT(*) FROM information_schema.tables
      WHERE table_schema = DATABASE() AND table_name = 'expedientes') = 0,
    'RENAME TABLE `entrada_expedientes` TO `expedientes`',
    'DO 0'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.tables
      WHERE table_schema = DATABASE() AND table_name = 'instituicoes_sectores') > 0
    AND
    (SELECT COUNT(*) FROM information_schema.tables
      WHERE table_schema = DATABASE() AND table_name = 'instituicao') = 0,
    'RENAME TABLE `instituicoes_sectores` TO `instituicao`',
    'DO 0'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 2. Remover as colunas legadas `idinstituicao` (e respectivas FKs),
--    porque a aplicação guarda proveniência/destino pelo nome.
-- ---------------------------------------------------------------------
SET @fk := (
  SELECT constraint_name FROM information_schema.table_constraints
   WHERE table_schema = DATABASE() AND table_name = 'expedientes'
     AND constraint_type = 'FOREIGN KEY'
     AND constraint_name = 'fk_instituicao_entrada_expediente'
   LIMIT 1
);
SET @sql := IF(@fk IS NOT NULL,
  CONCAT('ALTER TABLE `expedientes` DROP FOREIGN KEY `', @fk, '`'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = 'expedientes'
     AND column_name = 'idinstituicao'
);
SET @sql := IF(@col > 0,
  'ALTER TABLE `expedientes` DROP COLUMN `idinstituicao`', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (
  SELECT constraint_name FROM information_schema.table_constraints
   WHERE table_schema = DATABASE() AND table_name = 'saida_expedientes'
     AND constraint_type = 'FOREIGN KEY'
     AND constraint_name = 'fk_instituicao_saida'
   LIMIT 1
);
SET @sql := IF(@fk IS NOT NULL,
  CONCAT('ALTER TABLE `saida_expedientes` DROP FOREIGN KEY `', @fk, '`'), 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = 'saida_expedientes'
     AND column_name = 'idinstituicao'
);
SET @sql := IF(@col > 0,
  'ALTER TABLE `saida_expedientes` DROP COLUMN `idinstituicao`', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 3. Collation utf8_general_ci
-- ---------------------------------------------------------------------
ALTER DATABASE `sigexpediente`
  CHARACTER SET utf8
  COLLATE utf8_general_ci;

ALTER TABLE `usuarios`           CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE `expedientes`        CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE `saida_expedientes`  CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE `requeridos`         CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE `beneficiarios`      CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE `instituicao`        CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE `logs`               CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE `configuracoes`      CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;

-- ---------------------------------------------------------------------
-- 4. Número de processo opcional + marca de expediente interno
-- ---------------------------------------------------------------------
ALTER TABLE `expedientes`
  MODIFY `nrm_processo` varchar(50) DEFAULT NULL COMMENT 'NULL quando o expediente é interno',
  MODIFY `proveniencia` varchar(150) NOT NULL,
  ADD COLUMN IF NOT EXISTS `interno` tinyint(1) NOT NULL DEFAULT 0
    COMMENT '1 = expediente interno (sem número de processo)' AFTER `nrm_processo`;

UPDATE `expedientes`
   SET `interno` = 1, `nrm_processo` = NULL
 WHERE `nrm_processo` IS NULL OR `nrm_processo` = '';

ALTER TABLE `saida_expedientes`
  MODIFY `destino` varchar(150) NOT NULL;

ALTER TABLE `requeridos`
  MODIFY `patente` varchar(50) NOT NULL;

-- ---------------------------------------------------------------------
-- 5. Notificações de acesso ao sistema
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notificacoes` (
  `idnotificacao` int(11) NOT NULL AUTO_INCREMENT,
  `idusuario` int(11) NOT NULL COMMENT 'Utilizador destinatário',
  `tipo` varchar(20) NOT NULL DEFAULT 'acesso',
  `mensagem` varchar(255) NOT NULL,
  `referencia` varchar(100) DEFAULT NULL,
  `lida` tinyint(1) NOT NULL DEFAULT 0,
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idnotificacao`),
  KEY `idx_notificacoes_usuario` (`idusuario`, `lida`, `criado_em`),
  CONSTRAINT `fk_notificacoes_usuario` FOREIGN KEY (`idusuario`)
    REFERENCES `usuarios` (`idusuario`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

ALTER TABLE `notificacoes` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;
