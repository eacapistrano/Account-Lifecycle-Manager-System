import { useCallback, useEffect, useState } from "react";
import { apiRequest } from "../../../lib/api";
import type { AutomationQueueResponse } from "./policyTypes";

type Props = {
  canDispatch: boolean;
  refreshToken?: number;
};

export function AutomationQueuePanel({ canDispatch, refreshToken = 0 }: Props) {
  const [data, setData] = useState<AutomationQueueResponse | null>(null);
  const [error, setError] = useState("");
  const [isLoading, setIsLoading] = useState(false);
  const [isDispatching, setIsDispatching] = useState(false);

  const load = useCallback(async () => {
    setError("");
    setIsLoading(true);
    try {
      const payload = await apiRequest<AutomationQueueResponse>("/automation/queue");
      setData(payload);
    } catch (e) {
      setData(null);
      setError(e instanceof Error ? e.message : "Failed to load automation queue.");
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load, refreshToken]);

  async function dispatchTask(task: "policy_evaluation" | "suspended_due_dates") {
    setError("");
    setIsDispatching(true);
    try {
      await apiRequest("/automation/queue/dispatch", {
        method: "POST",
        body: JSON.stringify({ task }),
      });
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to queue automation task.");
    } finally {
      setIsDispatching(false);
    }
  }

  const workspace = data?.google_workspace;
  const suspendStatus = workspace?.ready_for_suspend
    ? "Live"
    : workspace?.suspend_enabled
      ? workspace.suspend_dry_run
        ? "Dry run"
        : "Needs setup"
      : "Disabled";
  const deleteStatus = workspace?.ready_for_delete
    ? "Live"
    : workspace?.delete_enabled
      ? workspace.delete_dry_run
        ? "Dry run"
        : "Needs setup"
      : "Disabled";

  return (
    <section className="card stack automation-queue-panel">
      <header className="module-page-header">
        <div>
          <h3>Scheduled automation queue</h3>
          <p className="hint">
            Cron-driven jobs run via <code>php artisan schedule:run</code> and the queue worker.
          </p>
        </div>
        <div className="audit-actions">
          <button type="button" className="secondary" onClick={() => void load()} disabled={isLoading || isDispatching}>
            {isLoading ? "Loading..." : "Refresh queue"}
          </button>
          {canDispatch ? (
            <>
              <button type="button" onClick={() => void dispatchTask("policy_evaluation")} disabled={isLoading || isDispatching}>
                Queue policy run
              </button>
              <button type="button" className="secondary" onClick={() => void dispatchTask("suspended_due_dates")} disabled={isLoading || isDispatching}>
                Queue due-date run
              </button>
            </>
          ) : null}
        </div>
      </header>

      {error ? <p className="toast toast-error">{error}</p> : null}

      <div className="automation-queue-summary">
        <div className="automation-queue-stat">
          <span>Pending jobs</span>
          <strong>{data?.pending_count ?? "-"}</strong>
        </div>
        <div className="automation-queue-stat">
          <span>Failed jobs</span>
          <strong>{data?.failed_count ?? "-"}</strong>
        </div>
        <div className="automation-queue-stat">
          <span>Connection</span>
          <strong>{data?.queue_connection ?? "-"}</strong>
        </div>
      </div>

      <div className="automation-schedule-grid">
        {(data?.schedules ?? []).map((task) => (
          <article key={task.key} className="automation-schedule-card">
            <strong>{task.name}</strong>
            <p className="hint mono-cell">{task.cron}</p>
            <p className="hint">{task.description}</p>
          </article>
        ))}
      </div>

      {workspace ? (
        <div className="automation-schedule-grid">
          <article className="automation-schedule-card">
            <strong>Google Workspace suspend</strong>
            <p className="hint">Status: {suspendStatus}</p>
            <p className="hint">User key: {workspace.suspend_user_key}</p>
          </article>
          <article className="automation-schedule-card">
            <strong>Google Workspace delete</strong>
            <p className="hint">Status: {deleteStatus}</p>
            <p className="hint">User key: {workspace.delete_user_key}</p>
          </article>
          <article className="automation-schedule-card">
            <strong>Admin SDK credentials</strong>
            <p className="hint">
              Credentials: {workspace.credentials_readable ? "readable" : workspace.credentials_configured ? "not readable" : "not configured"}
            </p>
            <p className="hint">Impersonates: {workspace.impersonate_email ?? "not configured"}</p>
          </article>
        </div>
      ) : null}

      <div className="audit-table-wrap">
        <table className="audit-table">
          <thead>
            <tr>
              <th>Task</th>
              <th>Status</th>
              <th>Attempts</th>
              <th>Next available</th>
            </tr>
          </thead>
          <tbody>
            {(data?.recent_pending ?? []).length === 0 ? (
              <tr>
                <td colSpan={4} className="hint">
                  No pending jobs in the queue.
                </td>
              </tr>
            ) : (
              data?.recent_pending.map((row) => (
                <tr key={row.id}>
                  <td>{row.job_name}</td>
                  <td>
                    <span className={`status-pill ${row.status === "processing" ? "status-pill-running" : "status-pill-queued"}`}>
                      {row.status}
                    </span>
                  </td>
                  <td>{row.attempts}</td>
                  <td>{row.available_at ? new Date(row.available_at).toLocaleString() : "-"}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </section>
  );
}
