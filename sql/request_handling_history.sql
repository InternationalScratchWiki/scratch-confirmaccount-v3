BEGIN;

CREATE TABLE IF NOT EXISTS /*_*/scratch_accountrequest_history (
	history_id integer unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
	history_request_id integer unsigned NOT NULL,
	history_action varchar(255) NOT NULL,
	history_performer integer unsigned,
	history_comment text,
	history_timestamp varbinary(14) NOT NULL
)/*$wgDBTableOptions*/;

CREATE INDEX /*i*/history_request_id ON /*_*/scratch_accountrequest_history (history_request_id);
CREATE INDEX /*i*/history_timestamp ON /*_*/scratch_accountrequest_history (history_timestamp);

COMMIT;