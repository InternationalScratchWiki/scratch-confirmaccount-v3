<?php
require_once __DIR__ . '/database/DatabaseInteractions.php';
require_once __DIR__ . '/database/CheckUserIntegration.php';
require_once __DIR__ . '/common.php';

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;

class RequestPage {
	private $pageContext;
	private $userContext;
	private $requestId;

	public function __construct(int $requestId, SpecialPage $pageContext, string $userContext) {
		$this->requestId = $requestId;
		$this->pageContext = $pageContext;
		$this->userContext = $userContext;
	}

	public function isAuthorizedToViewRequest() {
		$session = $this->pageContext->getRequest()->getSession();
		return $this->userContext == 'admin' || ($session->exists('requestId') && $session->get('requestId') == $requestId);
	}

	function render($conflictTimestamp = null) {
		global $wgUser;
	
		$output = $this->pageContext->getOutput();
		$request = $this->pageContext->getRequest();
		$session = $request->getSession();
		$language = $this->pageContext->getLanguage();
		
		$dbr = getReadOnlyDatabase();
		
		if (!$this->isAuthorizedToViewRequest()) {
			$output->showErrorPage('error', 'scratch-confirmaccount-findrequest-nopermission');
			return;
		}
	
		$accountRequest = getAccountRequestById($this->requestId, $dbr);
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
	
		$this->requestMetadataDisplay($accountRequest);
		$this->requestNotesDisplay($accountRequest);
		$this->requestHistoryDisplay($accountRequest, $history, $conflictTimestamp);
		$this->requestCheckUserDisplay($accountRequest, $dbr);
		$this->requestActionsForm($accountRequest, $hasBeenHandledByAdminBefore, $dbr->timestamp());
		emailConfirmationForm($accountRequest, $this->userContext, $this->pageContext);
	}

