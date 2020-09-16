<?php
require_once __DIR__ . '/verification/ScratchVerification.php';
require_once __DIR__ . '/database/DatabaseInteractions.php';
require_once __DIR__ . '/objects/AccountRequest.php';
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/RequestPage.php';

use MediaWiki\MediaWikiServices;

class SpecialRequestAccount extends SpecialPage {
	function __construct() {
		parent::__construct( 'RequestAccount' );
	}

	function sanitizedPostData(&$request, &$session, &$out_error) {
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
		

		$blockReason = getBlockReason($username);
		if ($blockReason) {
			$out_error = wfMessage('scratch-confirmaccount-user-blocked', $blockReason)->text();
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
		$form = $this->formSectionHeader(wfMessage('scratch-confirmaccount-usernameverification')->text());

		$form .= Html::openElement('p');
		$form .= Html::element(
			'label',
			['for' => 'scratch-confirmaccount-username'],
			wfMessage('scratch-confirmaccount-scratchusername')->text()
		);
		$form .= Html::element(
			'input',
			[
				'type' => 'text',
				'name' => 'scratchusername',
				'id' => 'scratch-confirmaccount-username',
				'value' => $request->getText('scratchusername')
			]
		);
		$form .= Html::closeElement('p');

        $form .= Html::openElement('p');
		$form .= Html::element(
			'label',
			['for' => 'scratch-confirmaccount-password'],
			wfMessage('scratch-confirmaccount-password')->text()
		);
		$form .= Html::element(
			'input',
			[
				'type' => 'password',
				'name' => 'password',
				'id' => 'scratch-confirmaccount-password',
				'value' => ''
			]
		);
		$form .= Html::closeElement('p');

        $form .= Html::openElement('p');
		$form .= Html::element(
			'label',
			['for' => 'scratch-confirmaccount-password2'],
			wfMessage('scratch-confirmaccount-password2')->text()
		);
		$form .= Html::element(
			'input',
			[
				'type' => 'password',
				'name' => 'password2',
				'id' => 'scratch-confirmaccount-password2',
				'value' => ''
			]
		);
		$form .= Html::closeElement('p');

		$form .= Html::openElement('p');
		$form .= Html::element(
			'label',
			['for' => 'scratch-confirmaccount-email'],
			wfMessage('scratch-confirmaccount-email')->text()
		);
		$form .= Html::element(
			'input',
			[
				'type' => 'email',
				'name' => 'email',
				'id' => 'scratch-confirmaccount-email',
				'value' => $request->getText('email')
			]
		);
		$form .= Html::closeElement('p');

		$form .= Html::rawElement(
			'p',
			[],
			wfMessage('scratch-confirmaccount-vercode-explanation', sprintf(ScratchVerification::PROJECT_LINK, wgScratchVerificationProjectID()))->parse()
		);
		$form .= Html::element(
			'p',
			['class' => 'mw-scratch-confirmaccount-verifcode'],
			ScratchVerification::sessionVerificationCode($session)
		);

		$form .= $this->formSectionFooter();

		return $form;
	}

	function requestNotesArea(&$request) {
		$form = $this->formSectionHeader(wfMessage('scratch-confirmaccount-requestnotes')->text());

		$form .= Html::element('p', [], wfMessage('scratch-confirmaccount-requestnotes-explanation')->parse());
		$form .= Html::element(
			'label',
			['for' => 'scratch-confirmaccount-requestnotes'],
			wfMessage('scratch-confirmaccount-requestnotes')->text()
		);
		$form .= Html::element('textarea', ['id' => 'scratch-confirmaccount-requestnotes', 'name' => 'requestnotes'], $request->getText('requestnotes'));

		$form .= Html::openElement('br');

		$form .= Html::element('input', [
			'type' => 'checkbox',
			'name' => 'agree',
			'value' => 'true',
			'id' => 'scratch-confirmaccount-agree'
		]);
		$form .= Html::element(
			'label',
			['for' => 'scratch-confirmaccount-agree'],
			wfMessage('scratch-confirmaccount-checkbox-agree')->text()
		);
		$form .= Html::openElement('br');

		$form .= $this->formSectionFooter();

		return $form;
	}

	function guidelinesArea() {
		return '';
	}

	function handleAccountRequestFormSubmission(&$request, &$output, &$session) {
		//validate and sanitize the input
		$formData = $this->sanitizedPostData($request, $session, $error);
		if ($error != '') {
			return $this->requestForm($request, $output, $session, $error);
		}

		//now actually create the request and reset the verification code
		createAccountRequest(
			$formData['username'],
			$formData['passwordHash'],
			$formData['requestnotes'],
			$formData['email'],
			$request->getIP()
		);
		ScratchVerification::generateNewCodeForSession($session);

		//and show the output
		$output->addHTML(Html::element(
			'p',
			[],
			wfMessage('scratch-confirmaccount-success')->text()
		));
	}

	function handleFormSubmission(&$request, &$output, &$session) {
		if ($request->getText('action')) {
			handleRequestActionSubmission('user', $request, $output, $session);
		} else if ($request->getText('findRequest')) {
			$this->handleFindRequestFormSubmission($request, $output, $session);
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

		//form body
		$form .= $this->usernameAndVerificationArea($session, $request);
		$form .= $this->requestNotesArea($request);
		$form .= $this->guidelinesArea();

		$form .= Html::element('input',
			[
				'type' => 'submit',
				'value' => wfMessage('scratch-confirmaccount-request-submit')->parse()
			]
		);

		$form .= Html::closeElement('form');

		$output->addHTML($form);
	}

	function handleFindRequestFormSubmission(&$request, &$output, &$session) {
		$linkRenderer = $this->getLinkRenderer();
		
		$username = $request->getText('username');
		$password = $request->getText('password');
		
		$passwordFactory = MediaWikiServices::getInstance()->getPasswordFactory();
		
		$requests = getAccountRequestsByUsername($username);
		$matchingRequests = array_filter($requests, function($accountRequest) use ($passwordFactory, $password) { return $passwordFactory->newFromCipherText($accountRequest->passwordHash)->verify($password); });
		
		if (empty($matchingRequests)) {
			$output->addHTML('no matching requests');
			return;
		}
		
		$requestId = $matchingRequests[0]->id;
		
		authenticateForViewingRequest($requestId, $session);
		
		$output->addHTML('<p>' . $linkRenderer->makeKnownLink(
			SpecialPage::getTitleFor('RequestAccount', $requestId),
			'See your request'
		) . '</p>');
	}

	function execute( $par ) {
		$request = $this->getRequest();
		$output = $this->getOutput();
        $output->addModules('ext.scratchConfirmAccount');
		$session = $request->getSession();
		$this->setHeaders();

		if ($request->wasPosted()) {
			return $this->handleFormSubmission($request, $output, $session);
		} else if ($par == '') {
			return $this->requestForm($request, $output, $session);
		} else if ($par == 'FindRequest') {
			return findRequestPage($request, $output, $session);
		} else if (ctype_digit($par)) {
			return requestPage($par, 'user', $output, $this, $session);
		} else {
			$output->showErrorPage('error', 'scratch-confirmaccount-nosuchrequest');
		}
	}
}
