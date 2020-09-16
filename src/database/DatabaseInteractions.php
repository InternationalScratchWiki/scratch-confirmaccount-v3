<?php
require_once __DIR__ . '/../objects/AccountRequest.php';

function getBlockReason($username) {
	$dbr = wfGetDB( DB_REPLICA );

	$row = $dbr->selectRow('scratch_accountrequest_block', array('block_reason'), ['LOWER(block_username)' => strtolower($username)], __METHOD__);
	return $row ? $row->block_reason : false;
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

function getAccountRequests($status, $offset = 0, $limit = 10, $username = null) {
	$dbr = wfGetDB( DB_REPLICA );
	$result = $dbr->select('scratch_accountrequest', array('request_id', 'request_username', 'request_email', 'request_timestamp', 'request_notes', 'request_ip', 'request_status'), ['request_status' => $status], __METHOD__, ['order_by' => ['request_timestamp', 'DESC']]);
	
	$requests = array();
	foreach ($result as $row) {
		$requests[] = AccountRequest::fromRow($row);
	}
	
	return $requests;
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