<?php
require_once __DIR__ . '/verification/ScratchVerification.php';
require_once __DIR__ . '/verification/ScratchUserCheck.php';
require_once __DIR__ . '/database/DatabaseInteractions.php';
require_once __DIR__ . '/objects/AccountRequest.php';
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/RequestPage.php';

use MediaWiki\MediaWikiServices;

class SpecialRequestAccount extends SpecialPage {
	const REQUEST_NOTES_MAX_LENGTH = 5000;
	
	function __construct() {
		parent::__construct( 'RequestAccount' );
	}

	function accountRequestFormData(&$out_error, IDatabase $dbr) {
		global $wgScratchAccountJoinedRequirement;

		$request = $this->getRequest();
		$session = $request->getSession();
		
		//if the user is IP banned, don't even consider anything else
		if ($this->getUser()->isBlockedFromCreateAccount()) {
			$out_error = wfMessage('scratch-confirmaccount-ip-blocked')->text();
			return;
		}
		
		//verify that the username is valid
		$username = $request->getText('scratchusername');

		if ($username == '' || !ScratchVerification::isValidScratchUsername($username)) {
			$out_error = wfMessage('scratch-confirmaccount-invalid-username')->text();
			return;
		}

		//check that the passwords match
		$password = $request->getText('password');
		$password2 = $request->getText('password2');
		if ($password != $password2) {
			// It just compares two user input, no need to prevent timing attack
			$out_error = wfMessage('scratch-confirmaccount-incorrect-password')->text();
			return;
		}

		//check the password length
		$passwordRequirement = passwordMinMax();
		// Scratch requires password to be at least 6 chars
		$passwordMin = max($passwordRequirement[0], 6);
		$passwordMax = $passwordRequirement[1];
		$passwordLen = strlen($password);
		if ($passwordLen < $passwordMin) {
			$out_error = wfMessage('scratch-confirmaccount-password-min', $passwordMin)->text();
			return;
		}
		if ($passwordLen > $passwordMax) {
			$out_error = wfMessage('scratch-confirmaccount-password-max', $passwordMax)->text();
			return;
		}

		//check that there isn't already a registered user with this username (importantly we have to use our own function rather than a MediaWiki one for case sensitivity reasons)
		if (userExists($username, $dbr)) {
			$out_error = wfMessage('scratch-confirmaccount-user-exists')->text();
			return;
		}

		//make sure the user actually commented the verification code
		if (ScratchVerification::topVerifCommenter(ScratchVerification::sessionVerificationCode($session)) !== $username) {
			$out_error = wfMessage('scratch-confirmaccount-verif-missing', $username)->text();
			return;
		}

		//verify the account's age and Scratcher status
		$user_check_error = ScratchUserCheck::check($username);
		switch ($user_check_error) {
			case 'scratch-confirmaccount-new-scratcher':
			case 'scratch-confirmaccount-profile-error':
				$out_error = wfMessage($user_check_error)->text();
				return;
			case 'scratch-confirmaccount-joinedat':
				$days = ceil($wgScratchAccountJoinedRequirement / (24 * 60 * 60));
				$out_error = wfMessage($user_check_error, $days)->text();
				return;
		}
		
		//also make sure there aren't any active requests under the given username
		if (!canMakeRequestForUsername($username, $dbr)) {
			$out_error = wfMessage('scratch-confirmaccount-request-exists')->text();
			return;
		}
				
		//see if the username is blocked from submitting account requests (note that this is done after verifying the confirmation code so that we don't accidentally allow block information to be revealed)
		$block = getSingleBlock($username, $dbr);
		if ($block) {
			$out_error = wfMessage('scratch-confirmaccount-user-blocked', $block->reason)->text();
			// note : blocks are not publicly visible on scratch, so this needs to run after checking the verification code
			return;
		}

		//make sure that the email is valid
		$email = $request->getText('email');
		if ($email != '' && !Sanitizer::validateEmail($email)) {
			$out_error = wfMessage('scratch-confirmaccount-invalid-email')->text();
			return;
		}

		//make sure that the user agreed to the ToS
		if($request->getText('agree') != "true"){
			$out_error = wfMessage('scratch-confirmaccount-disagree-tos')->text();
			return;
		}

		//make sure the request notes are non-empty
		$request_notes = $request->getText('requestnotes');

		if($request_notes == ""){
			$out_error = wfMessage('scratch-confirmaccount-no-request-notes')->text();
			return;
		}
		
		if (strlen($request_notes) > self::REQUEST_NOTES_MAX_LENGTH) {
			$out_error = wfMessage('scratch-confirmaccount-request-notes-too-long', self::REQUEST_NOTES_MAX_LENGTH)->text();
			return;
		}

		// Create password hash
		$passwordFactory = MediaWikiServices::getInstance()->getPasswordFactory();
		$passwordHash = $passwordFactory->newFromPlaintext($password)->toString();

		return [
			'username' => $username,
			'email' => $email,
			'requestnotes' => $request_notes,
			'passwordHash' => $passwordHash
		];
	}

