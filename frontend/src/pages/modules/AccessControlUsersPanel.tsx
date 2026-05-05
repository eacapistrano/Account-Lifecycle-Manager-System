import { useCallback, useEffect, useState } from "react";
import { apiRequest } from "../../lib/api";
import type { RoleRow, UserRow } from "./accessControlApi";
import { fetchRolesList, fetchUsersPage } from "./accessControlApi";

export function AccessControlUsersPanel() {
  const [roles, setRoles] = useState<RoleRow[]>([]);
  const [users, setUsers] = useState<UserRow[]>([]);
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");

  const load = useCallback(async () => {
    setRoles(await fetchRolesList());
    setUsers(await fetchUsersPage());
  }, []);

  useEffect(() => {
    setMessage("");
    setError("");
    void (async () => {
      try {
        await load();
      } catch (e) {
        setError(e instanceof Error ? e.message : "Request failed");
      }
    })();
  }, [load]);

  async function saveUserRole(userRow: UserRow, roleId: number) {
    setError("");
    setMessage("");
    try {
      await apiRequest(`/authorization/users/${userRow.id}/role`, {
        method: "PATCH",
        body: JSON.stringify({ role_id: roleId }),
      });
      setMessage("User role saved.");
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Save failed");
    }
  }

  return (
    <>
      {message ? <p className="toast toast-success">{message}</p> : null}
      {error ? <p className="toast toast-error">{error}</p> : null}
      <div className="access-panel">
        <h3>Users</h3>
        <div className="audit-table-wrap">
          <table className="audit-table data-table-tight">
            <thead>
              <tr>
                <th>Email</th>
                <th>Name</th>
                <th>Role</th>
              </tr>
            </thead>
            <tbody>
              {users.map((u) => (
                <tr key={u.id}>
                  <td>{u.email}</td>
                  <td>{u.name}</td>
                  <td>
                    <select value={u.role_id ?? ""} onChange={(ev) => void saveUserRole(u, Number(ev.target.value))} className="role-select">
                      {roles.map((r) => (
                        <option key={r.id} value={r.id}>
                          {r.name}
                        </option>
                      ))}
                    </select>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {users.length === 0 ? <p className="hint">No users returned.</p> : null}
      </div>
    </>
  );
}
