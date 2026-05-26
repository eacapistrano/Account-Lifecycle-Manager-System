import { useCallback, useEffect, useMemo, useState } from "react";
import { useAuth } from "../../../auth/useAuth";
import { apiRequest } from "../../../lib/api";
import { AutomationQueuePanel } from "./AutomationQueuePanel";
import { PolicyEditorModal } from "./PolicyEditorModal";
import type {
  AutomationQueueResponse,
  PoliciesIndexResponse,
  PolicyNextRunResponse,
  PolicyRow,
} from "./policyTypes";

function scopeLabel(ruleJson: Record<string, unknown>): string {
  if (ruleJson.type === "student_graduation") {
    const days = typeof ruleJson.suspend_after_days === "number" ? ruleJson.suspend_after_days : 60;
    const warn = typeof ruleJson.warning_days_before_suspend === "number" ? ruleJson.warning_days_before_suspend : 14;
    const status = typeof ruleJson.graduation_status === "string" ? ruleJson.graduation_status : "graduated";
    return `Graduation · ${status} · suspend +${days}d · warn −${warn}d`;
  }

  const dept = typeof ruleJson.department === "string" ? ruleJson.department : "";
  const year = typeof ruleJson.school_year === "string" ? ruleJson.school_year : "";
  const parts: string[] = [];
  if (dept) {
    parts.push(`Dept ${dept}`);
  }
  if (year) {
    parts.push(`Year ${year}`);
  }
  return parts.length ? parts.join(" · ") : "—";
}

