ALTER TABLE incidents
  ADD COLUMN incident_at DATETIME NULL AFTER created_at,
  ADD COLUMN photo_filename VARCHAR(255) NULL AFTER injury_details;
