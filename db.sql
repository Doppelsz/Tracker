CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  passkey CHAR(40) NOT NULL UNIQUE,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active'
);

CREATE TABLE torrents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  info_hash BINARY(20) NOT NULL UNIQUE
);

CREATE TABLE peers (
  torrent_id INT NOT NULL,
  user_id INT NOT NULL,
  peer_id BINARY(20) NOT NULL,
  ip VARBINARY(16) NOT NULL,
  port SMALLINT UNSIGNED NOT NULL,
  is_seed TINYINT(1) NOT NULL,
  last_seen INT NOT NULL,
  PRIMARY KEY (torrent_id, user_id, peer_id),
  INDEX (torrent_id),
  FOREIGN KEY (torrent_id) REFERENCES torrents(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