export function PolicyExecutionWorkspace() {
  const { hasPermission } = useAuth();
  const canMutate = hasPermission("policy.write");

  const [rows, setRows] = useState<PolicyRow[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState("");

  const [editorOpen, setEditorOpen] = useState(false);
  const [editorPolicy, setEditorPolicy] = useState<PolicyRow | null>(null);
  const [isSaving, setIsSaving] = useState(false);

  const [deleteTarget, setDeleteTarget] = useState<PolicyRow | null>(null);
  const [preview, setPreview] = useState<{ title: string; payload: PolicyNextRunResponse } | null>(null);
  const [queueData, setQueueData] = useState<AutomationQueueResponse | null>(null);
  const [isDispatching, setIsDispatching] = useState(false);
  const [queueRefreshToken, setQueueRefreshToken] = useState(0);

  const load = useCallback(async () => {
    setError("");
    setIsLoading(true);
    try {
      const query = new URLSearchParams({ page: String(page), per_page: "25" });
      const payload = await apiRequest<PoliciesIndexResponse>(`/policies?${query.toString()}`);
      setRows(payload.data.data);
      setLastPage(Math.max(1, payload.data.last_page));
      setTotal(payload.data.total);
    } catch (e) {
      setRows([]);
      setError(e instanceof Error ? e.message : "Failed to load policies.");
    } finally {
      setIsLoading(false);
    }
  }, [page]);

  useEffect(() => {
    void load();
  }, [load]);

  const loadQueue = useCallback(async () => {
    try {
      const payload = await apiRequest<AutomationQueueResponse>("/automation/queue");
      setQueueData(payload);
    } catch {
      setQueueData(null);
    }
  }, []);

  useEffect(() => {
    void loadQueue();
  }, [loadQueue, queueRefreshToken]);

  async function handleRunAllPolicies() {
    setError("");
    setIsDispatching(true);
    try {
      await apiRequest("/automation/queue/dispatch", {
        method: "POST",
        body: JSON.stringify({ task: "policy_evaluation" }),
      });
      setQueueRefreshToken((current) => current + 1);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to run policies now.");
    } finally {
      setIsDispatching(false);
    }
  }

  async function handleNextRun(policy: PolicyRow) {
    setError("");
    try {
      const payload = await apiRequest<PolicyNextRunResponse>(`/policies/${policy.id}/next-run`);
      setPreview({ title: policy.name, payload });
    } catch (e) {
      setError(e instanceof Error ? e.message : "Next run lookup failed.");
    }
  }

  async function confirmDelete() {
    if (!deleteTarget) {
      return;
    }
    setError("");
    setIsSaving(true);
    try {
      await apiRequest(`/policies/${deleteTarget.id}`, { method: "DELETE" });
      setDeleteTarget(null);
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Delete failed.");
    } finally {
      setIsSaving(false);
    }
  }

  function openCreate() {
    setEditorPolicy(null);
    setEditorOpen(true);
  }

  function openEdit(row: PolicyRow) {
    setEditorPolicy(row);
    setEditorOpen(true);
  }

  const nextPolicySchedule = queueData?.schedules.find((task) => task.key === "policy_evaluation");
  const nextExecutionLabel = nextPolicySchedule ? `Next run window: ${nextPolicySchedule.name}` : "Next automation window";
  const scheduleHint = nextPolicySchedule
    ? `${nextPolicySchedule.cron} · ${nextPolicySchedule.description}`
    : "Scheduled policy evaluation is not configured.";

  const policyCards = useMemo(
    () =>
      rows.map((row) => {
        const isGraduation = row.rule_json.type === "student_graduation";
        const suspendDays = typeof row.rule_json.suspend_after_days === "number" ? row.rule_json.suspend_after_days : 0;
        const deleteDays = typeof row.rule_json.permanent_delete_after_days === "number" ? row.rule_json.permanent_delete_after_days : 0;
        const warningDays = typeof row.rule_json.warning_days_before_suspend === "number" ? row.rule_json.warning_days_before_suspend : 0;
        const retentionDays = typeof row.rule_json.data_retention_days === "number" ? row.rule_json.data_retention_days : 60;
        const immediateFlag = row.rule_json.immediate === true;

        return (
          <article key={row.id} className="policy-target-card">
            <div className="policy-card-header">
              <div className="policy-card-title-group">
                <h3>{row.name}</h3>
                <p className="hint">{scopeLabel(row.rule_json)}</p>
              </div>
              <div className="policy-card-state">
                <span className={`status-pill ${row.is_active ? "status-pill-running" : "status-pill-failed"}`}>
                  {row.is_active ? "Active" : "Inactive"}
                </span>
              </div>
            </div>

            <div className="policy-card-body">
              <div className="policy-card-grid">
                <div className="policy-card-detail">
                  <p className="policy-card-label">{isGraduation ? "Suspension Phase" : "Immediate Suspension"}</p>
                  <strong>{isGraduation ? `${suspendDays || 30} days` : immediateFlag ? "Immediate" : "Scheduled"}</strong>
                </div>
                <div className="policy-card-detail">
                  <p className="policy-card-label">{isGraduation ? "Permanent Deletion Phase" : "Data Retention Period"}</p>
                  <strong>{isGraduation ? `${deleteDays || 365} days` : `${retentionDays} days`}</strong>
                </div>
                {isGraduation ? (
                  <div className="policy-card-detail">
                    <p className="policy-card-label">Warning window</p>
                    <strong>{warningDays || 14} days before suspend</strong>
                  </div>
                ) : null}
              </div>
            </div>

            <div className="policy-card-footer">
              <div className="policy-card-meta">
                <p className="hint">Last evaluated {row.last_evaluated_at ? new Date(row.last_evaluated_at).toLocaleDateString() : "—"}</p>
                <p className="hint">{row.hold_reason ?? "No hold reason set."}</p>
              </div>
              <div className="policy-card-actions">
                <button type="button" className="secondary slim-button" onClick={() => void handleNextRun(row)} disabled={isLoading || isDispatching}>
                  Next run
                </button>
                {canMutate ? (
                  <>
                    <button type="button" className="secondary slim-button" onClick={() => openEdit(row)} disabled={isLoading || isDispatching}>
                      Edit
                    </button>
                    <button type="button" className="danger-button slim-button" onClick={() => setDeleteTarget(row)} disabled={isLoading || isDispatching}>
                      Delete
                    </button>
                  </>
                ) : null}
              </div>
            </div>
          </article>
        );
      }),
    [canMutate, handleNextRun, isDispatching, isLoading, openEdit, rows]
  );

  return (
    <section className="card stack module-page policy-execution-page">
      <header className="module-page-header policy-execution-header">
        <div>
          <h1>Policy Execution Settings</h1>
          <p className="hint">
            Define automated workflows for lifecycle management. Configure how and when accounts are suspended or permanently deleted based on status changes.
          </p>
        </div>
        <div className="policy-execution-actions">
          <button type="button" onClick={() => void handleRunAllPolicies()} disabled={isDispatching || isLoading}>
            {isDispatching ? "Running…" : "Run All Policies Now"}
          </button>
        </div>
      </header>

      {error ? <p className="toast toast-error">{error}</p> : null}

      <div className="policy-execution-summary">
        <article className="policy-summary-card">
          <div className="summary-header">
            <span className="summary-icon">⏰</span>
            <div>
              <p className="summary-title">Next Automation Window</p>
              <p className="hint">{nextExecutionLabel}</p>
            </div>
          </div>
          <p className="summary-copy">{scheduleHint}</p>
          <div className="summary-badge status-pill status-pill-running">Automation active</div>
        </article>

        <article className="policy-summary-card">
          <p className="summary-title">Policy execution queue</p>
          <p className="summary-copy">
            Pending: <strong>{queueData?.pending_count ?? "—"}</strong> · Failed: <strong>{queueData?.failed_count ?? "—"}</strong>
          </p>
          <p className="hint">Connection: {queueData?.queue_connection ?? "—"}</p>
        </article>
      </div>

      <div className="policy-cards-grid">
        {policyCards.length ? policyCards : (
          <article className="policy-target-card empty-state">
            <h3>No policies configured yet</h3>
            <p className="hint">Create a new lifecycle policy to begin automated evaluation.</p>
            {canMutate ? (
              <button type="button" onClick={openCreate} disabled={isLoading || isDispatching}>
                Create policy
              </button>
            ) : null}
          </article>
        )}
      </div>

      <AutomationQueuePanel canDispatch={canMutate} refreshToken={queueRefreshToken} />

      {preview ? (
        <div className="tracker-banner">
          <strong>Next run / state: {preview.title}</strong>
          <p className="hint">
            execution_at: {preview.payload.execution_at ? new Date(preview.payload.execution_at).toLocaleString() : "—"} · cron:{" "}
            {preview.payload.cron_expression ?? "—"} · last_status: {preview.payload.last_status} · last_evaluated_at:{" "}
            {preview.payload.last_evaluated_at ? new Date(preview.payload.last_evaluated_at).toLocaleString() : "—"}
            {preview.payload.policy_type === "student_graduation" && preview.payload.graduation_preview ? (
              <>
                {" "}
                · due warnings: {preview.payload.graduation_preview.eligible_warnings} · due suspensions:{" "}
                {preview.payload.graduation_preview.eligible_suspensions}
              </>
            ) : null}
          </p>
          <button type="button" className="secondary" onClick={() => setPreview(null)}>
            Dismiss
          </button>
        </div>
      ) : null}

      <div className="audit-table-wrap">
        <table className="audit-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Action</th>
              <th>Scope</th>
              <th>Active</th>
              <th>Status</th>
              <th>Hold / notes</th>
              <th className="col-actions">Actions</th>
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 ? (
              <tr>
                <td colSpan={7} className="hint">
                  No policies yet.
                </td>
              </tr>
            ) : (
              rows.map((row) => (
                <tr key={row.id}>
                  <td>{row.name}</td>
                  <td>{row.action}</td>
                  <td>{scopeLabel(row.rule_json)}</td>
                  <td>{row.is_active ? "Yes" : "No"}</td>
                  <td>{row.last_status}</td>
                  <td className="wrap-cell">{row.hold_reason ?? "—"}</td>
                  <td className="col-actions policy-row-actions">
                    <button type="button" className="secondary slim-button" onClick={() => void handleNextRun(row)} disabled={isLoading}>
                      Next run
                    </button>
                    {canMutate ? (
                      <>
                        <button type="button" className="secondary slim-button" onClick={() => openEdit(row)} disabled={isLoading}>
                          Edit
                        </button>
                        <button type="button" className="danger-button slim-button" onClick={() => setDeleteTarget(row)} disabled={isLoading}>
                          Delete
                        </button>
                      </>
                    ) : null}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      <div className="audit-actions">
        <button type="button" className="secondary" onClick={() => setPage((current) => Math.max(1, current - 1))} disabled={isLoading || page <= 1}>
          Previous
        </button>
        <span className="hint">
          Page {page} of {lastPage} ({total} policies)
        </span>
        <button type="button" className="secondary" onClick={() => setPage((current) => Math.min(lastPage, current + 1))} disabled={isLoading || page >= lastPage}>
          Next
        </button>
      </div>

      <PolicyEditorModal
        open={editorOpen}
        policy={editorPolicy}
        disabled={isLoading || Boolean(deleteTarget)}
        onClose={() => setEditorOpen(false)}
        onSaved={async () => {
          await load();
          setQueueRefreshToken((current) => current + 1);
        }}
      />

      {deleteTarget ? (
        <div className="modal-overlay" role="presentation">
          <div className="modal-panel" role="dialog" aria-modal="true" aria-labelledby="policy-delete-title">
            <h3 id="policy-delete-title">Delete policy</h3>
            <p className="hint">
              Remove <strong>{deleteTarget.name}</strong>? Historical audit rows referencing this policy remain immutable.
            </p>
            <div className="modal-actions">
              <button type="button" className="secondary" onClick={() => setDeleteTarget(null)} disabled={isSaving}>
                Cancel
              </button>
              <button type="button" className="danger-button" onClick={() => void confirmDelete()} disabled={isSaving}>
                Delete
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </section>
  );
}
