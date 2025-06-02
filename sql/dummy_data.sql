-- dummy_data.sql - initial data for admin panel

INSERT INTO users (username, email, password_hash, role, first_name, last_name, theme, twofa_enabled, twofa_secret) VALUES
('admin', 'admin@example.com', '$2b$12$0I95Alg5uzukxtv9gxMuMuOyYyfshFL5R7eh0n3WjgqA3n2.GdXY.', 'admin', 'Admin', 'User', 'light', 0, NULL),
('user1', 'user1@example.com', '$2b$12$2K5NlNUyz878mXkEkwKwfuaqtad/dmNHaue/FsCGisFGWBZ1lT5vS', 'user', 'User', 'One', 'light', 0, NULL);

INSERT INTO email_settings (smtp_host, smtp_port, smtp_user, smtp_pass, smtp_secure) VALUES
('smtp.example.com', '587', 'user@example.com', 'securepassword', 'tls');
