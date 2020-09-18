<?php
function wgScratchVerificationProjectID() {
	return isset($GLOBALS['wgScratchVerificationProjectID']) ? $GLOBALS['wgScratchVerificationProjectID'] : "10135908";
}
function wgScratchVerificationProjectAuthor() {
	return isset($GLOBALS['wgScratchVerificationProjectAuthor']) ? $GLOBALS['wgScratchVerificationProjectAuthor'] : "ModShare";
}
function wgScratchAccountRequestRejectCooldownDays() {
	return isset($GLOBALS['wgScratchAccountRequestRejectCooldownDays']) ? $GLOBALS['wgScratchAccountRequestRejectCooldownDays'] : 7;
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
		'performers' => ['admin'],
		'message' => 'scratch-confirmaccount-comment'
	]
];

const actionToStatus = [
	'set-status-accepted' => 'accepted',
	'set-status-rejected' => 'rejected',
	'set-status-awaiting-admin' => 'awaiting-admin',
	'set-status-awaiting-user' => 'awaiting-user'
];

const expirationActions = ['set-status-rejected'];

function passwordMinMax() {
	if (!isset($GLOBALS['wgScratchVerificationProjectAuthor'])) {
		return [6, 4096];
	}
	$default = $GLOBALS['wgScratchVerificationProjectAuthor']['policies']['default'];
	$min = $default['MinimalPasswordLength'];
	if (is_array($min)) {
		$min = $min['value'];
	}
	$max = $default['MaximalPasswordLength'];
	if (is_array($max)) {
		$max = $max['value'];
	}
	return [$min, $max];
}

function humanTimestamp($dbTimestamp, $language) {
	return $language->getHumanTimestamp(new MWTimestamp($dbTimestamp ));
}