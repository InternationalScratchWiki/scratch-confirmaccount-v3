BEGIN;

CREATE TABLE IF NOT EXISTS /*_*/scratch_accountrequest_request (
	-- Primary key
	request_id integer unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
	request_username VARCHAR(255) binary NOT NULL,
	password_hash tinyblob NOT NULL,
	request_email VARCHAR(255) binary,
	request_timestamp varbinary(14) NOT NULL,
	request_last_updated varbinary(14) NOT NULL,
	request_expiry varbinary(14),
	request_notes TEXT NOT NULL,
	request_ip VARCHAR(255) NOT NULL,
	request_status VARCHAR(255) binary NOT NULL DEFAULT 'new',
	request_email_token varbinary(32),
	request_email_token_expiry varbinary(14),
	request_email_confirmed TINYINT(1) NOT NULL DEFAULT 0
)/*$wgDBTableOptions*/;


CREATE INDEX /*i*/request_username ON /*_*/scratch_accountrequest_request (request_username);
CREATE INDEX/*i*/request_status ON /*_*/scratch_accountrequest_request (request_status);
CREATE INDEX /*i*/request_last_updated ON /*_*/scratch_accountrequest_request (request_last_updated);
CREATE INDEX /*i*/request_expiry ON /*_*/scratch_accountrequest_request (request_expiry);

COMMIT;
