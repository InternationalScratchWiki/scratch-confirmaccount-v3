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
	'comment' => 'scratch-confirmaccount-comment',
	'accept' => 'scratch-confirmaccount-accept',
	'reject' => 'scratch-confirmaccount-reject',
	'reqfeedback' => 'scratch-confirmaccount-reqfeedback'
];