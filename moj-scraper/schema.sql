CREATE DATABASE IF NOT EXISTS moj_judicial_decisions
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE moj_judicial_decisions;

CREATE TABLE IF NOT EXISTS judicial_decisions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  source_url VARCHAR(1024) NOT NULL,
  page_number INT UNSIGNED NOT NULL,
  title VARCHAR(500) NULL,
  decision_number VARCHAR(100) NULL,
  decision_date VARCHAR(100) NULL,
  court VARCHAR(255) NULL,
  category VARCHAR(255) NULL,
  summary TEXT NULL,
  detail_url VARCHAR(1024) NULL,
  raw_text MEDIUMTEXT NOT NULL,
  content_hash CHAR(64) NOT NULL,
  scraped_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_judicial_decisions_hash (content_hash),
  KEY idx_page_number (page_number),
  KEY idx_decision_number (decision_number),
  KEY idx_scraped_at (scraped_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
