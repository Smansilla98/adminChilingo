/**
 * Grabación de referencia del toque (audio_ref) en la app. Se baja por la API (con la sesión)
 * a la caché del teléfono y se reproduce con expo-audio. El cursor de la partitura sale de
 * la posición de la grabación (ver sincro.ts).
 */
import { createAudioPlayer, type AudioPlayer } from 'expo-audio';
import { Directory, File, Paths } from 'expo-file-system';

import { headersSesion, urlApi } from '../api';
import type { AudioRef } from './modelo';

/** Velocidades de la grabación (50–150 %), sin cambiar la afinación. */
export const VELOCIDADES = [50, 75, 100, 125, 150] as const;

/** Nombre del archivo en el servidor: solo el último tramo de audio_ref.path. */
export function archivoDeRef(ref: AudioRef | null | undefined): string | null {
  const path = ref?.path ?? '';
  if (!path) return null;
  const nombre = path.split('/').pop() ?? '';
  return /^[0-9]+-[a-z0-9]{16}\.(mp3|wav|m4a|ogg)$/.test(nombre) ? nombre : null;
}

export class GrabacionRef {
  private player: AudioPlayer | null = null;
  private velocidad = 100;
  private slug = '';
  private archivo: string | null = null;
  sonando = false;

  /** Baja (si hace falta) y prepara la grabación del toque. Devuelve false si no hay grabación. */
  async cargar(slug: string, ref: AudioRef | null | undefined): Promise<boolean> {
    const archivo = archivoDeRef(ref);
    if (!archivo) return false;
    if (this.player && this.archivo === archivo && this.slug === slug) return true;
    this.soltar();
    const dir = new Directory(Paths.cache, 'grabaciones-chilinga');
    if (!dir.exists) dir.create({ intermediates: true });
    const dest = new File(dir, `${slug}-${archivo}`);
    if (!dest.exists) {
      await File.downloadFileAsync(urlApi(`partituras/${slug}/audio/${archivo}`), dest, { headers: headersSesion() });
    }
    this.player = createAudioPlayer({ uri: dest.uri });
    this.slug = slug;
    this.archivo = archivo;
    return true;
  }

  get listo(): boolean {
    return this.player !== null;
  }

  /** Posición actual en segundos de la grabación. */
  get tiempo(): number {
    return this.player?.currentTime ?? 0;
  }

  get duracion(): number {
    return this.player?.duration ?? 0;
  }

  play() {
    if (!this.player) return;
    this.player.setPlaybackRate(this.velocidad / 100, 'high');
    this.player.play();
    this.sonando = true;
  }

  pausar() {
    this.player?.pause();
    this.sonando = false;
  }

  async buscar(segundos: number) {
    if (!this.player) return;
    await this.player.seekTo(Math.max(0, segundos));
  }

  /** Silencia la grabación (modo «sonido sintetizado»: la línea de tiempo sigue siendo la grabación). */
  setSilenciada(on: boolean) {
    if (this.player) this.player.muted = on;
  }

  setVelocidad(v: number) {
    this.velocidad = Math.min(150, Math.max(50, v));
    if (this.player) this.player.setPlaybackRate(this.velocidad / 100, 'high');
  }

  get velocidadActual(): number {
    return this.velocidad;
  }

  soltar() {
    this.player?.remove();
    this.player = null;
    this.sonando = false;
    this.archivo = null;
  }
}
