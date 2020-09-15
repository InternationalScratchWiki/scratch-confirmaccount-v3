<?php
require_once __DIR__ . '/verification/ScratchVerification.php';
require_once __DIR__ . '/database/DatabaseInteractions.php';
class SpecialRequestAccount extends SpecialPage {
	function __construct() {
		parent::__construct( 'RequestAccount' );
	}
	
	function validate(&$request, &$session) {
		$username = $request->getText('scratchusername');
		
		if ($username == '' || !isValidScratchUsername($username)) {
			return 'invalid scratch username' . $username;
		}
		
		if (userExists($username)) {
			return 'user already exists';
		}
		
		if (isUsernameBlocked($username)) {
			return 'username blocked';
		}
		
		if (hasActiveRequest($username)) {
			return 'user already has active request';
		}
		
		if (topVerifCommenter(sessionVerificationCode($session)) != $username) {
			return 'verification code missing';
		}
		
		return '';
	}
	
	function getGroupName() {
		return 'login';
	}
	
	function formSectionHeader($name) {
		$form = Xml::openElement('fieldset');
		$form .= Xml::openElement('legend');
		$form .= $name;
		$form .= Xml::closeElement('legend');

		return $form;
	}
	
	function formSectionFooter() {
		return Xml::closeElement('fieldset');
	}
	
	function usernameAndVerificationArea(&$session, $request) {
		$form = $this->formSectionHeader(wfMessage('scratch-confirmaccount-usernameverification'));
		
		$form .= '<label for="scratch-confirmaccount-username">' . wfMessage('scratch-confirmaccount-scratchusername') . '</label><br />';
		$form .= Html::rawElement('input', ['type' => 'text', 'name' => 'scratchusername', 'id' => 'scratch-confirmaccount-username', 'value' => $request->getText('scratchusername')]);
		
		$form .= '<p>' . wfMessage('scratch-confirmaccount-vercode-explanation')->params(sprintf(PROJECT_LINK, wfMessage('scratch-confirmaccount-request-verification-project-id')->text()))->parse() . '</p>';
		$form .= '<p style=\"font-weight: bold\">' . sessionVerificationCode($session) . '</p>';
		
		$form .= $this->formSectionFooter();
		
		return $form;
	}
	
	function requestNotesArea(&$request) {
		$form = $this->formSectionHeader(wfMessage('scratch-confirmaccount-requestnotes'));
		
		$form .= '<p>' . wfMessage('scratch-confirmaccount-requestnotes-explanation')->parse() . '</p>';
		
		$form .= '<label for="scratch-confirmaccount-requestnotes">' . wfMessage('scratch-confirmaccount-requestnotes') . '</label>';
		$form .= Html::element('textarea', ['id' => 'scratch-confirmaccount-requestnotes', 'name' => 'requestnotes'], $request->getText('requestnotes'));
		
		$form .= $this->formSectionFooter();
		
		return $form;
	}
	
	function guidelinesArea() {
		return '';
	}
	
	function handleFormSubmission(&$request, &$output, &$session) {
		$error = $this->validate($request, $session);
		if ($error != '') {
			return $this->requestForm($request, $output, $session, $error);
		}
		
		$output->addHTML('success');
	}
	
	function requestForm(&$request, &$output, &$session, $error = '') {
		$form = Xml::openElement('form', [ 'method' => 'post', 'name' => 'requestaccount', 'action' => $this->getPageTitle()->getLocalUrl(), 'enctype' => 'multipart/form-data' ]);
		
		//display errors if there are any relevant
		if ($error != '') {
			$form .= '<p>' . $error . '</p>';
		}
		
		//form body
		$form .= $this->usernameAndVerificationArea($session, $request);
		$form .= $this->requestNotesArea($request);
		$form .= $this->guidelinesArea();
		
		$form .= '<input type="submit" value="' . wfMessage('scratch-confirmaccount-request-submit') . '" />';
		
		$form .= Xml::closeElement('form');
		
		$output->addHTML($form);
	}

	function execute( $par ) {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$session = $this->getRequest()->getSession();
		$this->setHeaders();
		
		if ($request->wasPosted()) {
			return $this->handleFormSubmission($request, $output, $session);
		} else {
			return $this->requestForm($request, $output, $session);
		}
	}
}
