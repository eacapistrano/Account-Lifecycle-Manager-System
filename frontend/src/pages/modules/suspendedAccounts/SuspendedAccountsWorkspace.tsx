import { useCallback, useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { useAuth } from "../../../auth/useAuth";
import { apiRequest } from "../../../lib/api";
import type { LaravelPaginator } from "../../../types/api";

type SuspendedStudentRow = {
  id: number;
  external_account_id: string;
  primary_email: string;
  full_name: string | null;
  department: string | null;
  school_year: string | null;
  deletion_scheduled_at: string | null;
  priority_flag: boolean;
  compliance_notes: string | null;
};

type SuspendedIndexResponse = {
  data: LaravelPaginator<SuspendedStudentRow>;
};

type DraftRow = {
  priority_flag: boolean;
  compliance_notes: string;
  deletion_scheduled_at: string;
};

function draftsFromRows(rows: SuspendedStudentRow[]): Record<number, DraftRow> {
  const next: Record<number, DraftRow> = {};
  for (const row of rows) {
    next[row.id] = {
      priority_flag: row.priority_flag,
      compliance_notes: row.compliance_notes ?? "",
      deletion_scheduled_at: row.deletion_scheduled_at ? row.deletion_scheduled_at.slice(0, 10) : "",
    };
  }
  return next;
}

export function SuspendedAccountsWorkspace() {
  const { hasPermission } = useAuth();
  const canEdit = hasPermission("suspended.priority");

  const [priorityOnly, setPriorityOnly] = useState(false);
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [perPage] = useState(50);
  const [rows, setRows] = useState<SuspendedStudentRow[]>([]);
  const [drafts, setDrafts] = useState<Record<number, DraftRow>>({});
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState("");
  const [info, setInfo] = useState("");
  const [savingId, setSavingId] = useState<number | null>(null);

  const load = useCallback(async () => {
    setError("");
    setInfo("");
    setIsLoading(true);
    try {
      const query = new URLSearchParams({
        page: String(page),
        per_page: String(perPage),
      });
      if (priorityOnly) {
        query.set("priority_only", "1");
      }
      if (search.trim()) {
        query.set("search", search.trim());
      }
      const payload = await apiRequest<SuspendedIndexResponse>(`/suspended-accounts?${query.toString()}`);
      setRows(payload.data.data);
      setDrafts(draftsFromRows(payload.data.data));
      setLastPage(Math.max(1, payload.data.last_page));
      setTotal(payload.data.total);
    } catch (e) {
      setRows([]);
      setDrafts({});
      setError(e instanceof Error ? e.message : "Failed to load suspended accounts.");
    } finally {
      setIsLoading(false);
    }
  }, [page, perPage, priorityOnly]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    setPage(1);
  }, [priorityOnly, search]);

  function patchDraft(id: number, patch: Partial<DraftRow>) {
    setDrafts((current) => {
      const existing = current[id];
      if (!existing) {
        return current;
      }
      return { ...current, [id]: { ...existing, ...patch } };
    });
  }

  async function saveRow(row: SuspendedStudentRow) {
    const draft = drafts[row.id];
    if (!draft) {
      return;
    }
    setSavingId(row.id);
    setError("");
    setInfo("");
    try {
      await apiRequest(`/suspended-accounts/${row.id}`, {
        method: "PATCH",
        body: JSON.stringify({
          priority_flag: draft.priority_flag,
          compliance_notes: draft.compliance_notes.trim() || null,
          deletion_scheduled_at: draft.deletion_scheduled_at.trim() || null,
        }),
      });
      setInfo(`Saved triage fields for ${row.primary_email}.`);
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Save failed.");
    } finally {
      setSavingId(null);
    }
  }

  function isDirty(row: SuspendedStudentRow): boolean {
    const draft = drafts[row.id];
    if (!draft) {
      return false;
    }
    const baseline = draftsFromRows([row])[row.id];
    return (
      draft.priority_flag !== baseline.priority_flag ||
      draft.compliance_notes !== baseline.compliance_notes ||
      draft.deletion_scheduled_at !== baseline.deletion_scheduled_at
    );
  }

  return (
    <section className="card stack module-page">
      <header className="module-page-header">
        <div>
          <h2>Suspended accounts</h2>
          <p className="hint">
            Review deferred deletion targets, adjust scheduled deletion dates, and flag priority/compliance notes for downstream automation jobs.
          </p>
        </div>
        <div className="audit-actions">
          <div className="search-bar">
            <input
              type="text"
              placeholder="Search all columns..."
              value={search}
              onChange={(ev) => setSearch(ev.target.value)}
              className="search-input"
            />
            <button
              type="button"
              className="primary"
              onClick={() => void load()}
              disabled={isLoading}
            >
              Search
            </button>
          </div>
          <button type="button" className="secondary" onClick={() => void load()} disabled={isLoading}>
            {isLoading ? "Loading…" : "Refresh"}
          </button>
          <Link className="hint-inline dashboard-link" to="/audit-logs">
            Audit references →
          </Link>
        </div>
      </header>

      {!canEdit ? <p className="hint">You have read-only access. Request suspended.priority to edit triage fields.</p> : null}

      <div className="audit-actions wrap-bar">
        <label className="perm-row tight-label">
          <input type="checkbox" checked={priorityOnly} onChange={(ev) => setPriorityOnly(ev.target.checked)} disabled={isLoading} />
          Priority flagged only
        </label>
      </div>

      {info ? <p className="toast toast-success">{info}</p> : null}
      {error ? <p className="toast toast-error">{error}</p> : null}

      <div className="audit-table-wrap">
        <table className="audit-table">
          <thead>
            <tr>
              <th>Email</th>
              <th>Name</th>
              <th>Deletion due</th>
              <th>Priority</th>
              <th>Compliance notes</th>
              <th className="col-actions">Actions</th>
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 ? (
              <tr>
                <td colSpan={7} className="hint">
                  No suspended accounts match this filter.
                </td>
              </tr>
            ) : (
              rows.map((row) => {
                const draft = drafts[row.id];
                return (
                  <tr key={row.id}>
                    <td>{row.primary_email}</td>
                    <td>{row.full_name ?? "—"}</td>
                    <td>
                      <input
                        type="date"
                        value={draft?.deletion_scheduled_at ?? ""}
                        onChange={(ev) => patchDraft(row.id, { deletion_scheduled_at: ev.target.value })}
                        disabled={!canEdit || isLoading}
                      />
                    </td>
                    <td>
                      <input
                        type="checkbox"
                        checked={draft?.priority_flag ?? false}
                        onChange={(ev) => patchDraft(row.id, { priority_flag: ev.target.checked })}
                        disabled={!canEdit || isLoading}
                      />
                    </td>
                    <td className="wrap-cell">
                      <textarea
                        rows={2}
                        value={draft?.compliance_notes ?? ""}
                        onChange={(ev) => patchDraft(row.id, { compliance_notes: ev.target.value })}
                        disabled={!canEdit || isLoading}
                      />
                    </td>
                    <td className="col-actions">
                      {canEdit ? (
                        <button
                          type="button"
                          className="secondary slim-button"
                          onClick={() => void saveRow(row)}
                          disabled={isLoading || savingId === row.id || !isDirty(row)}
                        >
                          {savingId === row.id ? "Saving…" : "Save"}
                        </button>
                      ) : (
                        <span className="hint-inline">—</span>
                      )}
                      {hasPermission("student.bulk_suspend") ? (
                        <button
                          type="button"
                          className="primary slim-button"
                          onClick={async () => {
                            if (!confirm(`Unsuspend ${row.primary_email}?`)) return;
                            setError("");
                            setInfo("");
                            try {
                              const payload = { account_ids: [row.external_account_id] };
                              await apiRequest(`/students/unsuspend`, {
                                method: "POST",
                                body: JSON.stringify(payload),
                              });
                              setInfo(`Unsuspended ${row.primary_email}.`);
                              await load();
                            } catch (e) {
                              setError(e instanceof Error ? e.message : "Unsuspend failed.");
                            }
                          }}
                          disabled={isLoading}
                        >
                          Unsuspend
                        </button>
                      ) : null}
                    </td>
                  </tr>
                );
              })
            )}
          </tbody>
        </table>
      </div>

      <div className="audit-actions">
        <button type="button" className="secondary" onClick={() => setPage((current) => Math.max(1, current - 1))} disabled={isLoading || page <= 1}>
          Previous
        </button>
        <span className="hint">
          Page {page} of {lastPage} ({total} suspended)
        </span>
        <button type="button" className="secondary" onClick={() => setPage((current) => Math.min(lastPage, current + 1))} disabled={isLoading || page >= lastPage}>
          Next
        </button>
      </div>
    </section>
  );
}
