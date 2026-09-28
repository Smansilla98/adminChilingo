export interface Diseno {
  id: string;
  name: string;
  canvas_json: string;
  width: number;
  height: number;
  thumbnail_url: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface PaginaDiseno {
  id: string;
  design_id?: string;
  title: string;
  canvas_json: string;
  sort_order: number;
}

export interface Plantilla {
  id: string;
  name: string;
  category: string;
  canvas_json: string;
  width: number;
  height: number;
  thumbnail_url: string | null;
}

export interface GrupoMarca { clave: string; titulo: string; items: { id: string; url: string; label: string; kit_id?: number }[] }

export const FORMATOS = [
  { ancho: 1080, alto: 1350, nombre: 'Flyer para feed' },
  { ancho: 1080, alto: 1920, nombre: 'Historia' },
  { ancho: 1080, alto: 1080, nombre: 'Post cuadrado' },
  { ancho: 1240, alto: 1754, nombre: 'Afiche A4' },
  { ancho: 1200, alto: 628, nombre: 'Banner web' },
];

export const PALETA = ['#000000', '#ffffff', '#f26422', '#e31b23', '#3daf3a', '#4fb3d9', '#e6a817', '#7a3fb0', '#1c1c1c', '#a3a3a3'];
