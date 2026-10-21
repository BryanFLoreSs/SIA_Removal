export const API_URL = (process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api").replace(/\/$/, "");

export type CityPulseUser = { id: number; name: string; email: string };
export type AuthResponse = { user: CityPulseUser; token: string };

export async function api<T>(path: string, options: RequestInit = {}, token?: string): Promise<T> {
  const response = await fetch(`${API_URL}${path}`, {
    ...options,
    headers: {
      Accept: "application/json",
      ...(options.body ? { "Content-Type": "application/json" } : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...options.headers,
    },
    cache: "no-store",
  });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    const validation = payload.errors && Object.values(payload.errors).flat().join(" ");
    throw new Error(validation || payload.message || `Request failed (${response.status}).`);
  }
  return payload as T;
}

export function saveSession(result: AuthResponse) {
  localStorage.setItem("citypulse_token", result.token);
  localStorage.setItem("citypulse_user", JSON.stringify(result.user));
}

export function clearSession() {
  localStorage.removeItem("citypulse_token");
  localStorage.removeItem("citypulse_user");
}
