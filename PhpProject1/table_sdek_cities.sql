CREATE TABLE `prefix__sdek_cities` (
  `id` int(4) NOT NULL,
  `country` varchar(50) NOT NULL,
  `city` varchar(100) NOT NULL,
  `address` varchar(300) NOT NULL,
  `phones` varchar(100) NOT NULL,
  `time` varchar(150) NOT NULL,
  `code` varchar(10) NOT NULL,
  `upd` date NOT NULL,
  `active` int(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



ALTER TABLE `prefix__sdek_cities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `code` (`code`),
  ADD KEY `update` (`upd`),
  ADD KEY `city` (`city`),
  ADD KEY `active` (`active`),
  ADD KEY `country` (`country`);

ALTER TABLE `artl2__sdek_cities`
  MODIFY `id` int(4) NOT NULL AUTO_INCREMENT;
COMMIT;