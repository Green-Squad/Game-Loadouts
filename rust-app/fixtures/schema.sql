-- REPRESENTATIVE TEST SCHEMA ONLY. Never execute against an existing database.
USE loadouts_fixture;
CREATE TABLE games (id VARCHAR(255) PRIMARY KEY, live TINYINT NOT NULL, short_name VARCHAR(100), theme_color VARCHAR(20), image_url VARCHAR(500), thumb_url VARCHAR(500), header_url VARCHAR(500), created_at DATETIME, updated_at DATETIME) CHARACTER SET utf8mb4;
CREATE TABLE weapons (id INT PRIMARY KEY, game_id VARCHAR(255), name VARCHAR(255), type VARCHAR(100), image_url VARCHAR(500), thumb_url VARCHAR(500), min_attachments INT, max_attachments INT, created_at DATETIME, updated_at DATETIME, INDEX(game_id,name)) CHARACTER SET utf8mb4;
CREATE TABLE users (email VARCHAR(255) PRIMARY KEY, username VARCHAR(255), role VARCHAR(50)) CHARACTER SET utf8mb4;
CREATE TABLE loadouts (id INT PRIMARY KEY, weapon_id INT, user_id VARCHAR(255), created_at DATETIME, updated_at DATETIME, INDEX(weapon_id)) CHARACTER SET utf8mb4;
CREATE TABLE attachments (id INT PRIMARY KEY, game_id VARCHAR(255), name VARCHAR(255), slot VARCHAR(100), image_url VARCHAR(500), thumb_url VARCHAR(500)) CHARACTER SET utf8mb4;
CREATE TABLE attachment_loadout (attachment_id INT, loadout_id INT, created_at DATETIME, updated_at DATETIME, PRIMARY KEY(attachment_id,loadout_id), INDEX(loadout_id));
CREATE TABLE attachment_weapon (attachment_id INT, weapon_id INT, created_at DATETIME, updated_at DATETIME, PRIMARY KEY(attachment_id,weapon_id), INDEX(weapon_id));
CREATE TABLE loadout_user (loadout_id INT, user_id VARCHAR(255), created_at DATETIME, updated_at DATETIME, PRIMARY KEY(loadout_id,user_id));
CREATE USER 'loadouts_reader'@'%' IDENTIFIED BY '';
GRANT SELECT ON loadouts_fixture.* TO 'loadouts_reader'@'%';
