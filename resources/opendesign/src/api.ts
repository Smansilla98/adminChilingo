import { CONFIG } from "./config";

/** Llamada a la API del editor (sesión del panel + CSRF de Laravel). */
export async function api<T>(method: string, path: string, body?: unknown): Promise<T> {
  const headers: Record<string, string> = {
    Accept: "application/json",
    "X-CSRF-TOKEN": CONFIG.csrfToken,
    "X-Requested-With": "XMLHttpRequest",
  };
  const opts: RequestInit = { method, headers, credentials: "same-origin" };
  if (body) {
    headers["Content-Type"] = "application/json";
    opts.body = JSON.stringify(body);
  }
  const r = await fetch(CONFIG.apiBase + path.replace(/^\/api/, ""), opts);
  const data = await r.json().catch(() => ({}));
  if (!r.ok) throw new Error(data.error || data.message || "No se pudo completar la operación");
  return data as T;
}

/** Sube una imagen y devuelve su URL. */
export async function subirImagen(file: File): Promise<string | null> {
  const form = new FormData();
  form.append("file", file);
  const r = await fetch(CONFIG.apiBase + "/uploads", {
    method: "POST",
    body: form,
    credentials: "same-origin",
    headers: { Accept: "application/json", "X-CSRF-TOKEN": CONFIG.csrfToken },
  });
  const data = await r.json().catch(() => ({}));
  if (!r.ok) {
    alert(data.message || data.error || "No se pudo subir la imagen.");
    return null;
  }
  return data.url ?? null;
}
