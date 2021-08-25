<?php
class AccountRequest {
	var $id;
	var $username;
	var $email;
	var $requestNotes;
	var $status;
	var $timestamp;
	var $ip;
	var $lastUpdated;
	var $expiry;
	var $passwordHash;
	var $emailConfirmed;
	var $emailToken;
	var $emailExpiry;

	function __construct($id, $username, $email, $passwordHash, $requestNotes, $status, $timestamp, $ip, $lastUpdated, $expiry, $emailConfirmed, $emailToken, $emailExpiry) {
		$this->id = $id;
		$this->username = $username;
		$this->email = $email;
		$this->requestNotes = $requestNotes;
		$this->status = $status;
		$this->timestamp = $timestamp;
		$this->ip = $ip;
		$this->passwordHash = $passwordHash;
		$this->lastUpdated = $lastUpdated;
		$this->expiry = $expiry;
		$this->emailConfirmed = $emailConfirmed;
		$this->emailToken = $emailToken;
		$this->emailExpiry = $emailExpiry;
	}

	static function fromRow($row) {
		return new AccountRequest(
			$row->request_id,
			$row->request_username,
			$row->request_email,
			$row->password_hash,
			$row->request_notes,
			$row->request_status,
			$row->request_timestamp,
			$row->request_ip,
			$row->request_last_updated,
			$row->request_expiry,
			$row->request_email_confirmed,
			$row->request_email_token,
			$row->request_email_token_expiry
		);
	}
	
	function isActive() : bool {
		return in_array($this->status, ['new', 'awaiting-admin', 'awaiting-user']);
	}
	
	function isExpired() : bool {
		return $this->expiry != null && $this->expiry < wfTimestampNow();
	}
}

class AccountRequestHistoryEntry {
	var $timestamp;
	var $action;
	var $comment;
	var $performer;

	function __construct($timestamp, $action, $comment, $performer) {
		$this->timestamp = $timestamp;
		$this->action = $action;
		$this->comment = $comment;
		$this->performer = $performer;
	}

	static function fromRow($row) {
		return new AccountRequestHistoryEntry($row->history_timestamp, $row->history_action, $row->history_comment, $row->user_name);
	}
}

class AccountRequestUsernameBlock {
	var $blockedUsername;
	var $reason;
	var $expirationTimestamp;

	function __construct($blockedUsername, $reason, $expirationTimestamp) {
		$this->blockedUsername = $blockedUsername;
		$this->reason = $reason;
		$this->expirationTimestamp = $expirationTimestamp;
	}

	static function fromRow($row) {
		return new AccountRequestUsernameBlock($row->block_username, $row->block_reason, $row->block_expiration_timestamp);
	}
}
