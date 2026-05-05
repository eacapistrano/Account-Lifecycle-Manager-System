import type { StudentRow } from "./studentDeletionTypes";

type Props = {
  rows: StudentRow[];
  selectedIds: ReadonlySet<string>;
  disabled: boolean;
  onToggleOne: (externalAccountId: string, checked: boolean) => void;
  onTogglePage: (checked: boolean, pageIds: string[]) => void;
};

export function StudentDeletionTable({ rows, selectedIds, disabled, onToggleOne, onTogglePage }: Props) {
  const pageIds = rows.map((r) => r.external_account_id);
  const allSelected = pageIds.length > 0 && pageIds.every((id) => selectedIds.has(id));

  function headerCheckboxChange(checked: boolean) {
    onTogglePage(checked, pageIds);
  }

  return (
    <div className="audit-table-wrap">
      <table className="audit-table">
        <thead>
          <tr>
            <th className="col-check">
              <input type="checkbox" checked={allSelected} onChange={(ev) => headerCheckboxChange(ev.target.checked)} disabled={disabled || rows.length === 0} />
            </th>
            <th>Email</th>
            <th>Name</th>
            <th>Dept</th>
            <th>Year</th>
            <th>Graduation</th>
            <th>Status</th>
            <th>Registry ID</th>
          </tr>
        </thead>
        <tbody>
          {rows.length === 0 ? (
            <tr>
              <td colSpan={8} className="hint">
                No students match these filters.
              </td>
            </tr>
          ) : (
            rows.map((row) => (
              <tr key={row.id}>
                <td className="col-check">
                  <input
                    type="checkbox"
                    checked={selectedIds.has(row.external_account_id)}
                    onChange={(ev) => onToggleOne(row.external_account_id, ev.target.checked)}
                    disabled={disabled}
                  />
                </td>
                <td>{row.primary_email}</td>
                <td>{row.full_name ?? "—"}</td>
                <td>{row.department ?? "—"}</td>
                <td>{row.school_year ?? "—"}</td>
                <td>{row.graduation_date ?? "—"}</td>
                <td>
                  <span className={`account-pill ${row.suspended ? "suspended" : "active"}`}>{row.suspended ? "Suspended" : "Active"}</span>
                  {row.deletion_scheduled_at ? (
                    <span className="hint-inline block">Del: {new Date(row.deletion_scheduled_at).toLocaleDateString()}</span>
                  ) : null}
                </td>
                <td className="mono-cell">{row.external_account_id}</td>
              </tr>
            ))
          )}
        </tbody>
      </table>
    </div>
  );
}
