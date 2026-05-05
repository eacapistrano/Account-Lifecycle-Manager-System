import { useCallback, useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { useAuth } from "../../../auth/useAuth";
import { apiRequest } from "../../../lib/api";
import { PolicyEditorModal } from "./PolicyEditorModal";
import type { PoliciesIndexResponse, PolicyNextRunResponse, PolicyRow } from "./policyTypes";

function scopeLabel(ruleJson: Record<string, unknown>): string {
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

  return (
    <section className="card stack module-page">
      <header className="module-page-header">
        <div>
          <h2>Policy execution</h2>
          <p className="hint">Define scoped automation rules and review hold status from the evaluator. Changes require the policy.write permission.</p>
        </div>
        <div className="audit-actions">
          <button type="button" className="secondary" onClick={() => void load()} disabled={isLoading}>
            {isLoading ? "Loading…" : "Refresh"}
          </button>
          {canMutate ? (
            <button type="button" onClick={openCreate} disabled={isLoading}>
              New policy
            </button>
          ) : null}
          <Link className="hint-inline dashboard-link" to="/audit-logs">
            Related audit →
          </Link>
        </div>
      </header>

      {error ? <p className="toast toast-error">{error}</p> : null}

      {preview ? (
        <div className="tracker-banner">
          <strong>Next run / state: {preview.title}</strong>
          <p className="hint">
            execution_at: {preview.payload.execution_at ? new Date(preview.payload.execution_at).toLocaleString() : "—"} · cron:{" "}
            {preview.payload.cron_expression ?? "—"} · last_status: {preview.payload.last_status} · last_evaluated_at:{" "}
            {preview.payload.last_evaluated_at ? new Date(preview.payload.last_evaluated_at).toLocaleString() : "—"}
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
        onSaved={load}
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
