<?php
require_once __DIR__ . '/database/DatabaseInteractions.php';

function requestPage($requestId, $userContext, &$output, &$pageContext) {
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
	$disp .= Html::openElement('table');
	$disp .= Html::openElement('tr');
	$disp .= Html::element(
		'td',
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
		'td',
		[],
		wfMessage('scratch-confirmaccount-request-timestamp')->text()
	);
	$disp .= Html::element(
		'td',
		[],
		wfTimestamp( TS_ISO_8601, $accountRequest->timestamp )
	);
	$disp .= Html::closeElement('tr');
	$disp .= Html::openElement('tr');
	$disp .= Html::element(
		'td',
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
	$disp .= Html::openElement('tr');
	$disp .= Html::element(
		'td',
		[],
		wfMessage('scratch-confirmaccount-requestnotes')->text()
	);
	$disp .= Html::rawElement(
		'td',
		[],
		Html::element(
			'textarea',
			[
				'readonly' => true
			],
			$accountRequest->requestNotes
		)
	);
	$disp .= Html::closeElement('tr');
	$disp .= Html::closeElement('table');

	//history section
	$disp .= Html::element(
		'h4',
		[],
		wfMessage('scratch-confirmaccount-history')->text()
	);
	$disp .= implode(array_map(function($historyEntry) use($accountRequest) {
		$row = Html::openElement('div');
		$row .= Html::openElement('h5');
		$row .= Html::element('span', [], $historyEntry->performer ?: $accountRequest->username);
		$row .= Html::element('span', [], wfTimestamp( TS_ISO_8601, $historyEntry->timestamp ));
		$row .= Html::element('span', [], wfMessage(actions[$historyEntry->action]['message'])->text());
		$row .= Html::closeElement('h5');
		$row .= Html::element('p', [], $historyEntry->comment);
		$row .= Html::closeElement('div');

		return $row;
	}, $history));

	//actions section
	if ($accountRequest->status !='accepted' && !($accountRequest->status == 'rejected' && $userContext == 'user')) { //don't allow anyone to comment on accepted requests and don't allow regular users to comment on rejected requests
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

	$output->addHTML($disp);
}

function handleAccountCreation($accountRequest, &$output) {	
	if (userExists($accountRequest->username)) {
		$output->showErrorPage('error', 'scratch-confirmaccount-user-exists');
		return;
	}
	
	createAccount($accountRequest);
	$output->addHTML('account created');
}

function handleRequestActionSubmission($userContext, &$request, &$output) {
	global $wgUser;

	$requestId = $request->getText('requestid');
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
		$output->showErrorPage('error');
		return;
	}

	actionRequest($accountRequest, $action, $userContext == 'admin' ? $wgUser->getId() : null, $request->getText('comment'));
	if ($action == 'set-status-accepted') {
		handleAccountCreation($accountRequest, $output);
	} else {
		$output->addHTML(Html::element(
			'p',
			[],
			wfMessage(actions[$action]['message'] . '-done')->text()
		));
	}
}