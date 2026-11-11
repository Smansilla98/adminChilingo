import { expandirTimeline, planificar, recortarRango, tempoDe, TEMPO_MAX, TEMPO_MIN, type Score } from '../modelo';
import { posicionEnGrabacion, pulsoDeTiempo, tiempoDeCompas, tempoDeGrabacion } from '../sincro';

const compas = (surdo: string[]) => ({
  voces: {
    surdo_grave: surdo.map((stroke) => ({ dur: 'q' as const, rest: false, stroke })),
  },
});

const cuatro: Score = {
  tempo: 100,
  timeSignature: { num: 4, den: 4 },
  instruments: [{ id: 'surdo_grave' }],
  sections: [{
    name: 'Toque',
    repeatX: 1,
    measures: [
      compas(['nota', 'nota', 'nota', 'nota']),
      compas(['acentuado', 'nota', 'nota', 'nota']),
      compas(['nota', 'tapado', 'nota', 'nota']),
      compas(['nota', 'nota', 'chapa', 'nota']),
    ],
  }],
};

describe('tempo', () => {
  it('admite de 30 a 260 bpm (no 60–100)', () => {
    expect(tempoDe(cuatro, 10).tempo).toBe(TEMPO_MIN);
    expect(tempoDe(cuatro, 400).tempo).toBe(TEMPO_MAX);
    expect(tempoDe(cuatro, 180).tempo).toBe(180);
  });
});

describe('loop por rango de compases', () => {
  it('recorta a los compases pedidos, una sola pasada', () => {
    const r = recortarRango(cuatro, { sectionIdx: 0, desde: 1, hasta: 2 });
    expect(r.sections[0].measures).toHaveLength(2);
    expect(r.sections[0].repeatX).toBe(1);
    expect(expandirTimeline(r)).toHaveLength(2);
  });

  it('el plan del tramo dura dos compases y arranca en 0', () => {
    const plan = planificar(cuatro, { bpm: 60, conteo: false, rango: { sectionIdx: 0, desde: 1, hasta: 2 } });
    expect(plan.compases).toHaveLength(2);
    expect(plan.duracion).toBeCloseTo(8, 6);
    expect(plan.eventos.filter((e) => e.tipo === 'nota')[0].t).toBeCloseTo(0, 6);
  });

  it('el tramo se acota a la sección', () => {
    const r = recortarRango(cuatro, { sectionIdx: 0, desde: 2, hasta: 99 });
    expect(r.sections[0].measures).toHaveLength(2);
  });
});

describe('sincro con la grabación', () => {
  const base: Score = { ...cuatro, audio_ref: { path: 'partituras/audio/1-abcdefghijklmnop.mp3', offset_seg: 2, beats: [], tempo_origen: 120 } };

  it('sin pulsos: un compás a 120 bpm dura 2 s desde el inicio', () => {
    expect(pulsoDeTiempo(base, 2)).toBeCloseTo(0, 9);
    expect(tiempoDeCompas(base, 1)).toBeCloseTo(4, 9);
    const p = posicionEnGrabacion(base, 5, 4);
    expect(p.orden).toBe(1);
    expect(p.frac).toBeCloseTo(0.5, 6);
  });

  it('antes del inicio y después del final se marca fuera de la partitura', () => {
    expect(posicionEnGrabacion(base, 0.5, 4).antes).toBe(true);
    expect(posicionEnGrabacion(base, 50, 4).despues).toBe(true);
  });

  it('con pulsos sigue la aceleración y el tempo medio sale de ellos', () => {
    const s: Score = { ...cuatro, audio_ref: { path: 'partituras/audio/1-abcdefghijklmnop.mp3', offset_seg: 0, beats: [1, 1.6, 2.15, 2.65, 3.1], tempo_origen: null } };
    expect(pulsoDeTiempo(s, 1.6)).toBeCloseTo(1, 9);
    expect(tempoDeGrabacion(s)).toBe(Math.round(60 / ((3.1 - 1) / 4)));
  });
});
