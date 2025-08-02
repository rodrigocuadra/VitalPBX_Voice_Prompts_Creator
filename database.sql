-- ==============================================================================
-- DATABASE SCHEMA FOR TEXT-TO-SPEECH MANAGEMENT SYSTEM
-- ==============================================================================
-- This script creates the required tables for the Text-to-Speech Management
-- System, including:
--   - Users (authentication and permissions)
--   - OpenAI Voices (catalog of available voices)
--   - Voice Profiles (custom voice configurations)
--   - TTS Logs (history of text-to-speech conversions)
--   - TTS Jobs (queued batch jobs for CSV processing)
--
-- Notes:
-- ------
-- - All tables use AUTO_INCREMENT primary keys.
-- - Default timestamps are set to CURRENT_TIMESTAMP.
-- - Foreign keys enforce relational integrity.
--
-- ==============================================================================
-- TABLE: users
-- ==============================================================================
-- Purpose:
--   Stores system users, authentication data, and permissions.
--
-- Columns:
--   id              : Primary key
--   full_name       : Full name of the user
--   username        : Unique username for login
--   email           : Email address (used for notifications and recovery)
--   password        : BCRYPT-hashed password
--   message         : Optional message shown for the user (e.g., disabled reason)
--   permissions     : 20-character string of 'S'/'N' flags for each module
--                     (position 20 = user disabled)
--   created_at      : Timestamp of account creation
--   ip              : Last IP address used
--   reset_token     : Temporary token for password reset
--   reset_expires_at: Expiration date/time for reset token
-- ==============================================================================
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  message VARCHAR(255) DEFAULT '',
  permissions CHAR(20) DEFAULT 'NNNNNNNNNNNNNNNNNNNN',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ip VARCHAR(50) DEFAULT NULL,
  reset_token VARCHAR(64) DEFAULT NULL,
  reset_expires_at DATETIME DEFAULT NULL
);

-- ==============================================================================
-- INSERT DEFAULT ADMIN USER
-- ==============================================================================
-- Instructions:
--   1. Generate a BCRYPT hash for the default password using:
--        php -r "echo password_hash('admin123', PASSWORD_BCRYPT) . PHP_EOL;"
--   2. Replace the value below with the generated hash.
-- ==============================================================================
INSERT INTO users (full_name, username, password, permissions, message)
VALUES (
  'Administrator',
  'admin',
  '$2y$10$XHQGTx7I7j4xXuv4VTI8l.pAwrUJ4mv9awTTh07m6Pq8LZBj5b9a6', -- hash for "admin123"
  'SSSSSSNNNNNNNNNNNNNN', -- enable first 6 permissions by default
  'Default administrator account'
);

-- ==============================================================================
-- TABLE: openai_voices
-- ==============================================================================
-- Purpose:
--   Stores the list of available OpenAI voices for TTS.
--
-- Columns:
--   id          : Primary key
--   name        : Voice identifier (e.g., alloy, coral)
--   description : Optional description of the voice style
-- ==============================================================================
CREATE TABLE openai_voices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description VARCHAR(255) DEFAULT ''
);

-- Initial voice list
INSERT INTO openai_voices (name, description)
VALUES
  ('alloy', 'Default balanced voice'),
  ('ash', 'Voice Ash'),
  ('ballad', 'Voice Ballad'),
  ('coral', 'Soft tone voice'),
  ('echo', 'Voice Echo'),
  ('fable', 'Voice Fable'),
  ('nova', 'Voice Nova'),
  ('onyx', 'Voice Onyx'),
  ('sage', 'Voice Sage'),
  ('shimmer', 'Voice Shimmer');

-- ==============================================================================
-- TABLE: voice_profiles
-- ==============================================================================
-- Purpose:
--   Stores voice profiles that define how text is converted into speech.
--
-- Columns:
--   id           : Primary key
--   name         : Human-readable name of the profile
--   model        : TTS model (e.g., gpt-4o-mini-tts)
--   voice        : Selected voice from openai_voices
--   audio_format : Output format (mp3/wav/pcm)
--   description  : Additional notes about the profile
--   style_prompt : Special instructions for style or tone
--   volume       : Volume factor (1.0 = normal)
--   pitch        : Pitch factor (1.0 = normal)
--   created_at   : Timestamp when profile was created
-- ==============================================================================
CREATE TABLE voice_profiles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  model VARCHAR(50) NOT NULL DEFAULT 'gpt-4o-mini-tts',
  voice VARCHAR(50) NOT NULL,
  audio_format ENUM('mp3','wav','pcm') NOT NULL DEFAULT 'mp3',
  description TEXT,
  style_prompt TEXT,
  volume DECIMAL(3,2) DEFAULT 1.0,
  pitch DECIMAL(3,2) DEFAULT 1.0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==============================================================================
-- TABLE: tts_logs
-- ==============================================================================
-- Purpose:
--   Logs all text-to-speech conversions for auditing.
--
-- Columns:
--   id               : Primary key
--   user_id          : ID of the user who made the request
--   text             : Input text used for conversion
--   voice_profile_id : ID of the voice profile used
--   file_path        : Path to the generated audio file
--   created_at       : Timestamp of the request
--
-- Relations:
--   - Foreign key to users(id)
--   - Foreign key to voice_profiles(id)
-- ==============================================================================
CREATE TABLE tts_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  text LONGTEXT NOT NULL,
  voice_profile_id INT,
  file_path VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (voice_profile_id) REFERENCES voice_profiles(id)
);

-- ==============================================================================
-- TABLE: tts_jobs
-- ==============================================================================
-- Purpose:
--   Manages queued jobs for processing CSV batch TTS requests.
--
-- Columns:
--   id            : Primary key
--   user_id       : ID of the user who submitted the job
--   csv_filename  : Path or name of the uploaded CSV
--   zip_filename  : Path of the resulting ZIP after processing
--   status        : Current job status (pending, processing, completed, failed)
--   created_at    : When the job was queued
--   completed_at  : When the job finished (if any)
--   email_sent    : Whether a notification email was sent
--
-- Relations:
--   - Foreign key to users(id)
-- ==============================================================================
CREATE TABLE tts_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    csv_filename VARCHAR(255) NOT NULL,
    zip_filename VARCHAR(255) DEFAULT NULL,
    status ENUM('pending','processing','completed','failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    email_sent TINYINT(1) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id)
);


-- ==============================================================
-- INDEXES
-- ==============================================================

-- voice_profiles
CREATE INDEX idx_voice_profiles_name ON voice_profiles (name);

-- openai_voices
CREATE INDEX idx_openai_voices_name ON openai_voices (name);

-- tts_logs
CREATE INDEX idx_tts_logs_user_id ON tts_logs (user_id);
CREATE INDEX idx_tts_logs_voice_profile_id ON tts_logs (voice_profile_id);
CREATE INDEX idx_tts_logs_created_at ON tts_logs (created_at);

-- tts_jobs
CREATE INDEX idx_tts_jobs_user_id ON tts_jobs (user_id);
CREATE INDEX idx_tts_jobs_status ON tts_jobs (status);
CREATE INDEX idx_tts_jobs_created_at ON tts_jobs (created_at);