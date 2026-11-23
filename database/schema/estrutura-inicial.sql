CREATE TABLE `calendar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` timestamp NULL DEFAULT NULL,
  `bo_ativo` tinyint(4) DEFAULT NULL,
  `id_usuario` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_customers` int(11) DEFAULT NULL,
  `date_end` text,
  PRIMARY KEY (`id`,`id_usuario`),
  KEY `fk_calendar_usuario_idx` (`id_usuario`),
  KEY `fk_calendar_customers2` (`id_customers`),
  KEY `idx_calendar_id_customers` (`id_customers`),
  KEY `idx_calendar_id_usuario` (`id_usuario`),
  CONSTRAINT `fk_calendar_customers2` FOREIGN KEY (`id_customers`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_calendar_usuario2` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` mediumtext COLLATE utf8mb4_unicode_ci,
  `address` mediumtext COLLATE utf8mb4_unicode_ci,
  `status` mediumtext COLLATE utf8mb4_unicode_ci,
  `id_usuario` int(11) NOT NULL,
  `id_parent` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `observation` mediumtext COLLATE utf8mb4_unicode_ci,
  `bo_ativo` tinyint(1) DEFAULT '1',
  `bo_preference` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`,`id_usuario`),
  KEY `fk_customers_usuario1_idx` (`id_usuario`),
  KEY `idx_customers_phone` (`phone`(13)),
  KEY `idx_customers_id_usuario` (`id_usuario`),
  KEY `idx_customers_id_parent` (`id_parent`),
  CONSTRAINT `fk_customers_usuario1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `customers_phone` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bo_ativo` tinyint(4) DEFAULT '1',
  `bo_main` tinyint(4) DEFAULT '0',
  `bo_whatsapp` tinyint(4) DEFAULT '0',
  `phone` text NOT NULL,
  `id_customers` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `country_code` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_customersphone_customers2` (`id_customers`),
  KEY `idx_customers_phone_phone` (`phone`(13)),
  KEY `idx_customers_phone_id_customers` (`id_customers`),
  CONSTRAINT `fk_customersphone_customers2` FOREIGN KEY (`id_customers`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
CREATE TABLE `strategy` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `strategy` mediumtext COLLATE utf8mb4_unicode_ci,
  `id_usuario` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `url_sale` mediumtext COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `fk_strategy_usuario1_idx` (`id_usuario`),
  KEY `idx_strategy_id_usuario` (`id_usuario`),
  CONSTRAINT `fk_strategy_usuario1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `usuario` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` text,
  `email` text,
  `usuario` text,
  `password` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `bo_ativo` tinyint(1) DEFAULT '1',
  `img` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE `usuario_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `display_resolution` text,
  `os` text,
  `language` text,
  `device` text,
  `browser` text,
  `ip` text,
  `user_agent` text,
  `id_user` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_customerslog_usuario2` (`id_user`),
  CONSTRAINT `fk_customerslog_usuario2` FOREIGN KEY (`id_user`) REFERENCES `usuario` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
