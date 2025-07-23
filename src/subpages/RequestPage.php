<?php
require_once __DIR__ . '/../database/DatabaseInteractions.php';
require_once __DIR__ . '/../database/CheckUserIntegration.php';
require_once __DIR__ . '/../common.php';

use ScratchConfirmAccount\Hook\HookRunner;

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;

function isAuthorizedToViewRequest($requestId, $userContext, &$session) {
	return $userContext === 'admin' || ($session->exists('requestId') && $session->get('requestId') === $requestId);
}

function loginPage($loginType, &$pageContext, $session, $extra = null) {
	$output = $pageContext->getOutput();
	
	$form = Html::openElement('form', [
		'method' => 'post',
		'action' => SpecialPage::getTitleFor('RequestAccount')->getFullURL()
	]);
	$form .= Html::element('input', [
		'type' => 'hidden',
		'name' => 'csrftoken',
		'value' => setCSRFToken($session)
	]);
	$form .= Html::element('input', [
		'type' => 'hidden',
		'name' => $loginType,
		'value' => '1'
	]);
	if ($extra) {
		foreach ($extra as $extraInputName => $extraInputValue) {
			$form .= Html::element('input', [
				'type' => 'hidden',
				'name' => $extraInputName,
				'value' => $extraInputValue
			]);
		}
	}
	$form .= Html::openElement('table');
	$form .= Html::openElement('tr');
	$form .= Html::rawElement('td', [], Html::element(
		'label',
		['for' => 'scratch-confirmaccount-findrequest-username'],
		wfMessage('scratch-confirmaccount-scratchusername')->text()
	));
	$form .= Html::rawElement('td', [], Html::element(
		'input',
		[
			'type' => 'text',
			'name' => 'username',
			'id' => 'scratch-confirmaccount-findrequest-username'
		]
	));
	$form .= Html::closeElement('tr');
	$form .= Html::openElement('tr');
	$form .= Html::rawElement('td', [], Html::element(
		'label',
		['for' => 'scratch-confirmaccount-findrequest-password'],
		wfMessage('scratch-confirmaccount-findrequest-password-prompt')->text()
	));
	$form .= Html::rawElement('td', [], Html::element(
		'input',
		[
			'type' => 'password',
			'name' => 'password',
			'id' => 'scratch-confirmaccount-findrequest-password'
		]
	));
	$form .= Html::closeElement('tr');
	$form .= Html::closeElement('table');
	$form .= Html::element('input', [
		'type' => 'submit',
		'value' => wfMessage('scratch-confirmaccount-submit')->parse()
	]);
	$form .= Html::closeElement('table');

	$output->addHTML($form);
}

function findRequestPage(&$pageContext, $session) {
	loginPage('findRequest', $pageContext, $session);
}

function confirmEmailPage($token, &$pageContext, $session) {
	loginPage('confirmEmail', $pageContext, $session, [
		'emailToken' => $token
	]);
}

//return if a request can actually be acted on in a given context
function isActionableRequest(AccountRequest &$accountRequest, string $userContext) {
	return $accountRequest->status !== 'accepted' && !($accountRequest->status === 'rejected' && ($userContext === 'user' || $accountRequest->isExpired()));
}

//the headings to show in the actions section for each context
const actionHeadingsByContext = [
	'user' => 'scratch-confirmaccount-leave-comment',
	'admin' => 'scratch-confirmaccount-actions'
];

