<?php
class AccountRequest {
	var $id;
	var $username;
	var $requestNotes;
	var $status;
	
	function accept() {
	}
	
	function setStatus() {
	}
}

class AccountRequestHistoryEntry {
	var $timestamp;
	var $action;
	var $comment;
	var $admin;
}