import type { StudentFiltersState } from "./studentDeletionTypes";

type Props = {
  filters: StudentFiltersState;
  perPage: number;
  disabled: boolean;
  onFiltersChange: (next: StudentFiltersState) => void;
  onPerPageChange: (n: number) => void;
};

export function StudentDeletionFiltersBar({
  filters,
  perPage,
  disabled,
  onFiltersChange,
  onPerPageChange,
}: Props) {
  function patch<K extends keyof StudentFiltersState>(key: K, value: StudentFiltersState[K]) {
    onFiltersChange({ ...filters, [key]: value });
  }

  return (
    <div className="audit-filters student-filters">
      <label>
        Department
        <input value={filters.department} onChange={(ev) => patch("department", ev.target.value)} placeholder="Science" disabled={disabled} />
      </label>
      <label>
        School year
        <input value={filters.school_year} onChange={(ev) => patch("school_year", ev.target.value)} placeholder="2026" disabled={disabled} />
      </label>
      <label>
        Graduation status
        <input
          value={filters.graduation_status}
          onChange={(ev) => patch("graduation_status", ev.target.value)}
          placeholder="graduated"
          disabled={disabled}
        />
      </label>
      <label>
        Graduated from
        <input type="date" value={filters.graduated_from} onChange={(ev) => patch("graduated_from", ev.target.value)} disabled={disabled} />
      </label>
      <label>
        Graduated to
        <input type="date" value={filters.graduated_to} onChange={(ev) => patch("graduated_to", ev.target.value)} disabled={disabled} />
      </label>
      <label className="audit-page-size">
        Rows
        <select value={perPage} onChange={(ev) => onPerPageChange(Number(ev.target.value))} disabled={disabled}>
          <option value={25}>25</option>
          <option value={50}>50</option>
          <option value={100}>100</option>
        </select>
      </label>
    </div>
  );
}
