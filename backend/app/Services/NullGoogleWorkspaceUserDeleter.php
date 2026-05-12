<?php

namespace App\Services;

use App\Contracts\GoogleWorkspaceUserDeleter;

class NullGoogleWorkspaceUserDeleter implements GoogleWorkspaceUserDeleter
{
    public function deleteUser(string $userKey): void {}
}
