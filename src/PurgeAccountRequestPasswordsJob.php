<?php
require_once __DIR__ . '/database/DatabaseInteractions.php';
class PurgeAccountRequestPasswordsJob extends Job {
	public function __construct() {
		parent::__construct( 'purgeAccountRequestPasswords', [] );
	}
	
	public function run() {
		$dbw = getTransactableDatabase($mutexId);
		
		purgeOldAccountRequestPasswords($dbw);
		
		commitTransaction($mutexId);
	}
}