import { Fragment, useCallback, useEffect, useRef, useState } from "react";
import { Link } from "react-router-dom";
import { apiRequest } from "../../lib/api";
import type { BulkHistoryResponse, BulkHistoryRow } from "./studentDeletion/studentDeletionTypes";

type Filters = {
  status: string;
  from: string;
  to: string;
};

type FailureRow = {
  id: number;
  target_account_id: string | null;
  error_message: string | null;
  created_at: string | null;
  correlation_id: string | null;
};

type FailuresResponse = {
  data: {
    operation: {
      operation_id: string;
      action: string;
      status: string;
      requested_at: string | null;
      failed: number;
    };
    failures: {
      data: FailureRow[];
    };
  };
};

const INITIAL_FILTERS: Filters = {
  status: "",
  from: "",
  to: "",
};

function formatDate(value: string | null): string {
  return value ? new Date(value).toLocaleString() : "-";
}

function buildQuery(filters: Filters, page: number, perPage: number): string {
  const query = new URLSearchParams({
    action: "delete",
    page: String(page),
    per_page: String(perPage),
  });

  if (filters.status) {
    query.set("status", filters.status);
  }
  if (filters.from) {
    query.set("from", filters.from);
  }
  if (filters.to) {
    query.set("to", filters.to);
  }

  return query.toString();
}

