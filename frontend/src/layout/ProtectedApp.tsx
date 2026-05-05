import { Navigate } from "react-router-dom";
import { useAuth } from "../auth/useAuth";
import { AppLayout } from "./AppLayout";

export function ProtectedApp() {
  const { isAuthenticated } = useAuth();
  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  return <AppLayout />;
}
