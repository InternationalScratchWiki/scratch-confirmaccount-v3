<?php
class AccountRequest {
	var $id;
	var $username;
	var $requestNotes;
	var $status;
	var $timestamp;
	
	function __construct($id, $username, $requestNotes, $status, $timestamp) {
		$this->id = $id;
		$this->username = $username;
		$this->requestNotes = $requestNotes;
		$this->status = $status;
		$this->timestamp = $timestamp;
	}
	
	static function fromRow($row) {
		return new AccountRequest($row->request_id, $row->request_username, $row->request_notes, $row->request_status, $row->request_timestamp);
	}
}

class AccountRequestHistoryEntry {
	var $timestamp;
	var $action;
	var $comment;
	var $performer;
	
	function __construct($timestamp, $action, $comment) {
		$this->timestamp = $timestamp;
		$this->action = $action;
		$this->comment = $comment;
	}
	
	static function fromRow($row) {
		return new AccountRequestHistoryEntry($row->history_timestamp, $row->history_action, $row->history_comment);
	}
}