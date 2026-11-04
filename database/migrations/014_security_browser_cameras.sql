-- Add browser-discoverable device identity to the existing Security camera inventory.
ALTER TABLE cctv_cameras
  ADD COLUMN browser_device_key CHAR(64) NULL,
  ADD COLUMN connection_type ENUM('manual','browser') NOT NULL DEFAULT 'manual',
  ADD COLUMN added_by BIGINT UNSIGNED NULL,
  ADD UNIQUE KEY uq_security_camera_device_key (browser_device_key),
  ADD KEY ix_security_camera_added_by (added_by),
  ADD CONSTRAINT fk_security_camera_added_by FOREIGN KEY (added_by) REFERENCES users (id);
