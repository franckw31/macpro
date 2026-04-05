-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost
-- Généré le : lun. 12 jan. 2026 à 08:13
-- Version du serveur : 10.11.6-MariaDB-0+deb12u1
-- Version de PHP : 8.2.20

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `dbs9616600`
--

-- --------------------------------------------------------

--
-- Structure de la table `activite`
--

CREATE TABLE `activite` (
  `id-activite` int(11) NOT NULL,
  `id_challenge` int(11) NOT NULL DEFAULT 4,
  `id_structure` int(11) DEFAULT 1,
  `id-membre` int(11) NOT NULL DEFAULT 265,
  `titre-activite` varchar(64) DEFAULT NULL,
  `date_depart` timestamp NOT NULL DEFAULT current_timestamp(),
  `end_date` timestamp NULL DEFAULT current_timestamp(),
  `heure_depart` timestamp NULL DEFAULT current_timestamp(),
  `ville` varchar(64) DEFAULT NULL,
  `rue` varchar(64) DEFAULT NULL,
  `lng` double DEFAULT 0,
  `lat` float DEFAULT 0,
  `icon` varchar(255) DEFAULT 'wpt',
  `ico-siz` float DEFAULT NULL,
  `photo` varchar(255) DEFAULT 'bg.png',
  `lien` varchar(255) NOT NULL DEFAULT '<a href="/panel/voir-activite.php?uid=	',
  `lien-id` varchar(64) DEFAULT NULL,
  `lien-texte` varchar(255) NOT NULL DEFAULT '"><img src="panel/images/',
  `lien-texte-fin` varchar(1024) NOT NULL DEFAULT '" width="150" height="150" align="center">Cliquer Pour Infos',
  `places` int(11) DEFAULT 16,
  `reserves` int(11) DEFAULT NULL,
  `options` int(11) DEFAULT 0,
  `libre` int(11) DEFAULT NULL,
  `commentaire` varchar(128) DEFAULT NULL,
  `buyin` int(11) DEFAULT 10,
  `rake` int(11) DEFAULT 5,
  `bounty` int(11) DEFAULT 0,
  `jetons` int(11) DEFAULT 35000,
  `recave` int(11) DEFAULT 1,
  `recave_montant` int(11) NOT NULL DEFAULT 10,
  `recave_jetons` int(11) NOT NULL DEFAULT 40000,
  `addon` int(11) DEFAULT 0,
  `ante` varchar(16) DEFAULT '0',
  `bonus` int(11) DEFAULT 0,
  `nb-tables` int(11) NOT NULL DEFAULT 2,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `id-challengee` int(11) NOT NULL DEFAULT 1,
  `id-structuree` int(11) NOT NULL DEFAULT 2,
  `id_rake` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `timestamp` timestamp NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `source` varchar(50) DEFAULT 'Standard',
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `adresse`
--

CREATE TABLE `adresse` (
  `id` int(11) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `date_ajout` timestamp NULL DEFAULT current_timestamp(),
  `identifier` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `blindes`
--

CREATE TABLE `blindes` (
  `id-blinde` int(11) NOT NULL,
  `ordre` int(11) NOT NULL,
  `nom` varchar(16) DEFAULT NULL,
  `val-sb` int(11) NOT NULL,
  `val-bb` int(11) NOT NULL,
  `pause` int(11) NOT NULL,
  `ante` varchar(16) DEFAULT '0',
  `duree` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `blindes-live`
--

CREATE TABLE `blindes-live` (
  `id` int(11) NOT NULL,
  `id-activite` int(11) DEFAULT NULL,
  `ordre` int(11) DEFAULT NULL,
  `nom` varchar(64) DEFAULT NULL,
  `sb` int(11) NOT NULL DEFAULT 0,
  `bb` int(11) NOT NULL DEFAULT 0,
  `minutes` int(11) NOT NULL DEFAULT 0,
  `fin` datetime DEFAULT NULL,
  `ante` varchar(16) DEFAULT '0',
  `en_pause` int(11) DEFAULT 0,
  `heure_pause` datetime DEFAULT NULL,
  `heure_depause` datetime DEFAULT NULL,
  `delta` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `blindes_snapshots`
--

CREATE TABLE `blindes_snapshots` (
  `id` int(11) NOT NULL,
  `id_activite` int(11) NOT NULL,
  `id_membre` int(11) NOT NULL,
  `snapshot_name` varchar(255) NOT NULL,
  `snapshot_data` longtext NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `blind_levels`
--

CREATE TABLE `blind_levels` (
  `id` int(11) NOT NULL,
  `structure_id` int(11) DEFAULT NULL,
  `level` int(11) NOT NULL,
  `small_blind` int(11) NOT NULL,
  `big_blind` int(11) NOT NULL,
  `ante` int(11) DEFAULT 0,
  `duration` int(11) DEFAULT 900
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `blind_structures`
--

CREATE TABLE `blind_structures` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `challenge`
--

CREATE TABLE `challenge` (
  `id_challenge` int(11) NOT NULL,
  `titre_challenge` varchar(64) NOT NULL,
  `chal_com` varchar(128) NOT NULL,
  `chal_deb` date NOT NULL,
  `chal_fin` date NOT NULL,
  `chal_org` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `challenge-partie`
--

CREATE TABLE `challenge-partie` (
  `chapar_id` int(11) NOT NULL,
  `chapar_id_chal` int(11) NOT NULL,
  `chapar_id_part` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `chat_groups`
--

CREATE TABLE `chat_groups` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `chat_group_members`
--

CREATE TABLE `chat_group_members` (
  `group_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `joined_at` datetime DEFAULT current_timestamp(),
  `last_read_at` datetime DEFAULT '1970-01-01 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `group_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `audio` varchar(255) DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0,
  `is_censored` tinyint(1) DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `collections`
--

CREATE TABLE `collections` (
  `id_collection` int(11) NOT NULL,
  `nom` varchar(255) DEFAULT NULL,
  `commentaire` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `collections-individu`
--

CREATE TABLE `collections-individu` (
  `id` int(11) NOT NULL,
  `id_col` int(11) NOT NULL,
  `id-indiv` int(11) DEFAULT NULL,
  `date` timestamp NOT NULL DEFAULT current_timestamp(),
  `co` varchar(255) NOT NULL DEFAULT 'Inconnu'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `competences`
--

CREATE TABLE `competences` (
  `id` int(11) NOT NULL,
  `nom` varchar(255) DEFAULT NULL,
  `commentaire` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `competences-individu`
--

CREATE TABLE `competences-individu` (
  `id` int(11) NOT NULL,
  `id-comp` int(11) NOT NULL,
  `id-indiv` int(11) DEFAULT NULL,
  `date` timestamp NOT NULL DEFAULT current_timestamp(),
  `co` varchar(255) NOT NULL DEFAULT 'Inconnu'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `doctorslog`
--

CREATE TABLE `doctorslog` (
  `id` int(11) NOT NULL,
  `uid` int(11) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `userip` binary(16) DEFAULT NULL,
  `loginTime` timestamp NULL DEFAULT current_timestamp(),
  `logout` varchar(255) DEFAULT NULL,
  `status` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `eliminations`
--

CREATE TABLE `eliminations` (
  `id` int(11) NOT NULL,
  `id_membre_victime` int(11) DEFAULT NULL,
  `nom_membre_victime` varchar(64) DEFAULT NULL,
  `id_membre` int(11) NOT NULL,
  `nom_membre` varchar(255) NOT NULL,
  `id_activite` int(11) DEFAULT NULL,
  `id_participation` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `is_definitive` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `eventsgps`
--

CREATE TABLE `eventsgps` (
  `id` int(11) NOT NULL,
  `name` char(60) NOT NULL DEFAULT 'Poker',
  `description` char(255) NOT NULL,
  `icon` varchar(255) NOT NULL DEFAULT 'poker',
  `lat` double NOT NULL,
  `lng` double NOT NULL,
  `t1` varchar(64) NOT NULL,
  `t2` varchar(255) NOT NULL,
  `lien` varchar(255) NOT NULL DEFAULT '<a href="/panel/voir-partie.php?uid=',
  `lien-id` varchar(64) NOT NULL,
  `lien-texte` varchar(255) NOT NULL DEFAULT '">',
  `lien-texte-fin` varchar(255) NOT NULL DEFAULT 'Partie N°',
  `icon-size` float NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `loisirs`
--

CREATE TABLE `loisirs` (
  `id` int(11) NOT NULL,
  `nom` varchar(255) DEFAULT NULL,
  `commentaire` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `loisirs-individu`
--

CREATE TABLE `loisirs-individu` (
  `id` int(11) NOT NULL,
  `id-lois` int(11) NOT NULL,
  `id-indiv` int(11) DEFAULT NULL,
  `date` timestamp NOT NULL DEFAULT current_timestamp(),
  `co` varchar(255) NOT NULL DEFAULT 'Inconnu'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `membres`
--

CREATE TABLE `membres` (
  `id-membre` int(11) NOT NULL,
  `id_membre` int(11) DEFAULT NULL,
  `pseudo` varchar(30) DEFAULT NULL,
  `droits` varchar(11) NOT NULL DEFAULT '1',
  `fname` varchar(255) DEFAULT NULL,
  `lname` varchar(255) DEFAULT NULL,
  `type` varchar(6) DEFAULT 'M',
  `lastip` varchar(20) DEFAULT NULL,
  `nbpoints` int(11) DEFAULT -1,
  `password` varchar(255) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT '1234',
  `password_ext` varchar(64) DEFAULT NULL,
  `CodeV` varchar(64) DEFAULT NULL,
  `verification` tinyint(4) NOT NULL DEFAULT 0,
  `telephone` varchar(255) NOT NULL DEFAULT '0600000000',
  `email` varchar(30) DEFAULT 'test@test.fr',
  `photo` varchar(200) NOT NULL DEFAULT 't1.jpg',
  `photo_org` varchar(200) NOT NULL DEFAULT 't1.jpg',
  `photo-map` varchar(255) NOT NULL DEFAULT 'url(panel/images/',
  `commentaire` int(11) DEFAULT NULL,
  `rue` char(255) DEFAULT NULL,
  `ville` char(255) DEFAULT NULL,
  `country` varchar(64) DEFAULT 'France',
  `longitude` double DEFAULT NULL,
  `latitude` float DEFAULT NULL,
  `icon` varchar(255) NOT NULL DEFAULT 'user-m',
  `ico-siz` float DEFAULT NULL,
  `ico_size` int(11) NOT NULL DEFAULT 100,
  `lien` varchar(255) NOT NULL DEFAULT '<a href="/panel/voir-membre.php?uid=',
  `lien-id` varchar(64) DEFAULT NULL,
  `lien-texte` varchar(255) NOT NULL DEFAULT '">',
  `lien-texte-fin` varchar(255) NOT NULL DEFAULT 'Cliquer Pour Infos',
  `def_nomact` varchar(64) NOT NULL DEFAULT 'Chez ',
  `def_str` int(11) DEFAULT 1,
  `def_nbj` int(11) DEFAULT 8,
  `def_buy` int(11) DEFAULT 10,
  `def_rak` int(11) DEFAULT 0,
  `def_bou` int(11) DEFAULT 0,
  `def_rec` int(11) DEFAULT 1,
  `def_jet` int(11) DEFAULT 30000,
  `def_bon` int(11) DEFAULT 0,
  `def_add` int(11) DEFAULT 0,
  `def_ant` int(11) DEFAULT 0,
  `def_rdv` varchar(64) DEFAULT NULL,
  `def_sta` varchar(64) DEFAULT NULL,
  `def_com` varchar(128) DEFAULT NULL,
  `def_cha` int(11) NOT NULL DEFAULT 999,
  `def_recave_montant` int(11) DEFAULT NULL,
  `def_recave_jetons` int(11) DEFAULT NULL,
  `association_date` date DEFAULT '1970-01-01',
  `posting_date` date DEFAULT '1970-01-01',
  `naissance_date` date DEFAULT NULL,
  `notif_zero` tinyint(1) DEFAULT 1,
  `notif_allannonces` tinyint(1) DEFAULT 0,
  `notif_grpannonces` tinyint(1) DEFAULT 0,
  `notif_inscription` tinyint(1) DEFAULT 0,
  `solde` int(11) NOT NULL DEFAULT 0,
  `creationDate` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Structure de la table `participation`
--

CREATE TABLE `participation` (
  `id-participation` int(11) NOT NULL,
  `id-membre` int(11) NOT NULL DEFAULT 1,
  `nom-membre` varchar(64) DEFAULT NULL,
  `id-membre-vainqueur` int(11) NOT NULL DEFAULT 0,
  `nom-membre-vainqueur` varchar(64) DEFAULT NULL,
  `id-activite` int(11) NOT NULL,
  `id-siege` int(11) DEFAULT 1,
  `id-table` int(11) DEFAULT 1,
  `id-challenge` int(11) NOT NULL DEFAULT 3,
  `option` varchar(20) NOT NULL DEFAULT 'Réservation',
  `ordre` int(11) NOT NULL DEFAULT 0,
  `position` int(11) NOT NULL DEFAULT 0,
  `valide` varchar(11) NOT NULL DEFAULT 'Actif',
  `commentaire` varchar(255) DEFAULT 'Aucun',
  `classement` int(11) NOT NULL DEFAULT 0,
  `recave` int(11) NOT NULL DEFAULT 0,
  `addon` int(11) NOT NULL DEFAULT 0,
  `tf` int(11) NOT NULL DEFAULT 0,
  `win` tinyint(1) NOT NULL DEFAULT 0,
  `points` int(11) NOT NULL DEFAULT 0,
  `bonbon` int(11) NOT NULL DEFAULT 0,
  `rake` int(11) DEFAULT 0,
  `gain` int(11) NOT NULL DEFAULT 0,
  `challenger` tinyint(1) NOT NULL DEFAULT 0,
  `caisse_chal` int(11) NOT NULL DEFAULT 0,
  `cout_in` int(11) DEFAULT NULL,
  `ds` timestamp NULL DEFAULT current_timestamp(),
  `cagnotte` int(11) DEFAULT NULL,
  `remise` tinyint(1) NOT NULL DEFAULT 0,
  `jetons` int(11) DEFAULT NULL,
  `jetons_cumul` int(11) NOT NULL DEFAULT 35000,
  `gain_cumul` int(11) NOT NULL DEFAULT 0,
  `gain_total` decimal(10,2) DEFAULT 0.00,
  `pertes` int(11) DEFAULT NULL,
  `test` int(11) DEFAULT NULL,
  `id_rake` int(11) DEFAULT 1,
  `anonyme` tinyint(1) NOT NULL DEFAULT 0,
  `latereg` tinyint(1) NOT NULL DEFAULT 0,
  `rake_0` int(11) DEFAULT 0,
  `rake_5` int(11) DEFAULT 0,
  `rake_10` int(11) DEFAULT 0,
  `rake_12` int(11) DEFAULT 0,
  `rake_15` int(11) DEFAULT 0,
  `rake_20` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `poker_players`
--

CREATE TABLE `poker_players` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `buyin` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `portefeuille`
--

CREATE TABLE `portefeuille` (
  `id_mvt` int(11) NOT NULL,
  `id_mvt_membre` int(11) NOT NULL,
  `id_membre_tier` int(11) NOT NULL DEFAULT 1,
  `montant` int(11) NOT NULL,
  `id_type_mvt` int(11) NOT NULL DEFAULT 0,
  `date_mvt` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_participation` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `qrcodes`
--

CREATE TABLE `qrcodes` (
  `id` int(11) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `rake`
--

CREATE TABLE `rake` (
  `id_rake` int(11) NOT NULL,
  `nom` varchar(64) DEFAULT NULL,
  `montant` int(11) DEFAULT NULL,
  `commentaire` varchar(64) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `structure`
--

CREATE TABLE `structure` (
  `id` int(11) NOT NULL,
  `id-structure` int(11) NOT NULL,
  `ordre` int(11) NOT NULL,
  `id-blinde` int(11) NOT NULL,
  `duree` int(11) NOT NULL,
  `ante` varchar(16) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `structure-buyin`
--

CREATE TABLE `structure-buyin` (
  `id-structure-buyin` int(11) NOT NULL,
  `buyin` int(11) NOT NULL DEFAULT 10,
  `rake` int(11) NOT NULL DEFAULT 0,
  `id-stricture-rake` int(11) NOT NULL DEFAULT 1,
  `bounty` int(11) NOT NULL DEFAULT 0,
  `nb-recave` int(11) NOT NULL DEFAULT 0,
  `nb-Jetons` int(11) NOT NULL DEFAULT 25000,
  `bonus-nb-jetons` int(11) NOT NULL DEFAULT 0,
  `Addon` int(11) NOT NULL DEFAULT 0,
  `Addon-nb-jetons` int(11) NOT NULL DEFAULT 25000,
  `ante` int(11) NOT NULL DEFAULT 0,
  `id-structure-ante` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `structure_modele`
--

CREATE TABLE `structure_modele` (
  `id_modele_structure` int(11) NOT NULL,
  `num_structure` int(11) DEFAULT NULL,
  `Detail` varchar(256) DEFAULT NULL,
  `id_orga` int(11) DEFAULT NULL,
  `nom` varchar(64) DEFAULT NULL,
  `sb` int(11) DEFAULT NULL,
  `bb` int(11) DEFAULT NULL,
  `heure_fin_recave` datetime DEFAULT NULL,
  `fin_pour_21H` datetime DEFAULT NULL,
  `duree` time DEFAULT NULL,
  `nb_jetons` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tblcontactus`
--

CREATE TABLE `tblcontactus` (
  `id` int(11) NOT NULL,
  `fullname` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `contactno` bigint(20) DEFAULT NULL,
  `message` mediumtext DEFAULT NULL,
  `PostingDate` timestamp NULL DEFAULT current_timestamp(),
  `AdminRemark` mediumtext DEFAULT NULL,
  `LastupdationDate` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `IsRead` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tblcontactusinfo`
--

CREATE TABLE `tblcontactusinfo` (
  `id` int(11) NOT NULL,
  `Address` tinytext DEFAULT NULL,
  `EmailId` varchar(255) DEFAULT NULL,
  `ContactNo` char(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tblcontactusquery`
--

CREATE TABLE `tblcontactusquery` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `EmailId` varchar(120) DEFAULT NULL,
  `ContactNumber` char(11) DEFAULT NULL,
  `Message` longtext DEFAULT NULL,
  `PostingDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tblpage`
--

CREATE TABLE `tblpage` (
  `ID` int(11) NOT NULL,
  `PageType` varchar(200) DEFAULT NULL,
  `PageTitle` varchar(200) DEFAULT NULL,
  `PageDescription` mediumtext DEFAULT NULL,
  `Email` varchar(200) DEFAULT NULL,
  `MobileNumber` bigint(20) DEFAULT NULL,
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tblpages`
--

CREATE TABLE `tblpages` (
  `id` int(11) NOT NULL,
  `PageName` varchar(255) DEFAULT NULL,
  `type` varchar(255) NOT NULL DEFAULT '',
  `detail` longtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Structure de la table `tbltestimonial`
--

CREATE TABLE `tbltestimonial` (
  `id` int(11) NOT NULL,
  `UserEmail` varchar(100) NOT NULL,
  `Testimonial` mediumtext NOT NULL,
  `PostingDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `userlog`
--

CREATE TABLE `userlog` (
  `id` int(11) NOT NULL,
  `uid` int(11) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `userip` binary(16) DEFAULT NULL,
  `loginTime` timestamp NULL DEFAULT current_timestamp(),
  `logout` varchar(255) DEFAULT NULL,
  `status` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `activite`
--
ALTER TABLE `activite`
  ADD PRIMARY KEY (`id-activite`);

--
-- Index pour la table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `adresse`
--
ALTER TABLE `adresse`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `blindes`
--
ALTER TABLE `blindes`
  ADD PRIMARY KEY (`id-blinde`);

--
-- Index pour la table `blindes-live`
--
ALTER TABLE `blindes-live`
  ADD PRIMARY KEY (`id`) USING BTREE;

--
-- Index pour la table `blindes_snapshots`
--
ALTER TABLE `blindes_snapshots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_activite` (`id_activite`);

--
-- Index pour la table `blind_levels`
--
ALTER TABLE `blind_levels`
  ADD PRIMARY KEY (`id`),
  ADD KEY `structure_id` (`structure_id`);

--
-- Index pour la table `blind_structures`
--
ALTER TABLE `blind_structures`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `challenge`
--
ALTER TABLE `challenge`
  ADD PRIMARY KEY (`id_challenge`);

--
-- Index pour la table `chat_groups`
--
ALTER TABLE `chat_groups`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `chat_group_members`
--
ALTER TABLE `chat_group_members`
  ADD PRIMARY KEY (`group_id`,`member_id`);

--
-- Index pour la table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `collections`
--
ALTER TABLE `collections`
  ADD PRIMARY KEY (`id_collection`);

--
-- Index pour la table `collections-individu`
--
ALTER TABLE `collections-individu`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `eliminations`
--
ALTER TABLE `eliminations`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `membres`
--
ALTER TABLE `membres`
  ADD PRIMARY KEY (`id-membre`),
  ADD UNIQUE KEY `ordre` (`id-membre`);

--
-- Index pour la table `participation`
--
ALTER TABLE `participation`
  ADD PRIMARY KEY (`id-participation`),
  ADD KEY `fk_membre` (`id-membre`),
  ADD KEY `fk_activite` (`id-activite`),
  ADD KEY `test` (`id-membre`) USING BTREE;

--
-- Index pour la table `poker_players`
--
ALTER TABLE `poker_players`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `portefeuille`
--
ALTER TABLE `portefeuille`
  ADD PRIMARY KEY (`id_mvt`);

--
-- Index pour la table `qrcodes`
--
ALTER TABLE `qrcodes`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `rake`
--
ALTER TABLE `rake`
  ADD PRIMARY KEY (`id_rake`);

--
-- Index pour la table `structure`
--
ALTER TABLE `structure`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `structure-buyin`
--
ALTER TABLE `structure-buyin`
  ADD PRIMARY KEY (`id-structure-buyin`);

--
-- Index pour la table `structure_modele`
--
ALTER TABLE `structure_modele`
  ADD PRIMARY KEY (`id_modele_structure`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `activite`
--
ALTER TABLE `activite`
  MODIFY `id-activite` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `adresse`
--
ALTER TABLE `adresse`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `blindes-live`
--
ALTER TABLE `blindes-live`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `blindes_snapshots`
--
ALTER TABLE `blindes_snapshots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `blind_levels`
--
ALTER TABLE `blind_levels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `blind_structures`
--
ALTER TABLE `blind_structures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `chat_groups`
--
ALTER TABLE `chat_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `collections`
--
ALTER TABLE `collections`
  MODIFY `id_collection` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `collections-individu`
--
ALTER TABLE `collections-individu`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `eliminations`
--
ALTER TABLE `eliminations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `membres`
--
ALTER TABLE `membres`
  MODIFY `id-membre` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `participation`
--
ALTER TABLE `participation`
  MODIFY `id-participation` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `poker_players`
--
ALTER TABLE `poker_players`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `portefeuille`
--
ALTER TABLE `portefeuille`
  MODIFY `id_mvt` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `qrcodes`
--
ALTER TABLE `qrcodes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `rake`
--
ALTER TABLE `rake`
  MODIFY `id_rake` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `structure-buyin`
--
ALTER TABLE `structure-buyin`
  MODIFY `id-structure-buyin` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `structure_modele`
--
ALTER TABLE `structure_modele`
  MODIFY `id_modele_structure` int(11) NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `blind_levels`
--
ALTER TABLE `blind_levels`
  ADD CONSTRAINT `blind_levels_ibfk_1` FOREIGN KEY (`structure_id`) REFERENCES `blind_structures` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
