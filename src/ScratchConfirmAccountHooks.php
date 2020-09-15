<?php
class ScratchConfirmAccountHooks {
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$updater->addExtensionTable('scratch_accountrequest', __DIR__ . '/../sql/requests.sql');
		$updater->addExtensionTable('scratch_accountrequest_block', __DIR__ . '/../sql/blocks.sql');
		$updater->addExtensionTable('scratch_accountrequest_history', __DIR__ . '/../sql/request_handling_history.sql');
	}
}