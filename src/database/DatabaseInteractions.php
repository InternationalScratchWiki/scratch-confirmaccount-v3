<?php
require_once __DIR__ . '/../objects/AccountRequest.php';
require_once __DIR__ . '/../common.php';

function getSingleBlock($username) {
	$dbr = wfGetDB( DB_REPLICA );

	$row = $dbr->selectRow('scratch_accountrequest_block', array('block_username', 'block_reason'), ['LOWER(CONVERT(block_username using utf8))' => strtolower($username)], __METHOD__);
	return $row ? AccountRequestUsernameBlock::fromRow($row) : false;
}

function addBlock($username, $reason, $blocker) {
	$dbw = wfGetDB( DB_MASTER );
	$dbw->insert('scratch_accountrequest_block', [
		'block_username' => $username,
		'block_reason' => $reason,
		'block_blocker_user_id' => $blocker->getId(),
		'block_timestamp' => $dbw->timestamp()
	], __METHOD__);
}

function updateBlock($username, $reason, $blocker) {
	$dbw = wfGetDB( DB_MASTER );
	$dbw->update('scratch_accountrequest_block', [
		'block_reason' => $reason,
		'block_blocker_user_id' => $blocker->getId(),
		'block_timestamp' => $dbw->timestamp()
	], ['block_username' => $username], __METHOD__);
}

function deleteBlock($username) {
	$dbw = wfGetDB( DB_MASTER );
	$dbw->delete('scratch_accountrequest_block', ['block_username' => $username], __METHOD__);
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
			$this->criteria['LOWER(CONVERT(request_username using utf8))'] = strtolower($username);
		}

		parent::__construct();
	}

	function getQueryInfo() {
		return [
			'tables' => 'scratch_accountrequest_request',
			'fields' => ['request_id', 'request_username', 'password_hash', 'request_email', 'request_timestamp', 'request_last_updated', 'request_expiry', 'request_notes', 'request_ip', 'request_status', 'request_email_token', 'request_email_confirmed', 'request_email_token_expiry'],
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
	$result = $dbr->select('scratch_accountrequest_request', array('request_id', 'request_username', 'password_hash', 'request_email', 'request_timestamp', 'request_notes', 'request_ip', 'request_status', 'request_expiry', 'request_email_confirmed', 'request_email_token', 'request_email_token_expiry', 'request_last_updated'), ['LOWER(CONVERT(request_username using utf8))' => strtolower($username)], __METHOD__, ['ORDER BY' => 'request_timestamp DESC']);

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
	$result = $dbr->selectRow('scratch_accountrequest_request', array('request_id', 'request_username', 'password_hash', 'request_email', 'request_timestamp', 'request_last_updated', 'request_expiry', 'request_notes', 'request_ip', 'request_status', 'request_email_token', 'request_email_confirmed', 'request_email_token_expiry'), ['request_id' => $id], __METHOD__);

	return $result ? AccountRequest::fromRow($result) : false;
}

function actionRequest(AccountRequest $request, bool $updateStatus, string $action, $userPerformingAction, string $comment) {
	global $wgScratchAccountRequestRejectCooldownDays;
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
	if (isset(actionToStatus[$action]) && $updateStatus) {
		// if the action also updates the status and $updateStatus is true
		// then set the status appropriately
		// $updateStatus could be false e.g. user commenting to New request
		$request_update_fields['request_status'] = actionToStatus[$action];
	}
	if (in_array($action, expirationActions)) { //and if the action makes the request expire, make the request expire
		$request_update_fields['request_expiry'] = $dbw->timestamp(time() + 86400 * $wgScratchAccountRequestRejectCooldownDays);
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
	], ['history_request_id' => $request->id], __METHOD__, ['ORDER BY' => 'history_timestamp ASC'], [
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

	$updater = [
		'user_password' => $request->passwordHash
	];
	
	$dbw = wfGetDB( DB_MASTER );

	// If email is confirmed, set it
	if ($request->emailConfirmed) {
		$updater['user_email'] = $request->email;
		$updater['user_email_authenticated'] = $dbw->timestamp();
	}

	//then set the user's password to match
	$dbw->update(
		'user',
		$updater,
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

function purgeOldAccountRequestPasswords() {
	$dbw = wfGetDB( DB_MASTER );
	
	$dbw->update('scratch_accountrequest_request', ['password_hash' => ''],
		$dbw->makeList([
			'request_status' => ['accepted'],
			$dbw->makeList([
				'request_status' => ['rejected'],
				'request_expiry < ' . $dbw->timestamp()
			], $dbw::LIST_AND)
		], $dbw::LIST_OR));
}

function userExists(string $username) : bool {
	$dbr = wfGetDB( DB_REPLICA );

	// Use db directly to make it case insensitive
	return $dbr->selectRowCount(
		'user',
		'*',
		['LOWER(CONVERT(user_name using utf8))' => strtolower($username)],
		__METHOD__
	) > 0;
}

function hasActiveRequest(string $username) : bool {
	$dbr = wfGetDB( DB_REPLICA );

	return $dbr->selectRowCount('scratch_accountrequest_request', array('1'),
		$dbr->makeList([
			'LOWER(CONVERT(request_username using utf8))' => strtolower($username),
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

function getBlocks() : array {
	$dbr = wfGetDB( DB_REPLICA );

	$result = $dbr->select('scratch_accountrequest_block', ['block_username', 'block_reason'], [], __METHOD__, ['ORDER BY' => 'block_timestamp ASC']);

	$blocks = [];
	foreach ($result as $row) {
		$blocks[] = AccountRequestUsernameBlock::fromRow($row);
	}

	return $blocks;
}

function setRequestEmailToken($request_id, $hash, $expiry) {
	$dbw = wfGetDB( DB_MASTER );

	$dbw->update(
		'scratch_accountrequest_request',
		[
			'request_email_token' => $hash,
			'request_email_token_expiry' => $expiry,
			'request_email_confirmed' => 0
		],
		[ 'request_id' => $request_id ],
		__METHOD__
	);
}

function setRequestEmailConfirmed($request_id) {
	$dbw = wfGetDB( DB_MASTER );

	$dbw->update(
		'scratch_accountrequest_request',
		[
			'request_email_token' => null,
			'request_email_token_expiry' => null,
			'request_email_confirmed' => 1
		],
		[ 'request_id' => $request_id ],
		__METHOD__
	);
}

function getRequestUsernamesFromIP($ip, &$usernames, $request_username) {
	$dbr = wfGetDB(DB_REPLICA);
	$usernames = $dbr->selectFieldValues(
		'scratch_accountrequest_request',
		'request_username',
		[
			'request_ip' => $ip,
			'LOWER(CONVERT(request_username using utf8)) != ' . $dbr->addQuotes(strtolower($request_username))
		],
		__METHOD__
	);
}