	function getGroupName() {
		return 'login';
	}

	function formSectionHeader(string $name) {
		$form = Html::openElement('fieldset');
		$form .= Html::openElement('legend');
		$form .= $name;
		$form .= Html::closeElement('legend');

		return $form;
	}

	function formSectionFooter() {
		return Html::closeElement('fieldset');
	}

	function usernameAndVerificationArea() {
		$request = $this->getRequest();
		$session = $request->getSession();

		global $wgScratchVerificationProjectID;
		$form = $this->formSectionHeader(wfMessage('scratch-confirmaccount-usernameverification')->text());

		$form .= Html::openElement('table');

		$form .= Html::openElement('tr');
		$form .= Html::rawElement('td', [], Html::element(
			'label',
			['for' => 'scratch-confirmaccount-username'],
			wfMessage('scratch-confirmaccount-scratchusername')->text()
		));
		$form .= Html::rawElement('td', [], Html::element(
			'input',
			[
				'type' => 'text',
				'name' => 'scratchusername',
				'id' => 'scratch-confirmaccount-username',
				'value' => $request->getText('scratchusername')
			]
		));
		$form .= Html::closeElement('tr');

		$form .= Html::openElement('tr');
		$form .= Html::rawElement('td', [], Html::element(
			'label',
			['for' => 'scratch-confirmaccount-password'],
			wfMessage('scratch-confirmaccount-password')->text()
		));
		$form .= Html::rawElement('td', [], Html::element(
			'input',
			[
				'type' => 'password',
				'name' => 'password',
				'id' => 'scratch-confirmaccount-password',
				'value' => ''
			]
		));
		$form .= Html::closeElement('tr');

		$form .= Html::openElement('tr');
		$form .= Html::rawElement('td', [], Html::element(
			'label',
			['for' => 'scratch-confirmaccount-password2'],
			wfMessage('scratch-confirmaccount-password2')->text()
		));
		$form .= Html::rawElement('td', [], Html::element(
			'input',
			[
				'type' => 'password',
				'name' => 'password2',
				'id' => 'scratch-confirmaccount-password2',
				'value' => ''
			]
		));
		$form .= Html::closeElement('tr');

		$form .= Html::openElement('tr');
		$form .= Html::rawElement('td', [], Html::element(
			'label',
			['for' => 'scratch-confirmaccount-email'],
			wfMessage('scratch-confirmaccount-email')->text()
		));
		$form .= Html::rawElement('td', [], Html::element(
			'input',
			[
				'type' => 'email',
				'name' => 'email',
				'id' => 'scratch-confirmaccount-email',
				'value' => $request->getText('email')
			]
		));
		$form .= Html::closeElement('tr');
		$form .= Html::closeElement('table');

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
		$form .= Html::element(
			'button',
			[
				'class' => 'mw-scratch-confirmaccount-click-copy',
				'id' => 'mw-scratch-confirmaccount-click-copy',
				'type' => 'button'
			],
			wfMessage('scratch-confirmaccount-click-copy')->text()
		);
		$form .= $this->formSectionFooter();

		return $form;
	}

	function requestNotesArea(&$request) {
		$form = $this->formSectionHeader(wfMessage('scratch-confirmaccount-requestnotes')->text());

		$form .= Html::rawElement('p', [], wfMessage('scratch-confirmaccount-requestnotes-explanation')->parse());
		$form .= Html::rawElement(
			'label',
			['for' => 'scratch-confirmaccount-requestnotes'],
			wfMessage('scratch-confirmaccount-requestnotes')->parse()
		);
		$form .= Html::element(
			'textarea',
			['class' => 'mw-scratch-confirmaccount-textarea', 'name' => 'requestnotes', 'required' => true, 'maxlength' => self::REQUEST_NOTES_MAX_LENGTH],
			$request->getText('requestnotes')
		);

		$form .= Html::element('br');

		$form .= Html::element('input', [
			'type' => 'checkbox',
			'name' => 'agree',
			'value' => 'true',
			'id' => 'scratch-confirmaccount-agree'
		]);
		$form .= Html::rawElement(
			'label',
			['for' => 'scratch-confirmaccount-agree'],
			wfMessage('scratch-confirmaccount-checkbox-agree')->parse()
		);
		$form .= Html::element('br');

		$form .= $this->formSectionFooter();

		return $form;
	}

