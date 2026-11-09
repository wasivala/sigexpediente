-- =====================================================================
-- SIGExpediente - Sistema de Gestão de Entrada e Saída de Expediente
-- Base de dados: sigexpediente
-- Compatível com MariaDB 10.4+ / MySQL 5.7+
--
-- Estrutura original (modelo em anexo: sigexpediente.pdf) preservada.
-- Ajustes técnicos necessários encontram-se assinalados com "-- [AJUSTE]".
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `sigexpediente`
  DEFAULT CHARACTER SET utf8
  COLLATE utf8_general_ci;

USE `sigexpediente`;

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Tabela: usuarios
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `idusuario` int(11) NOT NULL AUTO_INCREMENT,
  `nume` varchar(50) NOT NULL COMMENT 'Nome do utilizador (nome original do modelo)',
  `email` varchar(50) NOT NULL,
  `senha` varchar(255) NOT NULL COMMENT '[AJUSTE] 255 para acomodar hash bcrypt',
  `grupo_acesso` varchar(20) NOT NULL DEFAULT 'Consulta',
  `reset_token` varchar(64) DEFAULT NULL COMMENT '[AJUSTE] recuperação de senha',
  `reset_expires` datetime DEFAULT NULL COMMENT '[AJUSTE] validade do token',
  `estado` varchar(10) NOT NULL DEFAULT 'Ativo' COMMENT '[AJUSTE] Ativo/Inativo',
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '[AJUSTE]',
  PRIMARY KEY (`idusuario`),
  UNIQUE KEY `uk_usuarios_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- ---------------------------------------------------------------------
-- Tabela: expedientes
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `expedientes`;
CREATE TABLE `expedientes` (
  `idexpediente` int(11) NOT NULL AUTO_INCREMENT,
  `descricao` varchar(100) NOT NULL,
  `nrm_processo` varchar(50) DEFAULT NULL COMMENT 'NULL quando o expediente é interno',
  `interno` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = expediente interno (sem número de processo)',
  `referencia` varchar(50) NOT NULL,
  `data_entrada` datetime NOT NULL,
  `proveniencia` varchar(150) NOT NULL,
  `Assunto` varchar(50) NOT NULL,
  `usuario` varchar(100) NOT NULL,
  `scan_documento` longblob DEFAULT NULL COMMENT '[AJUSTE] NULL quando não há digitalização',
  `status` varchar(10) NOT NULL DEFAULT 'pendente' COMMENT 'encurso|finalizado|pendente|arquivado',
  `idusuario` int(11) NOT NULL,
  PRIMARY KEY (`idexpediente`),
  KEY `fk_usuario_expediente` (`idusuario`),
  KEY `idx_exp_referencia` (`referencia`),
  KEY `idx_exp_status` (`status`),
  KEY `idx_exp_data` (`data_entrada`),
  CONSTRAINT `fk_usuario_expediente` FOREIGN KEY (`idusuario`) REFERENCES `usuarios` (`idusuario`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- ---------------------------------------------------------------------
-- Tabela: saida_expedientes
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `saida_expedientes`;
CREATE TABLE `saida_expedientes` (
  `idsaida` int(11) NOT NULL AUTO_INCREMENT,
  `descricao` varchar(100) NOT NULL,
  `referencia` varchar(50) NOT NULL,
  `destino` varchar(150) NOT NULL,
  `data_saida` datetime NOT NULL,
  `assunto` varchar(50) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `expediente` varchar(50) NOT NULL,
  `scan_documento` longblob DEFAULT NULL COMMENT '[AJUSTE] NULL quando não há digitalização',
  `idexpediente` int(11) NOT NULL,
  `idusuario` int(11) NOT NULL,
  PRIMARY KEY (`idsaida`),
  KEY `fk_expediente_saida` (`idexpediente`),
  KEY `fk_usuario_saida` (`idusuario`),
  KEY `idx_sai_referencia` (`referencia`),
  KEY `idx_sai_data` (`data_saida`),
  CONSTRAINT `fk_expediente_saida` FOREIGN KEY (`idexpediente`) REFERENCES `expedientes` (`idexpediente`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_usuario_saida` FOREIGN KEY (`idusuario`) REFERENCES `usuarios` (`idusuario`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- ---------------------------------------------------------------------
-- Tabela: requeridos
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `requeridos`;
CREATE TABLE `requeridos` (
  `idrequerido` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `patente` varchar(50) NOT NULL,
  `ramo` varchar(20) NOT NULL,
  `unidade` varchar(150) NOT NULL,
  `funcao` varchar(200) NOT NULL,
  `contacto` varchar(15) NOT NULL,
  `nuit` int(11) NOT NULL,
  `conta` int(11) NOT NULL,
  `banco` varchar(10) NOT NULL,
  `expediente` varchar(50) NOT NULL,
  `usurio` varchar(50) NOT NULL COMMENT 'Nome original do modelo (grafia mantida)',
  `idexpediente` int(11) NOT NULL,
  `idusuario` int(11) NOT NULL,
  PRIMARY KEY (`idrequerido`),
  KEY `fk_expediente_requerido` (`idexpediente`),
  KEY `fk_usuario_requerido` (`idusuario`),
  KEY `idx_req_nuit` (`nuit`),
  KEY `idx_req_nome` (`nome`),
  CONSTRAINT `fk_expediente_requerido` FOREIGN KEY (`idexpediente`) REFERENCES `expedientes` (`idexpediente`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_usuario_requerido` FOREIGN KEY (`idusuario`) REFERENCES `usuarios` (`idusuario`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- ---------------------------------------------------------------------
-- Tabela: beneficiarios
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `beneficiarios`;
CREATE TABLE `beneficiarios` (
  `idbeneficiario` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `contacto` varchar(20) NOT NULL,
  `endereco` varchar(50) NOT NULL,
  `conta` int(11) NOT NULL,
  `banco` varchar(10) NOT NULL,
  `qtd_beneficiario` int(11) NOT NULL,
  `expediente` varchar(50) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `idusuario` int(11) NOT NULL,
  `idexpediente` int(11) NOT NULL,
  PRIMARY KEY (`idbeneficiario`),
  KEY `fk_usuario_beneficiario` (`idusuario`),
  KEY `fk_expediente_beneficiario` (`idexpediente`),
  KEY `idx_ben_nome` (`nome`),
  CONSTRAINT `fk_usuario_beneficiario` FOREIGN KEY (`idusuario`) REFERENCES `usuarios` (`idusuario`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_expediente_beneficiario` FOREIGN KEY (`idexpediente`) REFERENCES `expedientes` (`idexpediente`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- ---------------------------------------------------------------------
-- Tabela: instituicao
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `instituicao`;
CREATE TABLE `instituicao` (
  `idinstituicao` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `tipo` varchar(25) NOT NULL,
  `estado` varchar(10) NOT NULL DEFAULT 'Ativo',
  PRIMARY KEY (`idinstituicao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- ---------------------------------------------------------------------
-- Tabela: logs
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `logs`;
CREATE TABLE `logs` (
  `idlog` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(50) NOT NULL,
  `accao` varchar(100) NOT NULL,
  `data` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `idbeneficiario` int(11) DEFAULT NULL COMMENT '[AJUSTE] NULL: o log pode referir-se a apenas uma entidade',
  `idexpediente` int(11) DEFAULT NULL,
  `idrequerido` int(11) DEFAULT NULL,
  `idsaida` int(11) DEFAULT NULL,
  `idusuario` int(11) DEFAULT NULL,
  PRIMARY KEY (`idlog`),
  KEY `fk_logs_beneficiario` (`idbeneficiario`),
  KEY `fk_logs_expediente` (`idexpediente`),
  KEY `fk_logs_requerido` (`idrequerido`),
  KEY `fk_logs_saida` (`idsaida`),
  KEY `fk_logs_usuario` (`idusuario`),
  KEY `idx_logs_data` (`data`),
  CONSTRAINT `fk_logs_beneficiario` FOREIGN KEY (`idbeneficiario`) REFERENCES `beneficiarios` (`idbeneficiario`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_logs_expediente` FOREIGN KEY (`idexpediente`) REFERENCES `expedientes` (`idexpediente`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_logs_requerido` FOREIGN KEY (`idrequerido`) REFERENCES `requeridos` (`idrequerido`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_logs_saida` FOREIGN KEY (`idsaida`) REFERENCES `saida_expedientes` (`idsaida`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_logs_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuarios` (`idusuario`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- ---------------------------------------------------------------------
-- Tabela: configuracoes  [AJUSTE] - necessário para o módulo Configurações
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `configuracoes`;
CREATE TABLE `configuracoes` (
  `chave` varchar(64) NOT NULL,
  `valor` text DEFAULT NULL,
  PRIMARY KEY (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- ---------------------------------------------------------------------
-- Tabela: notificacoes  [AJUSTE] - avisos de acesso ao sistema
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `notificacoes`;
CREATE TABLE `notificacoes` (
  `idnotificacao` int(11) NOT NULL AUTO_INCREMENT,
  `idusuario` int(11) NOT NULL COMMENT 'Utilizador destinatário',
  `tipo` varchar(20) NOT NULL DEFAULT 'acesso',
  `mensagem` varchar(255) NOT NULL,
  `referencia` varchar(100) DEFAULT NULL,
  `lida` tinyint(1) NOT NULL DEFAULT 0,
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idnotificacao`),
  KEY `idx_notificacoes_usuario` (`idusuario`, `lida`, `criado_em`),
  CONSTRAINT `fk_notificacoes_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuarios` (`idusuario`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- Dados iniciais
-- =====================================================================

-- Utilizador administrador padrão
-- E-mail: admin@sigexpediente.local
-- Senha: Admin@123  (altere após o primeiro acesso)
INSERT INTO `usuarios` (`nume`, `email`, `senha`, `grupo_acesso`, `estado`) VALUES
('Administrador', 'admin@sigexpediente.local', '$2y$10$wwoFjx8.nkPDHH/0C03k2Ot5AXsd/0FBRZKneaX31Ys.I.gAFTCJ.', 'Administrador', 'Ativo');

-- Cabeçalho institucional (modelo de relatório em anexo)
INSERT INTO `configuracoes` (`chave`, `valor`) VALUES
('app_nome', 'SIGExpediente'),
('app_subtitulo', 'Gestão de Entrada e Saída de Expediente'),
('inst_linha1', 'REPÚBLICA DE MOÇAMBIQUE'),
('inst_linha2', 'MINISTÉRIO DA DEFESA NACIONAL'),
('inst_linha3', 'ESTADO-MAIOR GENERAL'),
('inst_linha4', 'DEPARTAMENTO DE PESSOAL'),
('inst_linha5', 'REPARTIÇÃO DE BASE DE DADOS E ARQUIVO'),
('relatorio_assunto', 'Relatório de Pedido de Declaração de Vencimento'),
('relatorio_assinante1', 'O Diretor de Departamento de Pessoal'),
('relatorio_assinante2', 'O Chefe da Repartição de BDA'),
('relatorio_nome1', 'Frederico José Banto'),
('relatorio_posto1', 'Brigadeiro'),
('relatorio_nome2', 'Júlio Macamo Nelson'),
('relatorio_posto2', 'T. Coronel'),
('smtp_host', ''),
('smtp_port', '587'),
('smtp_user', ''),
('smtp_pass', ''),
('smtp_secure', 'tls'),
('mail_from', 'nao-responder@sigexpediente.local'),
('mail_from_nome', 'SIGExpediente'),
('recuperacao_ativa', '1'),
('sessao_minutos', '30'),
('itens_por_pagina', '15'),
('modo_desenvolvimento', '1');
