<?php

namespace ScratchConfirmAccount\Hook;

interface AccountRequestActionHook {
    public function onAccountRequestAction($accountRequest, string $action, ?string $actorUsername, string $comment);
}