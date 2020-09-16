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
		$linkRenderer = $this->getLinkRenderer();
		
		$disp = '';
		$disp .= '<h3>Request options</h3>';
		$disp .= '<ul>';
		$disp .= '<li>' . $linkRenderer->makeKnownLink(
					SpecialPage::getTitleFor('ConfirmAccounts', 'awaiting-user'), //TODO: make this display how many such requests there are
					wfMessage('scratch-confirmaccount-requests-awaiting-user-comment')->text()
				) . '</li>';
		$disp .= '<li>' . $linkRenderer->makeKnownLink(
			SpecialPage::getTitleFor('ConfirmAccounts', 'accepted'),
			wfMessage('scratch-confirmaccount-accepted-requests')->text()
		) . '</li>';
		$disp .= '<li>' . $linkRenderer->makeKnownLink(
			SpecialPage::getTitleFor('ConfirmAccounts', 'rejected'),
			wfMessage('scratch-confirmaccount-rejected-requests')->text()
		) . '</li>';
		$disp .= '</ul>';
		$disp .= '<form action="" method="get"><label for="scratch-confirmaccount-usernamesearch">Search by username</label><br /><input type="text" id="scratch-confirmaccount-usernamesearch" name="username" /><input type="submit" value="Search" /></form>';
		$output->addHTML($disp);
		
		$this->listRequestsByStatus('new', $output);
		$this->listRequestsByStatus('awaiting-admin', $output);
	}

	function handleFormSubmission(&$request, &$output) {
		handleRequestActionSubmission('admin', $request, $output);
	}
	
	function searchByUsername($username, &$request, &$output) {
		//TODO: implement this
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
		} else if ($request->getText('username')) {
			return $this->searchByUsername($request->getText('username'), $request, $output);
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
