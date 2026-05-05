import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { apiRequest, buildApiUrl, createApiHeaders } from "../../lib/api";

type AuditEvent = {
  id: number;
  module: string;
  action: string;
  target_account_id: string | null;
  success: boolean;
  correlation_id: string | null;
  created_at: string;
  actor?: {
    email?: string | null;
  } | null;
};

type AuditResponse = {
  data: {
    data: AuditEvent[];
    current_page: number;
    last_page: number;
  };
};

type Filters = {
  module: string;
  action: string;
  actor_email: string;
  from: string;
  to: string;
};

type Toast = {
  id: number;
  kind: "success" | "error";
  message: string;
};

const INITIAL_FILTERS: Filters = {
  module: "",
  action: "",
  actor_email: "",
  from: "",
  to: "",
};

function toQueryString(filters: Filters, page: number, pageSize: number): string {
  const query = new URLSearchParams();
  for (const [key, value] of Object.entries(filters)) {
    if (value.trim().length > 0) {
      query.set(key, value.trim());
    }
  }
  query.set("page", String(page));
  query.set("per_page", String(pageSize));
  return query.toString();
}

export function AuditLogsShellPage() {
  const [filters, setFilters] = useState<Filters>(INITIAL_FILTERS);
  const [events, setEvents] = useState<AuditEvent[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [pageSize, setPageSize] = useState(25);
  const [isLoading, setIsLoading] = useState(false);
  const [toasts, setToasts] = useState<Toast[]>([]);
  const latestRequestRef = useRef(0);
  const toastIdRef = useRef(0);
  const toastTimeoutsRef = useRef<number[]>([]);

  const queryString = useMemo(() => toQueryString(filters, page, pageSize), [filters, page, pageSize]);

  const pushToast = useCallback((kind: Toast["kind"], message: string) => {
    const id = ++toastIdRef.current;
    setToasts((current) => [...current, { id, kind, message }]);
    const timeoutId = window.setTimeout(() => {
      setToasts((current) => current.filter((toast) => toast.id !== id));
    }, 4000);
    toastTimeoutsRef.current.push(timeoutId);
  }, []);

  const loadEvents = useCallback(async (nextPage: number, nextFilters: Filters, nextPageSize: number) => {
    const requestId = ++latestRequestRef.current;
    setIsLoading(true);
    try {
      const data = await apiRequest<AuditResponse>(`/audit-events?${toQueryString(nextFilters, nextPage, nextPageSize)}`);
      if (requestId !== latestRequestRef.current) {
        return;
      }
      setEvents(data.data.data);
      setPage(data.data.current_page);
      setLastPage(Math.max(1, data.data.last_page));
    } catch (e) {
      if (requestId !== latestRequestRef.current) {
        return;
      }
      setEvents([]);
      pushToast("error", e instanceof Error ? e.message : "Failed to load audit events.");
    } finally {
      if (requestId === latestRequestRef.current) {
        setIsLoading(false);
      }
    }
  }, [pushToast]);

  useEffect(() => {
    const timeoutId = window.setTimeout(() => {
      void loadEvents(page, filters, pageSize);
    }, 250);
    return () => {
      window.clearTimeout(timeoutId);
    };
  }, [filters, loadEvents, page, pageSize]);

  useEffect(() => {
    const timeoutIds = toastTimeoutsRef.current;
    return () => {
      for (const timeoutId of timeoutIds) {
        window.clearTimeout(timeoutId);
      }
    };
  }, []);

  async function handleExport(kind: "csv" | "pdf") {
    const headers = createApiHeaders();
    try {
      const response = await fetch(buildApiUrl(`/audit-events/export/${kind}?${queryString}`), { headers });
      if (!response.ok) {
        throw new Error(await response.text());
      }
      const blob = await response.blob();
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      const extension = kind === "csv" ? "csv" : "pdf";
      link.href = url;
      link.download = `audit-export.${extension}`;
      link.click();
      URL.revokeObjectURL(url);
      pushToast("success", `Audit export (${kind.toUpperCase()}) downloaded.`);
    } catch (e) {
      pushToast("error", e instanceof Error ? e.message : `Failed to export ${kind.toUpperCase()}.`);
    }
  }

  function updateFilter<K extends keyof Filters>(key: K, value: string) {
    setPage(1);
    setFilters((prev) => ({ ...prev, [key]: value }));
  }

  return (
    <section className="card stack">
      <h2>Audit Logs</h2>
      <p className="hint">Filter immutable audit events and export review packs in CSV or PDF.</p>

      <div className="audit-filters">
        <label>
          Module
          <input
            value={filters.module}
            onChange={(event) => updateFilter("module", event.target.value)}
            placeholder="student-deletion"
          />
        </label>
        <label>
          Action
          <input
            value={filters.action}
            onChange={(event) => updateFilter("action", event.target.value)}
            placeholder="suspend"
          />
        </label>
        <label>
          Actor email
          <input
            type="email"
            value={filters.actor_email}
            onChange={(event) => updateFilter("actor_email", event.target.value)}
            placeholder="admin@example.com"
          />
        </label>
        <label>
          From
          <input
            type="date"
            value={filters.from}
            onChange={(event) => updateFilter("from", event.target.value)}
          />
        </label>
        <label>
          To
          <input type="date" value={filters.to} onChange={(event) => updateFilter("to", event.target.value)} />
        </label>
      </div>

      <div className="audit-actions">
        <button onClick={() => void loadEvents(page, filters, pageSize)} disabled={isLoading}>
          {isLoading ? "Loading..." : "Refresh"}
        </button>
        <button
          onClick={() => {
            setFilters(INITIAL_FILTERS);
            setPage(1);
          }}
          disabled={isLoading}
        >
          Reset
        </button>
        <label className="audit-page-size">
          Rows
          <select
            value={pageSize}
            onChange={(event) => {
              setPageSize(Number(event.target.value));
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
        <button onClick={() => void handleExport("csv")} disabled={isLoading}>
          Export CSV
        </button>
        <button onClick={() => void handleExport("pdf")} disabled={isLoading}>
          Export PDF
        </button>
      </div>

      <div className="toast-stack" aria-live="polite" aria-atomic="true">
        {toasts.map((toast) => (
          <p key={toast.id} className={`toast toast-${toast.kind}`}>
            {toast.message}
          </p>
        ))}
      </div>

      <div className="audit-table-wrap">
        <table className="audit-table">
          <thead>
            <tr>
              <th>Time</th>
              <th>Actor</th>
              <th>Module</th>
              <th>Action</th>
              <th>Target</th>
              <th>Success</th>
            </tr>
          </thead>
          <tbody>
            {events.length === 0 ? (
              <tr>
                <td colSpan={6}>No audit events loaded.</td>
              </tr>
            ) : (
              events.map((event) => (
                <tr key={event.id}>
                  <td>{new Date(event.created_at).toLocaleString()}</td>
                  <td>{event.actor?.email ?? "-"}</td>
                  <td>{event.module}</td>
                  <td>{event.action}</td>
                  <td>{event.target_account_id ?? "-"}</td>
                  <td>{event.success ? "Yes" : "No"}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      <div className="audit-actions">
        <button onClick={() => setPage((current) => Math.max(1, current - 1))} disabled={isLoading || page <= 1}>
          Previous
        </button>
        <span className="hint">
          Page {page} of {lastPage}
        </span>
        <button onClick={() => setPage((current) => Math.min(lastPage, current + 1))} disabled={isLoading || page >= lastPage}>
          Next
        </button>
      </div>
    </section>
  );
}
