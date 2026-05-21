<?php

namespace App\Contracts;

interface GoogleWorkspaceUserSuspender
{
    /**
     * Set Workspace user suspended state via Admin SDK Directory API users.patch.
     *
     * @param string $userKey Primary email, alias email, or unique user id
     */
    public function setSuspended(string $userKey, bool $suspended): void;
}
