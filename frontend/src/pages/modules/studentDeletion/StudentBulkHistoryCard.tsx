import type { BulkHistoryRow } from "./studentDeletionTypes";

type Props = {
  history: BulkHistoryRow[];
};

export function StudentBulkHistoryCard({ history }: Props) {
  return (
    <div className="card-inner">
      <h3 className="dashboard-subheading">Recent bulk operations</h3>
      <div className="audit-table-wrap">
        <table className="audit-table data-table-tight">
          <thead>
            <tr>
              <th>When</th>
              <th>Action</th>
              <th>Status</th>
              <th>Progress</th>
              <th>Actor</th>
            </tr>
          </thead>
          <tbody>
            {history.length === 0 ? (
              <tr>
                <td colSpan={5} className="hint">
                  No bulk runs recorded yet.
                </td>
              </tr>
            ) : (
              history.map((row) => (
                <tr key={row.operation_id}>
                  <td>{row.requested_at ? new Date(row.requested_at).toLocaleString() : "—"}</td>
                  <td>{row.action}</td>
                  <td>{row.status}</td>
                  <td>
                    {row.processed}/{row.total} · OK {row.ok} · fail {row.failed}
                  </td>
                  <td>{row.actor?.email ?? "—"}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
