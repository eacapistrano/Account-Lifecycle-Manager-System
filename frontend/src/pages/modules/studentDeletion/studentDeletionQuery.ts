import type { StudentFiltersState } from "./studentDeletionTypes";

export function filtersToQuery(filters: StudentFiltersState, page: number, perPage: number): string {
  const query = new URLSearchParams();
  for (const [key, value] of Object.entries(filters)) {
    const normalized = String(value ?? "").trim();
    if (normalized.length > 0) {
      query.set(key, normalized);
    }
  }
  query.set("page", String(page));
  query.set("per_page", String(perPage));
  return query.toString();
}
