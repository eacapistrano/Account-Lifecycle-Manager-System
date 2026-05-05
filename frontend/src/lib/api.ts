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

export async function apiRequest<T>(path: string, init?: RequestInit): Promise<T> {
  const headers = createApiHeaders(init);

  const response = await fetch(buildApiUrl(path), { ...init, headers });
  if (!response.ok) {
    const message = await response.text();
    throw new Error(message || `Request failed (${response.status})`);
  }

  return (await response.json()) as T;
}
