<?php
require_once __DIR__ . '/database/DatabaseInteractions.php';

class SpecialConfirmAccounts extends SpecialPage {
	const statuses = [
		'unreviewed' => 'Unreviewed'
	];
	
	const actions = [
		'comment' => 'Comment',
		'accept' => 'Accept',
		'reject' => 'Reject',
		'reqfeedback' => 'Request further feedback'
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
		$table .= implode(array_map(function (&$accountRequest) use ($linkRenderer) {
			$row = '<tr>';
			$row .= Html::element('td', [], wfTimestamp( TS_ISO_8601, $accountRequest->timestamp ));
			$row .= Html::element('td', [], $accountRequest->username);
			$row .= Html::element('td', [], $accountRequest->requestNotes);
			$row .= '<td>';
			$row .= $linkRenderer->makeKnownLink(SpecialPage::getTitleFor('ConfirmAccounts', $accountRequest->id), 'View');
			$row .= '</td>';
			$row .= '</tr>';
			
			return $row;
		}, $requests));
		
		$table .= '</table>';
		
		$output->addHTML($table);
	}
	
	function showIndividualRequest($requestId, &$output) {		
		$accountRequest = getAccountRequestById($requestId);
		if (!$accountRequest) {
			$output->addHTML('<p>No such request</p>');
			return;
		}
		
		$disp = '<h3>Account request</h3>';
		
		//the top of the request, basic metadata
		$disp .= '<h4>Details</h4>';
		$disp .= '<table>';
		$html_username = htmlspecialchars($accountRequest->username);
		$disp .= '<tr><td>Status</td><td>' . self::statuses[$accountRequest->status] . '</td></tr>';
		$disp .= '<tr><td>Request timestamp</td><td>' . wfTimestamp( TS_ISO_8601, $accountRequest->timestamp ) . '</td></tr>';
		$disp .= '<tr><td>' . wfMessage('scratch-confirmaccount-scratchusername') . '</td><td><a href="https://scratch.mit.edu/users/' . $html_username . '">' . $html_username . '</a></td></tr>';
		$disp .= '<tr><td>' . wfMessage('scratch-confirmaccount-requestnotes') . '</td><td>' . Html::element('textarea', ['readonly' => true], $accountRequest->requestNotes) . '</td></tr>';
		$disp .= '</table>';
		
		//history section
		$disp .= '<h4>History</h4>';
		
		//actions section
		$disp .= '<h4>Actions</h4>';
		$disp .= '<form>';
		$disp .= Html::rawElement('input', ['type' => 'hidden', 'requestId' => $requestId]);
		$disp .= '<ul style="list-style-type: none; padding-left: 0">';
		$disp .= implode(array_map(function($key, $val) {
			return '<li style="display: inline">' . Html::rawElement('input', ['type' => 'radio', 'name' => 'action', 'id' => 'scratch-confirmaccount-action-' . $key, 'value' => $key]) . '<label for="scratch-confirmaccount-action-' . $key . '">' . $val . '</li>';
		}, array_keys(self::actions), array_values(self::actions)));
		$disp .= '</ul>';
		$disp .= '<p><label for="scratch-confirmaccount-comment">Comment</label><textarea name="comment" id="scratch-confirmaccount-comment"></textarea></p>';
		$disp .= '<p>' . Html::rawElement('input', ['type' => 'submit', 'value' => 'Submit']) . '</p>';
		$disp .= '</form>';
		
		$output->addHTML($disp);
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
		} else if (ctype_digit($par)) {
			return $this->showIndividualRequest($par, $output);
		} else if (empty($par)) {
			return $this->defaultPage($output);
		} else {
			//TODO: show an error message
		}
	}
}
