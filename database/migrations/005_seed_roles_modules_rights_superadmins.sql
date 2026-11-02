-- 005_seed_roles_modules_rights_superadmins.sql  (task S1-DBA-05)
-- No passwords here. Each superadmin sets the first password through a one-time link
-- (see scripts/issue_setpassword_link.sql).

INSERT INTO role (role_code, role_name) VALUES
    ('SUPERADMIN', 'Superadmin'),
    ('ADMIN',      'Admin'),
    ('USER',       'User')
ON CONFLICT DO NOTHING;

INSERT INTO module (module_code, module_name, sort_order) VALUES
    ('CUSTOMER', 'Customer Management', 1),
    ('PRODUCT',  'Product Management',  2),
    ('HR',       'Human Resources',     3),
    ('SALES',    'Sales Management',    4),
    ('REPORTS',  'Reports',             5)
ON CONFLICT DO NOTHING;

-- The 36 rights from guide section 3.8 (3 + 6 + 13 + 7 + 7)
INSERT INTO rights_catalogue (right_code, module_code, description, sort_order) VALUES
    ('CUSTOMER_ADD',        'CUSTOMER', 'Add Customer',        1),
    ('CUSTOMER_EDIT',       'CUSTOMER', 'Edit Customer',       2),
    ('CUSTOMER_DELETE',     'CUSTOMER', 'Delete Customer',     3),

    ('PRODUCT_ADD',         'PRODUCT',  'Add Product',         1),
    ('PRODUCT_EDIT',        'PRODUCT',  'Edit Product',        2),
    ('PRODUCT_DELETE',      'PRODUCT',  'Delete Product',      3),
    ('PRICE_ADD',           'PRODUCT',  'Add Price',           4),
    ('PRICE_EDIT',          'PRODUCT',  'Edit Price',          5),
    ('PRICE_DELETE',        'PRODUCT',  'Delete Price',        6),

    ('JOB_ADD',             'HR',       'Add Job',             1),
    ('JOB_EDIT',            'HR',       'Edit Job',            2),
    ('JOB_DELETE',          'HR',       'Delete Job',          3),
    ('DEPARTMENT_ADD',      'HR',       'Add Department',      4),
    ('DEPARTMENT_EDIT',     'HR',       'Edit Department',     5),
    ('DEPARTMENT_DELETE',   'HR',       'Delete Department',   6),
    ('EMPLOYEE_ADD',        'HR',       'Add Employee',        7),
    ('EMPLOYEE_EDIT',       'HR',       'Edit Employee',       8),
    ('EMPLOYEE_DELETE',     'HR',       'Delete Employee',     9),
    ('JOBHISTORY_VIEW',     'HR',       'View Job History',   10),
    ('JOBHISTORY_ADD',      'HR',       'Add Job History',    11),
    ('JOBHISTORY_EDIT',     'HR',       'Edit Job History',   12),
    ('JOBHISTORY_DELETE',   'HR',       'Delete Job History', 13),

    ('SALE_ADD',            'SALES',    'Add Sale',            1),
    ('SALE_EDIT',           'SALES',    'Edit Sale',           2),
    ('SALE_DELETE',         'SALES',    'Delete Sale',         3),
    ('SALEDETAIL_VIEW',     'SALES',    'View Sale Details',   4),
    ('SALEDETAIL_ADD',      'SALES',    'Add Sale Detail',     5),
    ('SALEDETAIL_EDIT',     'SALES',    'Edit Sale Detail',    6),
    ('SALEDETAIL_DELETE',   'SALES',    'Delete Sale Detail',  7),

    ('RPT_TOP_CUSTOMERS',   'REPORTS',  'Top Customers',       1),
    ('RPT_PRODUCT_MASTER',  'REPORTS',  'Product Master List', 2),
    ('RPT_TOP_PRODUCTS',    'REPORTS',  'Top Products',        3),
    ('RPT_ACTIVE_EMPLOYEES','REPORTS',  'Active Employees',    4),
    ('RPT_ALL_EMPLOYEES',   'REPORTS',  'All Employees',       5),
    ('RPT_EMPLOYEE_HISTORY','REPORTS',  'Employee Job History',6),
    ('RPT_TOP_SALES',       'REPORTS',  'Top Sales',           7)
ON CONFLICT DO NOTHING;

INSERT INTO app_user (email, first_name, last_name, role_code, approval_status) VALUES
    ('REPLACE-ME.rhianroxanne.burasca@neu.edu.ph',     'Rhian',     'Burasca',  'SUPERADMIN', 'approved'),
    ('REPLACE-ME.katricegail.bughaw@neu.edu.ph',   'Katrice Gail',   'Bughaw',   'SUPERADMIN', 'approved'),
    ('REPLACE-ME.archielynnearl.deloya@neu.edu.ph',    'Archie Lynne Arl',    'Deloya',   'SUPERADMIN', 'approved'),
    ('REPLACE-ME.cassandra.garbosa@neu.edu.ph', 'Cassandra', 'Garbosa',  'SUPERADMIN', 'approved'),
    ('REPLACE-ME.theresa.tulauan@neu.edu.ph',      'Theresa',      'Tulauan',  'SUPERADMIN', 'approved')
ON CONFLICT DO NOTHING;
