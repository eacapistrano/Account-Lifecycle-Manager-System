import { useEffect, useState } from "react";
import { Navigate } from "react-router-dom";
import { useAuth } from "../../auth/useAuth";
import { AccessControlRolesPanel } from "./AccessControlRolesPanel";
import { AccessControlUsersPanel } from "./AccessControlUsersPanel";

export function AccessControlPage() {
  const { hasPermission } = useAuth();
  const canPage =
    hasPermission("roles.view") || hasPermission("roles.manage") || hasPermission("users.manage");
  const canRoles = hasPermission("roles.view") || hasPermission("roles.manage");
  const [tab, setTab] = useState<"roles" | "users">("roles");

  useEffect(() => {
    if (!hasPermission("roles.view") && !hasPermission("roles.manage") && hasPermission("users.manage")) {
      setTab("users");
    }
  }, [hasPermission]);

  if (!canPage) {
    return <Navigate to="/dashboard" replace />;
  }

  return (
    <section className="card stack access-module">
      <header>
        <h2>Access control</h2>
        <p className="hint">Roles, permissions, and user assignments.</p>
      </header>
      <div className="access-tabs">
        {canRoles ? (
          <button type="button" className={tab === "roles" ? "tab active" : "tab"} onClick={() => setTab("roles")}>
            Roles
          </button>
        ) : null}
        {hasPermission("users.manage") ? (
          <button type="button" className={tab === "users" ? "tab active" : "tab"} onClick={() => setTab("users")}>
            Users
          </button>
        ) : null}
      </div>
      {tab === "roles" && canRoles ? (
        <AccessControlRolesPanel canView={hasPermission("roles.view")} canManage={hasPermission("roles.manage")} />
      ) : null}
      {tab === "users" && hasPermission("users.manage") ? <AccessControlUsersPanel /> : null}
    </section>
  );
}
