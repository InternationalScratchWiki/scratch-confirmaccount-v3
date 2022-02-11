<?php
require_once __DIR__ . '/verification/ScratchVerification.php';
require_once __DIR__ . '/verification/ScratchUserCheck.php';
require_once __DIR__ . '/database/DatabaseInteractions.php';
require_once __DIR__ . '/objects/AccountRequest.php';
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/subpages/RequestPage.php';

use MediaWiki\MediaWikiServices;

class SpecialRecoverRequestPassword extends SpecialPage {
	function __construct() {
		parent::__construct( 'RecoverRequestPassword' );
	}

	function getGroupName() {
		return 'login';
	}
	
	function passwordResetForm() {
		global $wgScratchVerificationProjectID;
		
		$request = $this->getRequest();
		$output = $this->getOutput();
		$session = $request->getSession();
		
		$output->enableOOUI();
		
		$form = Html::openElement('form', [ 'method' => 'post', 'name' => 'requestaccount', 'action' => $this->getPageTitle()->getLocalUrl(), 'enctype' => 'multipart/form-data' ]);
		
		$form .= new OOUI\FieldsetLayout( [
			'items' => [
				new OOUI\FieldLayout(
					new OOUI\TextInputWidget( [
						'name' => 'scratchusername',
						'required' => true,
						'value' => $request->getText('scratchusername')
					] ),
					[
						'label' => wfMessage('scratch-confirmaccount-scratchusername')->text(),
						'align' => 'top',
					]
				),
				new OOUI\FieldLayout(
					new OOUI\TextInputWidget( [
						'name' => 'password',
						'type' => 'password',
						'required' => true
					] ),
					[
						'label' => wfMessage('scratch-confirmaccount-password')->text(),
						'align' => 'top',
						'notices' => [wfMessage('scratch-confirmaccount-password-caption')->parse()]
					]
				),
				new OOUI\FieldLayout(
					new OOUI\TextInputWidget( [
						'name' => 'password2',
						'type' => 'password',
						'required' => true
					]),
					[
						'label' => wfMessage('scratch-confirmaccount-password2')->text(),
						'align' => 'top',
					]
				),
			]
		]);

		$form .= Html::rawElement(
			'p',
			[],
			wfMessage('scratch-confirmaccount-vercode-explanation', sprintf(ScratchVerification::PROJECT_LINK, $wgScratchVerificationProjectID))->parse()
		);
		$form .= Html::element(
			'p',
			[
				'class' => 'mw-scratch-confirmaccount-verifcode',
		   		'id' => 'mw-scratch-confirmaccount-verifcode'
		  	],
			ScratchVerification::sessionVerificationCode($session)
		);
		$form .= new OOUI\ButtonWidget([
			'id' => 'mw-scratch-confirmaccount-click-copy',
			'classes' => ['mw-scratch-confirmaccount-click-copy'],
			'label' => wfMessage('scratch-confirmaccount-click-copy')->text()
		]);
		
		$form .= Html::element('input', [
			'type' => 'hidden',
			'name' => 'csrftoken',
			'value' => setCSRFToken($session)
		]);
		
		$form .= new OOUI\ButtonInputWidget([
			'type' => 'submit',
			'flags' => ['primary', 'progressive'],
			'label' => wfMessage('scratch-confirmaccount-request-submit')->parse()
		]);
		
		$form .= Html::closeElement('form');

		$output->addHTML($form);
	}
	
	function handleFormSubmission() {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$session = $request->getSession();

		if (isCSRF($session, $request->getText('csrftoken'))) {
			$output->showErrorPage('error', 'scratch-confirmaccount-csrf');
			return;
		}
		
		$username = $request->getText('username');
		$password = $request->getText('password');
		
		//TODO: find any matching requests
		
		//TODO: do scratch verification
		
		//TODO: reset the password
		
		$dbw = getTransactableDatabase(__METHOD__);
		
		commitTransaction($dbw, __METHOD__);
	}

	function execute( $par ) {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$language = $this->getLanguage();
		
		$output->setPageTitle( $this->msg( "requestaccount" )->escaped() );
		
		$output->addModules('ext.scratchConfirmAccount.js');
		$output->addModuleStyles('ext.scratchConfirmAccount.css');
		
		$session = $request->getSession();
		$this->setHeaders();
		
		$this->checkReadOnly();

		if ($request->wasPosted()) {
			return $this->handleFormSubmission();
		} else {
			return $this->passwordResetForm();
		}
	}
}
