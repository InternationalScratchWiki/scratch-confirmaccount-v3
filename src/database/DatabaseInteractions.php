<?php
require_once __DIR__ . '/../objects/AccountRequest.php';
require_once __DIR__ . '/../common.php';

function getBlockReason($username) {
	$dbr = wfGetDB( DB_REPLICA );

	$row = $dbr->selectRow('scratch_accountrequest_block', array('block_reason'), ['LOWER(block_username)' => strtolower($username)], __METHOD__);
	return $row ? $row->block_reason : false;
}

function createAccountRequest($username, $passwordHash, $requestNotes, $email, $ip) {
	$dbw = wfGetDB( DB_MASTER );
	$dbw->insert('scratch_accountrequest_request', [
		'request_username' => $username,
		'password_hash' => $passwordHash,
		'request_email' => $email,
		'request_timestamp' => $dbw->timestamp(),
		'request_last_updated' => $dbw->timestamp(),
		'request_notes' => $requestNotes,
		'request_ip' => $ip,
		'request_status' => 'new'
	], __METHOD__);

	return $dbw->insertID();
}

abstract class AbstractAccountRequestPager extends ReverseChronologicalPager {
	private $criteria;
	function __construct($username, $status) {
		$this->criteria = [];
		if ($status != null) {
			$this->criteria['request_status'] = $status;
		}
		if ($username != null) {
			$this->criteria['LOWER(request_username)'] = strtolower($username);
		}

		parent::__construct();
	}

	function getQueryInfo() {
		return [
			'tables' => 'scratch_accountrequest_request',
			'fields' => ['request_id', 'request_username', 'password_hash', 'request_email', 'request_timestamp', 'request_last_updated', 'request_expiry', 'request_notes', 'request_ip', 'request_status'],
			'conds' => $this->criteria
		];
	}

	function getIndexField() {
		return 'request_last_updated';
	}

	function formatRow($row) {
		return $this->rowFromRequest(AccountRequest::fromRow($row));
	}

	abstract function rowFromRequest(AccountRequest $accountRequest);
}

function getAccountRequestsByUsername(string $username) : array {
	$dbr = wfGetDB( DB_REPLICA );
	$result = $dbr->select('scratch_accountrequest_request', array('request_id', 'request_username', 'password_hash', 'request_email', 'request_timestamp', 'request_notes', 'request_ip', 'request_status'), ['request_username' => $username], __METHOD__);

	$results = [];
	foreach ($result as $row) {
		$results[] = AccountRequest::fromRow($row);
	}

	return $results;
}

function getNumberOfRequestsByStatus(array $statuses) : array {
	$dbr = wfGetDb( DB_REPLICA ); //TODO: have a way to cache this
	$result = $dbr->select('scratch_accountrequest_request', ['request_status', 'count' => 'COUNT(request_id)'], ['request_status' => $statuses], __METHOD__, ['GROUP BY' => 'request_status']);

	$statusCounts = [];
	foreach ($statuses as $status) {
		$statusCounts[$status] = 0;
	}
	foreach ($result as $row) {
		$statusCounts[$row->request_status] = $row->count;
	}

	return $statusCounts;
}

function getNumberOfRequestsByStatusAndUser(array $statuses, $user_id) : array {
	$dbr = wfGetDb( DB_REPLICA );
	$statusCounts = [];
	foreach ($statuses as $status) {
		$statusCounts[$status] = 0;
	}
	$user_req = $dbr->selectFieldValues(
		'scratch_accountrequest_history',
		'history_request_id',
		['history_performer' => $user_id]
	);
	if (count($user_req) == 0) {
		return $statusCounts;
	}
	$result = $dbr->select('scratch_accountrequest_request', [
			'request_status',
			'count' => 'COUNT(request_id)'
		], [
			'request_status' => $statuses,
			'request_id' => $user_req
		], __METHOD__, ['GROUP BY' => 'request_status']
	);

	foreach ($result as $row) {
		$statusCounts[$row->request_status] = $row->count;
	}

	return $statusCounts;
}

