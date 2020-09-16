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
	var $admin;
}