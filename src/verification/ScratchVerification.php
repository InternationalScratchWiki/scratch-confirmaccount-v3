<?php
require_once __DIR__ . '/../common.php';

use Wikimedia\Timestamp\ConvertibleTimestamp;

class ScratchVerification {
	const SCRATCH_COMMENT_API_URL = 'https://api.scratch.mit.edu/users/%s/projects/%s/comments?offset=0&limit=20';
	const PROJECT_LINK = 'https://scratch.mit.edu/projects/%s/';
	const USER_API_LINK = 'https://api.scratch.mit.edu/users/%s/';

	private static function randomVerificationCode() {
		// translate 0->A, 1->B, etc to bypass Scratch phone number censor
		return strtr(hash('sha256', random_bytes(16)), '0123456789', 'ABCDEFGHIJ');
	}

	public static function generateNewCodeForSession(&$session) {
		$session->persist();
		$session->set('vercode', self::randomVerificationCode());
		$session->save();
	}

	public static function sessionVerificationCode(&$session) {
		if (!$session->exists('vercode')) {
			self::generateNewCodeForSession($session);
		}
		return $session->get('vercode');
	}

	private static function commentsForProject($author, $project_id) {
		return json_decode(file_get_contents(sprintf(
			self::SCRATCH_COMMENT_API_URL, $author, $project_id
		)), true);
	}

	private static function verifComments() {
		global $wgScratchVerificationProjectAuthor;
		global $wgScratchVerificationProjectID;
		return self::commentsForProject(
			$wgScratchVerificationProjectAuthor,
			$wgScratchVerificationProjectID
		);
	}

	public static function isValidScratchUsername($username) {
		return !preg_match('/^_+|_+$|__+|[^a-zA-Z0-9\-_]/', $username);
	}

	public static function topVerifCommenter($req_comment) {
		$comments = self::verifComments();

		$matching_comments = array_filter($comments, function(&$comment) use($req_comment) {
			return self::isValidScratchUsername($comment['author']['username']) && stristr($comment['content'], $req_comment);
		});
		if (empty($matching_comments)) {
			return null;
		}
		return array_values($matching_comments)[0]['author']['username'];
	}
	
	public static function getScratchUserRegisteredAt($username) {
		$apiText = file_get_contents(sprintf(
			self::USER_API_LINK, $username
		));
		
		//fail loudly if the API call fails
		if (!isset($http_response_header)) {
			throw new Exception('API call failed');
		}
		
		//this shouldn't happen, but since this is a security-sensitive component we need to be ultra-defensive
		if (!strstr($http_response_header[0], '200 OK')) {
			throw new Exception('User does not exist');
		}
		
		$info = json_decode($apiText, true);
		
		$registeredAt = $info['history']['joined'];
		return new ConvertibleTimestamp($registeredAt);
	}
}
