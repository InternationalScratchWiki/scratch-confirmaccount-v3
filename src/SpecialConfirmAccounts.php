<?php
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/database/DatabaseInteractions.php';
require_once __DIR__ . '/RequestPage.php';

class AccountRequestPager extends AbstractAccountRequestPager {
	private $linkRenderer;
	function __construct($username, $status, $linkRenderer) {
		parent::__construct($username, $status);

		$this->linkRenderer = $linkRenderer;
	}

	function rowFromRequest($accountRequest) {
		$row = Html::openElement('tr');
		$row .= Html::element('td', [], wfTimestamp( TS_ISO_8601, $accountRequest->lastUpdated ));
		$row .= Html::element('td', [], $accountRequest->username);
		$row .= Html::element('td', [], $accountRequest->requestNotes);
		$row .= Html::rawElement(
			'td',
			[],
			$this->linkRenderer->makeKnownLink(
				SpecialPage::getTitleFor('ConfirmAccounts', $accountRequest->id),
				wfMessage('scratch-confirmaccount-view')->text()
			)
		);
		$row .= Html::closeElement('tr');

		return $row;
	}
}

class SpecialConfirmAccounts extends SpecialPage {
	function __construct() {
		parent::__construct( 'ConfirmAccounts' );
	}

	function getGroupName() {
		return 'users';
	}

	function requestTable($status, $username, &$linkRenderer) {
		$pager = new AccountRequestPager($status, $username, $linkRenderer);

		if ($pager->getNumRows() == 0) {
			return Html::element('p', [], wfMessage('scratch-confirmaccount-norequests')->text());
		}

		$table = $pager->getNavigationBar();

		$table .= Html::openElement('table');

		//table heading
		$table .= Html::openElement('tr');
		$table .= Html::element(
			'th',
			[],
			wfMessage('scratch-confirmaccount-lastupdated')->text()
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
		$table .= $pager->getBody();

		$table .= Html::closeElement('table');

		$table .= $pager->getNavigationBar();

		return $table;
	}

	function listRequestsByStatus($status, &$output) {
		$linkRenderer = $this->getLinkRenderer();

		$output->addHTML(Html::element(
			'h3',
			[],
			wfMessage('scratch-confirmaccount-confirm-header', $status)->text()
		));

		$table = $this->requestTable(null, $status, $linkRenderer);

		$output->addHTML($table);
	}

	function defaultPage(&$output) {
		$linkRenderer = $this->getLinkRenderer();

		$disp = Html::element('h3', [], wfMessage('scratch-confirmaccount-request-options')->text());
		$disp .= Html::openElement('ul');
		$disp .= Html::rawElement('li', [], $linkRenderer->makeKnownLink(
			SpecialPage::getTitleFor('ConfirmAccounts', 'awaiting-user'), //TODO: make this display how many such requests there are
			wfMessage('scratch-confirmaccount-requests-awaiting-user-comment')->text()
		));
		$disp .= Html::rawElement('li', [], $linkRenderer->makeKnownLink(
			SpecialPage::getTitleFor('ConfirmAccounts', 'accepted'), //TODO: make this display how many such requests there are
			wfMessage('scratch-confirmaccount-accepted-requests')->text()
		));
		$disp .= Html::rawElement('li', [], $linkRenderer->makeKnownLink(
			SpecialPage::getTitleFor('ConfirmAccounts', 'rejected'), //TODO: make this display how many such requests there are
			wfMessage('scratch-confirmaccount-rejected-requests')->text()
		));
		$disp .= Html::closeElement('ul');
		$disp .= Html::openElement('form', [
			'action' => '',
			'method' => 'get'
		]);
		$disp .= Html::element(
			'label',
			['for' => 'scratch-confirmaccount-usernamesearch'],
			wfMessage('scratch-confirmaccount-search-label')->text()
		);
		$disp .= Html::element('input', [
			'type' => 'search',
			'id' => 'scratch-confirmaccount-usernamesearch',
			'name' => 'username'
		]);
		$disp .= Html::element('input', [
			'type' => 'submit',
			'value' => wfMessage('scratch-confirmaccount-search')->parse()
		]);
		$disp .= Html::closeElement('form');
		$output->addHTML($disp);

		$this->listRequestsByStatus('new', $output);
		$this->listRequestsByStatus('awaiting-admin', $output);
	}

	function handleFormSubmission(&$request, &$output) {
		handleRequestActionSubmission('admin', $request, $output, $session);
	}

	function searchByUsername($username, &$request, &$output) {
		$linkRenderer = $this->getLinkRenderer();

		$output->addHTML(Html::element(
			'h3',
			[],
			wfMessage('scratch-confirmaccount-confirm-search-results', $username)->text()
		));

		$table = $this->requestTable($username, null, $linkRenderer);

		$output->addHTML($table);
	}

	function execute( $par ) {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$output->addModules('ext.scratchConfirmAccount');
		$session = $request->getSession();
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
			return requestPage($par, 'admin', $output, $this, $session);
		} else if (empty($par)) {
			return $this->defaultPage($output);
		} else {
			$output->showErrorPage('error', 'scratch-confirmaccount-nosuchrequest');
		}
	}
}
