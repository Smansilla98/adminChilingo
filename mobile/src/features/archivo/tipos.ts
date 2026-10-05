/** Tipos del archivo histórico (ver docs/ARCHIVO_HISTORICO.md y /api/v1/archivo). */

export interface ImagenArchivo { chica: string; media: string; grande: string; completa: string; srcset: string | null }

export interface FotoResumen {
  id: number;
  slug: string;
  titulo: string;
  anio: number | null;
  fecha: string | null;
  descripcion: string | null;
  alt: string;
  ancho: number | null;
  alto: number | null;
  color: string | null;
  imagen: ImagenArchivo;
  url: string | null;
  acontecimiento: { titulo: string; slug: string; url: string | null } | null;
  sede: string | null;
  estado?: string;
  estado_etiqueta?: string;
  es_aporte?: boolean;
  notas_revision?: string | null;
}

export interface PersonaEnFoto { persona_id: number | null; nombre: string; detalle: string | null }

export interface FotoGestion extends FotoResumen {
  titulo_original: string | null;
  estado: string;
  estado_etiqueta: string;
  contexto: string | null;
  /** Etiquetas legibles del detalle (tipo, fuente, lugar compuesto, aportante público). */
  tipo: string | null;
  fuente: string | null;
  lugar: string | null;
  aportante: string | null;
  tipo_clave: string | null;
  fuente_clave: string | null;
  fuente_detalle: string | null;
  fotografo: string | null;
  credito: string | null;
  licencia: string | null;
  precision: string;
  fecha_iso: string | null;
  lugar_detalle: { lugar: string | null; ciudad: string | null; pais: string | null; latitud: number | null; longitud: number | null };
  alt_text: string | null;
  notas_aportante: string | null;
  sede_id: number | null;
  capitulo_id: number | null;
  acontecimiento_id: number | null;
  destacada: boolean;
  mostrar_aportante: boolean;
  aportante_nombre: string | null;
  es_aporte: boolean;
  notas_revision: string | null;
  motivo_rechazo: string | null;
  tags: { nombre: string; slug: string }[];
  personas_editables: PersonaEnFoto[];
  tecnico: { nombre_original: string | null; mime: string | null; bytes: number | null; ancho: number | null; alto: number | null };
  revisiones: { accion: string; etiqueta: string; notas: string | null; fecha: string | null; usuario: string | null }[];
  acciones: { editar: boolean; editar_equipo: boolean; enviar: boolean; moderar: boolean; publicar: boolean; eliminar: boolean };
  duplicados?: number;
}

export interface CatalogoArchivo {
  tipos: Record<string, string>;
  fuentes: Record<string, string>;
  precisiones: Record<string, string>;
  estados: Record<string, string>;
  sedes: { id: number; nombre: string }[];
  capitulos: { id: number; titulo: string; publicado: boolean }[];
  acontecimientos: { id: number; titulo: string; anio: number | null; publicado: boolean }[];
  max_mb: number;
  permisos: { aportar: boolean; gestionar: boolean; subir: boolean; moderar: boolean; publicar: boolean; capitulos: boolean };
}

export const COLOR_ESTADO: Record<string, string> = {
  borrador: '#8a8a8a',
  pendiente: '#f2c14e',
  cambios: '#ff9445',
  rechazada: '#ff6b5b',
  publicada: '#5fbf7f',
  oculta: '#8a8a8a',
};

/** URL pública del archivo (la web), a partir de la URL de la API. */
export function urlArchivoPublico(apiUrl: string): string {
  return apiUrl.replace(/\/api\/v1\/?$/, '') + '/archivo';
}
