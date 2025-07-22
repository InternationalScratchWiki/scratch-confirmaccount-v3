<?php
require_once __DIR__ . '/verification/ScratchVerification.php';
require_once __DIR__ . '/verification/ScratchUserCheck.php';
require_once __DIR__ . '/database/DatabaseInteractions.php';
require_once __DIR__ . '/objects/AccountRequest.php';
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/subpages/RequestPage.php';

use ScratchConfirmAccount\Hook\HookRunner;

use MediaWiki\HookContainer\HookContainer;

class SpecialRequestAccount extends SpecialPage {
	const REQUEST_NOTES_MAX_LENGTH = 5000;

	private PasswordFactory $passwordFactory;
	private HookContainer $hookContainer;
	private JobQueueGroup $jobQueueGroup;
	
	function __construct(PasswordFactory $passwordFactory, HookContainer $hookContainer, JobQueueGroup $jobQueueGroup) {
		parent::__construct( 'RequestAccount' );

		$this->passwordFactory = $passwordFactory;
		$this->hookContainer = $hookContainer;
		$this->jobQueueGroup = $jobQueueGroup;
	}

	function accountRequestFormData(&$out_error, Wikimedia\Rdbms\DBConnRef $dbr) {
		global $wgScratchAccountJoinedRequirement;

		$request = $this->getRequest();
		$session = $request->getSession();
		
		//if the user is IP banned, don't even consider anything else
		$block = $this->getUser()->getBlock();
		if ($block && $block->isCreateAccountBlocked()) {
			$out_error = wfMessage('scratch-confirmaccount-ip-blocked', $block->getReasonComment()->text)->parse();
			return;
		}
		
		//verify that the username is valid
		$username = $request->getText('scratchusername');

		if ($username === '' || !ScratchVerification::isValidScratchUsername($username)) {
			$out_error = wfMessage('scratch-confirmaccount-invalid-username')->parse();
			return;
		}

		//check that the passwords match
		$password = $request->getText('password');
		$password2 = $request->getText('password2');
		if ($password !== $password2) {
			// It just compares two user input, no need to prevent timing attack
			$out_error = wfMessage('scratch-confirmaccount-incorrect-password')->parse();
			return;
		}

		//check the password length
		$passwordRequirement = passwordMinMax();
		// Scratch requires password to be at least 6 chars
		$passwordMin = max($passwordRequirement[0], 6);
		$passwordMax = $passwordRequirement[1];
		$passwordLen = strlen($password);
		if ($passwordLen < $passwordMin) {
			$out_error = wfMessage('scratch-confirmaccount-password-min', $passwordMin)->parse();
			return;
		}
		if ($passwordLen > $passwordMax) {
			$out_error = wfMessage('scratch-confirmaccount-password-max', $passwordMax)->parse();
			return;
		}

		//check that there isn't already a registered user with this username (importantly we have to use our own function rather than a MediaWiki one for case sensitivity reasons)
		if (userExists($username, $dbr)) {
			$out_error = wfMessage('scratch-confirmaccount-user-exists')->parse();
			return;
		}

		//make sure the user actually commented the verification code
		if (strtolower(ScratchVerification::topVerifCommenter(ScratchVerification::sessionVerificationCode($session))) !== strtolower($username)) {
			$out_error = wfMessage('scratch-confirmaccount-verif-missing', $username)->parse();
			return;
		}

		//verify the account's age and Scratcher status
		if (!hasUsernameRequirementsBypass($username, $dbr)) {
			$user_check_error = ScratchUserCheck::check($username);
			switch ($user_check_error) {
				case 'scratch-confirmaccount-new-scratcher':
				case 'scratch-confirmaccount-profile-error':
					$out_error = wfMessage($user_check_error)->parse();
					return;
				case 'scratch-confirmaccount-joinedat':
					$days = ceil($wgScratchAccountJoinedRequirement / (24 * 60 * 60));
					$out_error = wfMessage($user_check_error, $days)->parse();
					return;
			}
		}
		
		//also make sure there aren't any active requests under the given username
		if (!canMakeRequestForUsername($username, $dbr)) {
			$out_error = wfMessage('scratch-confirmaccount-request-exists')->parse();
			return;
		}
				
		//see if the username is blocked from submitting account requests (note that this is done after verifying the confirmation code so that we don't accidentally allow block information to be revealed)
		$block = getSingleBlock($username, $dbr);
		if ($block && !blockExpired($block)) {
			$out_error = wfMessage('scratch-confirmaccount-user-blocked', $block->reason)->parse();
			if ($block->expirationTimestamp !== null) {
				$out_error .= Html::openElement('br');
				$out_error .= wfMessage(
					'scratch-confirmaccount-user-blocked-duration',
					$this->getContext()->getLanguage()->formatExpiry($block->expirationTimestamp)
				)->parse();
			}
			// note : blocks are not publicly visible on scratch, so this needs to run after checking the verification code
			return;
		}

		//make sure that the email is valid
		$email = $request->getText('email');
		if ($email !== '' && !Sanitizer::validateEmail($email)) {
			$out_error = wfMessage('scratch-confirmaccount-invalid-email')->parse();
			return;
		}

		//make sure that the user agreed to the ToS
		if($request->getText('agree') !== "true"){
			$out_error = wfMessage('scratch-confirmaccount-disagree-tos')->parse();
			return;
		}

		//make sure the request notes are non-empty
		$request_notes = $request->getText('requestnotes');

		if($request_notes === ''){
			$out_error = wfMessage('scratch-confirmaccount-no-request-notes')->parse();
			return;
		}
		
		if (strlen($request_notes) > self::REQUEST_NOTES_MAX_LENGTH) {
			$out_error = wfMessage('scratch-confirmaccount-request-notes-too-long', self::REQUEST_NOTES_MAX_LENGTH)->parse();
			return;
		}

		// Create password hash;
		$passwordHash = $this->passwordFactory->newFromPlaintext($password)->toString();

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

		$form .= new OOUI\FieldsetLayout( [
			'items' => [
				new OOUI\FieldLayout(
					new OOUI\TextInputWidget( [
						'name' => 'scratchusername',
						'required' => true,
						'value' => $request->getText('scratchusername'),
					] ),
					[
						'label' => wfMessage('scratch-confirmaccount-scratchusername')->text(),
						'align' => 'top',
						'infusable' => true,
					],
					
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
				new OOUI\FieldLayout(
					new OOUI\TextInputWidget( [
						'name' => 'email',
						'type' => 'email',
						'value' => $request->getText('email')
					] ),
					[
						'label' => wfMessage('scratch-confirmaccount-email')->text(),
						'align' => 'top',
					]
				),
			]
		]);

		$form .= Html::rawElement(
			'p',
			[],
			wfMessage('scratch-confirmaccount-vercode-explanation')->rawParams(
				Html::element(
					'a',
					[
						'href' => sprintf(ScratchVerification::PROJECT_LINK, $wgScratchVerificationProjectID),
						'class' => 'plainlinks',
						'target' => '_blank',
						'rel' => 'noopener noreferrer'
					],
					wfMessage('scratch-confirmaccount-verification-project')->text()
				)
			)->parse()
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

		$form .= $this->formSectionFooter();

		return $form;
	}

	function requestNotesArea() {
		$request = $this->getRequest();

		$form = $this->formSectionHeader(wfMessage('scratch-confirmaccount-requestnotes')->text());

		$form .= Html::rawElement('p', [], wfMessage('scratch-confirmaccount-requestnotes-explanation')->parse());

		$form .= new OOUI\FieldLayout(
			new OOUI\MultilineTextInputWidget( [
				'name' => 'requestnotes',
				'required' => true,
				'value' => $request->getText('requestnotes')
			] ),
			[
				'label' => wfMessage('scratch-confirmaccount-requestnotes')->text(),
				'align' => 'top',
			]
		);

		$form .= new OOUI\FieldLayout(
			new OOUI\CheckboxInputWidget([
				'name' => 'agree',
				'value' => 'true',
				'required' => true,
				'selected' => $request->getText('agree') === 'true'
			]),
			[
				'label' => wfMessage('scratch-confirmaccount-checkbox-agree')->parse(),
				'align' => 'inline'
			]
		);

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
		if ($error) {
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
		
		$sentEmail = false;
		ScratchVerification::generateNewCodeForSession($session);
		if ($requestId !== null) { //only send the verification email if this request actually created the request
			//run hooks for handling that the request was submitted
			$hookRunner = new HookRunner($this->hookContainer);
			$hookRunner->onAccountRequestSubmitted($requestId, $formData['username'], $formData['requestnotes']);

			if ($formData['email']) {
				$sentEmail = sendConfirmationEmail($this->getUser(), $this->getLanguage(), $requestId, $dbw);
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

	private function handleFormSubmission() {
		$request = $this->getRequest();
		$session = $request->getSession();

		if ($request->getText('action')) {
			handleRequestActionSubmission('user', $this, $session, $this->jobQueueGroup);
		} else if ($request->getText('findRequest')) {
			$this->handleFindRequestFormSubmission();
		} else if ($request->getText('confirmEmail')) {
			$this->handleConfirmEmailFormSubmission();
		} else if ($request->getText('sendConfirmationEmail')) {
			$this->handleSendConfirmEmailSubmission();
		} else {
			$this->handleAccountRequestFormSubmission();
		}
	}

	function requestForm($error = '') {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$output->enableOOUI();
		$session = $request->getSession();

		$form = Html::openElement('form', [ 'method' => 'post', 'name' => 'requestaccount', 'action' => $this->getPageTitle()->getLocalUrl(), 'enctype' => 'multipart/form-data' ]);

		//display errors if there are any relevant
		if ($error) {
			$form .= Html::rawElement('p', ['class' => 'errorbox'], $error);
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

		$form .= new OOUI\ButtonInputWidget([
			'type' => 'submit',
			'flags' => ['primary', 'progressive'],
			'label' => wfMessage('scratch-confirmaccount-request-submit')->parse()
		]);

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

		$username = $request->getText('username');
		$password = $request->getText('password');

		//see if there are any requests with the given password
		$requests = getAccountRequestsByUsername($username, $dbr);
		$matchingRequests = array_filter($requests, function($accountRequest) use ($password) { return $this->passwordFactory->newFromCipherText($accountRequest->passwordHash)->verify($password); });

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

		if (empty($emailToken) || !hash_equals($accountRequest->emailToken, $emailToken) || $accountRequest->emailExpiry <= wfTimestamp(TS_MW)) {
			$output->showErrorPage('error', 'scratch-confirmaccount-invalid-email-token', $requestURL);
			cancelTransaction($dbw, 'scratch-confirmaccount-submit-confirm-email');
			return;
		}
		
		if ($accountRequest->status === 'accepted') {
			$output->showErrorPage('error', 'scratch-confirmaccount-already-accepted-email');
			cancelTransaction($dbw, 'scratch-confirmaccount-submit-confirm-email');
			return;
		}
		
		setRequestEmailConfirmed($requestId, $dbw);
		$output->addHTML(Html::element('p', [], wfMessage('scratch-confirmaccount-email-confirmed')->parse()));
		
		commitTransaction($dbw, 'scratch-confirmaccount-submit-confirm-email');
	}

	function handleFindRequestFormSubmission() {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$session = $request->getSession();
		
		$dbr = getReadOnlyDatabase();
		
		$matchingRequests = $this->handleAuthenticationFormSubmission($dbr);
		if ($matchingRequests === null) return;

		$requestId = $matchingRequests[0]->id;

		//mark that the user can view the request attached to their username and redirect them to it
		authenticateForViewingRequest($requestId, $session);
		$output->redirect(SpecialPage::getTitleFor('RequestAccount', $requestId)->getFullURL());
	}

	function handleSendConfirmEmailSubmission() {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$session = $request->getSession();
		
		$requestId = $request->getText('requestid');
		if (!$session->exists('requestId') || $session->get('requestId') !== $requestId) {
			$output->showErrorPage('error', 'scratch-confirmaccount-findrequest-nopermission');
			return;
		}
		
		$dbw = getTransactableDatabase('scratch-confirmaccount-send-confirm-email');
		
		$accountRequest = getAccountRequestById($requestId, $dbw);
		if ($accountRequest->status === 'accepted') {
			cancelTransaction($dbw, 'scratch-confirmaccount-send-confirm-email');
			$output->showErrorPage('error', 'scratch-confirmaccount-already-accepted-email');
			return;
		}
		$sentEmail = sendConfirmationEmail($this->getUser(), $this->getLanguage(), $requestId, $dbw);
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
		
		$output->setPageTitle( $this->msg( "requestaccount" )->escaped() );
		
		$output->addModules('ext.scratchConfirmAccount.js');
		$output->addModuleStyles('ext.scratchConfirmAccount.css');

		$this->setHeaders();
		
		$this->checkReadOnly();

		if ($request->wasPosted()) {
			return $this->handleFormSubmission();
		} else if ($par === null || $par === '') {
			return $this->requestForm();
		} else if (strpos($par, 'ConfirmEmail/') === 0) { // starts with ConfirmEmail/
			return confirmEmailPage(
				explode('/', $par)[1], // ConfirmEmail/TOKENPARTHERE
				$this,
				$request->getSession());
		} else if ($par === 'FindRequest') {
			return findRequestPage($this, $request->getSession());
		} else if (ctype_digit($par)) {
			return requestPage($par, 'user', $this, $request->getSession());
		} else {
			$output->showErrorPage('error', 'scratch-confirmaccount-nosuchrequest');
		}
	}
}
