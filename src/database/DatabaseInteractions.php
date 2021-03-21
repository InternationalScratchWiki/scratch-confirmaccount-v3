<?php
require_once __DIR__ . '/../objects/AccountRequest.php';
require_once __DIR__ . '/../common.php';

class AccountRequestLoader {
	private $dbr;
	
	function __construct(IDatabase $dbr) {
		$this->dbr = $dbr;
	}
	
	function getAccountRequestsByUsername(string $username) : array {
		$result = $this->dbr->select('scratch_accountrequest_request', array('request_id', 'request_username', 'password_hash', 'request_email', 'request_timestamp', 'request_notes', 'request_ip', 'request_status', 'request_expiry', 'request_email_confirmed', 'request_email_token', 'request_email_token_expiry', 'request_last_updated'), ['LOWER(CONVERT(request_username using utf8))' => strtolower($username)], __METHOD__, ['ORDER BY' => 'request_timestamp DESC']);

		$results = [];
		foreach ($result as $row) {
			$results[] = AccountRequest::fromRow($row);
		}
		return $results;
	}

	function getNumberOfRequestsByStatus(array $statuses) : array {
		$result = $this->dbr->select('scratch_accountrequest_request', ['request_status', 'count' => 'COUNT(request_id)'], ['request_status' => $statuses], __METHOD__, ['GROUP BY' => 'request_status']);

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
		$result = $this->dbr->select(
			['scratch_accountrequest_history', 'scratch_accountrequest_request'],
			['request_status', 'count' => 'COUNT(DISTINCT history_request_id)'],
			['history_performer' => $user_id, 'request_status' => $statuses],
			__METHOD__,
			['GROUP BY' => 'request_status'],
			['scratch_accountrequest_request' => ['LEFT JOIN', 'request_id=history_request_id']]
		);
		
		$statusCounts = [];
		foreach ($statuses as $status) {
			$statusCounts[$status] = 0;
		}
		foreach ($result as $row) {
			$statusCounts[$row->request_status] = $row->count;
		}
		
		return $statusCounts;
	}

	function getAccountRequestById($id) {
		$result = $this->dbr->selectRow('scratch_accountrequest_request', array('request_id', 'request_username', 'password_hash', 'request_email', 'request_timestamp', 'request_last_updated', 'request_expiry', 'request_notes', 'request_ip', 'request_status', 'request_email_token', 'request_email_confirmed', 'request_email_token_expiry'), ['request_id' => $id], __METHOD__);

		return $result ? AccountRequest::fromRow($result) : false;
	}
	
