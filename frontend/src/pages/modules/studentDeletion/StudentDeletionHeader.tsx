import { Link } from "react-router-dom";

type Props = {
  latestImportHint: string;
  isLoading: boolean;
  canImport: boolean;
  onRefresh: () => void;
  onResetFilters: () => void;
  onImport: () => void;
};

export function StudentDeletionHeader({ latestImportHint, isLoading, canImport, onRefresh, onResetFilters, onImport }: Props) {
  return (
    <header className="module-page-header">
      <div>
        <h2>Graduated student deletion</h2>
        <p className="hint">
          Registry-driven lifecycle actions (suspend/delete). Bulk jobs run asynchronously — monitor progress below. Last import visible on this page:{" "}
          <strong>{latestImportHint}</strong>
        </p>
      </div>
      <div className="audit-actions">
        <button type="button" className="secondary" onClick={onRefresh} disabled={isLoading}>
          {isLoading ? "Loading…" : "Refresh"}
        </button>
        <button type="button" className="secondary" onClick={onResetFilters} disabled={isLoading}>
          Reset filters
        </button>
        <Link className="hint-inline dashboard-link" to="/student-deletion/history">
          Deletion history
        </Link>
        {canImport ? (
          <button type="button" onClick={onImport} disabled={isLoading}>
            Queue import
          </button>
        ) : null}
      </div>
    </header>
  );
}
