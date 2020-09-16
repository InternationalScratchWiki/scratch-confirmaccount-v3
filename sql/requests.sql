BEGIN;

CREATE TABLE IF NOT EXISTS /*_*/scratch_accountrequest (
	-- Primary key
	request_id integer unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
	request_username VARCHAR(255) binary NOT NULL,
	password_hash tinyblob NOT NULL,
	request_email VARCHAR(255) binary,
	request_timestamp varbinary(14) NOT NULL,
	request_notes TEXT NOT NULL,
	request_ip VARCHAR(255) NOT NULL,
	request_status VARCHAR(255) binary NOT NULL DEFAULT 'pending'
)/*$wgDBTableOptions*/;


CREATE INDEX /*i*/request_username ON /*_*/scratch_accountrequest (request_username);
CREATE INDEX /*i*/request_status ON /*_*/scratch_accountrequest (request_status);

COMMIT;