function requestActionsForm(AccountRequest &$accountRequest, string $userContext, bool $hasHandledBefore, OutputPage &$output, SpecialPage &$pageContext, &$session, $timestamp) {
	$user = $pageContext->getUser();
	$request = $pageContext->getRequest();
	if (isActionableRequest($accountRequest, $userContext)) { //don't allow anyone to comment on accepted requests and don't allow regular users to comment on rejected requests
		$disp = '';
		$userOptionsLookup = MediaWikiServices::getInstance()->getUserOptionsLookup();
		
		//show the header
		$disp .= Html::element(
			'h4',
			[],
			wfMessage(actionHeadingsByContext[$userContext])->text()
		);
		
		$disp .= Html::openElement(
			'form',
			[
				'action' => $pageContext->getPageTitle()->getLocalUrl(),
				'method' => 'post',
				'enctype' => 'multipart/form-data',
				'class' => 'mw-scratch-confirmaccount-request-form'
			]
		);
		
		$disp .= Html::element('input', [
			'type' => 'hidden',
			'name' => 'csrftoken',
			'value' => setCSRFToken($session)
		]);
		
		$disp .= Html::rawElement(
			'input',
			[
				'type' => 'hidden',
				'name' => 'shouldOpenScratchPage',
				'value' => $userContext === 'admin' && !$hasHandledBefore && $userOptionsLookup->getOption( $user, 'scratch-confirmaccount-open-scratch')
			]
		);
		
		$disp .= Html::rawElement(
			'input',
			[
				'type' => 'hidden',
				'name' => 'requestid',
				'value' => $accountRequest->id
			]
		);
		
		$disp .= Html::rawElement('input',
			[
				'type' => 'hidden',
				'name' => 'loadtimestamp',
				'value' => $timestamp
			]
		);
		
		//show the list of actions, or just a hidden element if there is only one available action
		$usable_actions = array_filter(actions, function($action) use($userContext) { return in_array($userContext, $action['performers']); });

		if (sizeof($usable_actions) === 1) {
			$disp .= Html::element(
				'input',
				[
					'type' => 'hidden',
					'name' => 'action',
					'value' => array_keys($usable_actions)[0],
					'required' => true
				]
			);
		} else {
			$disp .= Html::openElement('ul', ['class' => 'mw-scratch-confirmaccount-actions-list']);
			
			$selectedAction = $request->getText('action') ?? '';
			
			$disp .= implode(array_map(function($key, $val) use ($selectedAction) {
				$row = Html::openElement('li');
				$row .= Html::element(
					'input',
					[
						'type' => 'radio',
						'name' => 'action',
						'id' => 'scratch-confirmaccount-action-' . $key,
						'value' => $key,
						'required' => true,
						'checked' => $selectedAction === $key
					]
				);
				$row .= Html::element(
					'label',
					['for' => 'scratch-confirmaccount-action-' . $key],
					wfMessage($val['message'])->text()
				);
				$row .= Html::closeElement('li');
				return $row;
			}, array_keys($usable_actions), array_values($usable_actions)));
			$disp .= Html::closeElement('ul');
		}
		
		//display the common list of admin comments
		if ($userContext === 'admin') {
			$disp .= Xml::listDropDown('scratch-confirmaccount-comment-dropdown', wfMessage( 'scratch-confirmaccount-common-admin-comments' )->text(), wfMessage('scratch-confirmaccount-dropdown-other')->text(), '', 'mw-scratch-confirmaccount-bigselect');
		}
		
		//display the comment box
		$disp .= Html::openElement('p');
		$disp .= Html::element(
			'label',
			['for' => 'scratch-confirmaccount-comment'],
			wfMessage('scratch-confirmaccount-comment')->text()
		);
		$disp .= Html::element(
			'textarea',
			[
				'class' => 'mw-scratch-confirmaccount-textarea',
				'name' => 'comment',
				'id' => 'scratch-confirmaccount-comment',
				'required' => 'required'
			],
			$request->getText('comment') ?? ''
		);
		$disp .= Html::closeElement('p');
		$disp .= Html::rawElement(
			'p',
			[],
			Html::element('input', [
				'type' => 'submit',
				'value' => wfMessage('scratch-confirmaccount-submit')->parse()
			])
		);
		$disp .= Html::closeElement('form');
		
		$output->addHTML($disp);
	}
}

