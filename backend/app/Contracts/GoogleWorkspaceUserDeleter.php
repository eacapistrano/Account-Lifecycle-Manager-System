<?php

namespace App\Contracts;

interface GoogleWorkspaceUserDeleter
{
    /**
     * Permanently delete a Workspace user via Admin SDK Directory API users.delete.
     *
     * @param  string  $userKey  Primary email, alias email, or unique user id
     *
     * @see https://developers.google.com/admin-sdk/directory/v1/reference/users/delete
     */
    public function deleteUser(string $userKey): void;
}
