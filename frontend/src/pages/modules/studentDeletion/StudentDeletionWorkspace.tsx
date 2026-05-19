import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useAuth } from "../../../auth/useAuth";
import { DELETE_CONFIRMATION_PHRASE } from "../../../constants/security";
import { apiRequest } from "../../../lib/api";
import { StudentDeletionAlerts } from "./StudentDeletionAlerts";
import { StudentBulkHistoryCard } from "./StudentBulkHistoryCard";
import { StudentDeletionFiltersBar } from "./StudentDeletionFiltersBar";
import { StudentDeletionHeader } from "./StudentDeletionHeader";
import { StudentDeletionModals } from "./StudentDeletionModals";
import { StudentDeletionSelectionBar } from "./StudentDeletionSelectionBar";
import { StudentDeletionTable } from "./StudentDeletionTable";
import { filtersToQuery } from "./studentDeletionQuery";
import type {
  BulkHistoryResponse,
  BulkHistoryRow,
  StudentFiltersState,
  StudentRow,
  StudentsIndexResponse,
} from "./studentDeletionTypes";
import { INITIAL_STUDENT_FILTERS } from "./studentDeletionTypes";
import { useStudentDeletionOperationPolling } from "./useStudentDeletionOperationPolling";

export function StudentDeletionWorkspace() {
  const { hasPermission } = useAuth();
  const canImport = hasPermission("student_import.run");
  const canSuspend = hasPermission("student.bulk_suspend");
  const canDelete = hasPermission("student.bulk_delete");

  const [filters, setFilters] = useState<StudentFiltersState>(INITIAL_STUDENT_FILTERS);
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(50);
  const [rows, setRows] = useState<StudentRow[]>([]);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [isLoading, setIsLoading] = useState(false);
  const [selectedIds, setSelectedIds] = useState<Set<string>>(() => new Set());
  const [messages, setMessages] = useState<string[]>([]);
  const [errors, setErrors] = useState<string[]>([]);

  const [history, setHistory] = useState<BulkHistoryRow[]>([]);

  const [suspendOpen, setSuspendOpen] = useState(false);
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [phraseInput, setPhraseInput] = useState("");

  const [trackingId, setTrackingId] = useState<string | null>(null);

  const latestFetchRef = useRef(0);

  const pushMessage = useCallback((msg: string) => {
    setMessages((current) => [...current.slice(-4), msg]);
  }, []);

  const pushError = useCallback((msg: string) => {
    setErrors((current) => [...current.slice(-4), msg]);
  }, []);

  const loadStudents = useCallback(async () => {
    const requestId = ++latestFetchRef.current;
    setIsLoading(true);
    setErrors([]);
    try {
      const qs = filtersToQuery(filters, page, perPage);
      const payload = await apiRequest<StudentsIndexResponse>(`/students?${qs}`);
      if (requestId !== latestFetchRef.current) {
        return;
      }
      setRows(payload.data.data);
      setLastPage(Math.max(1, payload.data.last_page));
      setTotal(payload.data.total);
    } catch (e) {
      if (requestId !== latestFetchRef.current) {
        return;
      }
      setRows([]);
      pushError(e instanceof Error ? e.message : "Failed to load students.");
    } finally {
      if (requestId === latestFetchRef.current) {
        setIsLoading(false);
      }
    }
  }, [filters, page, perPage, pushError]);

  const loadHistory = useCallback(async () => {
    try {
      const payload = await apiRequest<BulkHistoryResponse>("/students/actions?per_page=8");
      setHistory(payload.data.data);
    } catch {
      setHistory([]);
    }
  }, []);

  const tracker = useStudentDeletionOperationPolling({
    trackingId,
    setTrackingId,
    pushMessage,
    pushError,
    loadStudents,
    loadHistory,
  });

  useEffect(() => {
    const timeoutId = window.setTimeout(() => {
      void loadStudents();
    }, 220);
    return () => window.clearTimeout(timeoutId);
  }, [loadStudents]);

  useEffect(() => {
    void loadHistory();
  }, [loadHistory]);

  const latestImportHint = useMemo(() => {
    let latest = 0;
    let label = "";
    for (const row of rows) {
      if (!row.last_imported_at) {
        continue;
      }
      const t = new Date(row.last_imported_at).getTime();
      if (t >= latest) {
        latest = t;
        label = row.last_imported_at;
      }
    }
    return label ? new Date(label).toLocaleString() : "No import timestamps on this page.";
  }, [rows]);

  function toggleOne(id: string, checked: boolean) {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (checked) {
        next.add(id);
      } else {
        next.delete(id);
      }
      return next;
    });
  }

  function togglePage(checked: boolean, pageIds: string[]) {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      for (const id of pageIds) {
        if (checked) {
          next.add(id);
        } else {
          next.delete(id);
        }
      }
      return next;
    });
  }

  async function queueSuspend() {
    const ids = [...selectedIds];
    if (ids.length === 0) {
      pushError("Select at least one student.");
      return;
    }
    setErrors([]);
    try {
      const response = await apiRequest<{ operation_id: string }>("/students/suspend", {
        method: "POST",
        body: JSON.stringify({ account_ids: ids }),
      });
      pushMessage(`Queued suspend for ${ids.length} accounts.`);
      setSuspendOpen(false);
      setSelectedIds(new Set());
      setTrackingId(response.operation_id);
    } catch (e) {
      pushError(e instanceof Error ? e.message : "Suspend request failed.");
    }
  }

  async function queueDelete() {
    const ids = [...selectedIds];
    if (ids.length === 0) {
      pushError("Select at least one student.");
      return;
    }
    if (phraseInput !== DELETE_CONFIRMATION_PHRASE) {
      pushError("Confirmation phrase must match exactly.");
      return;
    }
    setErrors([]);
    try {
      const response = await apiRequest<{ operation_id: string }>("/students/delete", {
        method: "POST",
        body: JSON.stringify({
          account_ids: ids,
          confirmation_phrase: phraseInput,
        }),
      });
      pushMessage(`Queued delete for ${ids.length} accounts.`);
      setDeleteOpen(false);
      setPhraseInput("");
      setSelectedIds(new Set());
      setTrackingId(response.operation_id);
    } catch (e) {
      pushError(e instanceof Error ? e.message : "Delete request failed.");
    }
  }

  async function triggerImport() {
    setErrors([]);
    try {
      await apiRequest<{ queued: boolean }>("/students/import", { method: "POST", body: JSON.stringify({}) });
      pushMessage("Import job queued.");
      void loadStudents();
      void loadHistory();
    } catch (e) {
      pushError(e instanceof Error ? e.message : "Import request failed.");
    }
  }

  const selectionCount = selectedIds.size;

  return (
    <section className="card stack module-page">
      <StudentDeletionHeader
        latestImportHint={latestImportHint}
        isLoading={isLoading}
        canImport={canImport}
        onRefresh={() => void loadStudents()}
        onResetFilters={() => {
          setFilters(INITIAL_STUDENT_FILTERS);
          setPage(1);
          setSelectedIds(new Set());
        }}
        onImport={() => void triggerImport()}
      />

      {!canSuspend && !canDelete ? (
        <p className="hint">You can browse students but lack suspend/delete permissions. Ask an administrator for student bulk-action roles.</p>
      ) : null}

      <StudentDeletionFiltersBar
        filters={filters}
        perPage={perPage}
        disabled={isLoading}
        onFiltersChange={(next) => {
          setPage(1);
          setFilters({
            graduation_status: next.graduation_status ?? "",
            email: next.email ?? "",
            search: next.search ?? "",
          });
        }}
        onPerPageChange={(n) => {
          setPerPage(n);
          setPage(1);
        }}
      />

      <StudentDeletionSelectionBar
        selectionCount={selectionCount}
        isLoading={isLoading}
        canSuspend={canSuspend}
        canDelete={canDelete}
        onSuspendClick={() => setSuspendOpen(true)}
        onDeleteClick={() => setDeleteOpen(true)}
      />

      <StudentDeletionAlerts messages={messages} errors={errors} trackingId={trackingId} tracker={tracker} />

      <StudentDeletionTable rows={rows} selectedIds={selectedIds} disabled={isLoading} onToggleOne={toggleOne} onTogglePage={togglePage} />

      <div className="audit-actions">
        <button type="button" className="secondary" onClick={() => setPage((current) => Math.max(1, current - 1))} disabled={isLoading || page <= 1}>
          Previous
        </button>
        <span className="hint">
          Page {page} of {lastPage} ({total} rows)
        </span>
        <button type="button" className="secondary" onClick={() => setPage((current) => Math.min(lastPage, current + 1))} disabled={isLoading || page >= lastPage}>
          Next
        </button>
      </div>

      <StudentBulkHistoryCard history={history} />

      <StudentDeletionModals
        suspendOpen={suspendOpen}
        deleteOpen={deleteOpen}
        phraseInput={phraseInput}
        selectionCount={selectionCount}
        onPhraseChange={setPhraseInput}
        onCloseSuspend={() => setSuspendOpen(false)}
        onCloseDelete={() => setDeleteOpen(false)}
        onConfirmSuspend={queueSuspend}
        onConfirmDelete={queueDelete}
      />
    </section>
  );
}
