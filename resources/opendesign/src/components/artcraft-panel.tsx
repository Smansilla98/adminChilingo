import { useEffect, useRef, useState } from "preact/hooks";
import { createPortal } from "preact/compat";
import * as fabric from "fabric";
import { ClipboardPaste, ExternalLink, ImageDown, ImagePlus, PanelTop, Replace, Wallpaper, X } from "lucide-preact";
import { subirImagen } from "../api";
import { CONFIG } from "../config";
import { useEditor } from "../context";

/**
 * Puente con ArtCraft (https://app.getartcraft.com, proyecto abierto de Storyteller).
 * Se puede usar dentro del admin (ventana sobre el editor) o en otra pestaña. Lo que se
 * hace allá no vuelve solo: el navegador no deja leer otra página. Este panel lleva el
 * diseño (o la imagen elegida) y trae el resultado: como capa nueva, como fondo o en
 * lugar de la imagen seleccionada.
 */
const ARTCRAFT_URL = "https://app.getartcraft.com/";
const ARTCRAFT_EDITAR = "https://app.getartcraft.com/edit-image";

/** Comprueba que la imagen subida se pueda mostrar antes de decir "listo". */
const cargable = (url: string) =>
  new Promise<boolean>((resolve) => {
    const img = new Image();
    img.crossOrigin = "anonymous";
    img.onload = () => resolve(true);
    img.onerror = () => resolve(false);
    img.src = url;
  });

type Destino = "capa" | "fondo" | "reemplazar";

