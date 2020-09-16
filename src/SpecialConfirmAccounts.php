<?php
require_once __DIR__ . '/database/DatabaseInteractions.php';

class SpecialConfirmAccounts extends SpecialPage {
	const statuses = [
		'unreviewed' => 'Unreviewed'
	];
	
	function __construct() {
		parent::__construct( 'ConfirmAccounts' );
	}
	
	function getGroupName() {
		return 'users';
	}
	
	function listRequestsByStatus($status, &$output) {
		$requests = getAccountRequests($status);
		
		$output->addHTML('<h3>Requests with status ' . $status . '</h3>');
		
		if (empty($requests)) {
			$output->addHTML('<p>There are no requests to view.</p>');
			return;
		}
		
		$table = '<table>';
		
		//table heading
		$table .= '<tr>';
		$table .= '<th>Date</th>';
		$table .= '<th>Username</th>';
		$table .= '<th>Request notes</th>';
		$table .= '<th>Actions</th>';
		$table .= '</tr>';
		
		//results
		$table .= implode(array_map(function (&$accountRequest) {
			$row = '<tr>';
			$row .= Html::element('td', [], wfTimestamp( TS_ISO_8601, $accountRequest->timestamp ));
			$row .= Html::element('td', [], $accountRequest->username);
			$row .= Html::element('td', [], $accountRequest->requestNotes);
			$row .= '<td>';
			$row .= Html::element('a', ['href' => '#'], 'View');
			$row .= '</td>';
			$row .= '</tr>';
			
			return $row;
		}, $requests));
		
		$table .= '</table>';
		
		$output->addHTML($table);
	}
	
	function showIndividualRequest($requestId) {
		
	}
	
	function defaultPage(&$output) {
		return $this->listRequestsByStatus('unreviewed', $output);
	}
	
	function execute( $par ) {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$this->setHeaders();
		
		//check permissions
		$user = $this->getUser();

		if (!$user->isAllowed('createaccount')) {
			throw new PermissionsError('createaccount');
		}

		if (isset(self::statuses[$par])) {
			return $this->listRequestsByStatus($par, $output);
		} else if (is_int($par)) {
			return $this->showIndividualRequest($par);
		} else if (empty($par)) {
			return $this->defaultPage($output);
		} else {
			//TODO:show an error message
		}
	}
}