function getAccountRequestById($id) {
	$dbr = wfGetDB( DB_REPLICA );
	$result = $dbr->selectRow('scratch_accountrequest_request', array('request_id', 'request_username', 'password_hash', 'request_email', 'request_timestamp', 'request_last_updated', 'request_expiry', 'request_notes', 'request_ip', 'request_status'), ['request_id' => $id], __METHOD__);

	return $result ? AccountRequest::fromRow($result) : false;
}

function actionRequest(AccountRequest $request, string $action, $userPerformingAction, string $comment) {
	$dbw = wfGetDB( DB_MASTER );

	$dbw->insert('scratch_accountrequest_history', [
		'history_request_id' => $request->id,
		'history_action' => $action,
		'history_comment' => $comment,
		'history_performer' => $userPerformingAction,
		'history_timestamp' => $dbw->timestamp()
	], __METHOD__);


	//set the timestamp for when the request was last updated
	$request_update_fields = ['request_last_updated' => $dbw->timestamp()];
	if (isset(actionToStatus[$action])) { //if the action also updates the status, then set the status appropriately
		$request_update_fields['request_status'] = actionToStatus[$action];
	}
	if (in_array($action, expirationActions)) { //and if the action makes the request expire, make the request expire
		$request_update_fields['request_expiry'] = $dbw->timestamp(time() + 86400 * wgScratchAccountRequestRejectCooldownDays());
	}
	$dbw->update('scratch_accountrequest_request', $request_update_fields, ['request_id' => $request->id], __METHOD__);
}

function getRequestHistory(AccountRequest $request) : array {
	$dbr = wfGetDB( DB_REPLICA );

	$result = $dbr->select(['scratch_accountrequest_history', 'user'], [
		'history_timestamp',
		'history_action',
		'history_comment',
		'user_name'
	], ['history_request_id' => $request->id], __METHOD__, ['order_by' => ['history_timestamp', 'ASC']], [
		'user' => ['LEFT JOIN', ['user_id=history_performer']]
	]);

	$history = array();
	foreach ($result as $row) {
		$history[] = AccountRequestHistoryEntry::fromRow($row);
	}

	return $history;
}

function createAccount(AccountRequest $request, $creator) {	
	//first create the user and add it to the database
	$user = User::newFromName($request->username);
	$user->addToDatabase();
	$dbw = wfGetDB( DB_MASTER );
	
	//then set the user's password to match
	$dbw->update(
		'user',
		[
			'user_password' => $request->passwordHash
		],
		[ 'user_id' => $user->getId() ],
		__METHOD__
	);
	
	//now log that the user was created
	$logEntry = new ManualLogEntry('newusers', 'create2');
	$logEntry->setPerformer($creator);
	$logEntry->setComment('');
	$logEntry->setParameters( [
		'4::user_id' => $user->getId()
	]);
	$logEntry->setTarget($user->getUserPage());
	
	$logId = $logEntry->insert();
	
	$logEntry->publish($logId);
}

function userExists(string $username) : bool {
	$dbr = wfGetDB( DB_REPLICA );

	return User::newFromName($username)->getId() != 0; //TODO: make this case-insensitive
}

function hasActiveRequest(string $username) : bool {
	$dbr = wfGetDB( DB_REPLICA );

	return $dbr->selectRowCount('scratch_accountrequest_request', array('1'), 
		$dbr->makeList([
			'LOWER(request_username)' => strtolower($username),
			$dbr->makeList([
				'request_status' => ['new', 'awaiting-admin', 'awaiting-user'],
				$dbr->makeList([
					'request_status' => 'rejected',
					'request_expiry > ' . $dbr->timestamp()
				], $dbr::LIST_AND)
			], $dbr::LIST_OR)
		], $dbr::LIST_AND)
	, __METHOD__) > 0;
}
