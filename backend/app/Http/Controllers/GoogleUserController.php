<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\GoogleWorkspaceService;

class GoogleUserController extends Controller
{
    protected $googleService;

    public function __construct(GoogleWorkspaceService $googleService)
    {
        $this->googleService = $googleService;
    }

    public function deleteUser(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $this->googleService->deleteUser(
            $request->email
        );

        return response()->json([
            'message' => 'User deleted successfully'
        ]);
    }
}
