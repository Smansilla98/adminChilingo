/**
 * Configuración que inyecta la vista Blade (resources/views/disenos/editor.blade.php).
 * Integración de OpenDesign en La Chilinga: el backend es Laravel (DisenoEditorController).
 */
export interface OpenDesignConfig {
  basePath: string; // ruta del editor en el panel, ej. "/disenos"
  apiBase: string; // ej. "/disenos/api"
  panelUrl: string; // volver al panel
  logoUrl?: string; // logo de La Chilinga para el encabezado
  csrfToken: string;
  puedeGestionarKit: boolean;
}

export const CONFIG: OpenDesignConfig = (window as unknown as { __OPENDESIGN__: OpenDesignConfig }).__OPENDESIGN__;
