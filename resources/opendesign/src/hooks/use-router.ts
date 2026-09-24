import { useState, useEffect, useCallback } from "preact/hooks";
import { CONFIG } from "../config";

/**
 * El editor vive bajo CONFIG.basePath (ej. /disenos). Los componentes siguen navegando
 * con rutas relativas al editor ("/" y "/design/:id") como en OpenDesign original.
 */
const base = CONFIG.basePath.replace(/\/$/, "");
const relativa = (pathname: string) => (pathname.startsWith(base) ? pathname.slice(base.length) : pathname) || "/";

export function useRouter() {
  const [path, setPath] = useState(relativa(window.location.pathname));

  const navigate = useCallback((to: string) => {
    window.history.pushState(null, "", base + (to === "/" ? "" : to));
    setPath(to);
  }, []);

  useEffect(() => {
    const handler = () => setPath(relativa(window.location.pathname));
    window.addEventListener("popstate", handler);
    return () => window.removeEventListener("popstate", handler);
  }, []);

  const match = path.match(/^\/design\/([^/]+)$/);
  const designId = match ? match[1] : null;

  return { path, navigate, designId };
}
