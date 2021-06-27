<?php
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

const expirationActions = ['set-status-rejected', 'set-status-accepted'];

function passwordMinMax() {
	global $wgPasswordPolicy;
	$policy = $wgPasswordPolicy['policies']['default'];
	$min = $policy['MinimalPasswordLength'];
	if (is_array($min)) {
		$min = $min['value'];
	}
	$max = $policy['MaximalPasswordLength'];
	if (is_array($max)) {
		$max = $max['value'];
	}
	return [$min, $max];
}

function humanTimestamp($dbTimestamp, $language) {
	assert(!empty($dbTimestamp));
	assert(!empty($language));

	$ts = new MWTimestamp($dbTimestamp);
	return Html::element(
		'span',
		['title' => $ts->getTimestamp(TS_DB) . ' (UTC)'],
		$language->getHumanTimestamp($ts)
	);
}

function setCSRFToken($session) {
	assert(!empty($session));

	$session->persist();
	$csrftoken = $session->getToken();
	$session->save();
	return $csrftoken;
}

function isCSRF($session, $csrftoken) {
	assert(!empty($session));

	return !$session->getToken()->match($csrftoken);
}

/**
 * Return a HTML-formatted link to a user's Scratch profile-link
 *
 * @param username The Scratch username whose profile is being linked to
 * @return A simple <a> link to the user's profile with the contents also being the username
 */
function linkToScratchProfile(string $username) : string {
	return Html::element(
		'a',
		[
			'href' => 'https://scratch.mit.edu/users/' . $username,
			'target' => '_blank',
			'id' => 'mw-scratch-confirmaccount-profile-link'
		],
		$username
	);
}