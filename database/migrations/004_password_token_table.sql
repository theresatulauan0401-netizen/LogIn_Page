-- 004_password_token_table.sql  (task S1-DBA-04)
-- One table for both "reset my password" and "first-time set-password" links.
-- The raw token is emailed and NEVER stored; only its hash is.

CREATE TABLE password_token (
    token_id   BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id    INT          NOT NULL REFERENCES app_user,
    purpose    VARCHAR(12)  NOT NULL
               CONSTRAINT purpose_ck CHECK (purpose IN ('RESET','SET_PASSWORD')),
    token_hash CHAR(64)     NOT NULL UNIQUE,           -- hex SHA-256 of the raw token
    expires_at TIMESTAMPTZ  NOT NULL,
    is_used    BOOLEAN      NOT NULL DEFAULT FALSE,
    used_at    TIMESTAMPTZ,
    created_at TIMESTAMPTZ  NOT NULL DEFAULT now(),
    CONSTRAINT used_consistent_ck CHECK ((is_used AND used_at IS NOT NULL) OR (NOT is_used AND used_at IS NULL))
);
CREATE INDEX password_token_user_ix ON password_token (user_id, purpose);


