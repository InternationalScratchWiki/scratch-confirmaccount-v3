<?php
require_once __DIR__ . '/database/DatabaseInteractions.php';

class SpecialConfirmAccounts extends SpecialPage {
	const statuses = [
		'unreviewed' => 'scratch-confirmaccount-unreviewed'
	];
	const actions = [
		'comment' => 'scratch-confirmaccount-comment',
		'accept' => 'scratch-confirmaccount-accept',
		'reject' => 'scratch-confirmaccount-reject',
		'reqfeedback' => 'scratch-confirmaccount-reqfeedback'
	];

	function __construct() {
		parent::__construct( 'ConfirmAccounts' );
	}

	function getGroupName() {
		return 'users';
	}

	function listRequestsByStatus($status, &$output) {
		$linkRenderer = $this->getLinkRenderer();

		$requests = getAccountRequests($status);

		$output->addHTML(Html::element(
			'h3',
			[],
			wfMessage('scratch-confirmaccount-confirm-header', $status)->text()
		));

		if (empty($requests)) {
			$output->addHTML(Html::element(
				'p',
				[],
				wfMessage('scratch-confirmaccount-norequests')->text()
			));
			return;
		}

		$table = Html::openElement('table');

		//table heading
		$table .= Html::openElement('tr');
		$table .= Html::element(
			'th',
			[],
			wfMessage('scratch-confirmaccount-date')->text()
		);
		$table .= Html::element(
			'th',
			[],
			wfMessage('scratch-confirmaccount-username')->text()
		);
		$table .= Html::element(
			'th',
			[],
			wfMessage('scratch-confirmaccount-requestnotes')->text()
		);
		$table .= Html::element(
			'th',
			[],
			wfMessage('scratch-confirmaccount-actions')->text()
		);
		$table .= Html::closeElement('tr');

		//results
		$table .= implode(array_map(function (&$accountRequest) use ($linkRenderer) {
			$row = Html::openElement('tr');
			$row .= Html::element('td', [], wfTimestamp( TS_ISO_8601, $accountRequest->timestamp ));
			$row .= Html::element('td', [], $accountRequest->username);
			$row .= Html::element('td', [], $accountRequest->requestNotes);
			$row .= Html::rawElement(
				'td',
				[],
				$linkRenderer->makeKnownLink(
					SpecialPage::getTitleFor('ConfirmAccounts', $accountRequest->id),
					wfMessage('scratch-confirmaccount-view')->text()
				)
			);
			$row .= Html::closeElement('tr');

			return $row;
		}, $requests));

		$table .= Html::closeElement('table');

		$output->addHTML($table);
	}

	function showIndividualRequest($requestId, &$output) {
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
			wfMessage(self::statuses[$accountRequest->status])->text()
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
			$row .= Html::element('span', [], wfMessage(self::actions[$historyEntry->action])->text());
			$row .= Html::closeElement('h5');
			$row .= Html::element('p', [], $historyEntry->comment);
			$row .= Html::closeElement('div');

			return $row;
		}, $history));

		//actions section
		$disp .= Html::element(
			'h4',
			[],
			wfMessage('scratch-confirmaccount-actions')->text()
		);
		$disp .= Html::openElement(
			'form',
			[
				'action' => $this->getPageTitle()->getLocalUrl(),
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
		$disp .= implode(array_map(function($key, $val) {
			$row = Html::openElement('li');
			$row .= Html::element(
				'input',
				[
					'type' => 'radio',
					'name' => 'action',
					'id' => 'scratch-confirmaccount-action-' . $key,
					'value' => $key
				]
			);
			$row .= Html::element(
				'label',
				['for' => 'scratch-confirmaccount-action-' . $key],
				wfMessage($val)->text()
			);
			$row .= Html::closeElement('li');
			return $row;
		}, array_keys(self::actions), array_values(self::actions)));
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

		$output->addHTML($disp);
	}

	function defaultPage(&$output) {
		return $this->listRequestsByStatus('unreviewed', $output);
	}

	function handleFormSubmission(&$request, &$output) {
		global $wgUser;

		$requestId = $request->getText('requestid');
		$accountRequest = getAccountRequestById($requestId);
		if (!$accountRequest) {
			//request not found
			$output->showErrorPage('error', 'scratch-confirmaccount-nosuchrequest');
			return;
		}

		$action = $request->getText('action');
		if (!isset(self::actions[$action])) {
			//invalid action
			$output->showErrorPage('error', 'scratch-confirmaccount-invalid-action');
			return;
		}

		if ($accountRequest->status == 'accepted') {
			//request was already accepted, so we can't act on it
			$output->showErrorPage('error', 'scratch-confirmaccount-already-accepted');
			return;
		}

		actionRequest($accountRequest, $action, $wgUser->getId(), $request->getText('comment'));
		if ($action == 'accept') {
			// @TODO make account.
		} else {
			$output->addHTML(Html::element(
				'p',
				[],
				wfMessage(self::actions[$action] . '-done')->text()
			));
		}
	}

	function execute( $par ) {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$output->addModules('ext.scratchConfirmAccount');
		$this->setHeaders();

		//check permissions
		$user = $this->getUser();

		if (!$user->isAllowed('createaccount')) {
			throw new PermissionsError('createaccount');
		}

		if ($request->wasPosted()) {
			return $this->handleFormSubmission($request, $output);
		} else if (isset(self::statuses[$par])) {
			return $this->listRequestsByStatus($par, $output);
		} else if (ctype_digit($par)) {
			return $this->showIndividualRequest($par, $output);
		} else if (empty($par)) {
			return $this->defaultPage($output);
		} else {
			$output->showErrorPage('error', 'scratch-confirmaccount-nosuchrequest');
		}
	}
}
