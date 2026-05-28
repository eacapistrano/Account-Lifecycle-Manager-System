<?php

namespace App\Services;

use App\Contracts\GoogleWorkspaceUserSuspender;
use Google\Client;
use Google\Service\Directory;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GoogleWorkspaceDirectoryUserSuspender implements GoogleWorkspaceUserSuspender
{
    private ?Directory $directory = null;

    public function __construct(
        private string $credentialsPath,
        private string $impersonateEmail,
        private array $scopes,
    ) {}

    public function setSuspended(string $userKey, bool $suspended): void
    {
        if ($userKey === '') {
            throw new RuntimeException('Google Workspace user key cannot be empty.');
        }

        try {
            $user = new Directory\User;
            $user->setSuspended($suspended);

            $this->directoryService()->users->patch($userKey, $user);

            Log::info('Successfully patched Google Directory user', [
                'userKey' => $userKey,
                'suspended' => $suspended,
            ]);
        } catch (GoogleServiceException $e) {
            Log::error('Google API error when setting suspended status', [
                'userKey' => $userKey,
                'suspended' => $suspended,
                'errorMessage' => $e->getMessage(),
            ]);

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
            throw new RuntimeException('GOOGLE_WORKSPACE_IMPERSONATE_EMAIL is required.');
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
            throw new RuntimeException('GOOGLE_WORKSPACE_CREDENTIALS_PATH is required.');
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
