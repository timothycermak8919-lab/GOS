-- Migration script for secure session handling and loc_ji columns
-- Run this SQL to add session columns to the Accounts table

ALTER TABLE Accounts ADD COLUMN session_token VARCHAR(64) NULL;
ALTER TABLE Accounts ADD COLUMN session_expires INT NULL;

-- Create index for faster session lookups
CREATE INDEX idx_session_token ON Accounts(session_token);
CREATE INDEX idx_session_expires ON Accounts(session_expires);

-- Add loc_ji columns for location-based stats (if not already present)
ALTER TABLE Users_stats ADD COLUMN loc_ji25 float NOT NULL DEFAULT '0';
ALTER TABLE Users_stats ADD COLUMN loc_ji26 float NOT NULL DEFAULT '0';
ALTER TABLE Users_stats ADD COLUMN loc_ji27 float NOT NULL DEFAULT '0';
ALTER TABLE Users_stats ADD COLUMN loc_ji28 float NOT NULL DEFAULT '0';
ALTER TABLE Users_stats ADD COLUMN loc_ji29 float NOT NULL DEFAULT '0';
ALTER TABLE Users_stats ADD COLUMN loc_ji30 float NOT NULL DEFAULT '0';
