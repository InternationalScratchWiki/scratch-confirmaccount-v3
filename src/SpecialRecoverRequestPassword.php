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
		$dbw = getTransactableDatabase(__METHOD__);
		
		$request = $this->getRequest();
		$output = $this->getOutput();
		$session = $request->getSession();

		if (isCSRF($session, $request->getText('csrftoken'))) {
			$output->showErrorPage('error', 'scratch-confirmaccount-csrf');
			cancelTransaction($dbw, __METHOD__);
			return;
		}
		
		$username = $request->getText('scratchusername');
		$password = $request->getText('password');
		$password2 = $request->getText('password2');
		if ($password != $password2) {
			echo 'passwords do not match';
			cancelTransaction($dbw, __METHOD__);
			return;
		}
		
		//do scratch verification
		if (ScratchVerification::topVerifCommenter(ScratchVerification::sessionVerificationCode($session)) !== $username) {
			// TODO: show errors
			echo ScratchVerification::topVerifCommenter(ScratchVerification::sessionVerificationCode($session)) . "\n";
			echo $username . "\n";
			echo 'code not commented';
			cancelTransaction($dbw, __METHOD__);
			return;
		}
		
		//find any matching requests
		$requests = getAccountRequestsByUsername($username, $dbw);
		if (empty($requests)) {
			echo 'no such request';
			cancelTransaction($dbw, __METHOD__);
			return;
		}
		usort($requests, function ($req1, $req2) { return $req2->timestamp - $req1->timestamp; }); // sort in DESCENDING order based on timestamp
		$applicableRequest = $requests[0];
		
		// we need to check the join date in case the user was renamed
		$accountJoinedBeforeRequest = ScratchUserCheck::joinedBefore($username, $applicableRequest->timestamp);
		if ($accountJoinedBeforeRequest === null) {
			echo 'network error';
			cancelTransaction($dbw, __METHOD__);
			return;
		}
		
		if (!$accountJoinedBeforeRequest) { 
			echo 'joined too late';
			cancelTransaction($dbw, __METHOD__);
			return;
		}
		
		//reset the password
		$passwordFactory = MediaWikiServices::getInstance()->getPasswordFactory();
		$passwordHash = $passwordFactory->newFromPlaintext($password)->toString();
		resetAccountRequestPassword($applicableRequest, $passwordHash, $dbw);
				
		commitTransaction($dbw, __METHOD__);
		
		//TODO: show a success message
		echo 'success';
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
