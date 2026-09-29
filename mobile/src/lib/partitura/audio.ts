/**
 * Reproductor de la partitura. Baja los WAV por la API y los dispara con un reloj
 * de anticipación. No abre el sitio: el catálogo es GET /partituras/muestras.
 */
import { createAudioPlayer, setAudioModeAsync, type AudioPlayer } from 'expo-audio';
import { Directory, File, Paths } from 'expo-file-system';

import { api, headersSesion, urlApi } from '../api';
import { archivoDe, DESFASE_UNISONO, GANANCIA_TIMBRE, resolverGolpe } from './catalogo';
import { planificar, realesDe, type Score } from './modelo';

export interface Mezcla {
  /** null = todas las cuerdas. 'todos' = solo la voz de unísono. */
  solo: string | null;
  mudas: string[];
}

export interface PosicionPlay {
  conteo: boolean;
  sectionIdx: number | null;
  measureIdx: number | null;
}

function wavClick(hz: number, ms: number): Uint8Array {
  const sr = 22050;
  const n = Math.floor((sr * ms) / 1000);
  const pcm = new Int16Array(n);
  for (let i = 0; i < n; i += 1) {
    const env = Math.exp(-i / (sr * 0.01));
    pcm[i] = Math.round(Math.sin((2 * Math.PI * hz * i) / sr) * env * 12000);
  }
  const bytes = n * 2;
  const buf = new ArrayBuffer(44 + bytes);
  const v = new DataView(buf);
  const txt = (o: number, s: string) => { for (let i = 0; i < s.length; i += 1) v.setUint8(o + i, s.charCodeAt(i)); };
  txt(0, 'RIFF');
  v.setUint32(4, 36 + bytes, true);
  txt(8, 'WAVE');
  txt(12, 'fmt ');
  v.setUint32(16, 16, true);
  v.setUint16(20, 1, true);
  v.setUint16(22, 1, true);
  v.setUint32(24, sr, true);
  v.setUint32(28, sr * 2, true);
  v.setUint16(32, 2, true);
  v.setUint16(34, 16, true);
  txt(36, 'data');
  v.setUint32(40, bytes, true);
  new Uint8Array(buf, 44).set(new Uint8Array(pcm.buffer));
  return new Uint8Array(buf);
}

export class MotorAudio {
  private uris = new Map<string, string>();
  private pools = new Map<string, AudioPlayer[]>();
  private clickFuerte = '';
  private clickDebil = '';
  private timer: ReturnType<typeof setInterval> | null = null;
  private listo = false;
  faltan: string[] = [];
  sonando = false;
  mezcla: Mezcla = { solo: null, mudas: [] };
  onFin: (() => void) | null = null;

  async preparar(): Promise<void> {
    if (this.listo) return;
    await setAudioModeAsync({ playsInSilentMode: true, interruptionMode: 'doNotMix', allowsRecording: false });
    const dir = new Directory(Paths.cache, 'perc-chilinga');
    if (!dir.exists) dir.create({ intermediates: true });
    this.clickFuerte = await this.click(dir, 'click-fuerte.wav', 1600);
    this.clickDebil = await this.click(dir, 'click-debil.wav', 1100);
    const cat = await api<{ data: { archivo: string; presente: boolean; bytes: number; mtime: number }[] }>('partituras/muestras');
    const faltan: string[] = [];
    await Promise.all(cat.data.map(async (m) => {
      if (!m.presente || m.bytes < 1) {
        faltan.push(m.archivo);
        return;
      }
      const dest = new File(dir, `${m.mtime}-${m.bytes}-${m.archivo}`);
      if (!dest.exists) {
        try {
          await File.downloadFileAsync(urlApi(`partituras/muestras/${m.archivo}`), dest, { headers: headersSesion() });
        } catch {
          faltan.push(m.archivo);
          return;
        }
      }
      this.uris.set(m.archivo, dest.uri);
    }));
    this.faltan = faltan;
    this.listo = true;
  }

  private async click(dir: Directory, nombre: string, hz: number): Promise<string> {
    const file = new File(dir, nombre);
    if (!file.exists) file.write(wavClick(hz, 40));
    return file.uri;
  }

