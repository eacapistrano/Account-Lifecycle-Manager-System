import { useEffect, useState } from "react";
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
  const [searchDraft, setSearchDraft] = useState(filters.search);

  useEffect(() => {
    setSearchDraft(filters.search);
  }, [filters.search]);

  function applySearch() {
    onFiltersChange({ ...filters, search: searchDraft.trim() });
  }

  function patchEmail(value: string) {
    onFiltersChange({ ...filters, email: value });
  }

  return (
    <div className="audit-filters student-filters">
      <label>
        Email
        <input
          value={filters.email}
          onChange={(ev) => patchEmail(ev.target.value)}
          placeholder="ava.chen@school.example"
          disabled={disabled}
        />
      </label>
      <label className="student-search-field">
        Search all columns
        <div className="student-search-row">
          <input
            value={searchDraft}
            onChange={(ev) => setSearchDraft(ev.target.value)}
            onKeyDown={(ev) => {
              if (ev.key === "Enter") {
                ev.preventDefault();
                applySearch();
              }
            }}
            placeholder="Name, dept, year, graduation, status, registry ID…"
            disabled={disabled}
          />
          <button type="button" onClick={applySearch} disabled={disabled}>
            Search
          </button>
        </div>
      </label>
      <label>
        Graduation status
        <input
          value={filters.graduation_status}
          onChange={(ev) => onFiltersChange({ ...filters, graduation_status: ev.target.value })}
          placeholder="graduated"
          disabled={disabled}
        />
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

