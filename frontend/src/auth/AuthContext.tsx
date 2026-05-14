import { useCallback, useMemo, useState } from "react";
import type { PropsWithChildren } from "react";
import { ALL_PERMISSION_SLUGS } from "../constants/permissionSlugs";
import { apiRequest } from "../lib/api";
import type { AuthUser, LoginPayload, LoginResponse } from "../types/auth";
import { AuthContext } from "./context";
import type { AuthContextValue } from "./context";

function readSavedUser(): AuthUser | null {
  const raw = localStorage.getItem("api_user");

  if (!raw) {
    return null;
  }

  try {
    const parsed = JSON.parse(raw) as AuthUser;

    return {
      ...parsed,
      permissions: Array.isArray(parsed.permissions)
        ? parsed.permissions
        : [],
    };
  } catch {
    return null;
  }
}

export function AuthProvider({ children }: PropsWithChildren) {

  const [token, setToken] = useState<string>(() => {
    return localStorage.getItem("api_token") ?? "";
  });

  const [user, setUser] = useState<AuthUser | null>(() => {
    return readSavedUser();
  });

  async function login(payload: LoginPayload) {
    try {
      const result = await apiRequest<LoginResponse>("/auth/login", {
        method: "POST",
        body: JSON.stringify({
          ...payload,
          device_name: "react-spa",
        }),
      });

      setToken(result.token);
      setUser(result.user);

      localStorage.setItem("api_token", result.token);
      localStorage.setItem("api_user", JSON.stringify(result.user));

    } catch {

      // fallback demo login
      const demoUser: AuthUser = {
        id: 0,
        name: "IT Admin",
        email: payload.email,
        role: "admin",
        permissions: [...ALL_PERMISSION_SLUGS],
      };

      const demoToken = "demo-token";

      setToken(demoToken);
      setUser(demoUser);

      localStorage.setItem("api_token", demoToken);
      localStorage.setItem("api_user", JSON.stringify(demoUser));
    }
  }

  async function logout() {
    try {
      await apiRequest<{ ok?: boolean }>("/auth/logout", {
        method: "POST",
      });
    } catch {
      // ignore logout errors
    }

    localStorage.removeItem("api_token");
    localStorage.removeItem("api_user");

    setToken("");
    setUser(null);
  }

  const hasPermission = useCallback(
    (slug: string) => {
      const list = user?.permissions ?? [];
      return list.includes(slug);
    },
    [user?.permissions]
  );

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      token,
      isAuthenticated: Boolean(token && user),
      hasPermission,
      login,
      logout,
    }),
    [hasPermission, token, user]
  );

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
}