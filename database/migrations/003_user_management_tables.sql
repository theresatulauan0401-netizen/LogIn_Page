-- 003_user_management_tables.sql  (task S1-DBA-03)
-- Accounts, roles, modules, the per-action rights catalogue, and who holds which right.
-- Guide sections 2.4 and 3.8. Reuses the stamp / soft-delete triggers from migration 002.

CREATE TABLE role (
    role_code  VARCHAR(10) PRIMARY KEY,
    role_name  VARCHAR(30) NOT NULL
);

CREATE TABLE module (
    module_code VARCHAR(10) PRIMARY KEY,
    module_name VARCHAR(30) NOT NULL,
    sort_order  SMALLINT    NOT NULL
);

-- One row per checkbox in the Rights dialog (36 rows, seeded in 005)
CREATE TABLE rights_catalogue (
    right_code  VARCHAR(30) PRIMARY KEY,
    module_code VARCHAR(10) NOT NULL REFERENCES module,
    description VARCHAR(60) NOT NULL,
    sort_order  SMALLINT    NOT NULL
);

CREATE TABLE app_user (
    user_id         INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    email           VARCHAR(100) NOT NULL,
    first_name      VARCHAR(50)  NOT NULL,
    last_name       VARCHAR(50)  NOT NULL,
    password_hash   VARCHAR(255),                       -- NULL until the first set-password link is used
    role_code       VARCHAR(10)  NOT NULL DEFAULT 'USER' REFERENCES role,
    approval_status VARCHAR(8)   NOT NULL DEFAULT 'pending'
                    CONSTRAINT approval_status_ck CHECK (approval_status IN ('pending','approved')),
    is_blocked      BOOLEAN      NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT now(),
    is_deleted      BOOLEAN      NOT NULL DEFAULT FALSE,
    stamp           VARCHAR(60)
);
CREATE UNIQUE INDEX app_user_email_uq ON app_user (lower(email));

-- A ticked checkbox = one row. Unticking = soft delete (is_deleted = true).
CREATE TABLE user_permission (
    user_id    INT         NOT NULL REFERENCES app_user,
    right_code VARCHAR(30) NOT NULL REFERENCES rights_catalogue,
    granted_by INT         REFERENCES app_user,
    is_deleted BOOLEAN     NOT NULL DEFAULT FALSE,
    stamp      VARCHAR(60),
    PRIMARY KEY (user_id, right_code)
);

-- Audit trigger for app_user that never copies password_hash into audit_log
CREATE OR REPLACE FUNCTION trg_audit_log_user() RETURNS TRIGGER
LANGUAGE plpgsql AS $$
DECLARE
    v_action TEXT;
BEGIN
    IF TG_OP = 'INSERT' THEN
        INSERT INTO audit_log (table_name, action, record_key, old_values, new_values, action_by)
        VALUES (TG_TABLE_NAME, 'ADD', NEW.user_id::text, NULL,
                to_jsonb(NEW) - 'password_hash', audit_user());
    ELSE
        IF (to_jsonb(OLD) - 'stamp') = (to_jsonb(NEW) - 'stamp') THEN
            RETURN NULL;
        END IF;
        v_action := CASE
                      WHEN NOT OLD.is_deleted AND NEW.is_deleted THEN 'DELETE'
                      WHEN OLD.is_deleted AND NOT NEW.is_deleted THEN 'RESTORE'
                      ELSE 'EDIT'
                    END;
        INSERT INTO audit_log (table_name, action, record_key, old_values, new_values, action_by)
        VALUES (TG_TABLE_NAME, v_action, NEW.user_id::text,
                to_jsonb(OLD) - 'password_hash', to_jsonb(NEW) - 'password_hash', audit_user());
    END IF;
    RETURN NULL;
END;
$$;

-- app_user: stamp, no hard delete, audit (without the hash), active view
CREATE TRIGGER app_user_biu_stamp  BEFORE INSERT OR UPDATE ON app_user
    FOR EACH ROW EXECUTE FUNCTION trg_set_stamp();
CREATE TRIGGER app_user_bd_block   BEFORE DELETE ON app_user
    FOR EACH ROW EXECUTE FUNCTION trg_block_hard_delete();
CREATE TRIGGER app_user_bt_block   BEFORE TRUNCATE ON app_user
    FOR EACH STATEMENT EXECUTE FUNCTION trg_block_hard_delete();
CREATE TRIGGER app_user_aiu_audit  AFTER INSERT OR UPDATE ON app_user
    FOR EACH ROW EXECUTE FUNCTION trg_audit_log_user();
CREATE VIEW app_user_active AS SELECT * FROM app_user WHERE NOT is_deleted;

-- user_permission: same pattern, standard audit function (key = user_id|right_code)
CREATE TRIGGER user_permission_biu_stamp BEFORE INSERT OR UPDATE ON user_permission
    FOR EACH ROW EXECUTE FUNCTION trg_set_stamp();
CREATE TRIGGER user_permission_bd_block  BEFORE DELETE ON user_permission
    FOR EACH ROW EXECUTE FUNCTION trg_block_hard_delete();
CREATE TRIGGER user_permission_bt_block  BEFORE TRUNCATE ON user_permission
    FOR EACH STATEMENT EXECUTE FUNCTION trg_block_hard_delete();
CREATE TRIGGER user_permission_aiu_audit AFTER INSERT OR UPDATE ON user_permission
    FOR EACH ROW EXECUTE FUNCTION trg_audit_log('user_id','right_code');
CREATE VIEW user_permission_active AS SELECT * FROM user_permission WHERE NOT is_deleted;
