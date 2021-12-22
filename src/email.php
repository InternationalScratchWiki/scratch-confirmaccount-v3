<?php

require_once __DIR__ . '/database/DatabaseInteractions.php';

function confirmationToken($request_id, &$expiration, IDatabase $dbw) {
	global $wgUserEmailConfirmationTokenExpiry; //do not confuse with the deprecated wgUser, this is still in use
	
	$now = time();
	$expires = $now + $wgUserEmailConfirmationTokenExpiry;
	$expiration = wfTimestamp(TS_MW, $expires);
	$token = MWCryptRand::generateHex(32);
	$hash = md5($token);
	
	setRequestEmailToken($request_id, $hash, $expiration, $dbw);
	
	return $token;
}

function getTokenUrl($request_id, &$expiration, IDatabase $dbw) {
	$token = confirmationToken($request_id, $expiration, $dbw);
	// Hack to bypass l10n
	$title = Title::makeTitle( NS_MAIN, "Special:RequestAccount/ConfirmEmail/$token" );
	return $title->getCanonicalURL();
}

function sendConfirmationEmail($request_id, IDatabase $dbw) {
	global $wgLang, $wgSitename, $wgPasswordSender;
	$user = RequestContext::getMain()->getUser();
	$request = getAccountRequestById($request_id, $dbw);
	if (!$request || empty($request->email) || $request->emailConfirmed) {
		return false;
	}
	
	$expiration = null;
	$url = getTokenUrl($request_id, $expiration, $dbw);
	
	$subject = wfMessage('scratch-confirmaccount-email-subject', $request->username)->text();
	$body = wfMessage(
		'scratch-confirmaccount-email-body',
		$request->username,
		$wgSitename,
		$url,
		$wgLang->userTimeAndDate($expiration, $user)
	)->text();
	
	$sender = new MailAddress($wgPasswordSender, wfMessage('emailsender')->text());
	$to = new MailAddress($request->email, $request->username);
	UserMailer::send($to, $sender, $subject, $body);
	
	return true;
}
