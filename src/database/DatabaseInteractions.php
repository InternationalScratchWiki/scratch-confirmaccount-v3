<?php
require_once __DIR__ . '/../objects/AccountRequest.php';

function isUsernameBlocked($username) {
}

function createAccountRequest($username, $requestNotes, $email, $ip) {
	$dbw = wfGetDB( DB_MASTER );
	$dbw->insert('scratch_accountrequest', [
		'request_username' => $username,
		'request_email' => $email,
		'request_timestamp' => wfTimestampNow(),
		'request_notes' => $requestNotes,
		'request_ip' => $ip,
		'request_status' => 'unreviewed'
	], __METHOD__);
	
	return $dbw->insertID();
}

function getAccountRequests($status) {
}

function getAccountRequestById($id) {
}

function addHistoryEntry($request, $history) {
}

function getRequestHistory($request) {
}

function processRequest($request, $status, $comment) {
}

function userExists($username) {
}

function hasActiveRequest($username) {
	$dbr = wfGetDB( DB_REPLICA );
	
	return $dbr->selectRowCount('scratch_accountrequest', array('1'), ['LOWER(request_username)' => strtolower($username), 'request_status' => ['unreviewed']], __METHOD__) > 0;
}