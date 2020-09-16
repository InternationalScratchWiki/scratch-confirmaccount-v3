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
		'request_status' => 'new'
	], __METHOD__);
	
	return $dbw->insertID();
}

function getAccountRequests(string $status, int $offset = 0, int $limit = 10, string $username = null) : array {
	$dbr = wfGetDB( DB_REPLICA );
	$result = $dbr->select('scratch_accountrequest', array('request_id', 'request_username', 'request_email', 'request_timestamp', 'request_notes', 'request_ip', 'request_status'), ['request_status' => $status], __METHOD__, ['order_by' => ['request_timestamp', 'DESC']]);
	
	$requests = array();
	foreach ($result as $row) {
		$requests[] = AccountRequest::fromRow($row);
	}
	
	return $requests;
}

function getAccountRequestById($id) {
	$dbr = wfGetDB( DB_REPLICA );
	$result = $dbr->selectRow('scratch_accountrequest', array('request_id', 'request_username', 'request_email', 'request_timestamp', 'request_notes', 'request_ip', 'request_status'), ['request_id' => $id], __METHOD__);
	
	return $result ? AccountRequest::fromRow($result) : false;
}

function actionRequest(AccountRequest $request, string $action, $userPerformingAction, string $comment) {
	$dbw = wfGetDB( DB_MASTER );
	
	$dbw->insert('scratch_accountrequest_history', [
		'history_request_id' => $request->id,
		'history_action' => $action,
		'history_comment' => $comment,
		'history_performer' => $userPerformingAction,
		'history_timestamp' => wfTimestampNow()
	], __METHOD__);
	
	//if the action also updates the status, then set the status appropriately
	if (isset(actionToStatus[$action])) {
		$dbw->update('scratch_accountrequest', [
			'request_status' => actionToStatus[$action]
		], ['request_id' => $request->id], __METHOD__);
	}
}

function getRequestHistory(AccountRequest $request) : array {
	$dbr = wfGetDB( DB_REPLICA );
	
	$result = $dbr->select('scratch_accountrequest_history', [
		'history_timestamp',
		'history_action',
		'history_comment',
	], ['history_request_id' => $request->id], __METHOD__, ['order_by' => ['history_timestamp', 'ASC']]);
	
	$history = array();
	foreach ($result as $row) {
		$history[] = AccountRequestHistoryEntry::fromRow($row);
	}
	
	return $history;
}

function processRequest($request, $status, $comment) {
}

function userExists($username) {
}

function hasActiveRequest($username) {
	$dbr = wfGetDB( DB_REPLICA );
	
	return $dbr->selectRowCount('scratch_accountrequest', array('1'), ['LOWER(request_username)' => strtolower($username), 'request_status' => ['unreviewed']], __METHOD__) > 0;
}
