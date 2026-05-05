import { apiRequest, buildApiUrl, createApiHeaders } from "../../lib/api";

export type PermissionRow = { id: number; slug: string; name: string; description: string | null };
export type RoleRow = {
  id: number;
  slug: string;
  name: string;
  is_system: boolean;
  permissions: PermissionRow[];
};
export type UserRow = { id: number; name: string; email: string; role_id: number | null; role: RoleRow | null };

type PaginatedUsers = { data: UserRow[]; total: number };

export async function fetchRolesList(): Promise<RoleRow[]> {
  const res = await apiRequest<{ data: RoleRow[] }>("/authorization/roles");
  return res.data;
}

export async function fetchPermissionsList(): Promise<PermissionRow[]> {
  const res = await apiRequest<{ data: PermissionRow[] }>("/authorization/permissions");
  return res.data;
}

export async function fetchUsersPage(): Promise<UserRow[]> {
  const headers = createApiHeaders();
  const response = await fetch(buildApiUrl("/authorization/users?per_page=100"), { headers });
  const body: { data?: PaginatedUsers; message?: string } = await response.json();
  if (!response.ok) {
    throw new Error(typeof body.message === "string" ? body.message : "Failed to load users");
  }
  return body.data?.data ?? [];
}
