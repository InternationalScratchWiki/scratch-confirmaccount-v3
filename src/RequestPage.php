<?php
require_once __DIR__ . '/database/DatabaseInteractions.php';

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


function requestPage($requestId, $userContext, &$output, &$pageContext, &$session, &$language) {
	if (!isAuthorizedToViewRequest($requestId, $userContext, $session)) {
		$output->showErrorPage('error', 'scratch-confirmaccount-findrequest-nopermission');
		return;
	}

	$accountRequest = getAccountRequestById($requestId);
	if (!$accountRequest) {
		$output->showErrorPage('error', 'scratch-confirmaccount-nosuchrequest');
		return;
	}

	$history = getRequestHistory($accountRequest);

	$disp = Html::element(
		'h3',
		[],
		wfMessage('scratch-confirmaccount-accountrequest')->text()
	);

	//the top of the request, basic metadata
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
				'target' => '_blank'
			],
			$accountRequest->username
		)
	);
	$disp .= Html::closeElement('tr');
	$disp .= Html::closeElement('table');

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

	//history section
	$disp .= Html::element(
		'h4',
		[],
		wfMessage('scratch-confirmaccount-history')->text()
	);
	$disp .= implode(array_map(function($historyEntry) use($accountRequest, $language) {
		global $wgUser;

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

	//actions section
	if ($accountRequest->status != 'accepted' && !($accountRequest->status == 'rejected' && $userContext == 'user')) { //don't allow anyone to comment on accepted requests and don't allow regular users to comment on rejected requests
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
				'enctype' => 'multipart/form-data'
			]
		);
		$disp .= Html::rawElement(
			'input',
			[
				'type' => 'hidden',
				'name' => 'requestid',
				'value' => $requestId
			]
		);
		$disp .= Html::openElement('ul', ['class' => 'mw-scratch-confirmaccount-actions-list']);

		$usable_actions = array_filter(actions, function($action) use($userContext) { return in_array($userContext, $action['performers']); });

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
		$disp .= Html::openElement('p');
		$disp .= Html::element(
			'label',
			['for' => 'cratch-confirmaccount-comment'],
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
	}

    if ($userContext == 'user' && $accountRequest->status !='accepted' && $accountRequest->status !='rejected') {
        if (!empty($accountRequest->email) && !$accountRequest->emailConfirmed) {
			$disp .= Html::openElement('form', [
				'action' => $pageContext->getPageTitle()->getLocalUrl(),
				'method' => 'post',
				'enctype' => 'multipart/form-data'
			]);
			$disp .= Html::element('input', [
				'type' => 'hidden',
				'name' => 'sendConfirmationEmail',
				'value' => '1'
			]);
			$disp .= Html::element('input', [
				'type' => 'hidden',
				'name' => 'requestid',
				'value' => $requestId
			]);
			$disp .= Html::element('input', [
				'type' => 'submit',
				'value' => wfMessage('scratch-confirmaccount-resend')->parse()
			]);
			$disp .= Html::closeElement('form');
		}
    }

	$output->addHTML($disp);
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

	actionRequest($accountRequest, $action, $userContext == 'admin' ? $wgUser->getId() : null, $request->getText('comment'));
	if ($action == 'set-status-accepted') {
		handleAccountCreation($accountRequest, $output);
	} else {
		$output->addHTML(Html::rawElement(
			'p',
			[],
			wfMessage(actions[$action]['message'] . '-done', $accountRequest->id)->parse()
		));
	}
}
