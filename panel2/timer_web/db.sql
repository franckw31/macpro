-- Simple schema for Timer app

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pseudo VARCHAR(100) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE activities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titre_activite VARCHAR(255) NOT NULL,
  `date` DATETIME NOT NULL,
  buyin INT DEFAULT 0,
  rake INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE registrations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  activity_id INT NOT NULL,
  user_id INT DEFAULT NULL,
  pseudo VARCHAR(100) DEFAULT NULL,
  anonyme TINYINT(1) DEFAULT 0,
  is_option TINYINT(1) DEFAULT 0,
  latereg TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE results (
  id INT AUTO_INCREMENT PRIMARY KEY,
  activity_id INT NOT NULL,
  user_id INT DEFAULT NULL,
  gain INT DEFAULT 0,
  classement INT DEFAULT NULL,
  FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Sample data
INSERT INTO users (pseudo) VALUES ('Fabien.P'), ('Franck');
INSERT INTO activities (titre_activite, `date`, buyin, rake) VALUES
('Franck Lundi 25+5 (23/3)', DATE_ADD(NOW(), INTERVAL 1 HOUR), 25, 5),
('Soirée Tuesday', DATE_ADD(NOW(), INTERVAL 2 DAY), 30, 6);

INSERT INTO registrations (activity_id, user_id, pseudo, anonyme) VALUES
(1, 1, 'Fabien.P', 0),
(1, NULL, 'Invité1', 1);

INSERT INTO results (activity_id, user_id, gain, classement) VALUES
(1, 2, 50, 1);
