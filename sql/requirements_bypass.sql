BEGIN;

CREATE TABLE IF NOT EXISTS /*_*/scratch_accountrequest_requirements_bypass (
	-- Primary key
	bypass_username VARCHAR(255) binary NOT NULL PRIMARY KEY
)/*$wgDBTableOptions*/;

COMMIT;
