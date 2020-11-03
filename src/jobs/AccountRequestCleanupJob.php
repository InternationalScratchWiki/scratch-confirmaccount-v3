<?php
require_once __DIR__ . '/../database/DatabaseInteractions.php';

class AccountRequestCleanupJob extends Job {
	public function __construct() {
		parent::__construct( 'accountRequestCleanup', [] );
	}
	
	public function run() {
		$dbw = getTransactableDatabase('scratch-confirmaccount-request-cleanup');
		
		purgeOldAccountRequestPasswords($dbw);
		rejectOldAwaitingUserRequests($dbw);
		
		commitTransaction($dbw, 'scratch-confirmaccount-request-cleanup');
	}
}