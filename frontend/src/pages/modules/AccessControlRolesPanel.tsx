import { useCallback, useEffect, useState } from "react";
import { apiRequest, buildApiUrl, createApiHeaders } from "../../lib/api";
import type { PermissionRow, RoleRow } from "./accessControlApi";
import { fetchPermissionsList, fetchRolesList } from "./accessControlApi";

type Props = {
  canView: boolean;
  canManage: boolean;
};

export function AccessControlRolesPanel({ canView, canManage }: Props) {
  const [roles, setRoles] = useState<RoleRow[]>([]);
  const [permissions, setPermissions] = useState<PermissionRow[]>([]);
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [picked, setPicked] = useState<Record<string, boolean>>({});
  const [newSlug, setNewSlug] = useState("");
  const [newName, setNewName] = useState("");

  const loadRoles = useCallback(async () => {
    if (!canView && !canManage) {
      return;
    }
    setRoles(await fetchRolesList());
  }, [canManage, canView]);

  const loadPermissions = useCallback(async () => {
    if (!canManage) {
      return;
    }
    setPermissions(await fetchPermissionsList());
  }, [canManage]);

  useEffect(() => {
    setMessage("");
    setError("");
    void (async () => {
      try {
        await loadRoles();
        await loadPermissions();
      } catch (e) {
        setError(e instanceof Error ? e.message : "Request failed");
      }
    })();
  }, [loadPermissions, loadRoles]);

  const selected = roles.find((r) => r.id === selectedId) ?? null;

  useEffect(() => {
    if (!selected) {
      setPicked({});
      return;
    }
    const next: Record<string, boolean> = {};
    permissions.forEach((p) => {
      next[p.slug] = selected.permissions.some((x) => x.slug === p.slug);
    });
    setPicked(next);
  }, [selected, permissions]);

  async function saveRolePatches() {
    if (!selected || !canManage) {
      return;
    }
    const permission_slugs = Object.entries(picked)
      .filter(([, v]) => v)
      .map(([k]) => k);
    setError("");
    setMessage("");
    try {
      await apiRequest<{ data: RoleRow }>(`/authorization/roles/${selected.id}`, {
        method: "PATCH",
        body: JSON.stringify({ permission_slugs }),
      });
      setMessage("Role updated.");
      await loadRoles();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Save failed");
    }
  }

  async function createRole() {
    if (!canManage || !newSlug || !newName) {
      return;
    }
    const permission_slugs = Object.entries(picked)
      .filter(([, v]) => v)
      .map(([k]) => k);
    if (permission_slugs.length === 0) {
      setError("Select at least one permission.");
      return;
    }
    setError("");
    setMessage("");
    try {
      await apiRequest("/authorization/roles", {
        method: "POST",
        body: JSON.stringify({ slug: newSlug, name: newName, permission_slugs }),
      });
      setMessage("Role created.");
      setNewSlug("");
      setNewName("");
      await loadRoles();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Create failed");
    }
  }

  async function removeRole(role: RoleRow) {
    if (!canManage || role.is_system) {
      return;
    }
    setError("");
    if (!window.confirm(`Delete role “${role.name}”?`)) {
      return;
    }
    try {
      const headers = createApiHeaders();
      const response = await fetch(buildApiUrl(`/authorization/roles/${role.id}`), {
        method: "DELETE",
        headers,
      });
      const body: { message?: string } = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(typeof body.message === "string" ? body.message : "Delete failed");
      }
      setMessage("Role deleted.");
      setSelectedId(null);
      await loadRoles();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Delete failed");
    }
  }

  if (!canView && !canManage) {
    return null;
  }

  return (
    <>
      {message ? <p className="toast toast-success">{message}</p> : null}
      {error ? <p className="toast toast-error">{error}</p> : null}
      <div className="access-grid">
        <div className="access-panel">
          <h3>Roles</h3>
          <ul className="access-role-list">
            {roles.map((r) => (
              <li key={r.id} className="access-role-row">
                <button type="button" className={selectedId === r.id ? "access-role-pill active" : "access-role-pill"} onClick={() => setSelectedId(r.id)}>
                  {r.name} <span className="hint-inline">({r.slug})</span>
                </button>
                {canManage && !r.is_system ? (
                  <button type="button" className="secondary access-delete" onClick={() => void removeRole(r)}>
                    Delete
                  </button>
                ) : null}
              </li>
            ))}
          </ul>
        </div>
        {canManage && selected ? (
          <div className="access-panel">
            <h3>Edit {selected.name}</h3>
            <div className="perm-grid">
              {permissions.map((p) => (
                <label key={p.id} className="perm-row">
                  <input
                    type="checkbox"
                    checked={Boolean(picked[p.slug])}
                    onChange={(ev) => setPicked((prev) => ({ ...prev, [p.slug]: ev.target.checked }))}
                  />
                  <span>{p.name}</span>
                  <span className="hint-inline">{p.slug}</span>
                </label>
              ))}
            </div>
            <button type="button" onClick={() => void saveRolePatches()}>
              Save role
            </button>
          </div>
        ) : null}
        {canManage ? (
          <div className="access-panel">
            <h3>New custom role</h3>
            <div className="access-form-row">
              <label>
                Slug
                <input value={newSlug} onChange={(e) => setNewSlug(e.target.value)} placeholder="e.g. helpdesk" autoComplete="off" />
              </label>
              <label>
                Name
                <input value={newName} onChange={(e) => setNewName(e.target.value)} placeholder="Display name" autoComplete="off" />
              </label>
            </div>
            <p className="hint">Pick permissions in the editor above (or select a role to copy), then create.</p>
            <button type="button" className="secondary" onClick={() => void createRole()}>
              Create role
            </button>
          </div>
        ) : null}
      </div>
    </>
  );
}
