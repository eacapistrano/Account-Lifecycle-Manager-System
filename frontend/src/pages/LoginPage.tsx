import { useEffect, useState } from "react";
import type { FormEvent } from "react";
import { Navigate, useNavigate } from "react-router-dom";
import { useAuth } from "../auth/useAuth";
import { BACKEND_WEB_URL } from "../lib/backendUrl";

export function LoginPage() {
  const { isAuthenticated, login } = useAuth();
  const navigate = useNavigate();
  const [email, setEmail] = useState("admin@example.com");
  const [password, setPassword] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(() => {
    return new URLSearchParams(window.location.search).get("error") ?? "";
  });

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const oauthError = params.get("error");

    if (!oauthError) {
      return;
    }

    setError(oauthError);
    params.delete("error");

    const query = params.toString();
    const nextUrl = query
      ? `${window.location.pathname}?${query}`
      : window.location.pathname;

    window.history.replaceState({}, document.title, nextUrl);
  }, []);

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    setBusy(true);
    setError("");

    try {
      await login({ email, password });
      navigate("/dashboard", { replace: true });
    } catch (err) {
      const message = err instanceof Error ? err.message : "Unable to log in.";
      setError(message);
    } finally {
      setBusy(false);
    }
  }

  if (isAuthenticated) {
    return <Navigate to="/dashboard" replace />;
  }

  return (
    <section className="auth-wrap">
      <form className="card stack auth-card" onSubmit={onSubmit}>
        <h1>IT Admin Login</h1>
        <p className="hint">Sign in to manage student lifecycle actions, imports, and audit history.</p>
        <label>
          Email
          <input value={email} onChange={(event) => setEmail(event.target.value)} />
        </label>
        <label>
          Password
          <input
            type="password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
          />
        </label>
        <button type="submit" disabled={busy}>
          {busy ? "Signing in..." : "Sign in"}
        </button>

        <a
          href={`${BACKEND_WEB_URL}/auth/google/redirect`}
          className="btn btn-danger w-100"
        >
          Login with Google
        </a>


        {error ? <p className="error">{error}</p> : null}
      </form>
    </section>
  );
}