function requestMetadataDisplay(AccountRequest &$accountRequest, string $userContext, Language $language, OutputPage &$output) {
	$user = $output->getUser();
	
	$disp = '';
	
	$disp .= Html::element(
		'h4',
		[],
		wfMessage('scratch-confirmaccount-details')->text()
	);
	$disp .= Html::openElement('table', [ 'class' => 'wikitable' ]);
	$disp .= Html::openElement('tr');
	$disp .= Html::element(
		'th',
		[],
		wfMessage('scratch-confirmaccount-status')->text()
	);
	$disp .= Html::element(
		'td',
		[],
		wfMessage(statuses[$accountRequest->status])->text()
	);
	$disp .= Html::closeElement('tr');
	$disp .= Html::openElement('tr');
	$disp .= Html::element(
		'th',
		[],
		wfMessage('scratch-confirmaccount-request-timestamp')->text()
	);
	$disp .= Html::rawElement(
		'td',
		[],
		humanTimestamp($accountRequest->timestamp, $language)
	);
	$disp .= Html::closeElement('tr');
	$disp .= Html::openElement('tr');
	$disp .= Html::element(
		'th',
		[],
		wfMessage('scratch-confirmaccount-scratchusername')->text()
	);
	$disp .= Html::rawElement(
		'td',
		[],
		linkToScratchProfile($accountRequest->username)
	);
	$disp .= Html::closeElement('tr');
	if ($userContext === 'admin' && CheckUserIntegration::isLoaded() && $user->isAllowed('checkuser')) {
		$disp .= Html::openElement('tr');
		$disp .= Html::element(
			'th',
			[],
			wfMessage('scratch-confirmaccount-ipaddress')->text()
		);
		$disp .= Html::element(
			'td',
			[],
			$accountRequest->ip
		);
		$disp .= Html::closeElement('tr');
	}
	$disp .= Html::closeElement('table');
	
	$output->addHTML($disp);
}

function requestNotesDisplay(AccountRequest &$accountRequest, OutputPage &$output) {
	$disp = '';
	
	$disp .= Html::element(
		'h4', [],
		wfMessage('scratch-confirmaccount-requestnotes')->text()
	);
	$disp .= Html::element(
		'textarea',
		[
			'class' => 'mw-scratch-confirmaccount-textarea',
			'readonly' => true
		],
		$accountRequest->requestNotes
	);
	
	$output->addHTML($disp);
}

function requestHistoryDisplay(AccountRequest &$accountRequest, array &$history, Language &$language, OutputPage &$output, $conflictTimestamp = null) {
	$disp = '';
	
	$disp .= Html::element(
		'h4',
		[],
		wfMessage('scratch-confirmaccount-history')->text()
	);
	
	$hasReachedConflictPoint = false;
	
	//display a row for each comment on the request
	foreach ($history as $historyEntry) {
		$row = '';
		
		//see if we have a "edit conflict"
		$isConflicted = $conflictTimestamp !== null && $historyEntry->timestamp > $conflictTimestamp;
		
		//if we see a conflict and this is the first conflicted entry we've seen, show a warning
		if ($isConflicted && !$hasReachedConflictPoint) {
			$row .= Html::rawElement(
				'div', 
				[
					'class' => 'mw-scratch-confirmaccount-conflict-warning'
				],
				wfMessage('scratch-confirmaccount-request-action-conflict-warning')->parse()
			);
			$hasReachedConflictPoint = true;
		}
		
		$row .= Html::openElement('div', ['class' => 'mw-scratch-confirmaccount-actionentry' . ($isConflicted ? ' mw-scratch-confirmaccount-actionentry__conflict' : '')]); //highlight conflicted edits
		
		$row .= Html::openElement('h5', ['class' => 'mw-scratch-confirmaccount-actionentry-heading']);

		$row .= $language->pipeList([
			Html::element('span', [], $historyEntry->performer ?: $accountRequest->username),
			humanTimestamp($historyEntry->timestamp, $language),
			Html::element('span', [], wfMessage(actions[$historyEntry->action]['message'])->text())
		]);

		$row .= Html::closeElement('h5');
		//format links for admin comments, but just show the comment as normal for requester comments
		if ($historyEntry->performer === null) {
			$row .= Html::element('p', [], $historyEntry->comment);
		} else {
			$row .= Html::rawElement('p', [], Linker::expandLocalLinks($historyEntry->comment));
		}
		$row .= Html::closeElement('div');

		$disp .= $row;
	}
	
	$output->addHTML($disp);
}

