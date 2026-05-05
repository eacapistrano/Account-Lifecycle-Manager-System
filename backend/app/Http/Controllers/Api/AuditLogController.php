<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'module' => ['nullable', 'string', 'max:64'],
            'action' => ['nullable', 'string', 'max:128'],
            'actor_email' => ['nullable', 'email', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $q = $this->filteredAuditQuery($data)->with('actor:id,name,email');

        return response()->json(['data' => $q->paginate($data['per_page'] ?? 50)]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'module' => ['nullable', 'string', 'max:64'],
            'action' => ['nullable', 'string', 'max:128'],
            'actor_email' => ['nullable', 'email', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $q = $this->filteredAuditQuery($data)->with('actor:id,email');

        $filename = 'audit-export-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($q): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'created_at', 'actor_email', 'module', 'action', 'target_account_id', 'success', 'correlation_id']);
            $q->chunk(500, function ($rows) use ($out): void {
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row->id,
                        $row->created_at?->toIso8601String(),
                        $row->actor?->email,
                        $row->module,
                        $row->action,
                        $row->target_account_id,
                        $row->success ? '1' : '0',
                        $row->correlation_id,
                    ]);
                }
            });
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportPdf(Request $request)
    {
        $data = $request->validate([
            'module' => ['nullable', 'string', 'max:64'],
            'action' => ['nullable', 'string', 'max:128'],
            'actor_email' => ['nullable', 'email', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $limit = $data['limit'] ?? 100;

        $q = $this->filteredAuditQuery($data)
            ->with('actor:id,name,email')
            ->limit($limit);

        $events = $q->get();

        $pdf = Pdf::loadView('audit.pdf', ['events' => $events]);

        return $pdf->download('audit-export-'.now()->format('Ymd-His').'.pdf');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredAuditQuery(array $filters): Builder
    {
        $q = AuditEvent::query()->latest('id');

        if (! empty($filters['module'])) {
            $q->where('module', $filters['module']);
        }

        if (! empty($filters['action'])) {
            $q->where('action', 'like', '%'.$filters['action'].'%');
        }

        if (! empty($filters['actor_email'])) {
            $q->whereHas('actor', function (Builder $subQuery) use ($filters): void {
                $subQuery->where('email', $filters['actor_email']);
            });
        }

        if (! empty($filters['from'])) {
            $q->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $q->where('created_at', '<=', $filters['to'].' 23:59:59');
        }

        return $q;
    }
}
