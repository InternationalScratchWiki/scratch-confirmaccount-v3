<?php

namespace ScratchConfirmAccount\Hook;

interface AccountRequestSubmittedHook {
	public function onAccountRequestSubmitted(int $requestId, string $username, string $requestNotes);
}