export function ArtcraftPanel() {
  const { exportPNG, addImage, setBackground, replaceSelectedImage, selectedObject } = useEditor();
  const [destino, setDestino] = useState<Destino>("capa");
  const [guardarEnKit, setGuardarEnKit] = useState(false);
  const [estado, setEstado] = useState<{ tipo: "ok" | "error" | "trabajando"; texto: string } | null>(null);
  const [arrastrando, setArrastrando] = useState(false);
  const [adentro, setAdentro] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);
  const hayImagenElegida = selectedObject instanceof fabric.FabricImage;

  // Si la imagen elegida deja de estar seleccionada, no tiene sentido "reemplazar".
  useEffect(() => {
    if (destino === "reemplazar" && !hayImagenElegida) setDestino("capa");
  }, [hayImagenElegida, destino]);

  const traer = async (archivo: File | null | undefined) => {
    if (!archivo) return;
    if (!archivo.type.startsWith("image/")) {
      setEstado({ tipo: "error", texto: "Eso no es una imagen. Exportá desde ArtCraft en PNG o JPG." });
      return;
    }
    setEstado({ tipo: "trabajando", texto: "Subiendo…" });
    const url = await subirImagen(archivo);
    if (!url) {
      setEstado(null);
      return;
    }
    if (!(await cargable(url))) {
      setEstado({ tipo: "error", texto: "La imagen se subió pero no se puede mostrar. Probá de nuevo o avisá a administración." });
      return;
    }
    let ok = true;
    if (destino === "fondo") setBackground("image", url);
    else if (destino === "reemplazar") ok = (await replaceSelectedImage(url)) || (await addImage(url));
    else ok = await addImage(url);
    if (!ok) {
      setEstado({ tipo: "error", texto: "No se pudo poner la imagen en el diseño. Probá de nuevo." });
      return;
    }

    if (guardarEnKit && CONFIG.puedeGestionarKit) {
      const form = new FormData();
      form.append("archivo", archivo);
      form.append("titulo", archivo.name.replace(/\.[^.]+$/, "") || "ArtCraft");
      await fetch(CONFIG.apiBase + "/marca/kit", {
        method: "POST",
        body: form,
        credentials: "same-origin",
        headers: { Accept: "application/json", "X-CSRF-TOKEN": CONFIG.csrfToken },
      }).catch(() => null);
    }
    setEstado({ tipo: "ok", texto: destino === "fondo" ? "Listo: quedó de fondo." : destino === "reemplazar" ? "Listo: reemplazó la imagen elegida." : "Listo: se agregó al diseño." });
  };

  // Pegar con Ctrl+V una imagen copiada en ArtCraft mientras el panel está abierto.
  useEffect(() => {
    const alPegar = (e: ClipboardEvent) => {
      const destinoTexto = e.target as HTMLElement | null;
      if (destinoTexto?.closest("input, textarea, [contenteditable='true']")) return;
      const archivo = Array.from(e.clipboardData?.files ?? []).find((f) => f.type.startsWith("image/"));
      if (archivo) {
        e.preventDefault();
        traer(archivo);
      }
    };
    window.addEventListener("paste", alPegar);
    return () => window.removeEventListener("paste", alPegar);
  });

  const paso = "flex items-center justify-center size-5 rounded-full bg-zinc-900 text-white text-[11px] font-bold shrink-0";
  const boton = "w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold border border-zinc-300 bg-white text-zinc-700 cursor-pointer hover:border-accent disabled:opacity-50";

  return (
    <div class="flex flex-col gap-4 text-xs text-zinc-600">
      <p class="m-0">Editá con inteligencia artificial en ArtCraft (quitar fondo, generar, retocar) y traé el resultado acá. Necesitás una cuenta de ArtCraft.</p>

      <section class="flex flex-col gap-2">
        <h3 class="flex items-center gap-2 m-0 text-xs font-semibold text-zinc-800"><span class={paso}>1</span> Llevá tu diseño</h3>
        <button class={boton} onClick={() => exportPNG("chilinga-para-artcraft.png")}>
          <ImageDown size={13} /> Descargar esta página
        </button>
        <button class={boton} disabled={!selectedObject} onClick={() => exportPNG("chilinga-elemento-para-artcraft.png", true)} title={selectedObject ? "" : "Seleccioná un elemento en el diseño"}>
          <ImageDown size={13} /> Descargar solo lo seleccionado
        </button>
      </section>

      <section class="flex flex-col gap-2">
        <h3 class="flex items-center gap-2 m-0 text-xs font-semibold text-zinc-800"><span class={paso}>2</span> Editalo en ArtCraft</h3>
        <button class={`${boton} !bg-zinc-900 !text-white !border-zinc-900`} onClick={() => setAdentro(true)}>
          <PanelTop size={13} /> Abrir ArtCraft acá
        </button>
        <a href={ARTCRAFT_URL} target="_blank" rel="noopener" class={`${boton} no-underline`}>
          <ExternalLink size={13} /> Abrir en otra pestaña
        </a>
        <p class="m-0 text-[11px] text-zinc-500">Subí ahí la imagen que descargaste. Cuando termines, exportala o copiala.</p>
      </section>

      <section class="flex flex-col gap-2">
        <h3 class="flex items-center gap-2 m-0 text-xs font-semibold text-zinc-800"><span class={paso}>3</span> Traé el resultado</h3>
        <div class="grid grid-cols-3 gap-1" role="radiogroup" aria-label="Dónde va la imagen">
          {([
            ["capa", "Capa nueva", ImagePlus],
            ["fondo", "Fondo", Wallpaper],
            ["reemplazar", "Reemplazar", Replace],
          ] as const).map(([valor, texto, Icono]) => (
            <button
              key={valor}
              role="radio"
              aria-checked={destino === valor}
              disabled={valor === "reemplazar" && !hayImagenElegida}
              title={valor === "reemplazar" && !hayImagenElegida ? "Seleccioná primero la imagen a reemplazar" : ""}
              onClick={() => setDestino(valor)}
              class={`flex flex-col items-center gap-1 px-1 py-2 rounded-lg border text-[11px] cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed ${destino === valor ? "border-zinc-900 bg-zinc-900 text-white" : "border-zinc-300 bg-white text-zinc-600"}`}
            >
              <Icono size={14} />
              {texto}
            </button>
          ))}
        </div>
        <button
          class={`w-full rounded-lg border-2 border-dashed px-3 py-5 text-center cursor-pointer ${arrastrando ? "border-accent bg-accent/10" : "border-zinc-300 bg-zinc-50"}`}
          onClick={() => inputRef.current?.click()}
          onDragOver={(e) => { e.preventDefault(); setArrastrando(true); }}
          onDragLeave={() => setArrastrando(false)}
          onDrop={(e) => { e.preventDefault(); setArrastrando(false); traer(e.dataTransfer?.files?.[0]); }}
          disabled={estado?.tipo === "trabajando"}
        >
          <ClipboardPaste size={18} class="mx-auto mb-1 text-zinc-400" />
          <span class="block font-semibold text-zinc-700">Arrastrá la imagen o tocá para elegirla</span>
          <span class="block text-[11px] text-zinc-500 mt-0.5">También podés pegarla con Ctrl+V</span>
        </button>
        <input ref={inputRef} type="file" accept="image/png,image/jpeg,image/webp,image/gif" class="hidden" onChange={(e) => { traer((e.target as HTMLInputElement).files?.[0]); (e.target as HTMLInputElement).value = ""; }} />
        {CONFIG.puedeGestionarKit && (
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" checked={guardarEnKit} onChange={(e) => setGuardarEnKit((e.target as HTMLInputElement).checked)} />
            Guardarla también en el kit de marca
          </label>
        )}
        {estado && (
          <p role="status" class={`m-0 rounded-md px-2 py-1.5 ${estado.tipo === "error" ? "bg-red-50 text-red-700" : estado.tipo === "ok" ? "bg-emerald-50 text-emerald-700" : "bg-zinc-100 text-zinc-600"}`}>{estado.texto}</p>
        )}
      </section>

      {adentro && createPortal(
        <div class="fixed inset-0 z-[1000] flex flex-col bg-zinc-950/70 p-0 sm:p-4" role="dialog" aria-modal="true" aria-label="ArtCraft">
          <div class="flex flex-1 flex-col overflow-hidden bg-white sm:rounded-xl shadow-2xl">
            <div class="flex items-center gap-2 border-b border-zinc-200 px-3 py-2">
              <strong class="text-sm text-zinc-900">ArtCraft</strong>
              <span class="hidden text-xs text-zinc-500 md:inline">Cuando termines, descargá o copiá la imagen y soltala o pegala abajo.</span>
              <a href={ARTCRAFT_URL} target="_blank" rel="noopener" class="ml-auto inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs text-zinc-600 no-underline hover:bg-zinc-100" title="Si no podés ingresar acá, abrilo en otra pestaña">
                <ExternalLink size={13} /> Otra pestaña
              </a>
              <button class="inline-flex items-center gap-1 rounded-md border-none bg-zinc-900 px-2.5 py-1 text-xs font-semibold text-white cursor-pointer" onClick={() => setAdentro(false)}>
                <X size={13} /> Volver al diseño
              </button>
            </div>
            <iframe src={ARTCRAFT_EDITAR} title="ArtCraft" class="flex-1 w-full border-0" allow="clipboard-read; clipboard-write; fullscreen" />
            <button
              class={`flex items-center justify-center gap-2 border-0 border-t px-3 py-3 text-xs cursor-pointer ${arrastrando ? "bg-accent/10 text-zinc-900" : "bg-zinc-50 text-zinc-600"}`}
              onClick={() => inputRef.current?.click()}
              onDragOver={(e) => { e.preventDefault(); setArrastrando(true); }}
              onDragLeave={() => setArrastrando(false)}
              onDrop={(e) => { e.preventDefault(); setArrastrando(false); traer(e.dataTransfer?.files?.[0]); }}
            >
              <ClipboardPaste size={15} />
              <span><strong>Traer el resultado</strong> ({destino === "fondo" ? "como fondo" : destino === "reemplazar" ? "reemplazando la imagen elegida" : "como capa nueva"}): soltá la imagen acá, tocá para elegirla o hacé clic y pegala con Ctrl+V</span>
              {estado && <span class={estado.tipo === "error" ? "text-red-700" : estado.tipo === "ok" ? "text-emerald-700" : ""}> · {estado.texto}</span>}
            </button>
          </div>
        </div>,
        document.body
      )}

      <p class="m-0 text-[11px] text-zinc-500">ArtCraft es un proyecto abierto (MIT / Apache-2.0) de Storyteller. Lo que generes ahí se rige por su cuenta y sus créditos.</p>
    </div>
  );
}
