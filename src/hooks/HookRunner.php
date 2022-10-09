<?php

namespace ScratchConfirmAccount\Hook;

use ScratchConfirmAccount\Hook\AccountRequestActionHook;
use ScratchConfirmAccount\Hook\AccountRequestSubmittedHook;
use ScratchConfirmAccount\Hook\RequestedAccountCreatedHook;
use MediaWiki\HookContainer\HookContainer;

class HookRunner implements AccountRequestActionHook, AccountRequestSubmittedHook, RequestedAccountCreatedHook
{
    private $container;

    public function __construct(HookContainer $container)
    {
        $this->container = $container;
    }

    public function onAccountRequestAction($accountRequest, string $action, ?string $actorUsername, string $comment)
    {
        return $this->container->run(
            'AccountRequestAction',
            [$accountRequest, $action, $actorUsername, $comment]
        );
    }

    public function onAccountRequestSubmitted(int $requestId, string $username, string $requestNotes)
    {
        return $this->container->run(
            'AccountRequestSubmitted',
            [$requestId, $username, $requestNotes]
        );
    }

    public function onRequestedAccountCreated($accountRequest, string $actorUsername)
    {
        return $this->container->run(
            'RequestedAccountCreated',
            [$accountRequest, $actorUsername]
        );
    }
}
