import * as DocumentPicker from 'expo-document-picker';
import { Directory, File, Paths, UploadType } from 'expo-file-system';
import * as ImagePicker from 'expo-image-picker';
import * as Print from 'expo-print';
import * as Sharing from 'expo-sharing';
import { Platform } from 'react-native';

import { api, ApiError, headersSesion, procesarRespuesta, urlApi } from './api';

/** Archivo elegido en el teléfono, listo para subir. */
export interface ArchivoLocal {
  uri: string;
  nombre: string;
  mime: string;
  tamano?: number;
}

export const MB = 1024 * 1024;

/** Elegir un documento (PDF o imagen por defecto). Devuelve null si se cancela. */
export async function elegirDocumento(tipos: string[] = ['application/pdf', 'image/*']): Promise<ArchivoLocal | null> {
  const r = await DocumentPicker.getDocumentAsync({ type: tipos, copyToCacheDirectory: true, multiple: false });
  if (r.canceled || !r.assets?.[0]) return null;
  const a = r.assets[0];
  return { uri: a.uri, nombre: a.name, mime: a.mimeType ?? 'application/octet-stream', tamano: a.size };
}

/** Foto de la galería o de la cámara. */
export async function elegirImagen(origen: 'galeria' | 'camara' = 'galeria'): Promise<ArchivoLocal | null> {
  if (origen === 'camara') {
    const permiso = await ImagePicker.requestCameraPermissionsAsync();
    if (!permiso.granted) throw new ApiError(0, 'Necesitamos permiso para usar la cámara.');
  }
  const opciones: ImagePicker.ImagePickerOptions = { mediaTypes: ['images'], quality: 0.8 };
  const r = origen === 'camara' ? await ImagePicker.launchCameraAsync(opciones) : await ImagePicker.launchImageLibraryAsync(opciones);
  if (r.canceled || !r.assets?.[0]) return null;
  const a = r.assets[0];
  const mime = a.mimeType ?? 'image/jpeg';
  return { uri: a.uri, nombre: a.fileName ?? `foto.${mime.split('/')[1] ?? 'jpg'}`, mime, tamano: a.fileSize };
}

interface OpcionesSubida {
  /** Campo del formulario donde va el archivo (por defecto "archivo"). */
  campo?: string;
  /** Resto de los campos del formulario (se envían como texto). */
  datos?: Record<string, string | number | boolean | null | undefined>;
  metodo?: 'POST' | 'PUT';
  onProgreso?: (fraccion: number) => void;
  signal?: AbortSignal;
}

/**
 * Sube un archivo como multipart/form-data con progreso y cancelación. El archivo se lee
 * en forma nativa (no se carga entero en memoria de JS). PUT se envía como POST con
 * `_method=PUT` (method spoofing de Laravel), igual que los formularios web.
 */
export async function subirArchivo<T>(ruta: string, archivo: ArchivoLocal | null, { campo = 'archivo', datos = {}, metodo = 'POST', onProgreso, signal }: OpcionesSubida = {}): Promise<T> {
  const parametros: Record<string, string> = {};
  for (const [k, v] of Object.entries(datos)) {
    if (v === undefined || v === null) continue;
    parametros[k] = typeof v === 'boolean' ? (v ? '1' : '0') : String(v);
  }
  if (metodo === 'PUT') parametros._method = 'PUT';

  // Sin archivo (o en web, donde la subida nativa no existe): multipart con fetch.
  if (!archivo || Platform.OS === 'web') {
    const form = new FormData();
    Object.entries(parametros).forEach(([k, v]) => form.append(k, v));
    if (archivo) form.append(campo, { uri: archivo.uri, name: archivo.nombre, type: archivo.mime } as unknown as Blob);
    let resp: Response;
    try {
      resp = await fetch(urlApi(ruta), { method: 'POST', headers: headersSesion(), body: form, signal });
    } catch (e) {
      if (e instanceof Error && e.name === 'AbortError') throw e;
      throw new ApiError(0, 'Sin conexión. Revisá internet e intentá de nuevo.');
    }
    return procesarRespuesta<T>(resp.status, await resp.text());
  }

  try {
    const r = await new File(archivo.uri).upload(urlApi(ruta), {
      httpMethod: 'POST',
      uploadType: UploadType.MULTIPART,
      fieldName: campo,
      mimeType: archivo.mime,
      parameters: parametros,
      headers: headersSesion(),
      onProgress: onProgreso ? (p) => onProgreso(p.totalBytes > 0 ? p.bytesSent / p.totalBytes : 0) : undefined,
      signal,
    });
    return procesarRespuesta<T>(r.status, r.body);
  } catch (e) {
    if (e instanceof ApiError) throw e;
    if (e instanceof Error && e.name === 'AbortError') throw e;
    throw new ApiError(0, 'No se pudo subir el archivo. Revisá la conexión e intentá de nuevo.');
  }
}

/**
 * Descarga un archivo autenticado (PDF, Excel, comprobante…) al caché del teléfono y
 * lo abre con el menú del sistema (ver, guardar, compartir). Se escribe directo a disco.
 */
export async function descargarYAbrir(ruta: string, { nombre, onProgreso, signal }: { nombre?: string; onProgreso?: (f: number) => void; signal?: AbortSignal } = {}): Promise<void> {
  if (Platform.OS === 'web') {
    throw new ApiError(0, 'La descarga de archivos está disponible en la app del teléfono.');
  }
  const carpeta = new Directory(Paths.cache, 'descargas');
  if (!carpeta.exists) carpeta.create({ intermediates: true });
  const destino = nombre ? new File(carpeta, nombre.replace(/[^\w.\- ]+/g, '_')) : carpeta;

  let archivo: File;
  try {
    archivo = await File.downloadFileAsync(urlApi(ruta), destino, {
      headers: headersSesion(),
      idempotent: true,
      onProgress: onProgreso ? (p) => onProgreso(p.totalBytes > 0 ? p.bytesWritten / p.totalBytes : 0) : undefined,
      signal,
    });
  } catch (e) {
    if (e instanceof Error && e.name === 'AbortError') throw e;
    const texto = e instanceof Error ? e.message : '';
    const status = Number(/\b(4\d\d|5\d\d)\b/.exec(texto)?.[1] ?? 0);
    if (status === 401) procesarRespuesta(401, '');
    throw new ApiError(status, status === 403 ? 'No tenés permiso para descargar este archivo.' : status === 404 ? 'El archivo ya no está disponible.' : 'No se pudo descargar el archivo.');
  }

  if (await Sharing.isAvailableAsync()) {
    await Sharing.shareAsync(archivo.uri, { dialogTitle: nombre ?? archivo.name });
  }
}

/**
 * PDF a partir del HTML imprimible que arma el servidor (mismo que imprime el panel
 * web). Se genera en el teléfono y se abre el menú para ver, guardar o compartir.
 */
export async function pdfDesdeServidor(ruta: string): Promise<void> {
  const { html, nombre } = await api<{ html: string; nombre: string }>(ruta);
  if (Platform.OS === 'web') {
    await Print.printAsync({ html });
    return;
  }
  const { uri } = await Print.printToFileAsync({ html });
  const destino = new File(Paths.cache, nombre);
  if (destino.exists) destino.delete();
  new File(uri).move(destino);
  if (await Sharing.isAvailableAsync()) {
    await Sharing.shareAsync(destino.uri, { mimeType: 'application/pdf', dialogTitle: nombre, UTI: 'com.adobe.pdf' });
  }
}
