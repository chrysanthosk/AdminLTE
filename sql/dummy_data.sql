-- dummy_data.sql - initial data for admin panel
INSERT INTO roles (role_name,role_desc ) VALUES
('admin','Full access, managing users and roles'),
('user','Standard user with limited access'),
('therapist','Therapist Role with limited access');

INSERT INTO users (username, email, password_hash, role_id, first_name, last_name, theme, twofa_enabled, twofa_secret) VALUES
('admin', 'admin@example.com', '$2b$12$0I95Alg5uzukxtv9gxMuMuOyYyfshFL5R7eh0n3WjgqA3n2.GdXY.', '1', 'Admin', 'User', 'light', 0, NULL),
('user1', 'user1@example.com', '$2b$12$2K5NlNUyz878mXkEkwKwfuaqtad/dmNHaue/FsCGisFGWBZ1lT5vS', '2', 'User', 'One', 'light', 0, NULL);

INSERT INTO email_settings (smtp_host, smtp_port, smtp_user, smtp_pass, smtp_secure,from_email,from_name) VALUES
('smtp.example.com', '587', 'user@example.com', 'securepassword', 'tls','user@example.com','Admin LTE');

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
    ('module.manage', 'Create/Edit/Delete Dashboard Modules'),
    ('client.manage', 'Client Create/Edit/Delete'),
    ('product_category.manage', 'Product Category Create/Edit/Delete'),
    ('vat.manage', 'VAT Management , Create/Edit/Delete'),
    ('product.manage', 'Create/Edit/Delete Products'),
    ('service_category.manage', 'Create/Edit/Delete Service Category'),
   ('services.manage', 'Create/Edit/Delete Service'),
   ('pricelist.manage','Create/Edit/Delete Pricelist'),
   ('pricelist_category.manage', 'Create/Edit/Delete Pricelist Category'),
   ('therapists.manage','Create/Edit/Delete Therapist'),
   ('calendar_view.view','Calendar View'),
   ('appointment.manage','Create/Edit/Delete Appointments'),
   ('dash_settings.manage','Dashboard Settings'),
   ('cashier.manage','Cashier Module'),
   ('reports.view','Reports');

  -- Assign some default permissions to “admin” role (role_id=1 if that was seeded earlier)
  INSERT IGNORE INTO role_permissions (role_id, permission_id)
    SELECT r.id, p.id
    FROM roles r
    JOIN permissions p
      ON p.permission_key IN ('user.manage','role.manage','email.manage','audit.view','profile.edit',
      'role.assign','permission.manage','module.manage','client.manage','vat.manage','product_category.manage',
      'product.manage','service_category.manage','services.manage','pricelist.manage','therapists.manage',
      'calendar_view.view','appointment.manage','dash_settings.manage','cashier.manage','reports.view','pricelist_category.manage')
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
      ('Modules','Modules Page','fas fa-lock','bg-success','modules.php','module.manage',7),
      ('Clients','Clients Page','fas fa-users','bg-info','clients.php','client.manage',8),
      ('Vat','Vat Page','fas fa-users','bg-info','vat.php','vat.manage',9),
      ('Product Category','Product Category Page','fas fa-users','bg-info','product_category.php','product_category.manage',10),
      ('Products','Products Page','fas fa-users','bg-info','products.php','product.manage',11),
      ('Service Category','Service Category Page','fas fa-users','bg-info','service_category.php','product.manage',12),
      ('Services','Services Page','fas fa-users','bg-info','services.php','services.manage',13),
      ('PriceList','PriceList Page','fas fa-users','bg-info','pricelist.php','pricelist.manage',14),
      ('Therapists','Therapists Page','fas fa-users','bg-info','therapists.php','therapists.manage',15),
      ('Calendar View','Calendar View Page','fas fa-users','bg-info','calendar_view.php','calendar_view.view',16),
      ('Appointments','Appointments Page','fas fa-users','bg-info','appointments.php','appointment.manage',17),
      ('Dashboard Settings','Dashboard Settings Page','fas fa-users','bg-info','dashboard_settings.php','dash_settings.manage',18),
      ('Cashier ','Cashier  Page','fas fa-users','bg-info','cashier.php','cashier.manage',19),
      ('Reports ','Reports Page','fas fa-users','bg-info','reports.php','reports.view',20),
      ('PriceList Categories ','Create/Edit/Delete Pricelist Category','fas fa-users','bg-info','pricelist_categories.php','pricelist_category.manage',21);