function requestAltWarningDisplay(OutputPage &$output, string $key, array &$usernames) {
	$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
	$disp = Html::openElement('fieldset');
	$disp .= Html::element(
		'legend',
		['class' => 'mw-scratch-confirmaccount-alt-warning'],
		wfMessage('scratch-confirmaccount-ip-warning')->text()
	);
	$disp .= Html::element(
		'strong',
		[],
		wfMessage($key)->text()
	);
	$disp .= Html::openElement('ul');
	$disp .= implode('', array_map(function($value) use($output, $linkRenderer) {
		if ($value instanceof CheckUserEntry) {
			return Html::rawElement(
				'li',
				[],
				wfMessage('scratch-confirmaccount-ip-warning-checkuser-time')->rawParams(
					Linker::userLink($value->userId, $value->username) . Linker::userToolLinks($value->userId, $value->username),
					humanTimestamp($value->lastTimestamp, $output->getLanguage())
				)->parse()
			);
		} elseif ($value instanceof AccountRequest) {
			return Html::rawElement(
				'li',
				[],
				wfMessage('scratch-confirmaccount-ip-warning-request-time')->rawParams(
					$linkRenderer->makeKnownLink(SpecialPage::getTitleFor('ConfirmAccounts', (string)$value->id), $value->username),
					humanTimestamp($value->timestamp, $output->getLanguage())
				)->params(
					wfMessage(statuses[$value->status])->text()
				)->parse()
			);
		}
		return Html::element('li', [], $value);
	}, $usernames));
	$disp .= Html::closeElement('ul');
	$disp .= Html::closeElement('fieldset');
	$output->addHTML($disp);
}

/**
 * Display the alternate account warning (if applicable for the request)
 *
 * @param accountRequest The relevant account request
 * @param userContext One of "admin" or "user", denoting who is viewing the request
 * @param output The page where the output will be displayed
 * @param dbr A readable database connection reference
 */
function requestCheckUserDisplay(AccountRequest &$accountRequest, string $userContext, OutputPage &$output, Wikimedia\Rdbms\DBConnRef $dbr) : void {
	if ($userContext !== 'admin') {
		return;
	}
	
	//find all users that have submitted account requests from this IP
	$requestUsernames = getRequestUsernamesFromIP($accountRequest->ip, $accountRequest->username, $dbr);
	
	//for the checkuser usernames, remove any entries that match the username on the request (which may happen after the request is accepted and the user is editing)
	$userNameUtils = MediaWikiServices::getInstance()->getUserNameUtils();
	$accountRequestWikiUsername = $userNameUtils->getCanonical($accountRequest->username);
	$checkUserEntries = array_filter(CheckUserIntegration::getCUUsernamesFromIP($accountRequest->ip, $dbr), 
	function ($testEntry) use ($accountRequestWikiUsername) { return $testEntry->username !== null && $testEntry->username !== $accountRequestWikiUsername; });
	
	if (!empty($requestUsernames)) {
		requestAltWarningDisplay($output, 'scratch-confirmaccount-ip-warning-request', $requestUsernames);
	}
	
	if (!empty($checkUserEntries)) {
		requestAltWarningDisplay($output, 'scratch-confirmaccount-ip-warning-checkuser', $checkUserEntries);
	}
}

