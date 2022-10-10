<?php

namespace ScratchConfirmAccount\Hook;

interface RequestedAccountCreatedHook {
    public function onRequestedAccountCreated($request, string $actorUsername);
}
