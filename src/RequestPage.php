<?php
require_once __DIR__ . '/database/DatabaseInteractions.php';
require_once __DIR__ . '/database/CheckUserIntegration.php';
require_once __DIR__ . '/common.php';

function isAuthorizedToViewRequest($requestId, $userContext, &$session) {
	return $userContext == 'admin' || ($session->exists('requestId') && $session->get('requestId') == $requestId);
}

function loginPage($loginType, &$request, &$output, &$session, $extra = null) {
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

function findRequestPage(&$request, &$output, &$session) {
	loginPage('findRequest', $request, $output, $session);
}

function confirmEmailPage($token, &$request, &$output, &$session) {
	loginPage('confirmEmail', $request, $output, $session, [
		'emailToken' => $token
	]);
}

//return if a request can actually be acted on in a given context
function isActionableRequest(AccountRequest &$accountRequest, string $userContext) {
	return $accountRequest->status != 'accepted' && !($accountRequest->status == 'rejected' && $userContext == 'user');
}

function requestActionsForm(AccountRequest &$accountRequest, string $userContext, bool $hasHandledBefore, OutputPage &$output, SpecialPage &$pageContext, &$session) {
	global $wgUser;
	
	if (isActionableRequest($accountRequest, $userContext)) { //don't allow anyone to comment on accepted requests and don't allow regular users to comment on rejected requests
		$disp = '';
		
		//show the header
		$disp .= Html::element(
			'h4',
			[],
			wfMessage('scratch-confirmaccount-actions')->text()
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
				'value' => $userContext == 'admin' && !$hasHandledBefore && $wgUser->getOption('scratch-confirmaccount-open-scratch')
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
		
		//show the list of actions, or just a hidden element if there is only one available action
		$usable_actions = array_filter(actions, function($action) use($userContext) { return in_array($userContext, $action['performers']); });

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
			
			$disp .= implode(array_map(function($key, $val) {
				$row = Html::openElement('li');
				$row .= Html::element(
					'input',
					[
						'type' => 'radio',
						'name' => 'action',
						'id' => 'scratch-confirmaccount-action-' . $key,
						'value' => $key,
						'required' => true
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
		if ($userContext == 'admin') {
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
				'id' => 'scratch-confirmaccount-comment'
			]
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
	global $wgUser;
	
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
		Html::element(
			'a',
			[
				'href' => 'https://scratch.mit.edu/users/' . $accountRequest->username,
				'target' => '_blank',
				'id' => 'mw-scratch-confirmaccount-profile-link'
			],
			$accountRequest->username
		)
	);
	$disp .= Html::closeElement('tr');
	if ($userContext == 'admin' && CheckUserIntegration::isLoaded() && $wgUser->isAllowed('checkuser')) {
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
		htmlspecialchars($accountRequest->requestNotes)
	);
	
	$output->addHTML($disp);
}

function requestHistoryDisplay(AccountRequest &$accountRequest, array &$history, Language &$language, OutputPage &$output) {
	$disp = '';
	
	$disp .= Html::element(
		'h4',
		[],
		wfMessage('scratch-confirmaccount-history')->text()
	);
	
	$disp .= implode(array_map(function($historyEntry) use($accountRequest, $language) {
		$row = Html::openElement('div', ['class' => 'mw-scratch-confirmaccount-actionentry']);
		$row .= Html::openElement('h5', ['class' => 'mw-scratch-confirmaccount-actionentry-heading']);

		$row .= $language->pipeList([
			Html::element('span', [], $historyEntry->performer ?: $accountRequest->username),
			humanTimestamp($historyEntry->timestamp, $language),
			Html::element('span', [], wfMessage(actions[$historyEntry->action]['message'])->text())
		]);

		$row .= Html::closeElement('h5');
		$row .= Html::element('p', [], $historyEntry->comment);
		$row .= Html::closeElement('div');

		return $row;
	}, $history));
	
	$output->addHTML($disp);
}

function requestAltWarningDisplay(OutputPage &$output, string $key, array &$usernames) {
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

function requestCheckUserDisplay(AccountRequest &$accountRequest, string $userContext, OutputPage &$output) {
	if ($userContext != 'admin') {
		return;
	}
	$requestUsernames = array();
	getRequestUsernamesFromIP($accountRequest->ip, $requestUsernames);
	$checkUserUsernames = array();
	CheckUserIntegration::getCUUsernamesFromIP($accountRequest->ip, $checkUserUsernames);
	if (empty($requestUsernames) && empty($checkUserUsernames)) {
		return;
	}
	
	if (!empty($requestUsernames)) {
		requestAltWarningDisplay($output, 'scratch-confirmaccount-ip-warning-request', $requestUsernames);
	}
	if (!empty($checkUserUsernames)) {
		requestAltWarningDisplay($output, 'scratch-confirmaccount-ip-warning-checkuser', $checkUserUsernames);
	}
}

function emailConfirmationForm(AccountRequest &$accountRequest, string $userContext, OutputPage &$output, SpecialPage &$pageContext, &$session) {
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

function requestPage($requestId, string $userContext, OutputPage &$output, SpecialPage &$pageContext, &$session, Language &$language) {
	global $wgUser;
	if (!isAuthorizedToViewRequest($requestId, $userContext, $session)) {
		$output->showErrorPage('error', 'scratch-confirmaccount-findrequest-nopermission');
		return;
	}

	$accountRequest = getAccountRequestById($requestId);
	if (!$accountRequest) {
		$output->showErrorPage('error', 'scratch-confirmaccount-nosuchrequest');
		return;
	}

	$output->addHTML(Html::element(
		'h3',
		[],
		wfMessage('scratch-confirmaccount-accountrequest')->text()
	));
	
	$history = getRequestHistory($accountRequest);
	
	$hasBeenHandledByAdminBefore = sizeof(array_filter($history, function($historyEntry) { return isset(actionToStatus[$historyEntry->action]) && in_array('admin', actions[$historyEntry->action]['performers']); })) > 0;

	requestMetadataDisplay($accountRequest, $userContext, $language, $output);
	requestNotesDisplay($accountRequest, $output);
	requestHistoryDisplay($accountRequest, $history, $language, $output);
	requestCheckUserDisplay($accountRequest, $userContext, $output);
	requestActionsForm($accountRequest, $userContext, $hasBeenHandledByAdminBefore, $output, $pageContext, $session);
	emailConfirmationForm($accountRequest, $userContext, $output, $pageContext,$session);
}

function handleAccountCreation($accountRequest, &$output) {
	global $wgUser;

	if (userExists($accountRequest->username)) {
		$output->showErrorPage('error', 'scratch-confirmaccount-user-exists');
		return;
	}

	createAccount($accountRequest, $wgUser);
	$output->addHTML(Html::element('p', [], wfMessage('scratch-confirmaccount-account-created')->text()));
}

function authenticateForViewingRequest($requestId, &$session) {
	$session->persist();
	$session->set('requestId', $requestId);
	$session->save();
}

function handleRequestActionSubmission($userContext, &$request, &$output, &$session) {
	global $wgUser;

	$requestId = $request->getText('requestid');

	if (!isAuthorizedToViewRequest($requestId, $userContext, $session)) {
		$output->showErrorPage('error', 'scratch-confirmaccount-findrequest-nopermission');
		return;
	}

	$accountRequest = getAccountRequestById($requestId);
	if (!$accountRequest) {
		//request not found
		$output->showErrorPage('error', 'scratch-confirmaccount-nosuchrequest');
		return;
	}

	$action = $request->getText('action');
	if (!isset(actions[$action])) {
		//invalid action
		$output->showErrorPage('error', 'scratch-confirmaccount-invalid-action');
		return;
	}

	if ($accountRequest->status == 'accepted') {
		//request was already accepted, so we can't act on it
		$output->showErrorPage('error', 'scratch-confirmaccount-already-accepted');
		return;
	}

	if ($userContext == 'user' && $accountRequest->status == 'rejected') {
		$output->showErrorPage('error', 'scratch-confirmaccount-already-rejected');
		return;
	}

	if (!in_array($userContext, actions[$action]['performers'])) {
		//admin does not have permission to perform this action
		$output->showErrorPage('error', 'scratch-confirmaccount-action-unauthorized');
		return;
	}
	
	$updateStatus = $userContext == 'admin' || $accountRequest->status != 'new';

	actionRequest($accountRequest, $updateStatus, $action, $userContext == 'admin' ? $wgUser->getId() : null, $request->getText('comment'));
	if ($action == 'set-status-accepted') {
		handleAccountCreation($accountRequest, $output);
	} else {
		$output->addHTML(Html::rawElement(
			'p',
			[],
			wfMessage(actions[$action]['message'] . '-done', $accountRequest->id)->parse()
		));
	}
	
	//also when someone acts on a request, add an option to clear out old account request passwords
	JobQueueGroup::singleton()->push(new PurgeAccountRequestPasswordsJob());
}
