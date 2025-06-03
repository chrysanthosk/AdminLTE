-- dummy_data.sql - initial data for admin panel

INSERT INTO users (username, email, password_hash, role, first_name, last_name, theme, twofa_enabled, twofa_secret) VALUES
('admin', 'admin@example.com', '$2b$12$0I95Alg5uzukxtv9gxMuMuOyYyfshFL5R7eh0n3WjgqA3n2.GdXY.', 'admin', 'Admin', 'User', 'light', 0, NULL),
('user1', 'user1@example.com', '$2b$12$2K5NlNUyz878mXkEkwKwfuaqtad/dmNHaue/FsCGisFGWBZ1lT5vS', 'user', 'User', 'One', 'light', 0, NULL);

INSERT INTO email_settings (smtp_host, smtp_port, smtp_user, smtp_pass, smtp_secure) VALUES
('smtp.example.com', '587', 'user@example.com', 'securepassword', 'tls');

INSERT INTO roles (role_name, role_desc) VALUES
  ('admin', 'Full access, managing users and roles'),
  ('user', 'Standard user with limited access');

  INSERT IGNORE INTO permissions (permission_key, description) VALUES
    ('user.manage',  'Create/Edit/Delete Users'),
    ('role.manage',  'Create/Edit/Delete Roles'),
    ('email.manage', 'Configure Email/SMTP Settings'),
    ('audit.view',   'View Audit Log'),
    ('profile.edit', 'Edit Own Profile'),
    ('role.assign', 'Assign Permissions to Roles'),
    ('permission.manage', 'Create/Edit/Delete Permissions'),
    ('module.manage', 'Create/Edit/Delete Dashboard Modules');

  -- Assign some default permissions to “admin” role (role_id=1 if that was seeded earlier)
  INSERT IGNORE INTO role_permissions (role_id, permission_id)
    SELECT r.id, p.id
    FROM roles r
    JOIN permissions p
      ON p.permission_key IN ('user.manage','role.manage','email.manage','audit.view','profile.edit','role.assign','permission.manage','module.manage')
    WHERE r.role_name = 'admin';

  -- Optionally give “user” role only the “profile.edit” permission:
  INSERT IGNORE INTO role_permissions (role_id, permission_id)
    SELECT r.id, p.id
    FROM roles r
    JOIN permissions p
      ON p.permission_key = 'profile.edit'
    WHERE r.role_name = 'user';


    INSERT IGNORE INTO modules
      (title, description, icon_class, box_color, link, permission_key, sort_order)
    VALUES
      ('Users', 'Manage Users', 'fas fa-users', 'bg-info', 'users.php', 'user.manage', 1),
      ('Roles', 'Manage Roles', 'fas fa-user-tag', 'bg-primary', 'roles.php', 'role.manage', 2),
      ('Role Perms', 'Assign Permissions to Roles', 'fas fa-key', 'bg-secondary', 'role_permissions.php', 'role.assign', 3),
      ('Permissions', 'Manage Permissions', 'fas fa-lock', 'bg-success', 'permissions.php', 'permission.manage', 4),
      ('Email', 'Email Settings', 'fas fa-envelope', 'bg-warning', 'email_settings.php', 'email.manage', 5),
      ('Logs', 'Audit Log', 'fas fa-file-alt', 'bg-danger', 'audit_log.php', 'audit.view', 6),
      ('Modules','Modules Page','fas fa-lock','bg-success','modules.php','module.manage',7);