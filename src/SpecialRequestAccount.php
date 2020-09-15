<?php
require_once __DIR__ . '/verification/ScratchVerification.php';
class SpecialRequestAccount extends SpecialPage {
	function __construct() {
		parent::__construct( 'RequestAccount' );
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
	
	function usernameAndVerificationArea(&$session) {
		$form = $this->formSectionHeader(wfMessage('scratch-confirmaccount-usernameverification'));
		
		$form .= '<label for="scratch-confirmaccount-username">' . wfMessage('scratch-confirmaccount-scratchusername') . '</label><br />';
		$form .= '<input type="text" id="scratch-confirmaccount-username" />';
		
		$form .= '<p>' . wfMessage('scratch-confirmaccount-vercode-explanation')->params(sprintf(PROJECT_LINK, wfMessage('scratch-confirmaccount-request-verification-project-id')->text()))->parse() . '</p>';
		$form .= '<p style=\"font-weight: bold\">' . sessionVerificationCode($session) . '</p>';
		
		$form .= $this->formSectionFooter();
		
		return $form;
	}
	
	function requestNotesArea() {
		$form = $this->formSectionHeader(wfMessage('scratch-confirmaccount-requestnotes'));
		
		$form .= '<p>' . wfMessage('scratch-confirmaccount-requestnotes-explanation')->parse() . '</p>';
		
		$form .= '<label for="scratch-confirmaccount-requestnotes">' . wfMessage('scratch-confirmaccount-requestnotes') . '</label>';
		$form .= '<textarea id="scratch-confirmaccount-requestnotes"></textarea>';
		
		$form .= $this->formSectionFooter();
		
		return $form;
	}
	
	function guidelinesArea() {
		return '';
	}

	function execute( $par ) {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$session = $this->getRequest()->getSession();
		$this->setHeaders();

		$form = Xml::openElement('form', [ 'method' => 'post', 'name' => 'requestaccount', 'action' => $this->getPageTitle()->getLocalUrl(), 'enctype' => 'multipart/form-data' ]);
		
		//form body
		$form .= $this->usernameAndVerificationArea($session);
		$form .= $this->requestNotesArea();
		$form .= $this->guidelinesArea();
		
		$form .= '<input type="submit" value="' . wfMessage('scratch-confirmaccount-request-submit') . '" />';
		
		$form .= Xml::closeElement('form');
		
		$output->addHTML($form);
	}
}
