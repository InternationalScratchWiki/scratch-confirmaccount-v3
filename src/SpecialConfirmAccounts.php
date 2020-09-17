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
	
	function blocksListPage(&$request, &$output) {
		$linkRenderer = $this->getLinkRenderer();
		
		//show the list of existing blocks
		//TODO: paginate (low priority right now)
		$output->addHTML(Html::element(
			'h3',
			[],
			wfMessage('scratch-confirmaccount-blocks')->text()
		));
		
		$table = Html::openElement('table');
		
		//table heading
		$table .= Html::openElement('tr');
		$table .= Html::element('th', [], wfMessage('scratch-confirmaccount-scratchusername'));
		$table .= Html::element('th', [], wfMessage('scratch-confirmaccount-blockreason'));
		$table .= Html::element('th', [], wfMessage('scratch-confirmaccount-actions'));
		$table .= Html::closeElement('tr');
		
		//actual list of blocks
		$blocks = getBlocks();
		$table .= implode(array_map(function ($block) use ($linkRenderer) {
			$row = Html::openElement('tr');
			$row .= Html::element('td', [], $block->blockedUsername);
			$row .= Html::element('td', [], $block->reason);
			$row .= Html::rawElement('td', [], $linkRenderer->makeKnownLink(
				SpecialPage::getTitleFor('ConfirmAccounts', wfMessage('scratch-confirmaccount-blocks')->text() . '/' . $block->blockedUsername),
				wfMessage('scratch-confirmaccount-view')->text()
			));
			$row .= Html::closeElement('tr');
			
			return $row;
		}, $blocks));
		
		$table .= Html::closeElement('table');
		
		$output->addHTML($table);
		
		//also show a form to add a new block
		$output->addHTML(Html::element('h3', [], 'Add new block')); //TODO: i18n this
		$this->singleBlockPage('', $request, $output);
	}
	
	function singleBlockPage($blockedUsername, &$request, &$output) {
		if ($blockedUsername) {
			$block = getSingleBlock($blockedUsername);
			if (!$block) {
				//TODO: show an error
				return;
			}
		} else {
			$block = false;
		}
		
		$output->addHTML(Html::element('h3', [], wfMessage('scratch-confirmaccount-vieweditblock')));
		
		$output->addHTML(Html::openElement('form', ['method' => 'post', 'enctype' => 'multipart/form-data', 'action' => SpecialPage::getTitleFor('ConfirmAccounts')->getFullURL()]));
		
		$output->addHTML(Html::element('input', ['type' => 'hidden', 'name' => 'blockAction', 'value' => $block ? 'update' : 'create']));
		
		$table = Html::openElement('table');
		
		$table .= Html::openElement('tr');
		$table .= Html::element('td', [], wfMessage('scratch-confirmaccount-scratchusername')->text());
		$table .= Html::rawElement('td', [], Html::element('input', ['type' => 'text', 'name' => 'username', 'value' => $blockedUsername, 'readonly' => (bool)$block]));
		$table .= Html::closeElement('tr');
		
		$table .= Html::openElement('tr');
		$table .= Html::element('td', [], wfMessage('scratch-confirmaccount-blockreason')->text());
		$table .= Html::rawElement('td', [], Html::element('textarea', ['name' => 'reason'], $block ? $block->reason : ''));
		$table .= Html::closeElement('tr');
		
		$table .= Html::closeElement('table');
		
		$output->addHTML($table);
		
		$output->addHTML(Html::element('input', ['type' => 'submit', 'name' => 'blockSubmit', 'value' => wfMessage('scratch-confirmaccount-submit')->text()]));
		
		$output->addHTML(Html::closeElement('form'));
	}
	
	function blocksPage($par, &$request, &$output) {
		$subpageParts = explode('/', $par);
		
		if (sizeof($subpageParts) < 2) {
			return $this->blocksListPage($request, $output);
		} else {
			return $this->singleBlockPage($subpageParts[1], $request, $output);
		}
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
		$disp .= Html::rawElement('li', [], $linkRenderer->makeKnownLink(
			SpecialPage::getTitleFor('ConfirmAccounts', wfMessage('scratch-confirmaccount-blocks')),
			wfMessage('scratch-confirmaccount-blocks')->text()
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
	
	function handleBlockFormSubmission(&$request, &$output) {
		global $wgUser;
		
		//TODO: show error message
		$username = $request->getText('username');
		$reason = $request->getText('reason');
		
		if (!$username) {
			$this->showErrorPage();
		}
		if (!$reason) {
			$this->showErrorPage();
		}
		
		$block = getSingleBlock($username);
		if ($block) {
			updateBlock($username, $reason, $wgUser);
		} else {
			addBlock($username, $reason, $wgUser);
		}
		
		$output->redirect(SpecialPage::getTitleFor('ConfirmAccounts', wfMessage('scratch-confirmaccount-blocks')->text())->getFullURL());
	}

	function handleFormSubmission(&$request, &$output) {
		if ($request->getText('action')) {
			handleRequestActionSubmission('admin', $request, $output, $session);
		} else if ($request->getText('blockSubmit')) {
			$this->handleBlockFormSubmission($request, $output);
		}
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
		} else if (strpos($par, wfMessage('scratch-confirmaccount-blocks')->text()) === 0) {
			return $this->blocksPage($par, $request, $output);
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
