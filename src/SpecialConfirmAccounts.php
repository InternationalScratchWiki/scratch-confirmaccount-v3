<?php
class SpecialConfirmAccounts extends SpecialPage {
	function __construct() {
		parent::__construct( 'ConfirmAccounts' );
	}
	
	function getGroupName() {
		return 'users';
	}
	
	function execute( $par ) {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$this->setHeaders();
		
		//check permissions
		$user = $this->getUser();

		if (!$user->isAllowed('confirmaccount')) {
			throw new PermissionsError('confirmaccount');
		}

		# Do stuff
		# ...
		$wikitext = 'Hello world!';
		$output->addWikiTextAsInterface( $wikitext );
	}
}