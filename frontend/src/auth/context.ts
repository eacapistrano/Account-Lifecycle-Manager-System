import { createContext } from "react";
import type { AuthUser, LoginPayload } from "../types/auth";

export type AuthContextValue = {
  user: AuthUser | null;
  token: string;
  isAuthenticated: boolean;
  hasPermission: (slug: string) => boolean;
  login: (payload: LoginPayload) => Promise<void>;
  logout: () => Promise<void>;
};

export const AuthContext = createContext<AuthContextValue | undefined>(undefined);
