<?php

namespace App\Services;

use App\Contracts\GoogleWorkspaceUserSuspender;

class NullGoogleWorkspaceUserSuspender implements GoogleWorkspaceUserSuspender
{
    public function setSuspended(string $userKey, bool $suspended): void {}
}
