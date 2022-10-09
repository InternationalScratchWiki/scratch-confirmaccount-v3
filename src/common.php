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
	$ts = new MWTimestamp($dbTimestamp);
	return Html::element(
		'span',
		['title' => $ts->getTimestamp(TS_DB) . ' (UTC)'],
		$language->getHumanTimestamp($ts)
	);
}

function humanTimestampOrInfinite($dbTimestamp, $language) {
	if ($dbTimestamp === null) return Html::element('span', array(), wfMessage('scratch-confirmaccount-block-infinite')->text());
	$ts = new MWTimestamp($dbTimestamp);
	return Html::element(
		'span',
		['title' => $ts->getTimestamp(TS_DB) . ' (UTC)'],
		$language->getHumanTimestamp($ts)
	);
}

function blockExpired($block) {
	// infinite block
	if ($block->expirationTimestamp === null) return false;
	$current = intval(wfTimestamp(TS_UNIX));
	$expirationTimestamp = intval(wfTimestamp(TS_UNIX, $block->expirationTimestamp));
	return $current > $expirationTimestamp;
}

function setCSRFToken(&$session) {
	$session->persist();
	$csrftoken = $session->getToken();
	$session->save();
	return $csrftoken;
}

function isCSRF(&$session, $csrftoken) {
	return !$session->getToken()->match($csrftoken);
}

/**
 * Return a HTML-formatted link to a user's Scratch profile-link
 *
 * @param username The Scratch username whose profile is being linked to
 * @return string A simple <a> link to the user's profile with the contents also being the username
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

function blockExpirationForm($lang, $user, bool $showCurrent, ?string $current) : string {
	$commonOptions = wfMessage( 'scratch-confirmaccount-block-expiration-options' )->inContentLanguage()->text();
	$expiryOptions = [];
	if ($showCurrent) {
		if ( $current === null ) {
			$existingExpiryMessage = wfMessage( 'scratch-confirmaccount-block-existing-infinite' );
		} else {
			$d = $lang->userDate( $current, $user );
			$t = $lang->userTime( $current, $user );
			$existingExpiryMessage = wfMessage( 'scratch-confirmaccount-block-existing' )
				->params(
					$d,
					$t
				);
		}
		$expiryOptions['existing'] = $existingExpiryMessage->text();
	}
	$expiryOptions['othertime'] = wfMessage( 'scratch-confirmaccount-block-othertime-op' )->text();
	$expiryOptions = array_merge( $expiryOptions, XmlSelect::parseOptionsMessage( $commonOptions ) );
	$output = '';
	$output .= Html::openElement('select', [
		'name' => 'expiration_timestamp',
		'class' => 'mw-scratch-confirmaccount-expiration-timestamp'
	]);
	foreach ($expiryOptions as $value => $displayName) {
		$attrs = [
			'value' => $value
		];
		if ($value === 'existing' && $showCurrent) {
			$attrs['selected'] = true;
		} else if ($value === 'infinite' && !$showCurrent) {
			$attrs['selected'] = true;
		}
		$output .= Html::element('option', $attrs, $displayName);
	}
	$output .= Html::closeElement('select');
	$output .= Html::openElement('br');
	$output .= Html::input('expiration_timestamp_time', '', 'text', [
		'disabled' => true
	]);
	return $output;
}