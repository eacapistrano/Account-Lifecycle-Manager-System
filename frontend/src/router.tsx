import { Navigate, createBrowserRouter } from "react-router-dom";
import { ProtectedApp } from "./layout/ProtectedApp";
import { DashboardPage } from "./pages/DashboardPage";
import { LoginPage } from "./pages/LoginPage";
import { AccessControlPage } from "./pages/modules/AccessControlPage";
import { AuditLogsShellPage } from "./pages/modules/AuditLogsShellPage";
import { PolicyExecutionShellPage } from "./pages/modules/PolicyExecutionShellPage";
import { StudentDeletionShellPage } from "./pages/modules/StudentDeletionShellPage";
import { SuspendedAccountsShellPage } from "./pages/modules/SuspendedAccountsShellPage";

export const appRouter = createBrowserRouter([
  {
    path: "/login",
    element: <LoginPage />,
  },
  {
    path: "/",
    element: <ProtectedApp />,
    children: [
      { index: true, element: <Navigate to="/dashboard" replace /> },
      { path: "dashboard", element: <DashboardPage /> },
      { path: "student-deletion", element: <StudentDeletionShellPage /> },
      { path: "policy-execution", element: <PolicyExecutionShellPage /> },
      { path: "suspended-accounts", element: <SuspendedAccountsShellPage /> },
      { path: "audit-logs", element: <AuditLogsShellPage /> },
      { path: "access-control", element: <AccessControlPage /> },
    ],
  },
  {
    path: "*",
    element: <Navigate to="/dashboard" replace />,
  },
]);
