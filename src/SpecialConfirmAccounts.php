<?php
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/database/DatabaseInteractions.php';
require_once __DIR__ . '/RequestPage.php';

class SpecialConfirmAccounts extends SpecialPage {
	

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

	function defaultPage(&$output) {
		return $this->listRequestsByStatus('new', $output);
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

		actionRequest($accountRequest, $action, $wgUser->getId(), $request->getText('comment'));
		if ($action == 'accept') {
			// @TODO make account.
		} else {
			$output->addHTML(Html::element(
				'p',
				[],
				wfMessage(actions[$action]['message'] . '-done')->text()
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
		} else if (isset(statuses[$par])) {
			return $this->listRequestsByStatus($par, $output);
		} else if (ctype_digit($par)) {
			return requestPage($par, 'admin', $output, $this);
		} else if (empty($par)) {
			return $this->defaultPage($output);
		} else {
			$output->showErrorPage('error', 'scratch-confirmaccount-nosuchrequest');
		}
	}
}
