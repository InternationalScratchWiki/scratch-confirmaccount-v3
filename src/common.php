<?php
function wgScratchVerificationProjectID() {
	return isset($GLOBALS['wgScratchVerificationProjectID']) ? $GLOBALS['wgScratchVerificationProjectID'] : "10135908";
}
function wgScratchVerificationProjectAuthor() {
	return isset($GLOBALS['wgScratchVerificationProjectAuthor']) ? $GLOBALS['wgScratchVerificationProjectAuthor'] : "ModShare";
}

const statuses = [
	'new' => 'scratch-confirmaccount-new',
	'awaiting-admin' => 'scratch-confirmaccount-awaiting-admin',
	'awaiting-user' => 'scratch-confirmaccount-awaiting-user',
	'rejected' => 'scratch-confirmaccount-rejected',
	'accepted' => 'scratch-confirmaccount-accepted'
];
const actions = [
	'set-status-accepted' => [
		'performers' => ['admin'], 
		'message' => 'scratch-confirmaccount-set-status-accepted'
	],
	'set-status-rejected' => [
		'performers' => ['admin'], 
		'message' => 'scratch-confirmaccount-set-status-rejected'
	],
	'set-status-awaiting-admin' => [
		'performers' => ['user'], 
		'message' => 'scratch-confirmaccount-set-status-awaiting-admin'
	],
	'set-status-awaiting-user' => [
		'performers' => ['admin'], 
		'message' => 'scratch-confirmaccount-set-status-awaiting-user'
	],
	'comment' => [
		'performers' => ['user', 'admin'], 
		'message' => 'scratch-confirmaccount-comment'
	]
];
const actionToStatus = [
	'set-status-accepted' => 'accepted',
	'set-status-rejected' => 'rejected',
	'set-status-awaiting-admin' => 'awaiting-admin',
	'set-status-awaiting-user' => 'awaiting-user'
];