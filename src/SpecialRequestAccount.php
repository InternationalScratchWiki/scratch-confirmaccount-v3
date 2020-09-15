<?php
require_once __DIR__ . '/verification/ScratchVerification.php';
require_once __DIR__ . '/database/DatabaseInteractions.php';
require_once __DIR__ . '/objects/AccountRequest.php';

class SpecialRequestAccount extends SpecialPage {
	function __construct() {
		parent::__construct( 'RequestAccount' );
	}
	
	function sanitizedPostData(&$request, &$session, &$out_error) {
		$username = $request->getText('scratchusername');
		
		if ($username == '' || !isValidScratchUsername($username)) {
			$out_error = 'invalid scratch username';
			return;
		}
		
		if (userExists($username)) {
			$out_error = 'user already exists';
			return;
		}
		
		if (isUsernameBlocked($username)) {
			$out_error = 'username blocked'; // note : blocks are not publicly visible on scratch, so this will never firet
			return;
		}
		
		if (hasActiveRequest($username)) {
			$out_error = 'user already has active request';
			return;
		}
		
		if (topVerifCommenter(sessionVerificationCode($session)) != $username) {
			$out_error = 'verification code missing';
			return;
		}
		
		$email = $request->getText('email');
		if ($email != '' && !Sanitizer::validateEmail($email)) {
			$out_error = 'invalid email';
			return;
		}
		
		$request_notes = $request->getText('requestnotes');
		
		return ['username' => $username, 'email' => $email, 'requestnotes' => $request_notes];
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
		
		$form .= '<p>';
		$form .= '<label for="scratch-confirmaccount-username">' . wfMessage('scratch-confirmaccount-scratchusername') . '</label><br />';
		$form .= Html::rawElement('input', ['type' => 'text', 'name' => 'scratchusername', 'id' => 'scratch-confirmaccount-username', 'value' => $request->getText('scratchusername')]);
		$form .= '</p>';
		
		$form .= '<p>';
		$form .= '<label for="scratch-confirmaccount-email">' . wfMessage('scratch-confirmaccount-email') . '</label><br />';
		$form .= Html::rawElement('input', ['type' => 'email', 'name' => 'email', 'id' => 'scratch-confirmaccount-email', 'value' => $request->getText('email')]);
		$form .= '</p>';
		
		$form .= '<p>' . wfMessage('scratch-confirmaccount-vercode-explanation')->params(sprintf(PROJECT_LINK, $GLOBALS['wgScratchVerificationProjectID'] ?: "10135908"))->parse() . '</p>';
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
		//validate and sanitize the input
		$formData = $this->sanitizedPostData($request, $session, $error);
		if ($error != '') {
			return $this->requestForm($request, $output, $session, $error);
		}
		
		//now actually create the request and reset the verification code
		createAccountRequest($formData['username'], $formData['requestnotes'], $formData['email'], $request->getIP());
		generateNewCodeForSession($session);
		
		//and show the output
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
