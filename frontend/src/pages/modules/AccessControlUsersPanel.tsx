import { useCallback, useEffect, useState } from "react";
import { apiRequest } from "../../lib/api";
import type { RoleRow, UserRow } from "./accessControlApi";
import { fetchRolesList, fetchUsersPage } from "./accessControlApi";

export function AccessControlUsersPanel() {
  const [roles, setRoles] = useState<RoleRow[]>([]);
  const [users, setUsers] = useState<UserRow[]>([]);
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");

  const [form, setForm] = useState({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    role_id: "",
  });

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

  async function createUser(e: React.FormEvent) {
    e.preventDefault();

    setMessage("");
    setError("");

    try {
      await apiRequest("/authorization/users", {
        method: "POST",
        body: JSON.stringify({
          name: form.name,
          email: form.email,
          password: form.password,
          password_confirmation: form.password_confirmation,
          role_id: Number(form.role_id),
        }),
      });

      setMessage("User created successfully.");

      setForm({
        name: "",
        email: "",
        password: "",
        password_confirmation: "",
        role_id: "",
      });

      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Create failed");
    }
  }

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
      {message ? (
        <p className="toast toast-success">{message}</p>
      ) : null}

      {error ? (
        <p className="toast toast-error">{error}</p>
      ) : null}

      <div className="access-panel">
        <h3>Create User</h3>

        <form onSubmit={createUser} className="grid-form">
          <input
            type="text"
            placeholder="Full Name"
            value={form.name}
            onChange={(e) =>
              setForm({
                ...form,
                name: e.target.value,
              })
            }
            required
          />

          <input
            type="email"
            placeholder="Email"
            value={form.email}
            onChange={(e) =>
              setForm({
                ...form,
                email: e.target.value,
              })
            }
            required
          />

          <input
            type="password"
            placeholder="Password"
            value={form.password}
            onChange={(e) =>
              setForm({
                ...form,
                password: e.target.value,
              })
            }
            required
          />

          <input
            type="password"
            placeholder="Confirm Password"
            value={form.password_confirmation}
            onChange={(e) =>
              setForm({
                ...form,
                password_confirmation: e.target.value,
              })
            }
            required
          />

          <select
            value={form.role_id}
            onChange={(e) =>
              setForm({
                ...form,
                role_id: e.target.value,
              })
            }
            required
          >
            <option value="">Select Role</option>

            {roles.map((r) => (
              <option key={r.id} value={r.id}>
                {r.name}
              </option>
            ))}
          </select>

          <button type="submit">
            Create User
          </button>
        </form>
      </div>

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
                    <select
                      value={u.role_id ?? ""}
                      onChange={(ev) =>
                        void saveUserRole(
                          u,
                          Number(ev.target.value)
                        )
                      }
                      className="role-select"
                    >
                      {roles.map((r) => (
                        <option
                          key={r.id}
                          value={r.id}
                        >
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

        {users.length === 0 ? (
          <p className="hint">
            No users returned.
          </p>
        ) : null}
      </div>
    </>
  );
}