	function getRequestHistory(AccountRequest $request) : array {
		$result = $this->dbr->select(['scratch_accountrequest_history', 'user'], [
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
	
	/**
	 * Get the usernames of account requests that were from a given IP address
	 *
	 * @param ip The IP address being looked at
	 * @param usernameToIgnore Do not return any users with this username (case-insensitive)
	 *
	 * @return An array of usernames with account requests originating from the IP address \p ip, but excluding \p usernameToIgnore
	 */
	function getRequestUsernamesFromIP($ip, string $usernameToIgnore) : array {
		return $this->dbr->selectFieldValues(
			'scratch_accountrequest_request',
			'DISTINCT request_username',
			[
				'request_ip' => $ip,
				'LOWER(CONVERT(request_username using utf8)) != ' . $this->dbr->addQuotes(strtolower($usernameToIgnore))
			],
			__METHOD__
		);
	}
	
	/**
	 * Check if it is possible to make a request under a given username
	 *
	 * @param username The username of of the user being requested
	 * @return true if it is possible to create a request under the given username, false if there is already an active request under that username
	 */
	function canMakeRequestForUsername(string $username) : bool {
		return !$this->dbr->selectField('scratch_accountrequest_request', '1', ['request_active_username' => strtolower($username)], __METHOD__, []);
	}
	
	function userExists(string $username) : bool {
		// Use db directly to make it case insensitive
		return $this->dbr->selectRowCount(
			'user',
			'*',
			['LOWER(CONVERT(user_name using utf8))' => strtolower($username)],
			__METHOD__
		) > 0;
	}

}

class AccountRequestRepository extends AccountRequestLoader {
	private $dbw;
	
	function __construct(IDatabase $dbw) {
		parent::__construct($dbw);
		
		if ($dbw->isReadOnly()) {
			throw new Exception('Read-only database passed to AccountRequestRepository constructor');
		}
		
		$this->dbw = $dbw;
	}
	
	/**
	 * Create an account request in the database
	 *
	 * @param username The username of the account request
	 * @param passwordHash The pre-hashed password to insert into the database
	 * @param email The email address (may be empty) for the request
	 * @param ip The IP from which the request was submitted
	 * @return The ID of the request if creating the request succeeded, or null if creating the request failed due to there already being an active request under that username
	 */
	function createAccountRequest(string $username, string $passwordHash, string $requestNotes, string $email, string $ip) : ?int {
		$this->dbw->insert('scratch_accountrequest_request', [
			'request_username' => $username,
			'request_active_username' => strtolower($username),
			'password_hash' => $passwordHash,
			'request_email' => $email,
			'request_timestamp' => $this->dbw->timestamp(),
			'request_last_updated' => $this->dbw->timestamp(),
			'request_notes' => $requestNotes,
			'request_ip' => $ip,
			'request_status' => 'new'
		], __METHOD__, ['IGNORE']);

		return $this->dbw->insertID();
	}
	
	function actionRequest(AccountRequest $request, bool $updateStatus, string $action, ?User $userPerformingAction, string $comment) {
		global $wgScratchAccountRequestRejectCooldownDays;
		
		$this->dbw->insert('scratch_accountrequest_history', [
			'history_request_id' => $request->id,
			'history_action' => $action,
			'history_comment' => $comment,
			'history_performer' => $userPerformingAction == null ? null : $userPerformingAction->getId(),
			'history_timestamp' => $this->dbw->timestamp()
		], __METHOD__);


		//set the timestamp for when the request was last updated
		$request_update_fields = ['request_last_updated' => $this->dbw->timestamp()];
		if (isset(actionToStatus[$action]) && $updateStatus) {
			// if the action also updates the status and $updateStatus is true
			// then set the status appropriately
			// $updateStatus could be false e.g. user commenting to New request
			$request_update_fields['request_status'] = actionToStatus[$action];
		}
		if (in_array($action, expirationActions)) { //and if the action makes the request expire, make the request expire
			$request_update_fields['request_expiry'] = $this->dbw->timestamp(time() + 86400 * $wgScratchAccountRequestRejectCooldownDays);
		}
		$this->dbw->update('scratch_accountrequest_request', $request_update_fields, ['request_id' => $request->id], __METHOD__);
	}
	
	function rejectOldAwaitingUserRequests() : void {
		global $wgScratchAccountAutoRejectStaleAwaitingUserRequestDays;
		
		//find all stale requests and for each request, the admin who marked it as "awaiting user" (there is guaranteed to be one since for a request to have status "awaiting user", it must have received the action "set-status-awaiting-user")
		//we also defensively use an INNER JOIN so even if by some fluke a request has no such corresponding admin, the request won't be picked up by the query, and we use the DISTINCT keyword so we only pick up each request once even if it's been marked as "awaiting-user" multiple times
		//the idea here is finding every request with status "awaiting-user" that hasn't been acted on in $wgScratchAccountAutoRejectStaleAwaitingUserRequestDays days
		$result = $this->dbw->select(
			['scratch_accountrequest_request', 'scratch_accountrequest_history'], 
			[
				'request_id' => 'DISTINCT request_id', 'request_username', 'password_hash', 'request_email', 'request_timestamp', 'request_notes', 'request_ip', 'request_status', 'request_expiry', 'request_email_confirmed', 'request_email_token', 'request_email_token_expiry', 'request_last_updated',
				'handling_admin_id' => 'history_performer'
			], 
			[
				'request_status' => 'awaiting-user',
				'request_last_updated < ' . $this->dbw->timestamp(time() - 86400 * $wgScratchAccountAutoRejectStaleAwaitingUserRequestDays)
			], 
			__METHOD__,
			[], 
			['scratch_accountrequest_history' => ['INNER JOIN', ['history_request_id=request_id', 'history_action' => 'set-status-awaiting-user']]]);
		
		//then based on the requests, prepare to act
		$staleRequests = [];
		foreach ($result as $row) {
			$staleRequests[] = [
				'request' => AccountRequest::fromRow($row),
				'admin' => User::newFromId($row->handling_admin_id)
			];
		}
				
		//for each request, mark it as rejected
		foreach ($staleRequests as $requestToDelete) {
			$this->actionRequest($requestToDelete['request'], true, 'set-status-rejected', $requestToDelete['admin'], wfMessage('scratch-confirmaccount-stale-awaiting-user-auto-reject-message', $wgScratchAccountAutoRejectStaleAwaitingUserRequestDays)->text());
		}
	}
	
	function setRequestEmailToken($request_id, string $hash, $expiry) {
		$this->dbw->update(
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
		$this->dbw->update(
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
	
	function createAccount(AccountRequest $request, User $creator) {
		//first create the user and add it to the database
		$user = User::newFromName($request->username);
		$user->addToDatabase();

		$updater = [
			'user_password' => $request->passwordHash
		];
		
		// If email is confirmed, set it
		if ($request->emailConfirmed) {
			$updater['user_email'] = $request->email;
			$updater['user_email_authenticated'] = $this->dbw->timestamp();
		}

		//then set the user's password to match
		$this->dbw->update(
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
		
		return $user;
	}

	function purgeOldAccountRequestPasswords() {	
		$this->dbw->update('scratch_accountrequest_request', ['password_hash' => '', 'request_active_username' => null],
		[
			'request_status' => ['accepted', 'rejected'],
			'request_expiry < ' . $this->dbw->timestamp()
		]);
	}
}

class AccountRequestBlockLoader {
	private $dbr;
	
	function __construct(IDatabase $dbr) {
		$this->dbr = $dbr;
	}
	
	function getBlocks() : array {
		$result = $this->dbr->select('scratch_accountrequest_block', ['block_username', 'block_reason'], [], __METHOD__, ['ORDER BY' => 'block_timestamp ASC']);

		$blocks = [];
		foreach ($result as $row) {
			$blocks[] = AccountRequestUsernameBlock::fromRow($row);
		}

		return $blocks;
	}
	
	function getSingleBlock(string $username) {
		$row = $this->dbr->selectRow('scratch_accountrequest_block', array('block_username', 'block_reason'), ['LOWER(CONVERT(block_username using utf8))' => strtolower($username)], __METHOD__);
		return $row ? AccountRequestUsernameBlock::fromRow($row) : false;
	}
}

class AccountRequestBlockRepository extends AccountRequestBlockLoader {
	private $dbw;
	
	function __construct(IDatabase $dbw) {
		parent::__construct($dbw);
		
		if ($dbw->isReadOnly()) {
			throw new Exception('Read-only database passed to AccountRequestBlockRepository constructor');
		}
		
		$this->dbw = $dbw;
	}
	
	function addBlock(string $username, string $reason, User $blocker) {
		$this->dbw->insert('scratch_accountrequest_block', [
			'block_username' => $username,
			'block_reason' => $reason,
			'block_blocker_user_id' => $blocker->getId(),
			'block_timestamp' => $this->dbw->timestamp()
		], __METHOD__);
	}

	function updateBlock(string $username, string $reason, User $blocker) {
		$this->dbw->update('scratch_accountrequest_block', [
			'block_reason' => $reason,
			'block_blocker_user_id' => $blocker->getId(),
			'block_timestamp' => $this->dbw->timestamp()
		], ['block_username' => $username], __METHOD__);
	}

	function deleteBlock(string $username) {
		$this->dbw->delete('scratch_accountrequest_block', ['block_username' => $username], __METHOD__);
	}
}

function getTransactableDatabase(string $mutexId) : IDatabase {	
	$dbw = wfGetDB( DB_MASTER );
	$dbw->startAtomic( $mutexId );
	
	return $dbw;
}

function commitTransaction(IDatabase $dbw, string $mutexId) : void {
	$dbw->endAtomic( $mutexId );
}

function cancelTransaction(IDatabase $dbw, string $mutexId) : void {
	$dbw->endAtomic( $mutexId );
}

function getReadOnlyDatabase() {
	return wfGetDB( DB_REPLICA );
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