  private tomar(uri: string): AudioPlayer {
    const pool = this.pools.get(uri) ?? [];
    this.pools.set(uri, pool);
    const libre = pool.find((p) => !p.playing);
    if (libre) return libre;
    if (pool.length < 4) {
      const creado = createAudioPlayer({ uri });
      pool.push(creado);
      return creado;
    }
    return pool[0];
  }

  private lanzar(uri: string, volumen: number, esperaMs = 0) {
    const ir = () => {
      const p = this.tomar(uri);
      p.volume = Math.max(0, Math.min(1, volumen));
      void p.seekTo(0).then(() => { if (!p.playing) p.play(); else { p.pause(); void p.seekTo(0).then(() => p.play()); } });
    };
    if (esperaMs > 2) setTimeout(ir, esperaMs);
    else ir();
  }

  /** Un golpe suelto, para escuchar la celda mientras se escribe. */
  async preescuchar(instId: string, stroke: string) {
    await this.preparar();
    this.disparar(instId, stroke, 1, [instId], true);
  }

  private disparar(instId: string, stroke: string, velocidad: number, ids: string[], preview = false) {
    const mezcla = preview ? { solo: null, mudas: [] as string[] } : this.mezcla;
    const destinos = instId === 'todos' ? (mezcla.solo && mezcla.solo !== 'todos' ? [mezcla.solo] : ids) : [instId];
    const audibles = destinos.filter((id) => {
      if (mezcla.mudas.includes(id)) return false;
      if (mezcla.solo === 'todos') return instId === 'todos';
      if (mezcla.solo) return id === mezcla.solo;
      return true;
    });
    if (!audibles.length) return;
    const comp = audibles.length > 1 ? 1 / Math.sqrt(audibles.length) : 1;
    const r = resolverGolpe(instId === 'todos' ? audibles[0] : instId, stroke);
    audibles.forEach((id) => {
      const res = instId === 'todos' ? resolverGolpe(id, stroke) : r;
      const uri = this.uris.get(archivoDe(res.instId, res.strokeId));
      if (!uri) return;
      const vol = Math.min(1, velocidad * res.vel * (GANANCIA_TIMBRE[id] ?? 1) * comp);
      const espera = audibles.length > 1 ? Math.round((DESFASE_UNISONO[id] ?? 0) * 1000) : 0;
      if (res.flam) this.lanzar(uri, vol * 0.55, Math.max(0, espera - 30));
      this.lanzar(uri, vol, espera + (res.flam ? 30 : 0));
    });
  }

  tocar(score: Score, opts: { bpm: number; soloSeccion?: number | null; conteo: boolean; metronomo: boolean; loop: boolean }, onClock: (p: PosicionPlay) => void) {
    this.parar(false);
    const plan = planificar(score, opts);
    const ids = realesDe(score);
    const t0 = performance.now() + 80;
    const hechos = new Set<number>();
    this.sonando = true;
    this.timer = setInterval(() => {
      const ahora = (performance.now() - t0) / 1000;
      const compas = plan.compases.find((c) => ahora >= c.t && ahora < c.t + c.dur);
      onClock({ conteo: ahora < plan.conteo, sectionIdx: compas?.sectionIdx ?? null, measureIdx: compas?.measureIdx ?? null });
      plan.eventos.forEach((ev, i) => {
        if (hechos.has(i) || ev.t > ahora + 0.09) return;
        hechos.add(i);
        if (ev.tipo === 'click') this.lanzar(ev.fuerte ? this.clickFuerte : this.clickDebil, ev.fuerte ? 0.7 : 0.45);
        else if (ev.inst && ev.stroke) this.disparar(ev.inst, ev.stroke, ev.vel ?? 1, ids);
      });
      if (ahora > plan.duracion + 0.05) {
        if (opts.loop) this.tocar(score, { ...opts, conteo: false }, onClock);
        else this.parar(true);
      }
    }, 25);
  }

  parar(avisar = true) {
    if (this.timer) clearInterval(this.timer);
    this.timer = null;
    const estaba = this.sonando;
    this.sonando = false;
    this.pools.forEach((pool) => pool.forEach((p) => { if (p.playing) p.pause(); }));
    if (avisar && estaba) this.onFin?.();
  }

  soltar() {
    this.parar(false);
    this.pools.forEach((pool) => pool.forEach((p) => p.remove()));
    this.pools.clear();
    this.listo = false;
  }
}
