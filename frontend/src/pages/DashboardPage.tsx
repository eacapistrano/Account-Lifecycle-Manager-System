import { useCallback, useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { apiRequest } from "../lib/api";
import type { DashboardPayload } from "../types/dashboard";

export function DashboardPage() {
  const [data, setData] = useState<DashboardPayload | null>(null);
  const [error, setError] = useState("");
  const [isLoading, setIsLoading] = useState(true);

  const load = useCallback(async () => {
    setError("");
    setIsLoading(true);
    try {
      const json = await apiRequest<DashboardPayload>("/dashboard");
      setData(json);
    } catch (e) {
      setData(null);
      setError(e instanceof Error ? e.message : "Failed to load dashboard.");
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const counts = data?.counts;

  return (
    <section className="card stack">
      <header className="dashboard-header">
        <div>
          <h2>Dashboard</h2>
          <p className="hint">Live counts, policy signals, lifecycle queue snapshot, and recent audit trail.</p>
        </div>
        <button type="button" className="secondary" onClick={() => void load()} disabled={isLoading}>
          {isLoading ? "Refreshing…" : "Refresh"}
        </button>
      </header>

      {error ? <p className="toast toast-error">{error}</p> : null}

      <div className="overview-grid">
        <article className="overview-item">
          <h3>Policy status</h3>
          <p className="dashboard-stat">{counts?.active_policies ?? "—"}</p>
          <p className="hint">Active automation policies</p>
          <Link className="dashboard-link" to="/policy-execution">
            Manage policies →
          </Link>
        </article>
        <article className="overview-item">
          <h3>Student lifecycle queue</h3>
          <p className="dashboard-stat">{counts?.students ?? "—"}</p>
          <p className="hint">Use filters on student deletion to scope suspend/delete batches.</p>
          <Link className="dashboard-link" to="/student-deletion">
            Open student deletion →
          </Link>
        </article>
        <article className="overview-item">
          <h3>Suspended accounts</h3>
          <p className="dashboard-stat">{counts?.suspended ?? "—"}</p>
          <p className="hint">
            Due for deletion now: <strong>{counts?.due_for_deletion ?? "—"}</strong>
          </p>
          <Link className="dashboard-link" to="/suspended-accounts">
            Review suspended →
          </Link>
        </article>
        <article className="overview-item">
          <h3>Audit activity</h3>
          <p className="hint">Latest immutable records (sample).</p>
          <Link className="dashboard-link" to="/audit-logs">
            Full audit log →
          </Link>
          <ul className="dashboard-mini-list">
            {(data?.recent_audit ?? []).slice(0, 5).map((row) => (
              <li key={row.id}>
                <span className={`dash-badge dash-badge-${row.success ? "ok" : "fail"}`}>{row.success ? "OK" : "FAIL"}</span>
                <span className="dash-mini-meta">
                  {row.module}.{row.action}
                </span>
                <span className="hint-inline">{new Date(row.created_at).toLocaleString()}</span>
              </li>
            ))}
            {!data?.recent_audit?.length ? <li className="hint">No audit rows yet.</li> : null}
          </ul>
        </article>
      </div>

      <div className="dashboard-policies card-inner">
        <h3 className="dashboard-subheading">Active policies</h3>
        {!data?.active_policies?.length ? (
          <p className="hint">No active policies configured.</p>
        ) : (
          <div className="audit-table-wrap">
            <table className="audit-table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Status</th>
                  <th>Next / scheduled</th>
                </tr>
              </thead>
              <tbody>
                {data.active_policies.map((p) => (
                  <tr key={p.id}>
                    <td>{p.name}</td>
                    <td>{p.last_status}</td>
                    <td>{p.execution_at ? new Date(p.execution_at).toLocaleString() : "Immediate / cron only"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </section>
  );
}
