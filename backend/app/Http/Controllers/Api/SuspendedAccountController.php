<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;

class SuspendedAccountController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'priority_only' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $q = Student::query()
            ->where('suspended', true)
            ->orderByDesc('priority_flag')
            ->orderBy('deletion_scheduled_at');

        if (! empty($data['priority_only'])) {
            $q->where('priority_flag', true);
        }

        return response()->json(['data' => $q->paginate($data['per_page'] ?? 50)]);
    }

    public function updatePriority(Request $request, Student $student)
    {
        if (! $student->suspended) {
            return response()->json(['message' => 'Student is not suspended.'], 422);
        }

        $data = $request->validate([
            'priority_flag' => ['sometimes', 'boolean'],
            'compliance_notes' => ['nullable', 'string', 'max:2000'],
            'deletion_scheduled_at' => ['nullable', 'date'],
        ]);

        if (array_key_exists('priority_flag', $data)) {
            $student->priority_flag = $data['priority_flag'];
        }
        if (array_key_exists('compliance_notes', $data)) {
            $student->compliance_notes = $data['compliance_notes'];
        }
        if (array_key_exists('deletion_scheduled_at', $data)) {
            $student->deletion_scheduled_at = $data['deletion_scheduled_at'];
        }

        $student->save();

        return response()->json($student);
    }
}
