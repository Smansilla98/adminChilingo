export type Alcance = 'escuela' | 'sedes' | 'bloques' | 'show';
export type Canal = 'interna' | 'push' | 'mail' | 'whatsapp';

export interface LimiteWhatsapp {
  cantidad: number;
  confirmar_desde: number;
  maximo: number;
  requiere_confirmacion: boolean;
  supera_maximo: boolean;
}

export interface CatalogoDifusion {
  alcances: Partial<Record<Alcance, string>>;
  canales: Record<Canal, string>;
  sedes: { id: number; nombre: string }[];
  bloques: { id: number; nombre: string; sede: string | null }[];
  shows: { id: number; titulo: string; fecha: string }[];
  whatsapp: LimiteWhatsapp;
}

export interface VistaPrevia {
  cantidad: number;
  con_app: number;
  con_email: number;
  con_telefono: number;
  muestra: string[];
  whatsapp: LimiteWhatsapp;
}

export interface Difusion {
  id: number;
  titulo: string;
  mensaje: string;
  enlace: string | null;
  canales: Canal[];
  canales_texto: string;
  destino: string;
  quienes: string;
  estado: 'programada' | 'enviando' | 'enviada' | 'cancelada';
  estado_texto: string;
  programada_para: string | null;
  enviada_at: string | null;
  creada_at: string | null;
  destinatarios: number;
  resultado: Partial<Record<Canal, number>>;
  autor: string | null;
  lecturas: { recibieron: number; leyeron: number } | null;
}

export interface DatosDifusion {
  titulo: string;
  mensaje: string;
  enlace: string;
  canales: Canal[];
  alcance: Alcance;
  sede_ids: number[];
  bloque_ids: number[];
  show_id: number | null;
  alumnos: boolean;
  docentes: boolean;
  solo_con_deuda: boolean;
  fecha: string;
  hora: string;
  confirmar_whatsapp: boolean;
}

/** Cuerpo para la API: la programación va como "YYYY-MM-DD HH:mm:00" (vacía = sale ya). */
export function cuerpoDifusion(d: DatosDifusion) {
  const { fecha, hora, ...resto } = d;
  return {
    ...resto,
    enlace: d.enlace.trim() || null,
    programada_para: fecha && hora ? `${fecha} ${hora}:00` : null,
  };
}

export const COLOR_ESTADO: Record<Difusion['estado'], string> = {
  programada: '#4fb3d9',
  enviando: '#e6a817',
  enviada: '#3daf3a',
  cancelada: '#a3a3a3',
};
