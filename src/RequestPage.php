<?php
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
	$disp .= implode(array_map(function($historyEntry) {
		$row = Html::openElement('div');
		$row .= Html::openElement('h5');
		$row .= Html::element('span', [], wfTimestamp( TS_ISO_8601, $historyEntry->timestamp ));
		$row .= Html::element('span', [], wfMessage(actions[$historyEntry->action]['message'])->text());
		$row .= Html::closeElement('h5');
		$row .= Html::element('p', [], $historyEntry->comment);
		$row .= Html::closeElement('div');

		return $row;
	}, $history));

	//actions section
	if (!in_array($accountRequest->status, ['accepted'])) {
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