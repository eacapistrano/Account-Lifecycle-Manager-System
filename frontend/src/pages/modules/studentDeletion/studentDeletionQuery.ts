import type { StudentFiltersState } from "./studentDeletionTypes";

export function filtersToQuery(filters: StudentFiltersState, page: number, perPage: number): string {
  const query = new URLSearchParams();
  for (const [key, value] of Object.entries(filters)) {
    if (value.trim().length > 0) {
      query.set(key, value.trim());
    }
  }
  query.set("page", String(page));
  query.set("per_page", String(perPage));
  return query.toString();
}
