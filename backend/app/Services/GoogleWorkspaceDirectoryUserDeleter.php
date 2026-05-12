<?php

namespace App\Services;

use App\Contracts\GoogleWorkspaceUserDeleter;
use Google\Client;
use Google\Service\Directory;
use Google\Service\Exception as GoogleServiceException;
use RuntimeException;

class GoogleWorkspaceDirectoryUserDeleter implements GoogleWorkspaceUserDeleter
{
    private ?Directory $directory = null;

    /**
     * @param  array<int, string>  $scopes
     */
    public function __construct(
        private string $credentialsPath,
        private string $impersonateEmail,
        private array $scopes,
    ) {}

    public function deleteUser(string $userKey): void
    {
        if ($userKey === '') {
            throw new RuntimeException('Google Workspace user key cannot be empty.');
        }

        try {
            $this->directoryService()->users->delete($userKey);
        } catch (GoogleServiceException $e) {
            if ($e->getCode() === 404) {
                return;
            }

            throw $e;
        }
    }

    private function directoryService(): Directory
    {
        if ($this->directory instanceof Directory) {
            return $this->directory;
        }

        $resolvedPath = $this->resolveCredentialsPath();
        if ($this->impersonateEmail === '') {
            throw new RuntimeException('GOOGLE_WORKSPACE_IMPERSONATE_EMAIL is required when Google Workspace deletion is enabled.');
        }

        $client = new Client;
        $client->setAuthConfig($resolvedPath);
        $client->setScopes($this->scopes);
        $client->setSubject($this->impersonateEmail);

        $this->directory = new Directory($client);

        return $this->directory;
    }

    private function resolveCredentialsPath(): string
    {
        $path = $this->credentialsPath;
        if ($path === '') {
            throw new RuntimeException('GOOGLE_WORKSPACE_CREDENTIALS_PATH is required when Google Workspace deletion is enabled.');
        }

        if (is_file($path)) {
            return $path;
        }

        $relativeToBase = base_path($path);
        if (is_file($relativeToBase)) {
            return $relativeToBase;
        }

        throw new RuntimeException(
            'Google Workspace credentials file not found. Checked: '.$path.' and '.$relativeToBase
        );
    }
}
