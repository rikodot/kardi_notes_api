SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `notes` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;
USE `notes`;

CREATE TABLE `config` (
  `id` int(11) NOT NULL,
  `platform` varchar(32) NOT NULL,
  `latest_ver` varchar(16) NOT NULL DEFAULT '',
  `oldest_allowed_ver` varchar(16) NOT NULL DEFAULT '',
  `latest_link` varchar(256) NOT NULL DEFAULT '',
  `instructions` varchar(512) NOT NULL DEFAULT '',
  `instructions_link` varchar(256) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `content` text NOT NULL,
  `creation_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `owner_key_id` int(11) NOT NULL,
  `seen_by_dev` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `title` varchar(128) NOT NULL,
  `content` varchar(4096) NOT NULL,
  `creation_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `pop_up_on_start` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE `messages_pop_up_seen` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `owner_key_id` int(11) NOT NULL,
  `seen_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE `messages_seen` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `owner_key_id` int(11) NOT NULL,
  `seen_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE `messages_target` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `target_all` tinyint(1) NOT NULL DEFAULT 1,
  `target_owner_key_id` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE `notes` (
  `id` int(11) NOT NULL,
  `title` varchar(256) NOT NULL,
  `content` text NOT NULL,
  `creation_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `key` varchar(32) NOT NULL,
  `owner_key_id` int(11) NOT NULL,
  `blur` tinyint(1) NOT NULL DEFAULT 0,
  `password` varchar(256) NOT NULL DEFAULT '',
  `color` bigint(20) DEFAULT NULL,
  `last_note_key` varchar(32) DEFAULT NULL,
  `next_note_key` varchar(32) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE `owners` (
  `id` int(11) NOT NULL,
  `key` varchar(256) NOT NULL,
  `default_note_color` bigint(20) DEFAULT NULL,
  `first_note_key` varchar(32) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE `requests` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE `sessions` (
  `id` int(11) NOT NULL,
  `session` varchar(32) NOT NULL,
  `enc_key` varchar(32) NOT NULL,
  `enc_iv` varchar(16) NOT NULL,
  `version_check_done` tinyint(1) NOT NULL DEFAULT 0,
  `revoked` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=COMPACT;

CREATE TABLE `variables` (
  `id` int(11) NOT NULL,
  `key` varchar(32) NOT NULL,
  `value` varchar(256) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;


ALTER TABLE `config`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `messages_pop_up_seen`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `messages_seen`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `messages_target`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `notes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key` (`key`);

ALTER TABLE `owners`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key` (`key`),
  ADD UNIQUE KEY `first_note_key` (`first_note_key`);

ALTER TABLE `requests`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session` (`session`);

ALTER TABLE `variables`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key` (`key`);


ALTER TABLE `config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `messages_pop_up_seen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `messages_seen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `messages_target`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `owners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `variables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;


INSERT INTO `config` (`id`, `platform`, `latest_ver`, `oldest_allowed_ver`, `latest_link`, `instructions`, `instructions_link`) VALUES
(1, 'android', '2.0.4', '2.0.0', 'https://www.kardi.tech/notes/downloads/kardi%20notes.apk', '', ''),
(2, 'windows', '2.0.4', '2.0.0', 'https://www.kardi.tech/notes/downloads/kardi%20notes.exe', '', ''),
(3, 'linux', '2.0.4', '2.0.0', 'https://www.kardi.tech/notes/downloads/kardi%20notes.AppImage', '', '');
COMMIT;
