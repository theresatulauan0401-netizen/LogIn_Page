
SELECT check_name, expected, actual, CASE WHEN expected = actual THEN 'PASS' ELSE 'FAIL' END AS result
FROM (
    SELECT 'employees loaded'      AS check_name, 32  AS expected, (SELECT count(*) FROM employee)         AS actual UNION ALL
    SELECT 'customers loaded',                    82,              (SELECT count(*) FROM customer)                    UNION ALL
    SELECT 'sales loaded',                        124,             (SELECT count(*) FROM sales)                       UNION ALL
    SELECT 'sales detail loaded',                 313,             (SELECT count(*) FROM salesDetail)                 UNION ALL
    SELECT 'payments loaded',                     165,             (SELECT count(*) FROM payment)                     UNION ALL
    SELECT 'rights in catalogue',                 36,              (SELECT count(*) FROM rights_catalogue)            UNION ALL
    SELECT 'modules',                             5,               (SELECT count(*) FROM module)                      UNION ALL
    SELECT 'roles',                               3,               (SELECT count(*) FROM role)                        UNION ALL
    SELECT 'superadmins',                         5,               (SELECT count(*) FROM app_user WHERE role_code = 'SUPERADMIN') UNION ALL
    SELECT 'passwords stored in seed',            0,               (SELECT count(*) FROM app_user WHERE password_hash IS NOT NULL) UNION ALL
    SELECT 'active views exist',                  12,              (SELECT count(*) FROM information_schema.views WHERE table_schema = 'public' AND table_name LIKE '%\_active')
) c
ORDER BY 1;
