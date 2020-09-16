<?php
require_once __DIR__ . '/database/DatabaseInteractions.php';

class ScratchConfirmAccountHooks {
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$updater->addExtensionTable('scratch_accountrequest', __DIR__ . '/../sql/requests.sql');
		$updater->addExtensionTable('scratch_accountrequest_block', __DIR__ . '/../sql/blocks.sql');
		$updater->addExtensionTable('scratch_accountrequest_history', __DIR__ . '/../sql/request_handling_history.sql');
	}
	
	public static function pendingRequestNotice(OutputPage &$out, Skin &$skin) {
		global $wgUser, $wg_scratch_confirmaccount_reqCountText;
		
		if (!$wgUser->isAllowed('createaccount')) {
			return true;
		}
		
		if (!$wg_scratch_confirmaccount_reqCountText) {
			$reqCounts = getNumberOfRequestsByStatus(['new', 'awaiting-admin']);
			$nonZeroReqCounts = array_filter($reqCounts, function ($x) { return $x > 0; });
			
			$reqCountTexts = array_map(function ($status, $count) { return wfMessage('scratch-confirmaccount-waiting-request', wfMessage('scratch-confirmaccount-' . $status), $count); }, array_keys($nonZeroReqCounts), array_values($nonZeroReqCounts));
			
			$wg_scratch_confirmaccount_reqCountText = implode(', ', $reqCountTexts); //have some HTML here
		}
		
		if ($wg_scratch_confirmaccount_reqCountText != '') {
			$out->prependHTML($wg_scratch_confirmaccount_reqCountText);
		}
		
		return true;
	}
}