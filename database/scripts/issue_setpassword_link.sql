
WITH raw AS (
    SELECT replace(gen_random_uuid()::text || gen_random_uuid()::text, '-', '') AS token
), ins AS (
    INSERT INTO password_token (user_id, purpose, token_hash, expires_at)
    SELECT u.user_id, 'SET_PASSWORD',
           encode(sha256(convert_to(r.token, 'UTF8')), 'hex'),
           now() + interval '24 hours'
      FROM app_user u CROSS JOIN raw r
     WHERE lower(u.email) = lower(:email)
    RETURNING 1
)
SELECT r.token AS raw_token_copy_now, (SELECT count(*) FROM ins) AS rows_created
  FROM raw r;
