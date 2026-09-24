import { useState, useCallback } from "preact/hooks";
import { Plus, Trash2, Edit3, Sparkles, ArrowLeft } from "lucide-preact";
import { CONFIG } from "../config";
import type { Design, Template } from "../types";
import { TemplateCard } from "./template-card";

interface HomeProps {
  designs: Design[];
  templates: Template[];
  navigate: (to: string) => void;
  createDesign: () => Promise<string | undefined>;
  deleteDesign: (id: string) => Promise<void>;
  renameDesign: (id: string, name: string) => Promise<void>;
  createFromTemplate: (template: Template) => Promise<string | undefined>;
}

export function Home({
  designs,
  templates,
  navigate,
  createDesign,
  deleteDesign,
  renameDesign,
  createFromTemplate,
}: HomeProps) {
  const [editingId, setEditingId] = useState<string | null>(null);
  const [editName, setEditName] = useState("");

  const handleCreate = useCallback(async () => {
    const id = await createDesign();
    if (id) navigate(`/design/${id}`);
  }, [createDesign, navigate]);

  const handleTemplateClick = useCallback(
    async (t: Template) => {
      const id = await createFromTemplate(t);
      if (id) navigate(`/design/${id}`);
    },
    [createFromTemplate, navigate]
  );

  const startRename = (id: string, name: string, e: Event) => {
    e.stopPropagation();
    setEditingId(id);
    setEditName(name);
  };

  const finishRename = () => {
    if (editingId && editName.trim()) renameDesign(editingId, editName.trim());
    setEditingId(null);
  };

  return (
    <div class="min-h-full bg-surface">
      {/* Encabezado alineado con el panel */}
      <header class="bg-white border-b border-zinc-200">
        <div class="max-w-6xl mx-auto px-6 py-4 flex flex-wrap items-center justify-between gap-4">
          <div class="flex items-center gap-3 min-w-0">
            {CONFIG.logoUrl && (
              <a href={CONFIG.panelUrl} class="shrink-0" aria-label="Volver al panel de La Chilinga">
                <img src={CONFIG.logoUrl} alt="" width={40} height={40} class="w-10 h-10 rounded-lg border border-zinc-200 bg-white p-1 object-contain" />
              </a>
            )}
            <div class="min-w-0">
              <nav aria-label="Estás en" class="text-xs text-zinc-500 mb-0.5">
                <a href={CONFIG.panelUrl} class="text-zinc-500 no-underline hover:text-zinc-800 hover:underline">Panel</a>
                <span class="mx-1.5 text-zinc-300" aria-hidden="true">/</span>
                <span>Contenido</span>
              </nav>
              <h1 class="text-xl font-extrabold tracking-tight text-zinc-900 m-0">Diseño</h1>
              <p class="text-xs text-zinc-500 mt-0.5 m-0">
                Flyers, historias y piezas con la identidad de La Chilinga · {designs.length} diseño{designs.length !== 1 ? "s" : ""}
              </p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <a href={CONFIG.panelUrl} class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-xs font-semibold no-underline border border-zinc-300 text-zinc-700 bg-white hover:bg-zinc-100 transition-all">
              <ArrowLeft size={14} /> Volver al panel
            </a>
            <button
              class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold border-none cursor-pointer bg-primary text-white hover:bg-primary-hover transition-all shadow-sm"
              onClick={handleCreate}
            >
              <Plus size={15} />
              Nuevo diseño
            </button>
          </div>
        </div>
      </header>

      <div class="max-w-6xl mx-auto px-6 py-6">
        {/* Templates section */}
        {templates.length > 0 && (
          <div class="mb-8">
            <div class="flex items-center gap-2 mb-3">
              <Sparkles size={14} class="text-accent-strong" />
              <h2 class="text-sm font-bold text-zinc-800 m-0">Empezar desde una plantilla</h2>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
              {templates.map((t) => (
                <TemplateCard key={t.id} template={t} onClick={() => handleTemplateClick(t)} />
              ))}
            </div>
          </div>
        )}

        {/* Designs grid */}
        {designs.length === 0 ? (
          <div class="text-center py-16 bg-white border border-zinc-200 rounded-xl">
            <div class="w-14 h-14 rounded-full bg-zinc-200 flex items-center justify-center mx-auto mb-4">
              <Plus size={24} class="text-zinc-500" />
            </div>
            <p class="text-sm font-semibold text-zinc-700 mb-1">Todavía no hay diseños</p>
            <p class="text-xs text-zinc-500 mb-4">Empezá con una plantilla de arriba o con un lienzo en blanco.</p>
            <button
              class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold border-none cursor-pointer bg-primary text-white hover:bg-primary-hover transition-all"
              onClick={handleCreate}
            >
              <Plus size={14} />
              Crear diseño
            </button>
          </div>
        ) : (
          <>
            <h2 class="text-sm font-bold text-zinc-800 mb-3 m-0">Tus diseños</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
              {designs.map((d) => (
                <div
                  key={d.id}
                  class="bg-white rounded-xl border border-zinc-200 overflow-hidden cursor-pointer transition-all hover:border-accent hover:shadow-md group"
                  onClick={() => navigate(`/design/${d.id}`)}
                >
                  {/* Preview area */}
                  <div class="aspect-[4/3] bg-zinc-100 flex items-center justify-center">
                    {d.thumbnail_url ? (
                      <img
                        src={d.thumbnail_url}
                        alt={d.name}
                        class="w-full h-full object-cover"
                      />
                    ) : (
                      <div class="text-zinc-300 text-[11px] font-medium">
                        {d.width} x {d.height}
                      </div>
                    )}
                  </div>

                  {/* Info */}
                  <div class="p-3">
                    {editingId === d.id ? (
                      <input
                        class="w-full bg-zinc-100 border border-accent rounded text-zinc-800 text-xs px-2 py-1 outline-none"
                        value={editName}
                        onInput={(e) => setEditName((e.target as HTMLInputElement).value)}
                        onBlur={finishRename}
                        onKeyDown={(e) => {
                          if (e.key === "Enter") finishRename();
                          if (e.key === "Escape") setEditingId(null);
                        }}
                        autoFocus
                        onClick={(e) => e.stopPropagation()}
                      />
                    ) : (
                      <div class="flex items-start justify-between">
                        <div class="min-w-0 flex-1">
                          <p class="text-xs font-semibold text-zinc-700 truncate m-0">
                            {d.name}
                          </p>
                          <p class="text-[11px] text-zinc-500 mt-0.5 m-0">
                            {d.width} x {d.height} &middot;{" "}
                            {new Date(d.updated_at).toLocaleDateString("es-AR")}
                          </p>
                        </div>
                        <div class="flex gap-0.5 opacity-100 md:opacity-0 md:group-hover:opacity-100 transition-opacity ml-2 shrink-0">
                          <button
                            class="p-1 rounded text-zinc-500 bg-transparent border-none cursor-pointer hover:text-zinc-700 transition-colors"
                            title="Renombrar"
                            aria-label={`Renombrar ${d.name}`}
                            onClick={(e) => startRename(d.id, d.name, e)}
                          >
                            <Edit3 size={12} />
                          </button>
                          <button
                            class="p-1 rounded text-zinc-500 bg-transparent border-none cursor-pointer hover:text-red-400 transition-colors"
                            title="Eliminar"
                            aria-label={`Eliminar ${d.name}`}
                            onClick={(e) => {
                              e.stopPropagation();
                              if (confirm(`¿Eliminar «${d.name}»? No se puede deshacer.`)) deleteDesign(d.id);
                            }}
                          >
                            <Trash2 size={12} />
                          </button>
                        </div>
                      </div>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </>
        )}
      </div>
    </div>
  );
}
