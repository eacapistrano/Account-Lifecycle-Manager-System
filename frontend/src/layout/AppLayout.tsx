import { NavLink, Outlet, useNavigate } from "react-router-dom";
import { useAuth } from "../auth/useAuth";

export function AppLayout() {
  const { user, logout, hasPermission } = useAuth();
  const navigate = useNavigate();

  const accessVisible =
    hasPermission("roles.view") || hasPermission("roles.manage") || hasPermission("users.manage");

  const links = [
    { to: "/dashboard", label: "Dashboard" },
    { to: "/student-deletion", label: "Student deletion" },
    { to: "/policy-execution", label: "Policy execution" },
    { to: "/suspended-accounts", label: "Suspended accounts" },
    { to: "/audit-logs", label: "Audit logs" },
    ...(accessVisible ? [{ to: "/access-control", label: "Access control" }] : []),
  ];

  async function handleLogout() {
    await logout();
    navigate("/login", { replace: true });
  }

  return (
    <div className="app-shell">
      <aside className="sidebar">
        <div className="sidebar-brand">
          <strong>Admin</strong>
          <span>Student lifecycle</span>
        </div>
        <nav className="nav-links">
          {links.map((link) => (
            <NavLink key={link.to} to={link.to} className={({ isActive }) => (isActive ? "active" : "")} end={link.to === "/dashboard"}>
              {link.label}
            </NavLink>
          ))}
        </nav>
        <div className="sidebar-footer">
          <div className="sidebar-user">{user?.name ?? "Admin"}</div>
          <div className="sidebar-meta">{user?.email ?? user?.role ?? ""}</div>
          <button type="button" className="sidebar-logout" onClick={() => void handleLogout()}>
            Sign out
          </button>
        </div>
      </aside>
      <div className="main-area">
        <header className="topbar">
          <div className="topbar-search">
            <input type="search" placeholder="Search students, IDs, or operations…" autoComplete="off" />
          </div>
          <div className="topbar-status">
            <span className="status-dot" aria-hidden />
            <span>Import / API online</span>
          </div>
        </header>
        <div className="layout-main">
          <Outlet />
        </div>
      </div>
    </div>
  );
}
