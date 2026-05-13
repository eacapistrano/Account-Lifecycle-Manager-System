<?php

namespace App\Services;

use Google\Client;
use Google\Service\Directory;

class GoogleWorkspaceService
{
    protected $service;

    public function __construct()
    {
        $client = new Client();

        $client->setAuthConfig(
            storage_path('app/google/service-account.json')
        );

        $client->setScopes([
            'https://www.googleapis.com/auth/admin.directory.user'
        ]);

        $client->setSubject('eacapistrano@ceu.edu.ph');

        $this->service = new Directory($client);
    }

    public function deleteUser($email)
    {
        return $this->service->users->delete($email);
    }
}