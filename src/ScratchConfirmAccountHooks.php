<?php
require_once __DIR__ . '/database/DatabaseInteractions.php';

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Hook\PersonalUrlsHook;
use MediaWiki\SpecialPage\Hook\AuthChangeFormFieldsHook;
use MediaWiki\MediaWikiServices;

class ScratchConfirmAccountHooks implements LoadExtensionSchemaUpdatesHook, BeforePageDisplayHook, GetPreferencesHook, PersonalUrlsHook, AuthChangeFormFieldsHook {
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$updater->addExtensionTable('scratch_accountrequest_request', __DIR__ . '/../sql/requests.sql');
		$updater->addExtensionTable('scratch_accountrequest_block', __DIR__ . '/../sql/blocks.sql');
		$updater->addExtensionTable('scratch_accountrequest_history', __DIR__ . '/../sql/request_handling_history.sql');
		$updater->addExtensionTable('scratch_accountrequest_requirements_bypass', __DIR__ . '/../sql/requirements_bypass.sql');
		$updater->addExtensionField('scratch_accountrequest_request', 'request_active_username', __DIR__ . '/../sql/requests_activeusername.sql');
		$updater->addExtensionField('scratch_accountrequest_block', 'block_expiration_timestamp', __DIR__ . '/../sql/block_expiration_timestamp.sql');
	}

	public function onBeforePageDisplay($out, $skin) : void {
		$user = $out->getUser();

		//don't show if the user doesn't have permission to create accounts
		if (!$user->isAllowed('createaccount')) {
			return;
		}

		//only show on Special:RecentChanges
		if(!$out->getContext()->getTitle()->isSpecial('Recentchanges')){
			return;
		}
		
		$dbr = getReadOnlyDatabase();

		$out->addModuleStyles('ext.scratchConfirmAccount.css');
		
		$reqCounts = getNumberOfRequestsByStatus(['new'], $dbr)['new'];
		$reqCounts += getNumberOfRequestsByStatusAndUser(['awaiting-admin'], $user->getId(), $dbr)['awaiting-admin'];
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
	}
	
	public function onGetPreferences($user, &$preferences) {
		//don't show if the user doesn't have permission to create accounts
		if (!$user->isAllowed('createaccount')) {
			return true;
		}
		
		$preferences['scratch-confirmaccount-open-scratch'] = [
			'type' => 'toggle',
			'label-message' => 'scratch-confirmaccount-open-scratch',
			'section' => 'rendering/advancedrendering'
		];
		return true;
	}
	
	public function onPersonalUrls(&$personal_urls, &$title, $skin) : void {
		# Add a link to Special:RequestAccount if a link exists for login
		if (isset($personal_urls['login'])) {
			$personal_urls['createaccount'] = [
				'text' => wfMessage('requestaccount')->text(),
				'href' => SpecialPage::getTitleFor('RequestAccount')->getLocalUrl()
			];
		}
	}
	
	public function onAuthChangeFormFields($request, $fieldInfo, &$formDescriptor, $action) {
		if ($action != 'login') return;
		$formDescriptor['requestAccount'] = [
			'type' => 'info',
			'raw' => true,
			'cssclass' => 'mw-form-related-link-container',
			'default' => wfMessage('scratch-confirmaccount-request-account-login-notice')->parse(),
			'weight' => -90
		];
		$formDescriptor['viewRequest'] = [
			'type' => 'info',
			'raw' => true,
			'cssclass' => 'mw-form-related-link-container',
			'default' => wfMessage('scratch-confirmaccount-view-request')->parse(),
			'weight' => 180
		];
		return true;
	}
}
