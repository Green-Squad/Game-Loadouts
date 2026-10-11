-- REPRESENTATIVE TEST SCHEMA ONLY. Never execute against an existing database.
USE loadouts_fixture;
CREATE TABLE games (id VARCHAR(255) PRIMARY KEY, live TINYINT NOT NULL, short_name VARCHAR(100), theme_color VARCHAR(20), image_url VARCHAR(500), thumb_url VARCHAR(500), header_url VARCHAR(500), created_at DATETIME, updated_at DATETIME) CHARACTER SET utf8mb4;
CREATE TABLE weapons (id INT PRIMARY KEY, game_id VARCHAR(255), name VARCHAR(255), type VARCHAR(100), image_url VARCHAR(500), thumb_url VARCHAR(500), min_attachments INT, max_attachments INT, created_at DATETIME, updated_at DATETIME, INDEX(game_id,name)) CHARACTER SET utf8mb4;
CREATE TABLE users (email VARCHAR(255) PRIMARY KEY, username VARCHAR(128), role VARCHAR(16), password VARCHAR(64) NOT NULL DEFAULT '', converted_guest INT NOT NULL DEFAULT 0, remember_token VARCHAR(100) NOT NULL DEFAULT '', disabled_until DATETIME NOT NULL DEFAULT '2000-01-01', failed_attempts INT NOT NULL DEFAULT 0, confirm_token VARCHAR(100) NOT NULL DEFAULT '1', created_at DATETIME NULL, updated_at DATETIME NULL) CHARACTER SET utf8mb4;
CREATE TABLE loadouts (id INT AUTO_INCREMENT PRIMARY KEY, weapon_id INT, user_id VARCHAR(255), created_at DATETIME, updated_at DATETIME, INDEX(weapon_id)) CHARACTER SET utf8mb4;
CREATE TABLE attachments (id INT PRIMARY KEY, game_id VARCHAR(255), name VARCHAR(255), slot VARCHAR(100), image_url VARCHAR(500), thumb_url VARCHAR(500)) CHARACTER SET utf8mb4;
CREATE TABLE attachment_loadout (id INT AUTO_INCREMENT PRIMARY KEY, attachment_id INT, loadout_id INT, created_at DATETIME, updated_at DATETIME, INDEX(attachment_id), INDEX(loadout_id));
CREATE TABLE attachment_weapon (attachment_id INT, weapon_id INT, created_at DATETIME, updated_at DATETIME, PRIMARY KEY(attachment_id,weapon_id), INDEX(weapon_id));
CREATE TABLE loadout_user (id INT AUTO_INCREMENT PRIMARY KEY, loadout_id INT, user_id VARCHAR(255), created_at DATETIME, updated_at DATETIME, INDEX(loadout_id), INDEX(user_id));
CREATE USER 'loadouts_reader'@'%' IDENTIFIED BY '';
GRANT SELECT ON loadouts_fixture.* TO 'loadouts_reader'@'%';
CREATE TABLE password_reminders (email VARCHAR(255), token VARCHAR(255), created_at TIMESTAMP NOT NULL, INDEX(email), INDEX(token)) ENGINE=InnoDB CHARACTER SET utf8mb4;
