<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Policy;
use App\Services\StudentGraduationPolicyEvaluator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PolicyController extends Controller
{
    public function __construct(
        private StudentGraduationPolicyEvaluator $graduationPreview,
    ) {}

    public function index(Request $request)
    {
        $data = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json([
            'data' => Policy::query()->orderByDesc('id')->paginate($data['per_page'] ?? 25),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $policy = Policy::query()->create($data);

        return response()->json($policy, 201);
    }

    public function show(Policy $policy)
    {
        return response()->json($policy);
    }

    public function update(Request $request, Policy $policy)
    {
        $data = $this->validated($request, partial: true);
        $policy->fill($data);
        $policy->save();

        return response()->json($policy);
    }

    public function destroy(Policy $policy)
    {
        $policy->delete();

        return response()->json(['ok' => true]);
    }

    public function nextRun(Policy $policy)
    {
        $next = $policy->execution_at instanceof Carbon
            ? $policy->execution_at->toIso8601String()
            : null;

        $payload = [
            'policy_id' => $policy->id,
            'execution_at' => $next,
            'cron_expression' => $policy->cron_expression,
            'last_evaluated_at' => $policy->last_evaluated_at?->toIso8601String(),
            'last_status' => $policy->last_status,
            'policy_type' => $this->resolvePolicyType($policy),
        ];

        if ($this->resolvePolicyType($policy) === 'student_graduation') {
            $rules = $policy->rule_json ?? [];
            $payload['graduation_preview'] = $this->graduationPreview->previewCounts($rules);
            $payload['suspend_after_days'] = (int) ($rules['suspend_after_days'] ?? config('automation.graduation.suspend_after_days', 60));
            $payload['warning_days_before_suspend'] = (int) ($rules['warning_days_before_suspend'] ?? config('automation.graduation.warning_days_before_suspend', 14));
        }

        return response()->json($payload);
    }

    protected function validated(Request $request, bool $partial = false): array
    {
        $rules = [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'action' => [$partial ? 'sometimes' : 'required', Rule::in(['suspend', 'delete'])],
            'rule_json' => [$partial ? 'sometimes' : 'required', 'array'],
            'execution_at' => ['nullable', 'date'],
            'cron_expression' => ['nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        $data = $request->validate($rules);

        if (array_key_exists('rule_json', $data)) {
            $this->assertValidRuleJson($data['rule_json'], $data['action'] ?? null);
        }

        if (($data['rule_json']['type'] ?? null) === 'student_graduation') {
            $data['action'] = 'suspend';
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $ruleJson
     */
    private function assertValidRuleJson(array $ruleJson, ?string $action): void
    {
        $type = $ruleJson['type'] ?? 'scope';

        if ($type === 'student_graduation') {
            $suspendAfter = (int) ($ruleJson['suspend_after_days'] ?? config('automation.graduation.suspend_after_days', 60));
            $warningBefore = (int) ($ruleJson['warning_days_before_suspend'] ?? config('automation.graduation.warning_days_before_suspend', 14));

            if ($suspendAfter < 1) {
                throw ValidationException::withMessages([
                    'rule_json' => ['suspend_after_days must be at least 1.'],
                ]);
            }

            if ($warningBefore < 0 || $warningBefore >= $suspendAfter) {
                throw ValidationException::withMessages([
                    'rule_json' => ['warning_days_before_suspend must be between 0 and suspend_after_days.'],
                ]);
            }

            if ($action !== null && $action !== 'suspend') {
                throw ValidationException::withMessages([
                    'action' => ['Graduation policies must use the suspend action.'],
                ]);
            }

            return;
        }

        $department = isset($ruleJson['department']) ? trim((string) $ruleJson['department']) : '';
        $schoolYear = isset($ruleJson['school_year']) ? trim((string) $ruleJson['school_year']) : '';

        if ($department === '' && $schoolYear === '') {
            throw ValidationException::withMessages([
                'rule_json' => ['Specify at least one of department or school_year so the policy scope is not empty.'],
            ]);
        }
    }

    private function resolvePolicyType(Policy $policy): string
    {
        $type = $policy->rule_json['type'] ?? 'scope';

        return is_string($type) ? $type : 'scope';
    }
}
