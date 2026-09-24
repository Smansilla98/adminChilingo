import { useEffect, useRef, useState } from "preact/hooks";
import { Trash2, Upload } from "lucide-preact";
import { api } from "../api";
import { CONFIG } from "../config";

/**
 * Pestaña "Marca" (agregado de La Chilinga a OpenDesign): logos oficiales, kit de marca
 * compartido y fotos de la Biblioteca. Un toque agrega la imagen al diseño.
 */
interface Item {
  id: string;
  url: string;
  label: string;
  kit_id?: number;
}
interface Grupo {
  clave: string;
  titulo: string;
  items: Item[];
}

export function MarcaPanel({ onAgregar }: { onAgregar: (url: string) => void }) {
  const [grupos, setGrupos] = useState<Grupo[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [subiendo, setSubiendo] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);

  const cargar = () =>
    api<{ grupos: Grupo[] }>("GET", "/marca")
      .then((r) => setGrupos(r.grupos))
      .catch((e) => setError(e.message));

  useEffect(() => {
    cargar();
  }, []);

  const subirKit = async (files: FileList | null) => {
    if (!files?.length) return;
    setSubiendo(true);
    const form = new FormData();
    form.append("archivo", files[0]);
    form.append("titulo", files[0].name.replace(/\.[^.]+$/, ""));
    try {
      const r = await fetch(CONFIG.apiBase + "/marca/kit", {
        method: "POST",
        body: form,
        credentials: "same-origin",
        headers: { Accept: "application/json", "X-CSRF-TOKEN": CONFIG.csrfToken },
      });
      if (!r.ok) throw new Error((await r.json().catch(() => ({}))).message || "No se pudo subir.");
      await cargar();
    } catch (e) {
      alert((e as Error).message);
    } finally {
      setSubiendo(false);
    }
  };

  const quitarKit = async (item: Item) => {
    if (!item.kit_id || !confirm(`¿Quitar «${item.label}» del kit de marca?`)) return;
    await api("DELETE", `/marca/kit/${item.kit_id}`).catch((e) => alert(e.message));
    await cargar();
  };

  if (error) return <p class="text-[11px] text-red-500">{error}</p>;
  if (!grupos) return <p class="text-[11px] text-zinc-400">Cargando…</p>;

  return (
    <div class="flex flex-col gap-4">
      {CONFIG.puedeGestionarKit && (
        <div>
          <button
            class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-[11px] font-semibold border border-dashed border-zinc-300 bg-white text-zinc-600 cursor-pointer hover:border-accent"
            onClick={() => inputRef.current?.click()}
            disabled={subiendo}
          >
            <Upload size={13} /> {subiendo ? "Subiendo…" : "Agregar al kit de marca"}
          </button>
          <input ref={inputRef} type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="hidden" onChange={(e) => subirKit((e.target as HTMLInputElement).files)} />
        </div>
      )}
      {grupos.map((g) => (
        <section key={g.clave}>
          <h3 class="text-[11px] font-semibold text-zinc-500 uppercase tracking-wider m-0 mb-2">{g.titulo}</h3>
          {g.items.length === 0 ? (
            <p class="text-[11px] text-zinc-400 m-0">Vacío.</p>
          ) : (
            <div class="grid grid-cols-2 gap-2">
              {g.items.map((it) => (
                <div key={it.id} class="relative group">
                  <button
                    class="w-full aspect-square rounded-lg border border-zinc-200 bg-zinc-50 p-1 cursor-pointer hover:border-accent overflow-hidden"
                    title={`Agregar ${it.label}`}
                    aria-label={`Agregar ${it.label}`}
                    onClick={() => onAgregar(it.url)}
                  >
                    <img src={it.url} alt="" loading="lazy" class="w-full h-full object-contain" />
                  </button>
                  {it.kit_id && CONFIG.puedeGestionarKit && (
                    <button
                      class="absolute top-1 right-1 p-1 rounded bg-white/90 border-none cursor-pointer text-zinc-500 hover:text-red-500"
                      title="Quitar del kit"
                      aria-label={`Quitar ${it.label} del kit`}
                      onClick={() => quitarKit(it)}
                    >
                      <Trash2 size={11} />
                    </button>
                  )}
                  <p class="text-[10px] text-zinc-500 truncate m-0 mt-0.5">{it.label}</p>
                </div>
              ))}
            </div>
          )}
        </section>
      ))}
    </div>
  );
}
