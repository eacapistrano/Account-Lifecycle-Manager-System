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
            $user = new Directory\User();
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
public function unsuspend(Request $request, StudentAccountLifecycleService $lifecycle)
{
    $max = (int) config('security.bulk_account_ids_max', 500);

    $data = $request->validate([
        'account_ids' => ['sometimes', 'array', 'max:'.$max],
        'account_ids.*' => ['required_with:account_ids', 'string', 'max:255'],
        'google_ids' => ['sometimes', 'array', 'max:'.$max],
        'google_ids.*' => ['required_with:google_ids', 'string', 'max:255'],
    ]);

    $ids = $data['account_ids'] ?? $data['google_ids'] ?? [];

    if ($ids === []) {
        throw ValidationException::withMessages([
            'account_ids' => ['The account ids field is required.'],
        ]);
    }

    $failures = [];

    foreach ($ids as $accountId) {
        try {
            $student = \App\Models\Student::where('external_account_id', $accountId)
                ->orWhere('primary_email', $accountId)
                ->firstOrFail();

            $lifecycle->unsuspendByPrimaryEmail($student->primary_email);

        } catch (\Throwable $e) {
            $failures[] = [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ];
        }
    }

    return response()->json([
        'queued' => false,
        'action' => 'unsuspend',
        'count' => count($ids),
        'ok' => count($ids) - count($failures),
        'failed' => count($failures),
        'failures' => $failures,
    ], 200);
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

        $client = new Client();
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
            'Google Workspace credentials file not found. Checked: ' . $path . ' and ' . $relativeToBase
        );
    }
}