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
	function __construct() {
		parent::__construct( 'RequestAccount' );
	}

	function sanitizedPostData(&$request, &$session, &$out_error) {
		global $wgScratchAccountJoinedRequirement;
		$username = $request->getText('scratchusername');

		if ($username == '' || !ScratchVerification::isValidScratchUsername($username)) {
			$out_error = wfMessage('scratch-confirmaccount-invalid-username')->text();
			return;
		}

		$password = $request->getText('password');
		$password2 = $request->getText('password2');
		if ($password != $password2) {
			// It just compares two user input, no need to prevent timing attack
			$out_error = wfMessage('scratch-confirmaccount-incorrect-password')->text();
			return;
		}

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

		if (userExists($username)) {
			$out_error = wfMessage('scratch-confirmaccount-user-exists')->text();
			return;
		}

		if (hasActiveRequest($username)) {
			$out_error = wfMessage('scratch-confirmaccount-request-exists')->text();
			return;
		}

		if (ScratchVerification::topVerifCommenter(ScratchVerification::sessionVerificationCode($session)) != $username) {
			$out_error = wfMessage('scratch-confirmaccount-verif-missing', $username)->text();
			return;
		}

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

		$block = getSingleBlock($username);
		if ($block) {
			$out_error = wfMessage('scratch-confirmaccount-user-blocked', $block->reason)->text();
			// note : blocks are not publicly visible on scratch, so this needs to run after checking the verification code
			return;
		}

		$email = $request->getText('email');
		if ($email != '' && !Sanitizer::validateEmail($email)) {
			$out_error = wfMessage('scratch-confirmaccount-invalid-email')->text();
			return;
		}

		if($request->getText('agree') != "true"){
			$out_error = wfMessage('scratch-confirmaccount-disagree-tos')->text();
			return;
		}

		$request_notes = $request->getText('requestnotes');

		if($request_notes == ""){
			$out_error = wfMessage('scratch-confirmaccount-no-request-notes')->text();
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

	function formSectionHeader($name) {
		$form = Html::openElement('fieldset');
		$form .= Html::openElement('legend');
		$form .= $name;
		$form .= Html::closeElement('legend');

		return $form;
	}

	function formSectionFooter() {
		return Html::closeElement('fieldset');
	}

	function usernameAndVerificationArea(&$session, $request) {
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
			['class' => 'mw-scratch-confirmaccount-verifcode'],
			ScratchVerification::sessionVerificationCode($session)
		);
		$form .= Html::element(
			'button',
			[
				'class' => 'mw-scratch-confirmaccount-clickCopy',
				'onclick' => 'copyToClipboard(mw-scratch-confirmaccount-verifcode)',
				'id' => 'mw-scratch-confirmaccount-clickCopy'
			],
			'Copy to clipboard'
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
			['class' => 'mw-scratch-confirmaccount-textarea', 'name' => 'requestnotes'],
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

	function handleAccountRequestFormSubmission(&$request, &$output, &$session) {
		if (isCSRF($session, $request->getText('csrftoken'))) {
			return $this->requestForm($request, $output, $session, wfMessage('scratch-confirmaccount-csrf')->text());
		}

		//validate and sanitize the input
		$formData = $this->sanitizedPostData($request, $session, $error);
		if ($error != '') {
			return $this->requestForm($request, $output, $session, $error);
		}

		//now actually create the request and reset the verification code
		$requestId = createAccountRequest(
			$formData['username'],
			$formData['passwordHash'],
			$formData['requestnotes'],
			$formData['email'],
			$request->getIP()
		);
		$sentEmail = false;
		if ($formData['email']) {
			$sentEmail = sendConfirmationEmail($requestId);
		}
		ScratchVerification::generateNewCodeForSession($session);
		authenticateForViewingRequest($requestId, $session);

		$message = 'scratch-confirmaccount-success';
		if ($sentEmail) {
			$message = 'scratch-confirmaccount-success-email';
		}

		//and show the output
		$output->addHTML(Html::rawElement(
			'p',
			[],
			wfMessage(
				$message,
				$requestId
			)->parse()
		));
	}

	function handleFormSubmission(&$request, &$output, &$session) {
		if ($request->getText('action')) {
			handleRequestActionSubmission('user', $request, $output, $session);
		} else if ($request->getText('findRequest')) {
			$this->handleFindRequestFormSubmission($request, $output, $session);
		} else if ($request->getText('confirmEmail')) {
			$this->handleConfirmEmailFormSubmission($request, $output, $session);
		} else if ($request->getText('sendConfirmationEmail')) {
			$this->handleSendConfirmEmailSubmission($request, $output, $session);
		} else {
			$this->handleAccountRequestFormSubmission($request, $output, $session);
		}
	}

	function requestForm(&$request, &$output, &$session, $error = '') {
		$form = Html::openElement('form', [ 'method' => 'post', 'name' => 'requestaccount', 'action' => $this->getPageTitle()->getLocalUrl(), 'enctype' => 'multipart/form-data' ]);

		//display errors if there are any relevant
		if ($error != '') {
			$form .= Html::element('p', ['class' => 'error'], $error);
		}

		if ($this->getUser()->isRegistered()) {
			$form .= Html::rawElement('p', [], wfMessage('scratch-confirmaccount-logged-in')->parse());
		}

		$form .= Html::rawElement('p', [], wfMessage('scratch-confirmaccount-view-request')->parse());

		//form body
		$form .= $this->usernameAndVerificationArea($session, $request);
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

	function handleAuthenticationFormSubmission(&$request, &$output, &$session) {
		if (isCSRF($session, $request->getText('csrftoken'))) {
			$output->showErrorPage('error', 'scratch-confirmaccount-csrf');
			return;
		}

		$linkRenderer = $this->getLinkRenderer();

		$username = $request->getText('username');
		$password = $request->getText('password');

		//see if there are any requests with the given password
		$passwordFactory = MediaWikiServices::getInstance()->getPasswordFactory();
		$requests = getAccountRequestsByUsername($username);
		$matchingRequests = array_filter($requests, function($accountRequest) use ($passwordFactory, $password) { return $passwordFactory->newFromCipherText($accountRequest->passwordHash)->verify($password); });

		if (empty($matchingRequests)) {
			$output->showErrorPage('error', 'scratch-confirmaccount-findrequest-nomatch');
			return;
		}
		return $matchingRequests;
	}

	function handleConfirmEmailFormSubmission(&$request, &$output, &$session) {
		$matchingRequests = $this->handleAuthenticationFormSubmission($request, $output, $session);
		if ($matchingRequests === null) return;
		//mark that the user can view the request attached to their username and redirect them to it
		$accountRequest = $matchingRequests[0];
		$requestId = $accountRequest->id;
		authenticateForViewingRequest($requestId, $session);
		$emailToken = md5($request->getText('emailToken'));
		$requestURL = SpecialPage::getTitleFor('RequestAccount', $requestId)->getFullURL();
		if (empty($emailToken)) {
			$output->showErrorPage('error', 'scratch-confirmaccount-invalid-email-token', $requestURL);
			return;
		}

		if ($accountRequest->emailToken !== $emailToken || $accountRequest->emailExpiry <= wfTimestamp(TS_MW)) {
			$output->showErrorPage('error', 'scratch-confirmaccount-invalid-email-token', $requestURL);
			return;
		}
		if ($accountRequest->status == 'accepted') {
			$output->showErrorPage('error', 'scratch-confirmaccount-already-accepted-email');
			return;
		}
		setRequestEmailConfirmed($requestId);
		$output->addHTML(Html::element('p', [], wfMessage('scratch-confirmaccount-email-confirmed')->parse()));
	}

	function handleFindRequestFormSubmission(&$request, &$output, &$session) {
		$matchingRequests = $this->handleAuthenticationFormSubmission($request, $output, $session);
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
		$accountRequest = getAccountRequestById($requestId);
		if ($accountRequest->status == 'accepted') {
			$output->showErrorPage('error', 'scratch-confirmaccount-already-accepted-email');
			return;
		}
		$sentEmail = sendConfirmationEmail($requestId);
		if (!$sentEmail) {
			$output->showErrorPage('error', 'scratch-confirmaccount-email-unregistered');
			return;
		}
		$output->addHTML(Html::element('p', [], wfMessage('scratch-confirmaccount-email-resent')->text()));
	}

	function execute( $par ) {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$language = $this->getLanguage();
		$output->addModules('ext.scratchConfirmAccount');
		$session = $request->getSession();
		$this->setHeaders();

		if ($request->wasPosted()) {
			return $this->handleFormSubmission($request, $output, $session);
		} else if ($par == '') {
			return $this->requestForm($request, $output, $session);
		} else if (strpos($par, 'ConfirmEmail/') === 0) { // starts with ConfirmEmail/
			return confirmEmailPage(
				explode('/', $par)[1], // ConfirmEmail/TOKENPARTHERE
				$request, $output, $session);
		} else if ($par == 'FindRequest') {
			return findRequestPage($request, $output, $session);
		} else if (ctype_digit($par)) {
			return requestPage($par, 'user', $output, $this, $session, $language);
		} else {
			$output->showErrorPage('error', 'scratch-confirmaccount-nosuchrequest');
		}
	}
}
