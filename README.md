Scratch ConfirmAccount V3

Rewrite of previous versions of ConfirmAccount which were based on [MediaWiki ConfirmAccount](https://mediawiki.org/wiki/Extension:ConfirmAccount) - this version is entirely custom-built to the needs of the International Scratch Wiki group including synchronization and verification through Scratch.

## Configs
- $wgScratchVerificationProjectAuthor - Author of the verification project (default: `ModShare`)
- $wgScratchVerificationProjectID - ID of the verification project (default: `10135908`)
- $wgScratchAccountRequestRejectCooldownDays - Days before rejected accounts can re-submit requests (default: `7`)
- $wgScratchAccountCheckDisallowNewScratcher - If set to true, disallow requests from New Scratchers (default: `false`)
- $wgScratchAccountJoinedRequirement - Scratch account's minimum age, in seconds (default: `0`)
- $wgAutoWelcomeNewUsers - If set to true, talk page is automatically created with welcome message

### Example
```php
$wgScratchAccountCheckDisallowNewScratcher = true; // New Scratchers cannot submit requests
$wgScratchAccountJoinedRequirement = 2 * 30 * 24 * 60 * 60; // Accounts must have been registered for 60 days (2 months)
```

## Previous Versions
[Version 1](https://github.com/jacob-g/swiki-confirmaccount)

[Version 2](https://github.com/InternationalScratchWiki/scratch-confirmaccount-v2)