function emailConfirmationForm(AccountRequest &$accountRequest, string $userContext, OutputPage &$output, SpecialPage &$pageContext, &$session) {
	if ($userContext === 'user' && $accountRequest->status !== 'accepted' && $accountRequest->status !== 'rejected') {
		$disp = '';
		if (!empty($accountRequest->email) && !$accountRequest->emailConfirmed) {
			$disp .= Html::openElement('form', [
				'action' => $pageContext->getPageTitle()->getLocalUrl(),
				'method' => 'post',
				'enctype' => 'multipart/form-data'
			]);
			$disp .= Html::element('input', [
				'type' => 'hidden',
				'name' => 'csrftoken',
				'value' => setCSRFToken($session)
			]);
			$disp .= Html::element('input', [
				'type' => 'hidden',
				'name' => 'sendConfirmationEmail',
				'value' => '1'
			]);
			$disp .= Html::element('input', [
				'type' => 'hidden',
				'name' => 'requestid',
				'value' => $accountRequest->id
			]);
			$disp .= Html::element('input', [
				'type' => 'submit',
				'value' => wfMessage('scratch-confirmaccount-resend')->parse()
			]);
			$disp .= Html::closeElement('form');
		}
		
		$output->addHTML($disp);
	}
}

function requestPage($requestId, string $userContext, SpecialPage &$pageContext, $session, $conflictTimestamp = null) {
	$output = $pageContext->getOutput();
	$language = $pageContext->getLanguage();
	
	$dbr = getReadOnlyDatabase();
	
	if (!isAuthorizedToViewRequest($requestId, $userContext, $session)) {
		$output->showErrorPage('error', 'scratch-confirmaccount-findrequest-nopermission');
		return;
	}

	$accountRequest = getAccountRequestById($requestId, $dbr);
	if (!$accountRequest) {
		$output->showErrorPage('error', 'scratch-confirmaccount-nosuchrequest');
		return;
	}

	$output->addHTML(Html::element(
		'h3',
		[],
		wfMessage('scratch-confirmaccount-accountrequest')->text()
	));
	
	$history = getRequestHistory($accountRequest, $dbr);
	
	$hasBeenHandledByAdminBefore = sizeof(array_filter($history, function($historyEntry) { return isset(actionToStatus[$historyEntry->action]) && in_array('admin', actions[$historyEntry->action]['performers']); })) > 0;

	requestMetadataDisplay($accountRequest, $userContext, $language, $output);
	requestNotesDisplay($accountRequest, $output);
	requestHistoryDisplay($accountRequest, $history, $language, $output, $conflictTimestamp);
	requestCheckUserDisplay($accountRequest, $userContext, $output, $dbr);
	requestActionsForm($accountRequest, $userContext, $hasBeenHandledByAdminBefore, $output, $pageContext, $session, $dbr->timestamp());
	emailConfirmationForm($accountRequest, $userContext, $output, $pageContext,$session);
}

function handleAccountCreation($accountRequest, OutputPage &$output, Wikimedia\Rdbms\DBConnRef $dbw) {
	global $wgAutoWelcomeNewUsers;

	$user = $output->getUser();

	if (userExists($accountRequest->username, $dbw)) {
		$output->showErrorPage('error', 'scratch-confirmaccount-user-exists');
		return;
	}

	$createdUser = createAccount($accountRequest, $user, $dbw);
	$hookRunner = new HookRunner(MediaWikiServices::getInstance()->getHookContainer());
	$hookRunner->onRequestedAccountCreated($accountRequest, $user->getName());
	
	if ($wgAutoWelcomeNewUsers) {
		$talkPage = new WikiPage($createdUser->getTalkPage());
		$updater = $talkPage->newPageUpdater($user);
		$updater->setContent(
			SlotRecord::MAIN,
			new WikitextContent('{{subst:MediaWiki:Scratch-confirmaccount-welcome}} ~~~~')
		);
		if ($user->isAllowed('autopatrol')) {
			$updater->setRcPatrolStatus(RecentChange::PRC_AUTOPATROLLED);
		}
		$updater->saveRevision(
			CommentStoreComment::newUnsavedComment(wfMessage('scratch-confirmaccount-welcome-summary')),
			EDIT_MINOR
		);
	}
	
	$output->addHTML(Html::element('p', [], wfMessage('scratch-confirmaccount-account-created')->text()));
}