export function StudentDeletionHistoryPage() {
  const [filters, setFilters] = useState<Filters>(INITIAL_FILTERS);
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(25);
  const [rows, setRows] = useState<BulkHistoryRow[]>([]);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState("");
  const [info, setInfo] = useState("");
  const [failureRows, setFailureRows] = useState<Record<string, FailureRow[]>>({});
  const [failureLoadingId, setFailureLoadingId] = useState<string | null>(null);
  const latestRequestRef = useRef(0);

  const load = useCallback(async () => {
    const requestId = ++latestRequestRef.current;
    setError("");
    setInfo("");
    setIsLoading(true);

    try {
      const payload = await apiRequest<BulkHistoryResponse>(`/students/actions?${buildQuery(filters, page, perPage)}`);
      if (requestId !== latestRequestRef.current) {
        return;
      }
      setRows(payload.data.data);
      setLastPage(Math.max(1, payload.data.last_page));
      setTotal(payload.data.total);
    } catch (e) {
      if (requestId !== latestRequestRef.current) {
        return;
      }
      setRows([]);
      setError(e instanceof Error ? e.message : "Failed to load deletion history.");
    } finally {
      if (requestId === latestRequestRef.current) {
        setIsLoading(false);
      }
    }
  }, [filters, page, perPage]);

  useEffect(() => {
    const timeoutId = window.setTimeout(() => {
      void load();
    }, 200);
    return () => window.clearTimeout(timeoutId);
  }, [load]);

  function updateFilter<K extends keyof Filters>(key: K, value: string) {
    setPage(1);
    setFilters((current) => ({ ...current, [key]: value }));
  }

  async function toggleFailures(row: BulkHistoryRow) {
    if (failureRows[row.operation_id]) {
      setFailureRows((current) => {
        const next = { ...current };
        delete next[row.operation_id];
        return next;
      });
      return;
    }

    setError("");
    setInfo("");
    setFailureLoadingId(row.operation_id);
    try {
      const payload = await apiRequest<FailuresResponse>(`/students/actions/${row.operation_id}/failures?per_page=100`);
      setFailureRows((current) => ({
        ...current,
        [row.operation_id]: payload.data.failures.data,
      }));
      if (payload.data.failures.data.length === 0) {
        setInfo("No failure rows were recorded for that deletion operation.");
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load operation failures.");
    } finally {
      setFailureLoadingId(null);
    }
  }

  return (
    <section className="card stack module-page">
      <header className="module-page-header">
        <div>
          <h2>Student deletion history</h2>
          <p className="hint">Review queued and completed deletion operations, outcomes, actors, and failed account details.</p>
        </div>
        <div className="audit-actions">
          <button type="button" className="secondary" onClick={() => void load()} disabled={isLoading}>
            {isLoading ? "Loading..." : "Refresh"}
          </button>
          <Link className="hint-inline dashboard-link" to="/student-deletion">
            Back to deletion
          </Link>
        </div>
      </header>

      <div className="audit-filters">
        <label>
          Status
          <select value={filters.status} onChange={(event) => updateFilter("status", event.target.value)} disabled={isLoading}>
            <option value="">All statuses</option>
            <option value="queued">Queued</option>
            <option value="running">Running</option>
            <option value="completed">Completed</option>
            <option value="failed">Failed</option>
          </select>
        </label>
        <label>
          From
          <input type="date" value={filters.from} onChange={(event) => updateFilter("from", event.target.value)} disabled={isLoading} />
        </label>
        <label>
          To
          <input type="date" value={filters.to} onChange={(event) => updateFilter("to", event.target.value)} disabled={isLoading} />
        </label>
        <label className="audit-page-size">
          Rows
          <select
            value={perPage}
            onChange={(event) => {
              setPerPage(Number(event.target.value));
              setPage(1);
            }}
            disabled={isLoading}
          >
            <option value={10}>10</option>
            <option value={25}>25</option>
            <option value={50}>50</option>
            <option value={100}>100</option>
          </select>
        </label>
      </div>

      <div className="audit-actions">
        <button
          type="button"
          className="secondary"
          onClick={() => {
            setFilters(INITIAL_FILTERS);
            setPage(1);
          }}
          disabled={isLoading}
        >
          Reset filters
        </button>
      </div>

      {info ? <p className="toast toast-success">{info}</p> : null}
      {error ? <p className="toast toast-error">{error}</p> : null}

      <div className="audit-table-wrap">
        <table className="audit-table">
          <thead>
            <tr>
              <th>Requested</th>
              <th>Status</th>
              <th>Progress</th>
              <th>Actor</th>
              <th>Completed</th>
              <th>Operation ID</th>
              <th className="col-actions">Details</th>
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 ? (
              <tr>
                <td colSpan={7} className="hint">
                  No deletion operations match these filters.
                </td>
              </tr>
            ) : (
              rows.map((row) => (
                <Fragment key={row.operation_id}>
                  <tr>
                    <td>{formatDate(row.requested_at)}</td>
                    <td>
                      <span className={`status-pill status-pill-${row.status}`}>{row.status}</span>
                    </td>
                    <td>
                      {row.processed}/{row.total} processed
                      <br />
                      <span className="hint-inline">
                        OK {row.ok} / failed {row.failed}
                      </span>
                    </td>
                    <td>{row.actor?.email ?? "-"}</td>
                    <td>{formatDate(row.completed_at)}</td>
                    <td className="mono-cell">{row.operation_id}</td>
                    <td className="col-actions">
                      {row.failed > 0 ? (
                        <button
                          type="button"
                          className="secondary slim-button"
                          onClick={() => void toggleFailures(row)}
                          disabled={failureLoadingId === row.operation_id}
                        >
                          {failureLoadingId === row.operation_id
                            ? "Loading..."
                            : failureRows[row.operation_id]
                              ? "Hide failures"
                              : "View failures"}
                        </button>
                      ) : (
                        <span className="hint-inline">No failures</span>
                      )}
                    </td>
                  </tr>
                  {failureRows[row.operation_id] ? (
                    <tr>
                      <td colSpan={7} className="operation-failures-cell">
                        <div className="operation-failures">
                          <strong>Failed accounts</strong>
                          {failureRows[row.operation_id].length === 0 ? (
                            <p className="hint">No failure rows were recorded.</p>
                          ) : (
                            <table className="audit-table data-table-tight">
                              <thead>
                                <tr>
                                  <th>Time</th>
                                  <th>Target</th>
                                  <th>Error</th>
                                  <th>Correlation ID</th>
                                </tr>
                              </thead>
                              <tbody>
                                {failureRows[row.operation_id].map((failure) => (
                                  <tr key={failure.id}>
                                    <td>{formatDate(failure.created_at)}</td>
                                    <td>{failure.target_account_id ?? "-"}</td>
                                    <td className="wrap-cell">{failure.error_message ?? "-"}</td>
                                    <td className="mono-cell">{failure.correlation_id ?? "-"}</td>
                                  </tr>
                                ))}
                              </tbody>
                            </table>
                          )}
                        </div>
                      </td>
                    </tr>
                  ) : null}
                </Fragment>
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
          Page {page} of {lastPage} ({total} deletion operations)
        </span>
        <button type="button" className="secondary" onClick={() => setPage((current) => Math.min(lastPage, current + 1))} disabled={isLoading || page >= lastPage}>
          Next
        </button>
      </div>
    </section>
  );
}
