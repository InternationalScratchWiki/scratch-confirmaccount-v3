<?php
require_once __DIR__ . '/database/DatabaseInteractions.php';
require_once __DIR__ . '/subpages/RequestPage.php';

class ScratchConfirmAccountPreAuthenticationProvider extends MediaWiki\Auth\AbstractPreAuthenticationProvider {
	private PasswordFactory $passwordFactory;

	public function __construct(PasswordFactory $passwordFactory) {
		$this->passwordFactory = $passwordFactory;
	}
	
	public function testForAuthentication(array $reqs) {
		foreach ($reqs as $authRequest) {
			if (get_class($authRequest) === 'MediaWiki\Auth\PasswordAuthenticationRequest') {
				//ignore any non-password authentication requests (like "remember me" or whatever)
				$response = $this->handlePasswordAuthenticationRequest($authRequest);
				
				if ($response) {
					return $response;
				}
			}
		}
		
		return Status::newGood(); //return a "Good" response (i.e. nothing to see here) if none of the requests resulted in any explicit response
	}
	
	private function handlePasswordAuthenticationRequest(MediaWiki\Auth\PasswordAuthenticationRequest $authRequest) : ?Status {
		$dbr = getReadOnlyDatabase();
		
		//find active but non-accepted requests under the username that have the password specified in the request
		$activeRequestsUnderUsername = array_filter(getAccountRequestsByUsername($authRequest->username, $dbr), 
			function (AccountRequest $accountRequest) use ($authRequest) { 
				return !$accountRequest->isExpired() && $accountRequest->status !== 'accepted' && $this->passwordFactory->newFromCipherText($accountRequest->passwordHash)->verify($authRequest->password); 
			});
		
		if (!empty($activeRequestsUnderUsername)) {
			//if there are any active requests, then authenticate the user for viewing that request (since they already specified the right password when logging in)
			//and give them a link to the request
			$activeRequest = array_values($activeRequestsUnderUsername)[0];
			
			$session = $this->manager->getRequest()->getSession();
			authenticateForViewingRequest($activeRequest->id, $session);
			
			return Status::newFatal('scratch-confirmaccount-attempted-login-with-active-request', $activeRequest->id);
		}
		
		return null;
	}
}
