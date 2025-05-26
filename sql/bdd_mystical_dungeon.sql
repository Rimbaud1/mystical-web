-- phpMyAdmin SQL Dump
-- version 5.1.2
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost:3306
-- Généré le : ven. 23 mai 2025 à 09:03
-- Version du serveur : 5.7.24
-- Version de PHP : 8.3.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `bdd_mystical_dungeon`
--

-- --------------------------------------------------------

--
-- Structure de la table `map`
--

CREATE TABLE `map` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `creation_date` datetime DEFAULT NULL,
  `map_value` text,
  `size` int(11) DEFAULT NULL,
  `map_game_count` int(11) DEFAULT NULL,
  `difficulty` int(11) DEFAULT NULL,
  `best_player_time` int(11) DEFAULT NULL,
  `best_player_moves` int(11) DEFAULT NULL,
  `best_player_time_name` varchar(255) DEFAULT NULL,
  `best_player_moves_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Déchargement des données de la table `map`
--

INSERT INTO `map` (`id`, `user_id`, `name`, `creation_date`, `map_value`, `size`, `map_game_count`, `difficulty`, `best_player_time`, `best_player_moves`, `best_player_time_name`, `best_player_moves_name`) VALUES
(1, 1, 'Level 1', '2025-05-21 14:33:21', '0000020000;0010010010;0011010110;0110010010;0010010010;0011111110;0010000100;0011110100;0010010100;0000030000', 10, 0, 3, 0, 999, 'none', 'none'),
(2, 1, 'Level 2', '2025-05-21 14:40:56', '02000300000000000000;01010111110111011100;01111010011101110100;01001000000001000110;01011111101011011010;01010000111100110110;01110110100111100100;00100011100100100110;01110010110100111010;00011110011111000110;01110101101001110100;01011110100001010110;01000011111101011010;01011010000110010110;01110010010010010100;00001110110111101110;01001010011100101010;01100011110101010110;00111110100111111100;00000000000000000000', 20, 0, 7, 0, 999, 'none', 'none');

-- --------------------------------------------------------

--
-- Structure de la table `stats`
--

CREATE TABLE `stats` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `played_time` int(11) DEFAULT NULL,
  `current_level` int(11) DEFAULT NULL,
  `user_game_count` int(11) DEFAULT NULL,
  `win_count` int(11) DEFAULT NULL,
  `money` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Déchargement des données de la table `stats`
--

INSERT INTO `stats` (`id`, `user_id`, `played_time`, `current_level`, `user_game_count`, `win_count`, `money`) VALUES
(1, 1, 10000, 0, 100, 90, 2000);

-- --------------------------------------------------------

--
-- Structure de la table `user`
--

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` varchar(20) DEFAULT NULL,
  `creation_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Déchargement des données de la table `user`
--

INSERT INTO `user` (`user_id`, `name`, `email`, `password`, `role`, `creation_date`) VALUES
(1, 'test', 'test@gmail.com', '$2y$10$KATgckw4pr2ftgJS94x5HegqkdURX7Z8Guhh/9VYkt0PTSXi1iIcm', 'user', '2025-05-19');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `map`
--
ALTER TABLE `map`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `stats`
--
ALTER TABLE `stats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `map`
--
ALTER TABLE `map`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `stats`
--
ALTER TABLE `stats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `map`
--
ALTER TABLE `map`
  ADD CONSTRAINT `map_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`);

--
-- Contraintes pour la table `stats`
--
ALTER TABLE `stats`
  ADD CONSTRAINT `stats_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