	private function handleAccountRequestFormSubmission() {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$session = $request->getSession();

		if (isCSRF($session, $request->getText('csrftoken'))) {
			return $this->requestForm(wfMessage('scratch-confirmaccount-csrf')->parse());
		}
		
		$dbw = getTransactableDatabase(__METHOD__);

		//validate and sanitize the input
		$formData = $this->accountRequestFormData($error, $dbw);
		if ($error != '') {
			cancelTransaction($dbw, __METHOD__);
			return $this->requestForm($error);
		}
		
		//now actually create the request and reset the verification code
		$requestId = createAccountRequest(
			$formData['username'],
			$formData['passwordHash'],
			$formData['requestnotes'],
			$formData['email'],
			$request->getIP(),
			$dbw
		);
		
		//run hooks for handling that the request was submitted
		Hooks::run('ScratchConfirmAccountHooks::onAccountRequestSubmitted', [$requestId, $formData['username'], $formData['requestnotes']]);
		
		$sentEmail = false;
		ScratchVerification::generateNewCodeForSession($session);
		if ($requestId != null) { //only send the verification email if this request actually created the request
			if ($formData['email']) {
				$sentEmail = sendConfirmationEmail($requestId, $dbw);
			}
			
			authenticateForViewingRequest($requestId, $session);
			
			if ($sentEmail) {
				$message = 'scratch-confirmaccount-success-email';
			} else {
				$message = 'scratch-confirmaccount-success';
			}
		} else { //if we encountered a race condition of getting a duplicate request, show the existing request instead
			//TODO: link to FindRequest instead
			$message = 'scratch-confirmaccount-success-noid';
		}
		assert(isset($message));
		

		//and show the output
		$output->addHTML(Html::rawElement(
			'p',
			[],
			wfMessage(
				$message,
				$requestId
			)->parse()
		));
		
		//and finally commit the database transaction
		commitTransaction($dbw, __METHOD__);
	}

	private function handleFormSubmission(&$request, &$output, &$session) {
		if ($request->getText('action')) {
			handleRequestActionSubmission('user', $this, $session, $this->getLanguage());
		} else if ($request->getText('findRequest')) {
			$this->handleFindRequestFormSubmission($request, $output, $session);
		} else if ($request->getText('confirmEmail')) {
			$this->handleConfirmEmailFormSubmission();
		} else if ($request->getText('sendConfirmationEmail')) {
			$this->handleSendConfirmEmailSubmission($request, $output, $session);
		} else {
			$this->handleAccountRequestFormSubmission();
		}
	}

	function requestForm($error = '') {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$session = $request->getSession();

		$form = Html::openElement('form', [ 'method' => 'post', 'name' => 'requestaccount', 'action' => $this->getPageTitle()->getLocalUrl(), 'enctype' => 'multipart/form-data' ]);

		//display errors if there are any relevant
		if ($error != '') {
			$form .= Html::element('p', ['class' => 'errorbox'], $error);
		}

		if ($this->getUser()->isRegistered()) {
			$form .= Html::rawElement('p', [], wfMessage('scratch-confirmaccount-logged-in')->parse());
		}

		$form .= Html::rawElement('p', [], wfMessage('scratch-confirmaccount-view-request')->parse());

		//form body
		$form .= $this->usernameAndVerificationArea();
		$form .= $this->requestNotesArea($request);

		$form .= Html::element('input', [
			'type' => 'hidden',
			'name' => 'csrftoken',
			'value' => setCSRFToken($session)
		]);

		$form .= Html::element('input',
			[
				'type' => 'submit',
				'value' => wfMessage('scratch-confirmaccount-request-submit')->parse()
			]
		);

		$form .= Html::closeElement('form');

		$output->addHTML($form);
	}

	function handleAuthenticationFormSubmission(IDatabase $dbr) {		
		$request = $this->getRequest();
		$output = $this->getOutput();
		$session = $request->getSession();

		if (isCSRF($session, $request->getText('csrftoken'))) {
			$output->showErrorPage('error', 'scratch-confirmaccount-csrf');
			return;
		}

		$linkRenderer = $this->getLinkRenderer();

		$username = $request->getText('username');
		$password = $request->getText('password');

		//see if there are any requests with the given password
		$passwordFactory = MediaWikiServices::getInstance()->getPasswordFactory();
		$requests = getAccountRequestsByUsername($username, $dbr);
		$matchingRequests = array_filter($requests, function($accountRequest) use ($passwordFactory, $password) { return $passwordFactory->newFromCipherText($accountRequest->passwordHash)->verify($password); });

		if (empty($matchingRequests)) {
			$output->showErrorPage('error', 'scratch-confirmaccount-findrequest-nomatch');
			return;
		}
		
		return $matchingRequests;
	}

