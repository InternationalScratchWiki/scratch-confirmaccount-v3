<?php
require_once __DIR__ . '/../database/DatabaseInteractions.php';

class RequirementsBypassPage {
    private $pageContext;

    function __construct(SpecialPage $pageContext) {
        $this->pageContext = $pageContext;
    }

    function handleFormSubmission() {
        $request = $request = $this->pageContext->getRequest();

        $dbw = getTransactableDatabase('scratch-confirmaccount-bypasses');

        if ($request->getText('bypassAddUsername')) {
            addUsernameRequirementsBypass($request->getText('bypassAddUsername'), $dbw);
        } else if ($request->getText('bypassRemoveUsername')) {
            removeUsernameRequirementsBypass($request->getText('bypassRemoveUsername'), $dbw);
        }

        commitTransaction($dbw, 'scratch-confirmaccount-bypasses');

        $this->render();
    }

    function showAddBypassForm() {
        $output = $this->pageContext->getOutput();
        $request = $this->pageContext->getRequest();
        
        $output->addHTML(
            new OOUI\FormLayout([
                'action' => SpecialPage::getTitleFor('ConfirmAccounts', 'bypasses')->getFullURL(), //TODO: use language key for subpage
                'method' => 'post',
                'items' => [
                    new OOUI\ActionFieldLayout(
                        new OOUI\TextInputWidget( [
                            'name' => 'bypassAddUsername',
                            'required' => true,
                            'value' => $request->getText('username')
                        ] ),
                        new OOUI\ButtonInputWidget([
                            'type' => 'submit',
                            'flags' => ['primary', 'progressive'],
                            'label' => wfMessage('scratch-confirmaccount-request-submit')->parse()
                        ])
                    )
                ],
            ])
        );
    }

    function showBypassesList() {
        $output = $this->pageContext->getOutput();

        $dbr = getReadOnlyDatabase();

        $bypassUsernames = getUsernameBypasses($dbr);

        $table = Html::openElement('table', [ 'class' => 'wikitable' ]);

        $table .= Html::openElement('tr');
        $table .= Html::element('th', [], wfMessage('scratch-confirmaccount-scratchusername'));
        $table .= Html::element('th', [], wfMessage('scratch-confirmaccount-actions'));
        $table .= Html::closeElement('tr');

        foreach ($bypassUsernames as $username) {
            $table .= Html::openElement('tr');

            $table .= Html::element('td', [], $username);

            $table .= Html::openElement('td');
            $table .= Html::openElement('form', ['action' => SpecialPage::getTitleFor('ConfirmAccounts', 'bypasses')->getFullURL(), 'method' => 'post']); //TODO: use localization key
            $table .= Html::element('input', ['type' => 'hidden', 'name' => 'bypassRemoveUsername', 'value' => $username]);
            $table .= Html::element('input', ['type' => 'submit', 'value' => 'Remove']);
            $table .= Html::closeElement('form');
            $table .= Html::closeElement('td');

            $table .= Html::closeElement('tr');
        }

        $table .= Html::closeElement('table');

        $output->addHTML($table);
    }

    function render() {
        $output = $this->pageContext->getOutput();

        $output->enableOOUI();

        $this->showAddBypassForm();
        $this->showBypassesList();
    }
}