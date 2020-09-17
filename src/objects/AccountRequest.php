<?php
class AccountRequest {
	var $id;
	var $username;
	var $requestNotes;
	var $status;
	var $timestamp;
	var $lastUpdated;
	var $expiry;
	var $passwordHash;

	function __construct($id, $username, $passwordHash, $requestNotes, $status, $timestamp, $lastUpdated, $expiry) {
		$this->id = $id;
		$this->username = $username;
		$this->requestNotes = $requestNotes;
		$this->status = $status;
		$this->timestamp = $timestamp;
		$this->passwordHash = $passwordHash;
	}

	static function fromRow($row) {
		return new AccountRequest($row->request_id, $row->request_username, $row->password_hash, $row->request_notes, $row->request_status, $row->request_timestamp, $row->request_last_updated, $row->request_expiry);
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
