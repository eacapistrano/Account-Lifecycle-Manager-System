export const API_URL = import.meta.env.VITE_API_URL ?? "http://localhost:8000/api";

export function buildApiUrl(path: string): string {
  return `${API_URL}${path}`;
}

export function createApiHeaders(init?: RequestInit): Headers {
  const token = localStorage.getItem("api_token");
  const headers = new Headers(init?.headers);
  headers.set("Accept", "application/json");

  if (init?.body) {
    headers.set("Content-Type", "application/json");
  }

  if (token) {
    headers.set("Authorization", `Bearer ${token}`);
  }

  return headers;
}

/**
 * Build a single readable message from a failed API response body (Laravel JSON or plain text).
 */
export function parseApiErrorMessage(status: number, bodyText: string): string {
  const trimmed = bodyText.trim();
  if (!trimmed) {
    return `Request failed (HTTP ${status}).`;
  }

  try {
    const json = JSON.parse(trimmed) as {
      message?: unknown;
      errors?: Record<string, string[] | string>;
    };

    const fromValidation: string[] = [];
    if (json.errors && typeof json.errors === "object") {
      for (const [field, msgs] of Object.entries(json.errors)) {
        if (Array.isArray(msgs)) {
          for (const m of msgs) {
            if (typeof m === "string") {
              fromValidation.push(field ? `${field}: ${m}` : m);
            }
          }
        } else if (typeof msgs === "string") {
          fromValidation.push(field ? `${field}: ${msgs}` : msgs);
        }
      }
    }
    if (fromValidation.length > 0) {
      return fromValidation.join(" ");
    }

    if (typeof json.message === "string" && json.message.trim() !== "") {
      return json.message;
    }
  } catch {
    // Not JSON; fall through to raw body.
  }

  const maxLen = 800;
  return trimmed.length > maxLen ? `${trimmed.slice(0, maxLen)}…` : trimmed;
}

export async function apiRequest<T>(path: string, init?: RequestInit): Promise<T> {
  const headers = createApiHeaders(init);

  const response = await fetch(buildApiUrl(path), { ...init, headers });
  if (!response.ok) {
    const bodyText = await response.text();
    throw new Error(parseApiErrorMessage(response.status, bodyText));
  }

  return (await response.json()) as T;
}
