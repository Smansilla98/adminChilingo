import { useState } from "preact/hooks";
import {
  Undo2,
  Redo2,
  ZoomIn,
  ZoomOut,
  Maximize,
  Download,
  Save,
  ChevronDown,
  Home,
} from "lucide-preact";
import { useEditor, CANVAS_SIZES } from "../context";

export function Toolbar() {
  const {
    canvasWidth,
    canvasHeight,
    setCanvasSize,
    undo,
    redo,
    canUndo,
    canRedo,
    zoom,
    fitScale,
    zoomToFit,
    zoomIn,
    zoomOut,
    exportPNG,
    saveDesign,
    saving,
    activeDesign,
    renameDesign,
    navigate,
  } = useEditor();

  const [showSizeDropdown, setShowSizeDropdown] = useState(false);
  const [editingName, setEditingName] = useState(false);
  const [nameValue, setNameValue] = useState("");

  const currentSize = CANVAS_SIZES.find(
    (s) => s.width === canvasWidth && s.height === canvasHeight
  );
  const sizeLabel = currentSize ? currentSize.label : `${canvasWidth} x ${canvasHeight}`;

  const startRename = () => {
    if (!activeDesign) return;
    setNameValue(activeDesign.name);
    setEditingName(true);
  };

  const finishRename = () => {
    if (activeDesign && nameValue.trim()) {
      renameDesign(activeDesign.id, nameValue.trim());
    }
    setEditingName(false);
  };

  return (
    <div class="flex items-center justify-between px-3 py-1.5 bg-white border-b border-zinc-200 shrink-0">
      {/* Left: Home + Design name + Canvas size */}
      <div class="flex items-center gap-3">
        <button
          class="p-1.5 rounded-md text-zinc-500 bg-transparent border-none cursor-pointer transition-all hover:bg-zinc-100 hover:text-zinc-900"
          onClick={() => navigate("/")}
          title="Volver a mis diseños"
        >
          <Home size={16} />
        </button>
        {activeDesign && (
          editingName ? (
            <input
              class="bg-zinc-100 border border-accent rounded px-2 py-0.5 text-xs text-zinc-900 outline-none w-40"
              value={nameValue}
              onInput={(e) => setNameValue((e.target as HTMLInputElement).value)}
              onBlur={finishRename}
              onKeyDown={(e) => {
                if (e.key === "Enter") finishRename();
                if (e.key === "Escape") setEditingName(false);
              }}
              autoFocus
            />
          ) : (
            <span
              class="text-xs font-semibold text-zinc-600 cursor-pointer hover:text-zinc-900 transition-colors"
              onDblClick={startRename}
            >
              {activeDesign.name}
            </span>
          )
        )}

        <div class="relative">
          <button
            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium text-zinc-500 bg-zinc-100 border border-zinc-300 cursor-pointer hover:text-zinc-900 hover:border-zinc-500 transition-all"
            onClick={() => setShowSizeDropdown(!showSizeDropdown)}
          >
            {sizeLabel}
            <ChevronDown size={12} />
          </button>
          {showSizeDropdown && (
            <>
              <div class="fixed inset-0 z-10" onClick={() => setShowSizeDropdown(false)} />
              <div class="absolute top-full left-0 mt-1 bg-white border border-zinc-300 rounded-lg shadow-xl z-20 min-w-[200px] py-1">
                {CANVAS_SIZES.map((s) => (
                  <button
                    key={s.label}
                    class={`w-full text-left px-3 py-1.5 text-xs cursor-pointer border-none transition-colors ${
                      s.width === canvasWidth && s.height === canvasHeight
                        ? "bg-accent/20 text-accent-strong"
                        : "text-zinc-600 bg-transparent hover:bg-zinc-100"
                    }`}
                    onClick={() => {
                      setCanvasSize(s.width, s.height);
                      setShowSizeDropdown(false);
                    }}
                  >
                    <span class="font-medium">{s.label}</span>
                    <span class="text-zinc-500 ml-2">
                      {s.width} x {s.height}
                    </span>
                  </button>
                ))}
              </div>
            </>
          )}
        </div>
      </div>

      {/* Center: Undo / Redo */}
      <div class="flex items-center gap-1">
        <button
          class="p-1.5 rounded-md text-zinc-500 bg-transparent border-none cursor-pointer transition-all hover:bg-zinc-100 hover:text-zinc-900 disabled:opacity-30 disabled:cursor-not-allowed"
          onClick={undo}
          disabled={!canUndo}
          title="Deshacer (Ctrl+Z)"
          aria-label="Deshacer (Ctrl+Z)"
        >
          <Undo2 size={16} />
        </button>
        <button
          class="p-1.5 rounded-md text-zinc-500 bg-transparent border-none cursor-pointer transition-all hover:bg-zinc-100 hover:text-zinc-900 disabled:opacity-30 disabled:cursor-not-allowed"
          onClick={redo}
          disabled={!canRedo}
          title="Rehacer (Ctrl+Shift+Z)"
          aria-label="Rehacer (Ctrl+Shift+Z)"
        >
          <Redo2 size={16} />
        </button>
      </div>

      {/* Right: Zoom + Export + Save */}
      <div class="flex items-center gap-1.5">
        <button
          class="p-1.5 rounded-md text-zinc-500 bg-transparent border-none cursor-pointer transition-all hover:bg-zinc-100 hover:text-zinc-900"
          onClick={zoomOut}
          title="Alejar"
          aria-label="Alejar"
        >
          <ZoomOut size={15} />
        </button>
        <span class="text-xs text-zinc-500 font-mono w-10 text-center">
          {Math.round((zoom / (fitScale || 1)) * 100)}%
        </span>
        <button
          class="p-1.5 rounded-md text-zinc-500 bg-transparent border-none cursor-pointer transition-all hover:bg-zinc-100 hover:text-zinc-900"
          onClick={zoomIn}
          title="Acercar"
          aria-label="Acercar"
        >
          <ZoomIn size={15} />
        </button>
        <button
          class="p-1.5 rounded-md text-zinc-500 bg-transparent border-none cursor-pointer transition-all hover:bg-zinc-100 hover:text-zinc-900"
          onClick={zoomToFit}
          title="Ajustar a la pantalla"
          aria-label="Ajustar a la pantalla"
        >
          <Maximize size={15} />
        </button>

        <div class="w-px h-5 bg-zinc-300 mx-1" />

        <button
          class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md text-xs font-semibold border border-zinc-300 cursor-pointer transition-all bg-transparent text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900"
          onClick={exportPNG}
          title="Exportar PNG"
          aria-label="Exportar PNG"
        >
          <Download size={13} />
          Exportar
        </button>
        <button
          class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-md text-xs font-semibold border-none cursor-pointer transition-all bg-primary text-white hover:bg-primary-hover disabled:opacity-50"
          onClick={saveDesign}
          disabled={saving || !activeDesign}
        >
          {saving ? <span class="spinner !border-white/30 !border-t-white" /> : <Save size={13} />}
          {saving ? "Guardando…" : "Guardar"}
        </button>
      </div>
    </div>
  );
}
