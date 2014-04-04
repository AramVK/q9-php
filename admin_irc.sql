-- phpMyAdmin SQL Dump
-- version 4.1.11
-- http://www.phpmyadmin.net
--
-- Machine: localhost
-- Gegenereerd op: 04 apr 2014 om 22:47
-- Serverversie: 5.5.36
-- PHP-versie: 5.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Databank: `irc`
--

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `auths`
--

CREATE TABLE IF NOT EXISTS `auths` (
  `id` int(50) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `password` varchar(255) NOT NULL DEFAULT '',
  `createdate` int(11) NOT NULL DEFAULT '0',
  `userflags` varchar(20) NOT NULL DEFAULT '',
  `email` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=9 ;

--
-- Gegevens worden geëxporteerd voor tabel `auths`
--

INSERT INTO `auths` (`id`, `name`, `password`, `createdate`, `userflags`, `email`) VALUES
(1, 'Arie', 'ohai', 1152888364, 'do', 'your@email.tld'),
(2, 'A', 'r54r6ew54r6', 1181993707, '', 'your@email.tld'),
(3, 'G', '5e6w4rwe654', 1181993707, '', 'your@email.tld'),
(5, 'R', 'as32d1a3s', 1181993707, '', 'your@email.tld'),
(6, 'Google', 'g65g4d6f546', 1181993707, '', 'your@email.tld'),
(7, 'O2', 'as654de5ee2', 1181993707, '', 'your@email.tld'),
(8, 'V', 'rew56r4w6we4', 1181993707, '', 'your@email.tld');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `chanlevs`
--

CREATE TABLE IF NOT EXISTS `chanlevs` (
  `channel` varchar(255) NOT NULL DEFAULT '',
  `auth` varchar(255) NOT NULL DEFAULT '',
  `flags` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`channel`,`auth`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `channels`
--

CREATE TABLE IF NOT EXISTS `channels` (
  `name` varchar(255) NOT NULL DEFAULT '',
  `flags` varchar(20) NOT NULL DEFAULT '',
  `topic` varchar(255) NOT NULL DEFAULT '',
  `owner` varchar(20) NOT NULL DEFAULT '',
  `suspended` varchar(255) NOT NULL DEFAULT '',
  `autolimit` int(3) NOT NULL DEFAULT '0',
  PRIMARY KEY (`name`,`owner`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Gegevens worden geëxporteerd voor tabel `channels`
--

INSERT INTO `channels` (`name`, `flags`, `topic`, `owner`, `suspended`, `autolimit`) VALUES
('#twilightzone', 'j', '', 'c', '', 0),
('#twilightzone', 'j', '', 'g', '', 0),
('#twilightzone', 'j', '', 'ms', '', 0),
('#twilightzone', 'j', '', 's', '', 0),
('#twilightzone', 'j', '', 'google', '', 0),
('#twilightzone', 'j', '', 'r', '', 0),
('#help', 'j', '', 'g', '', 0),
('#help', 'j', '', 'c', '', 0),
('#help.script', 'j', '', 'g', '', 0);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `helps`
--

CREATE TABLE IF NOT EXISTS `helps` (
  `tag` text NOT NULL,
  `answer` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Gegevens worden geëxporteerd voor tabel `helps`
--

INSERT INTO `helps` (`tag`, `answer`) VALUES
('n', 'You can read all commands of N by entering /msg A showcommands. If you would like more specific help of A go to: http://www.amservers.nl/N/');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `main`
--

CREATE TABLE IF NOT EXISTS `main` (
  `lastupdate` int(11) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `versions`
--

CREATE TABLE IF NOT EXISTS `versions` (
  `ip` varchar(15) NOT NULL DEFAULT '',
  `version` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `welcomes`
--

CREATE TABLE IF NOT EXISTS `welcomes` (
  `channel` varchar(255) NOT NULL DEFAULT '',
  `service` varchar(10) NOT NULL DEFAULT '',
  `message` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
