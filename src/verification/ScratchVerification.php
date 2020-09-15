<?php
define('SCRATCH_COMMENT_API_URL', 'https://api.scratch.mit.edu/users/%s/projects/%s/comments?offset=0&limit=20');
define('PROJECT_LINK', 'https://scratch.mit.edu/projects/%s/');

function randomVerificationCode() {
	// translate 0->A, 1->B, etc to bypass Scratch phone number censor
	return strtr(hash('sha256', random_bytes(16)), '0123456789', 'ABCDEFGHIJ');
}

function generateNewCodeForSession(&$session) {
	$session->persist();
	$session->set('vercode', randomVerificationCode());
	$session->save();
}

function sessionVerificationCode(&$session) {
	if (!$session->exists('vercode')) {
		generateNewCodeForSession($session);
	}
	return $session->get('vercode');
}

function commentsForProject($author, $project_id) {
	return json_decode(file_get_contents(sprintf(
		SCRATCH_COMMENT_API_URL, $author, $project_id
	)), true);
}

function verifComments() {
	return commentsForProject(
		wfMessage('scratch-confirmaccount-request-verification-project-author')->text(),
		wfMessage('scratch-confirmaccount-request-verification-project-id')->text()
	);
}

function isValidScratchUsername($username) {
	return !preg_match('/^_+|_+$|__+|[^a-zA-Z0-9\-_]/', $username);
}

function topVerifCommenter($req_comment) {
	$comments = verifComments();

	$matching_comments = array_filter($comments, function(&$comment) use($req_comment) {
		return !preg_match('/^_+|_+$|__+/', $comment['author']['username']) && stristr($comment['content'], $req_comment);
	});
	if (empty($matching_comments)) {
		return null;
	}
	return $matching_comments[0]['author']['username'];
}
