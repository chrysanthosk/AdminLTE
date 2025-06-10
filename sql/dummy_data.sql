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
   ('reports.view','Reports'),
   ('sms.manage','Sms Providers');

  -- Assign some default permissions to “admin” role (role_id=1 if that was seeded earlier)
  INSERT IGNORE INTO role_permissions (role_id, permission_id)
    SELECT r.id, p.id
    FROM roles r
    JOIN permissions p
      ON p.permission_key IN ('user.manage','role.manage','email.manage','audit.view','profile.edit',
      'role.assign','permission.manage','module.manage','client.manage','vat.manage','product_category.manage',
      'product.manage','service_category.manage','services.manage','pricelist.manage','therapists.manage',
      'calendar_view.view','appointment.manage','dash_settings.manage','cashier.manage','reports.view','pricelist_category.manage','sms.manage')
    WHERE r.role_name = 'admin';

  -- Optionally give “user” role only the “profile.edit” permission:
  INSERT IGNORE INTO role_permissions (role_id, permission_id)
    SELECT r.id, p.id
    FROM roles r
    JOIN permissions p
      ON p.permission_key = 'profile.edit'
    WHERE r.role_name = 'user';

INSERT INTO `menu_sections` (section_key,label,icon_class,sort_order) VALUES
 ('Admin',               'Admin',               'fas fa-cog',            1),
 ('CRM',                 'CRM',                 'fas fa-users',          2),
 ('Products & Pricing',  'Products & Pricing',  'fas fa-boxes',          3),
 ('Sales',               'Sales',               'fas fa-cash-register',  4),
 ('Reporting & Logs',    'Reporting & Logs',    'fas fa-chart-bar',      5),
 ('Settings & Config',   'Settings & Config',   'fas fa-sliders-h',      6);


INSERT INTO `modules`
  (title, description, icon_class, box_color, link, permission_key, sort_order, section_id)
VALUES
  -- Admin (section_id = 1)
  ('Users',        'Manage Users',                         'fas fa-users',       'bg-info', 'users.php',             'user.manage',               1, 1),
  ('Roles',        'Manage Roles',                         'fas fa-user-tag',    'bg-primary','roles.php',             'role.manage',               2, 1),
  ('Role Perms',   'Assign Permissions to Roles',          'fas fa-key',         'bg-secondary','role_permissions.php','role.assign',               3, 1),
  ('Permissions',  'Manage Permissions',                   'fas fa-lock',        'bg-success','permissions.php',       'permission.manage',         4, 1),
  ('Modules',      'Manage Application Modules & Menu',    'fas fa-cubes',       'bg-info', 'modules.php',           'module.manage',             5, 1),
  ('Email',        'Email Settings',                       'fas fa-envelope',    'bg-warning','email_settings.php',    'email.manage',              6, 1),
  ('Logs',         'Audit Log',                            'fas fa-file-alt',    'bg-danger', 'audit_log.php',        'audit.view',                7, 1),

  -- CRM (section_id = 2)
  ('Clients',      'Clients Page',                         'fas fa-users',       'bg-info', 'clients.php',           'client.manage',             8, 2),
  ('Therapists',   'Therapists Page',                      'fas fa-user-md',     'bg-info', 'therapists.php',        'therapists.manage',         9, 2),
  ('Calendar View','Calendar & Scheduler View',             'fas fa-calendar-alt','bg-info','calendar_view.php','calendar_view.view',      10,2),
  ('Appointments', 'Manage Appointments',                  'fas fa-calendar-check','bg-info','appointments.php','appointment.manage',    11,2),

  -- Products & Pricing (section_id = 3)
  ('VAT Types',            'Manage VAT Types',               'fas fa-percent',    'bg-info',   'vat.php',                 'vat.manage',               12,3),
  ('Product Categories',   'Product Categories Page',        'fas fa-tags',       'bg-info',   'product_category.php',    'product_category.manage',  13,3),
  ('Products',             'Products Page',                  'fas fa-box-open',   'bg-info',   'products.php',            'product.manage',           14,3),
  ('Service Categories',   'Service Category Page',          'fas fa-concierge-bell','bg-info','service_category.php', 'service_category.manage', 15,3),
  ('Services',             'Services Page',                  'fas fa-hands-helping','bg-info','services.php',          'services.manage',          16,3),
  ('Pricelist',            'Price List Page',                'fas fa-list-alt',   'bg-info',   'pricelist.php',           'pricelist.manage',         17,3),
  ('Pricelist Categories', 'Pricelist Categories Page',      'fas fa-list',       'bg-info',   'pricelist_categories.php','pricelist_category.manage',18,3),
  ('SMS Providers',        'SMS Provider Settings',          'fas fa-sms',        'bg-info',   'sms_settings.php',       'sms.manage',              19,3),

  -- Sales (section_id = 4)
  ('Cashier', 'Cashier Page', 'fas fa-cash-register', 'bg-info', 'cashier.php', 'cashier.manage', 20,4),

  -- Reporting & Logs (section_id = 5)
  ('Reports', 'Reports Page', 'fas fa-chart-bar', 'bg-info', 'reports.php', 'reports.view', 21,5),

  -- Settings & Config (section_id = 6)
  ('Dashboard Settings', 'Dashboard Settings Page','fas fa-sliders-h','bg-info','dashboard_settings.php','dash_settings.manage',22,6),
  ('Email Settings',     'Email Settings Page',     'fas fa-envelope','bg-info','email_settings.php','email.manage',     23,6),
  ('SMS Settings',     'SMS Provider Configuration',     'fas fa-sms','bg-info','sms_settings.php','sms.manage',     24,6),
  ('SideMenu Settings',     'SideMenu Configuration',     'fas fa-cubes','bg-info','sidemenu.php','module.manage',     25,1);

INSERT INTO sms_providers (name, doc_url) VALUES
  ('Twilio',     'https://www.twilio.com/docs/sms'),
  ('Vonage',     'https://developer.vonage.com/messaging/sms/overview'),
  ('Plivo',      'https://www.plivo.com/docs/sms'),
  ('Infobip',    'https://www.infobip.com/docs/sms');