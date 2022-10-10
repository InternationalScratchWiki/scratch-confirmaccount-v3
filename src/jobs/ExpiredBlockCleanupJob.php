<?php
require_once __DIR__ . '/../database/DatabaseInteractions.php';

class ExpiredBlockCleanupJob extends Job {
	public function __construct() {
		parent::__construct( 'expiredBlockCleanup', [] );
	}
	
	public function run() {
		$dbw = getTransactableDatabase('scratch-confirmaccount-block-cleanup');
		
		purgeExpiredBlocks($dbw);
		
		commitTransaction($dbw, 'scratch-confirmaccount-block-cleanup');
	}
}
