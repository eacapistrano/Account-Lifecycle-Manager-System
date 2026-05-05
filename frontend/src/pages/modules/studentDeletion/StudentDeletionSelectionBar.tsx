import { Link } from "react-router-dom";

type Props = {
  selectionCount: number;
  isLoading: boolean;
  canSuspend: boolean;
  canDelete: boolean;
  onSuspendClick: () => void;
  onDeleteClick: () => void;
};

export function StudentDeletionSelectionBar({
  selectionCount,
  isLoading,
  canSuspend,
  canDelete,
  onSuspendClick,
  onDeleteClick,
}: Props) {
  return (
    <div className="audit-actions wrap-bar">
      <span className="hint">{selectionCount} selected</span>
      {canSuspend ? (
        <button type="button" onClick={onSuspendClick} disabled={isLoading || selectionCount === 0}>
          Suspend selected
        </button>
      ) : null}
      {canDelete ? (
        <button type="button" className="danger-button" onClick={onDeleteClick} disabled={isLoading || selectionCount === 0}>
          Delete selected…
        </button>
      ) : null}
      <Link className="hint-inline dashboard-link" to="/audit-logs">
        Review audit trail →
      </Link>
    </div>
  );
}
