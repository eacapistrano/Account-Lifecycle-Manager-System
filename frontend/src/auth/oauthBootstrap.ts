export function readOAuthTokenFromUrl(): string | null {
  const params = new URLSearchParams(window.location.search);

  return params.get("api_token") ?? params.get("token");
}

export function stripOAuthTokenFromUrl(): void {
  const params = new URLSearchParams(window.location.search);

  if (!params.has("api_token") && !params.has("token")) {
    return;
  }

  params.delete("api_token");
  params.delete("token");

  const query = params.toString();
  const nextUrl = query
    ? `${window.location.pathname}?${query}${window.location.hash}`
    : `${window.location.pathname}${window.location.hash}`;

  window.history.replaceState({}, document.title, nextUrl);
}
