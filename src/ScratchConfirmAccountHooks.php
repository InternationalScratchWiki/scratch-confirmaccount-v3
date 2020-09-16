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
		
		//don't show if the user doesn't have permission to create accounts
		if (!$wgUser->isAllowed('createaccount')) {
			return true;
		}
		
		//only show on Special:RecentChanges
		if(!$out->getContext()->getTitle()->isSpecial('Recentchanges')){
			return true;
		}
		
		if (!$wg_scratch_confirmaccount_reqCountText) { //if we don't have the number of requests cached, then retrieve it
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
