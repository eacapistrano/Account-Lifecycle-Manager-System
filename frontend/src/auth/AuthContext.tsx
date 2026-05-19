import { useCallback, useEffect, useMemo, useState } from "react";
import type { PropsWithChildren } from "react";
import { apiRequest } from "../lib/api";
import type { AuthUser, LoginPayload, LoginResponse } from "../types/auth";
import { AuthContext } from "./context";
import type { AuthContextValue } from "./context";
import { readOAuthTokenFromUrl, stripOAuthTokenFromUrl } from "./oauthBootstrap";

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
  const [isBootstrapping, setIsBootstrapping] = useState(
    () => readOAuthTokenFromUrl() !== null
  );

  const [token, setToken] = useState<string>(() => {
    return localStorage.getItem("api_token") ?? "";
  });

  const [user, setUser] = useState<AuthUser | null>(() => {
    return readSavedUser();
  });

  useEffect(() => {
    const oauthToken = readOAuthTokenFromUrl();

    if (!oauthToken) {
      return;
    }

    let cancelled = false;
    const tokenFromUrl: string = oauthToken;

    async function bootstrapOAuthSession() {
      setIsBootstrapping(true);
      localStorage.setItem("api_token", tokenFromUrl);
      setToken(tokenFromUrl);

      try {
        const profile = await apiRequest<AuthUser>("/me");
        if (cancelled) {
          return;
        }

        setUser(profile);
        localStorage.setItem("api_user", JSON.stringify(profile));
        stripOAuthTokenFromUrl();
      } catch {
        if (cancelled) {
          return;
        }

        localStorage.removeItem("api_token");
        localStorage.removeItem("api_user");
        setToken("");
        setUser(null);
        stripOAuthTokenFromUrl();
        window.location.replace(
          "/login?error=" +
            encodeURIComponent("Google sign-in succeeded but the session could not be loaded."),
        );
      } finally {
        if (!cancelled) {
          setIsBootstrapping(false);
        }
      }
    }

    void bootstrapOAuthSession();

    return () => {
      cancelled = true;
    };
  }, []);

  async function login(payload: LoginPayload): Promise<void> {
    const result = await apiRequest<LoginResponse>("/login", {
      method: "POST",
      body: JSON.stringify({
        ...payload,
        device_name: "react-spa",
      }),
    });

    localStorage.setItem("api_token", result.token);
    localStorage.setItem("api_user", JSON.stringify(result.user));

    setToken(result.token);
    setUser(result.user);
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
      isBootstrapping,
      hasPermission,
      login,
      logout,
    }),
    [hasPermission, isBootstrapping, token, user]
  );

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
}