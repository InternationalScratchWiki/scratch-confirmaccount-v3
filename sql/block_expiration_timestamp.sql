BEGIN;

ALTER TABLE /*_*/scratch_accountrequest_block ADD COLUMN IF NOT EXISTS block_expiration_timestamp VARBINARY(14);

COMMIT;