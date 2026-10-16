import { useEffect, useRef, useState } from "preact/hooks";
import { BookOpen, ExternalLink, Trash2, Upload } from "lucide-preact";
import { api } from "../api";
import { CONFIG } from "../config";
import { useEditor } from "../context";

/**
 * Pestaña "Marca" (agregado de La Chilinga a OpenDesign): manual de marca con su paleta,
 * logos oficiales, kit de marca compartido y fotos de la Biblioteca. Un toque agrega la
 * imagen al diseño; un toque en un color lo aplica a lo seleccionado o al fondo.
 */
interface Color {
  nombre: string;
  hex: string;
  uso: string;
}
interface Sede {
  sede: string;
  fondo: string;
  sombra: string;
  acento: string;
}
interface Manual {
  version: string;
  url: string;
  paleta: Color[];
  sedes: Sede[];
}
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
  const { selectedObject, updateSelectedObject, setBackground } = useEditor();
  const [grupos, setGrupos] = useState<Grupo[] | null>(null);
  const [manual, setManual] = useState<Manual | null>(null);
  const [verSedes, setVerSedes] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [subiendo, setSubiendo] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);

  const cargar = () =>
    api<{ grupos: Grupo[]; manual: Manual | null }>("GET", "/marca")
      .then((r) => {
        setGrupos(r.grupos);
        setManual(r.manual);
      })
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

  // Con algo seleccionado pinta ese elemento; si no, el fondo de la página.
  const aplicar = (hex: string) => (selectedObject ? updateSelectedObject({ fill: hex }) : setBackground("color", hex));
  const muestra = (hex: string, titulo: string, clase = "size-7") => (
    <button
      key={titulo}
      class={`${clase} shrink-0 rounded-md border border-black/10 cursor-pointer hover:scale-110 transition-transform`}
      style={{ background: hex }}
      title={`${titulo} · ${hex}`}
      aria-label={`Aplicar ${titulo} (${hex})`}
      onClick={() => aplicar(hex)}
    />
  );

  if (error) return <p class="text-xs text-red-500">{error}</p>;
  if (!grupos) return <p class="text-xs text-zinc-500">Cargando…</p>;

  return (
    <div class="flex flex-col gap-4">
      {manual && (
        <section class="rounded-xl border border-zinc-200 bg-white p-3">
          <a
            href={manual.url}
            target="_blank"
            rel="noopener"
            class="flex items-center gap-2.5 rounded-lg bg-[#15110E] px-3 py-2.5 text-white no-underline hover:opacity-90"
          >
            <BookOpen size={16} />
            <span class="flex-1 min-w-0">
              <span class="block text-xs font-semibold">Manual de marca</span>
              <span class="block text-[11px] text-white/70">{manual.version} · PDF</span>
            </span>
            <ExternalLink size={13} />
          </a>
          <h3 class="text-xs font-semibold text-zinc-500 uppercase tracking-wider m-0 mt-3 mb-1">Colores</h3>
          <p class="text-[11px] text-zinc-500 m-0 mb-2">{selectedObject ? "Tocá un color para aplicarlo a lo seleccionado." : "Tocá un color para usarlo de fondo, o seleccioná un elemento primero."}</p>
          <div class="grid grid-cols-5 gap-1.5">
            {manual.paleta.map((c) => (
              <div key={c.hex} class="flex flex-col items-center gap-0.5">
                {muestra(c.hex, c.nombre, "w-full aspect-square")}
                <span class="text-[10px] text-zinc-600 truncate max-w-full">{c.nombre}</span>
              </div>
            ))}
          </div>
          <button
            class="mt-3 w-full text-left text-[11px] font-semibold text-zinc-600 bg-transparent border-none p-0 cursor-pointer hover:text-zinc-900"
            aria-expanded={verSedes}
            onClick={() => setVerSedes(!verSedes)}
          >
            {verSedes ? "▾" : "▸"} Colores de cada sede
          </button>
          {verSedes && (
            <ul class="list-none m-0 mt-2 p-0 flex flex-col gap-1.5">
              {manual.sedes.map((s) => (
                <li key={s.sede} class="flex items-center gap-1.5">
                  <span class="flex-1 min-w-0 truncate text-[11px] text-zinc-700">{s.sede}</span>
                  {muestra(s.fondo, `${s.sede} · fondo`, "size-5")}
                  {muestra(s.sombra, `${s.sede} · sombra del patrón`, "size-5")}
                  {muestra(s.acento, `${s.sede} · acento`, "size-5")}
                </li>
              ))}
            </ul>
          )}
        </section>
      )}
      {CONFIG.puedeGestionarKit && (
        <div>
          <button
            class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold border border-dashed border-zinc-300 bg-white text-zinc-600 cursor-pointer hover:border-accent"
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
          <h3 class="text-xs font-semibold text-zinc-500 uppercase tracking-wider m-0 mb-2">{g.titulo}</h3>
          {g.items.length === 0 ? (
            <p class="text-xs text-zinc-500 m-0">Vacío.</p>
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
                  <p class="text-[11px] text-zinc-500 truncate m-0 mt-0.5">{it.label}</p>
                </div>
              ))}
            </div>
          )}
        </section>
      ))}
    </div>
  );
}