function authenticateForViewingRequest($requestId, &$session) {
	$session->persist();
	$session->set('requestId', (string)$requestId);
	$session->save();
}

function handleRequestActionSubmission($userContext, SpecialPage $pageContext, $session, JobQueueGroup $jobQueueGroup) {
	$user = $pageContext->getUser();
	$request = $pageContext->getRequest();
	$output = $pageContext->getOutput();

	$requestId = $request->getText('requestid');

	if (!isAuthorizedToViewRequest($requestId, $userContext, $session)) {
		$output->showErrorPage('error', 'scratch-confirmaccount-findrequest-nopermission');
		return;
	}

	$mutexId = 'scratch-confirmaccount-action-request-' . $requestId;

	$dbw = getTransactableDatabase($mutexId);

	if($user->pingLimiter('requestaccountaction')){
		cancelTransaction($dbw, $mutexId);
		$output->showErrorPage('actionthrottled', 'actionthrottledtext');
		return;
	}
	//find the request
	$accountRequest = getAccountRequestById($requestId, $dbw);
	if (!$accountRequest) {
		//request not found
		cancelTransaction($dbw, $mutexId);
		$output->showErrorPage('error', 'scratch-confirmaccount-nosuchrequest');
		return;
	}
	
	//make sure that the request wasn't modified between the time that the submitter loaded the page and submitted the form
	$submissionTimestamp = $request->getText('loadtimestamp') ?? wfTimestamp();
	if ($accountRequest->lastUpdated > $submissionTimestamp) { //we got a conflict, so show the request page again
		requestPage($accountRequest->id, $userContext, $pageContext, $session, $submissionTimestamp);
		cancelTransaction($dbw, $mutexId);
		return;
	}
	
	$action = $request->getText('action');
	if (!isset(actions[$action])) {
		//invalid action
		cancelTransaction($dbw, $mutexId);
		$output->showErrorPage('error', 'scratch-confirmaccount-invalid-action');
		return;
	}
	
	if (trim($request->getText('comment', '')) === '') {
		cancelTransaction($dbw, $mutexId);
		$output->showErrorPage('error', 'scratch-confirmaccount-empty-comment');
		return;
	}

	if ($accountRequest->status === 'accepted') {
		//request was already accepted, so we can't act on it
		cancelTransaction($dbw, $mutexId);
		$output->showErrorPage('error', 'scratch-confirmaccount-already-accepted');
		return;
	}

	if ($userContext === 'user' && $accountRequest->status === 'rejected') {
		cancelTransaction($dbw, $mutexId);
		$output->showErrorPage('error', 'scratch-confirmaccount-already-rejected');
		return;
	}

	if (!in_array($userContext, actions[$action]['performers'])) {
		//admin does not have permission to perform this action
		cancelTransaction($dbw, $mutexId);
		$output->showErrorPage('error', 'scratch-confirmaccount-action-unauthorized');
		return;
	}
	
	$updateStatus = $userContext === 'admin' || $accountRequest->status !== 'new';

	actionRequest($accountRequest, $updateStatus, $action, $userContext === 'admin' ? $user : null, $request->getText('comment'), $dbw);
	
	$hookRunner = new HookRunner(MediaWikiServices::getInstance()->getHookContainer());
	$hookRunner->onAccountRequestAction($accountRequest, $action, $userContext === 'admin' ? $user->getName() : null, $request->getText('comment'));
	
	if ($action === 'set-status-accepted') {
		handleAccountCreation($accountRequest, $output, $dbw);
	} else {
		$output->addHTML(Html::rawElement(
			'p',
			[],
			wfMessage(actions[$action]['message'] . '-done', $accountRequest->id)->parse()
		));
	}
	
	commitTransaction($dbw, $mutexId);
	
	//also when someone acts on a request, add an option to clear out old account request passwords
	$jobQueueGroup->push(new AccountRequestCleanupJob());
	//And remove expired blocks
	$jobQueueGroup->push(new ExpiredBlockCleanupJob());
}
