<?php
require_once __DIR__ . '/database/DatabaseInteractions.php';

class ScratchConfirmAccountHooks {
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$updater->addExtensionTable('scratch_accountrequest', __DIR__ . '/../sql/requests.sql');
		$updater->addExtensionTable('scratch_accountrequest_block', __DIR__ . '/../sql/blocks.sql');
		$updater->addExtensionTable('scratch_accountrequest_history', __DIR__ . '/../sql/request_handling_history.sql');
	}

	public static function pendingRequestNotice(OutputPage &$out, Skin &$skin) {
		global $wgUser;

		//don't show if the user doesn't have permission to create accounts
		if (!$wgUser->isAllowed('createaccount')) {
			return true;
		}

		//only show on Special:RecentChanges
		if(!$out->getContext()->getTitle()->isSpecial('Recentchanges')){
			return true;
		}

		$out->addModules('ext.scratchConfirmAccount');
		$reqCounts = getNumberOfRequestsByStatus(['new'])['new'];
		$reqCounts += getNumberOfRequestsByStatusAndUser(['awaiting-admin'], $wgUser->getId())['awaiting-admin'];
		if ($reqCounts > 0) {
			$reqCountText = Html::openElement('div', [
				'class' => 'mw-scratch-confirmaccount-rc-awaiting'
			]);
			$reqCountText .= wfMessage('scratch-confirmaccount-requests-awaiting')->rawParams(
				Html::rawElement(
					'a',
					[
						'href' => SpecialPage::getTitleFor( 'ConfirmAccounts' )->getLocalURL()
					],
					wfMessage(
						'scratch-confirmaccount-requests-awaiting-linktext',
						$reqCounts
					)->parse()
				),
				$reqCounts,
				wfMessage(
					'scratch-confirmaccount-requests-awaiting-isare',
					$reqCounts
				)->parse()
			)->parse();
			$reqCountText .= Html::closeElement('div');
			$out->prependHTML($reqCountText);
		}

		return true;
	}
}