	function handleConfirmEmailFormSubmission() {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$session = $request->getSession();

		$dbw = getTransactableDatabase('scratch-confirmaccount-submit-confirm-email');
		
		$matchingRequests = $this->handleAuthenticationFormSubmission($dbw);
		if ($matchingRequests === null) {
			//TODO: actually show an error
			cancelTransaction($dbw, 'scratch-confirmaccount-submit-confirm-email');
			return;
		}
		
		//mark that the user can view the request attached to their username and redirect them to it
		$accountRequest = $matchingRequests[0];
		$requestId = $accountRequest->id;
		authenticateForViewingRequest($requestId, $session);
		$emailToken = md5($request->getText('emailToken'));
		$requestURL = SpecialPage::getTitleFor('RequestAccount', $requestId)->getFullURL();

		if (empty($emailToken) || $accountRequest->emailToken !== $emailToken || $accountRequest->emailExpiry <= wfTimestamp(TS_MW)) {
			$output->showErrorPage('error', 'scratch-confirmaccount-invalid-email-token', $requestURL);
			cancelTransaction($dbw, 'scratch-confirmaccount-submit-confirm-email');
			return;
		}
		
		if ($accountRequest->status == 'accepted') {
			$output->showErrorPage('error', 'scratch-confirmaccount-already-accepted-email');
			cancelTransaction($dbw, 'scratch-confirmaccount-submit-confirm-email');
			return;
		}
		
		setRequestEmailConfirmed($requestId, $dbw);
		$output->addHTML(Html::element('p', [], wfMessage('scratch-confirmaccount-email-confirmed')->parse()));
		
		commitTransaction($dbw, 'scratch-confirmaccount-submit-confirm-email');
	}

	function handleFindRequestFormSubmission(&$request, &$output, &$session) {
		$dbr = getReadOnlyDatabase();
		
		$matchingRequests = $this->handleAuthenticationFormSubmission($dbr);
		if ($matchingRequests === null) return;

		$requestId = $matchingRequests[0]->id;

		//mark that the user can view the request attached to their username and redirect them to it
		authenticateForViewingRequest($requestId, $session);
		$output->redirect(SpecialPage::getTitleFor('RequestAccount', $requestId)->getFullURL());
	}

	function handleSendConfirmEmailSubmission(&$request, &$output, &$session) {
		$requestId = $request->getText('requestid');
		if (!$session->exists('requestId') || $session->get('requestId') != $requestId) {
			$output->showErrorPage('error', 'scratch-confirmaccount-findrequest-nopermission');
			return;
		}
		
		$dbw = getTransactableDatabase('scratch-confirmaccount-send-confirm-email');
		
		$accountRequest = getAccountRequestById($requestId, $dbw);
		if ($accountRequest->status == 'accepted') {
			cancelTransaction($dbw, 'scratch-confirmaccount-send-confirm-email');
			$output->showErrorPage('error', 'scratch-confirmaccount-already-accepted-email');
			return;
		}
		$sentEmail = sendConfirmationEmail($requestId, $dbw);
		if (!$sentEmail) {
			cancelTransaction($dbw, 'scratch-confirmaccount-send-confirm-email');
			$output->showErrorPage('error', 'scratch-confirmaccount-email-unregistered');
			return;
		}
		$output->addHTML(Html::element('p', [], wfMessage('scratch-confirmaccount-email-resent')->text()));
		
		commitTransaction($dbw, 'scratch-confirmaccount-send-confirm-email');
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
			return $this->handleFormSubmission($request, $output, $session);
		} else if ($par == '') {
			return $this->requestForm();
		} else if (strpos($par, 'ConfirmEmail/') === 0) { // starts with ConfirmEmail/
			return confirmEmailPage(
				explode('/', $par)[1], // ConfirmEmail/TOKENPARTHERE
				$this);
		} else if ($par == 'FindRequest') {
			return findRequestPage($this);
		} else if (ctype_digit($par)) {
			return requestPage($par, 'user', $this, $language);
		} else {
			$output->showErrorPage('error', 'scratch-confirmaccount-nosuchrequest');
		}
	}
}