	private function requestActionsForm(AccountRequest &$accountRequest, bool $hasHandledBefore, $timestamp) {
		global $wgUser;
	
		$output = $this->pageContext->getOutput();
		$request = $this->pageContext->getRequest();
		$session = $request->getSession();
	
		if (isActionableRequest($accountRequest, $this->userContext)) { //don't allow anyone to comment on accepted requests and don't allow regular users to comment on rejected requests
			$disp = '';
			$userOptionsLookup = MediaWikiServices::getInstance()->getUserOptionsLookup();
			
			//show the header
			$disp .= Html::element(
				'h4',
				[],
				wfMessage(actionHeadingsByContext[$this->userContext])->text()
			);
			
			$disp .= Html::openElement(
				'form',
				[
					'action' => $this->pageContext->getPageTitle()->getLocalUrl(),
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
					'value' => $this->userContext == 'admin' && !$hasHandledBefore && $userOptionsLookup->getOption( $wgUser, 'scratch-confirmaccount-open-scratch')
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
			$temp_userContext = $this->userContext;
			$usable_actions = array_filter(actions, function($action) use($temp_userContext) { return in_array($temp_userContext, $action['performers']); });
	
			if (sizeof($usable_actions) == 1) {
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
			if ($this->userContext == 'admin') {
				$options = Xml::listDropDownOptions(
					 wfMessage( 'scratch-confirmaccount-common-admin-comments' )->text(),
					 [ 'other' => wfMessage( 'other' )->text() ]
				 );
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

	static function handleRequestActionSubmission(string $userContext, SpecialPage $pageContext) {
		global $wgUser;
	
		$request = $pageContext->getRequest();
		$output = $pageContext->getOutput();
		$session = $request->getSession();
		$language = $pageContext->getLanguage();
	
		$requestId = $request->getText('requestid');

		$requestPage = new self((int)$requestId, $pageContext, $userContext);
	
		if (!$requestPage->isAuthorizedToViewRequest()) {
			$output->showErrorPage('error', 'scratch-confirmaccount-findrequest-nopermission');
			return;
		}
	
		$mutexId = 'scratch-confirmaccount-action-request-' . $requestId;
	
		$dbw = getTransactableDatabase($mutexId);
		
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
			$requestPage->render($submissionTimestamp);
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
		
		if (trim($request->getText('comment', '') == '')) {
			cancelTransaction($dbw, $mutexId);
			$output->showErrorPage('error', 'scratch-confirmaccount-empty-comment');
			return;
		}
	
		if ($accountRequest->status == 'accepted') {
			//request was already accepted, so we can't act on it
			cancelTransaction($dbw, $mutexId);
			$output->showErrorPage('error', 'scratch-confirmaccount-already-accepted');
			return;
		}
	
		if ($userContext == 'user' && $accountRequest->status == 'rejected') {
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
		
		$updateStatus = $userContext == 'admin' || $accountRequest->status != 'new';
	
		actionRequest($accountRequest, $updateStatus, $action, $userContext == 'admin' ? $wgUser : null, $request->getText('comment'), $dbw);
		
		Hooks::run('ScratchConfirmAccountHooks::onAccountRequestAction', [$accountRequest, $action, $userContext == 'admin' ? $wgUser->getName() : null, $request->getText('comment')]);
		
		if ($action == 'set-status-accepted') {
			handleAccountCreation($accountRequest, $pageContext, $dbw);
		} else {
			$output->addHTML(Html::rawElement(
				'p',
				[],
				wfMessage(actions[$action]['message'] . '-done', $accountRequest->id)->parse()
			));
		}
		
		commitTransaction($dbw, $mutexId);
		
		//also when someone acts on a request, add an option to clear out old account request passwords
		JobQueueGroup::singleton()->push(new AccountRequestCleanupJob());
	}

	private function requestMetadataDisplay(AccountRequest &$accountRequest) {
		global $wgUser;
	
		$output = $this->pageContext->getOutput();
		$language = $this->pageContext->getLanguage();
	
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
		if ($this->userContext == 'admin' && CheckUserIntegration::isLoaded() && $wgUser->isAllowed('checkuser')) {
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
	
	private function requestNotesDisplay(AccountRequest &$accountRequest) {
		$output = $this->pageContext->getOutput();
	
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

	private function requestHistoryDisplay(AccountRequest &$accountRequest, array &$history, $conflictTimestamp = null) {
		$output = $this->pageContext->getOutput();
		$language = $this->pageContext->getLanguage();
		
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
			$isConflicted = $conflictTimestamp != null && $historyEntry->timestamp > $conflictTimestamp;
			
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
				$row .= Html::rawElement('p', [], Linker::formatComment($historyEntry->comment));
			}
			$row .= Html::closeElement('div');
	
			$disp .= $row;
		}
		
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
	function requestCheckUserDisplay(AccountRequest &$accountRequest, IDatabase $dbr) : void {
		if ($this->userContext != 'admin') {
			return;
		}
		
		//find all users that have submitted account requests from this IP
		$requestUsernames = getRequestUsernamesFromIP($accountRequest->ip, $accountRequest->username, $dbr);
		
		//for the checkuser usernames, remove any entries that match the username on the request (which may happen after the request is accepted and the user is editing)
		$accountRequestWikiUsername = User::getCanonicalName($accountRequest->username);
		$checkUserUsernames = array_filter(CheckUserIntegration::getCUUsernamesFromIP($accountRequest->ip, $dbr), 
		function ($testUsername) use ($accountRequestWikiUsername) { return $testUsername != $accountRequestWikiUsername; });
		
		if (!empty($requestUsernames)) {
			$this->requestAltWarningDisplay('scratch-confirmaccount-ip-warning-request', $requestUsernames);
		}
		
		if (!empty($checkUserUsernames)) {
			$this->requestAltWarningDisplay('scratch-confirmaccount-ip-warning-checkuser', $checkUserUsernames);
		}
	}

	function requestAltWarningDisplay(string $key, array &$usernames) {
		$output = $this->pageContext->getOutput();
	
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
		$disp .= implode('', array_map(function($value) {
			return Html::element('li', [], $value);
		}, $usernames));
		$disp .= Html::closeElement('ul');
		$disp .= Html::closeElement('fieldset');
		$output->addHTML($disp);
	}
}

function loginPage($loginType, SpecialPage $pageContext, $extra = null) {
	assert(!empty($loginType));

	$request = $pageContext->getRequest();
	$output = $pageContext->getOutput();
	$session = $request->getSession();

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

function findRequestPage(SpecialPage $pageContext) {
	loginPage('findRequest', $pageContext);
}

function confirmEmailPage($token, SpecialPage $pageContext) {
	loginPage('confirmEmail', $pageContext, [
		'emailToken' => $token
	]);
}

//return if a request can actually be acted on in a given context
function isActionableRequest(AccountRequest &$accountRequest, string $userContext) {
	return $accountRequest->status != 'accepted' && !($accountRequest->status == 'rejected' && ($userContext == 'user' || $accountRequest->isExpired()));
}

//the headings to show in the actions section for each context
const actionHeadingsByContext = [
	'user' => 'scratch-confirmaccount-leave-comment',
	'admin' => 'scratch-confirmaccount-actions'
];

function emailConfirmationForm(AccountRequest &$accountRequest, string $userContext, SpecialPage &$pageContext) {
	$output = $pageContext->getOutput();
	$session = $pageContext->getRequest()->getSession();

	if ($userContext == 'user' && $accountRequest->status !='accepted' && $accountRequest->status !='rejected') {
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

function handleAccountCreation($accountRequest, SpecialPage $pageContext, IDatabase $dbw) {
	global $wgUser, $wgAutoWelcomeNewUsers;

	$output = $pageContext->getOutput();

	if (userExists($accountRequest->username, $dbw)) {
		$output->showErrorPage('error', 'scratch-confirmaccount-user-exists');
		return;
	}

	$createdUser = createAccount($accountRequest, $wgUser, $dbw);
	Hooks::run('ScratchConfirmAccountHooks::onCreateAccount', [$accountRequest, $wgUser->getName()]);
	
	if ($wgAutoWelcomeNewUsers) {
		$talkPage = new WikiPage($createdUser->getTalkPage());
		$updater = $talkPage->newPageUpdater($wgUser);
		$updater->setContent(
			SlotRecord::MAIN,
			new WikitextContent('{{subst:MediaWiki:Scratch-confirmaccount-welcome}} ~~~~')
		);
		if ($wgUser->isAllowed('autopatrol')) {
			$updater->setRcPatrolStatus(RecentChange::PRC_AUTOPATROLLED);
		}
		$updater->saveRevision(
			CommentStoreComment::newUnsavedComment(wfMessage('scratch-confirmaccount-welcome-summary')),
			EDIT_MINOR
		);
	}
	
	$output->addHTML(Html::element('p', [], wfMessage('scratch-confirmaccount-account-created')->text()));
}

function authenticateForViewingRequest($requestId, $session) {
	assert(!empty($requestId));
	assert(!empty($session));

	$session->persist();
	$session->set('requestId', $requestId);
	$session->save();
}
