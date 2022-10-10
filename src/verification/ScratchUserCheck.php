<?php
require_once __DIR__ . '/../common.php';

class ScratchUserCheck {
	const PROFILE_URL = 'https://scratch.mit.edu/users/%s/';
	const STATUS_REGEX = '/<span class=\"group\">[\s]*([\w]{3})/';
	const JOINED_REGEX = '/<span title=\"([\d]{4}-[\d]{2}-[\d]{2})\">/';

	private static function fetchProfile($username, &$isScratcher, &$joinedAt, &$error) {
		$url = sprintf(self::PROFILE_URL, $username);
		$html = @file_get_contents($url);
		if ($html === false) {
			$isScratcher = null; // can't tell Scratcher status
			$error = 'scratch-confirmaccount-profile-error';
			return;
		}
		$status_matches = array();
		preg_match(self::STATUS_REGEX, $html, $status_matches);
		if (empty($status_matches)) {
			$isScratcher = null; // can't tell Scratcher status
			$error = 'scratch-confirmaccount-profile-error';
			return;
		} else {
			$isScratcher = $status_matches[1] !== 'New';
		}
		$joined_matches = array();
		preg_match(self::JOINED_REGEX, $html, $joined_matches);
		if (empty($joined_matches)) {
			$joinedAt = null; // can't find join date
			$error = 'scratch-confirmaccount-profile-error';
		} else {
			$joinedAt = wfTimestamp(TS_UNIX,
				$joined_matches[1] .
				'T00:00:00Z'
			); // $joined_matches[1] is YYYY-MM-DD
		}
	}

	public static function check($username) {
		global $wgScratchAccountCheckDisallowNewScratcher, $wgScratchAccountJoinedRequirement;
		$disallowNewScratcher = $wgScratchAccountCheckDisallowNewScratcher;
		$joinedAtRequirement = $wgScratchAccountJoinedRequirement;
		if (!$disallowNewScratcher && $joinedAtRequirement === 0) {
			return; // no need to check, both disabled
		}

		$isScratcher = null;
		$joinedAt = null;
		$error = '';
		self::fetchProfile($username, $isScratcher, $joinedAt, $error);
		if ($error) {
			return $error;
		}
		if ($disallowNewScratcher && !$isScratcher) {
			return 'scratch-confirmaccount-new-scratcher';
		}
		if ((intval(wfTimestamp(TS_UNIX)) - intval($joinedAt)) < $joinedAtRequirement) {
			return 'scratch-confirmaccount-joinedat';
		}
	}
}
