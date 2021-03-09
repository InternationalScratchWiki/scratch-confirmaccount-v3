BEGIN;

ALTER TABLE /*_*/scratch_accountrequest_request ADD COLUMN IF NOT EXISTS request_active_username VARCHAR(255) binary UNIQUE;

COMMIT;