BEGIN;

CREATE TABLE IF NOT EXISTS /*_*/scratch_accountrequest_block (
	-- Primary key: the username that is blocked from submitting requests 
	block_username VARCHAR(255) binary NOT NULL PRIMARY KEY,
	-- user.user_id of the user who performed the block
	block_blocker_user_id integer unsigned NOT NULL,
	-- timestamp for when the block was performed
	block_timestamp VARBINARY(14) NOT NULL
)/*$wgDBTableOptions*/;

COMMIT;