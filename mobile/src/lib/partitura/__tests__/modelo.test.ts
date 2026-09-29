import { eventosMusicales, expandirTimeline, grillaDeVoz, PASO, planificar, ticksDeCompas, escribirCelda } from '../modelo';
import type { Score } from '../modelo';

const samba: Score = {
  tempo: 80,
  timeSignature: { num: 4, den: 4 },
  instruments: [{ id: 'surdo_grave' }, { id: 'repique' }],
  sections: [{
    name: 'Llamada',
    repeatX: 2,
    measures: [{
      voces: {
        surdo_grave: [
          { dur: 'q', rest: false, stroke: 'nota' },
          { dur: 'q', rest: true, stroke: 'nota' },
          { dur: 'q', rest: false, stroke: 'acentuado' },
        ],
      },
    }],
  }],
};

describe('partitura', () => {
  it('repite la sección y no suena el silencio', () => {
    expect(expandirTimeline(samba)).toHaveLength(2);
    const ev = eventosMusicales(samba);
    expect(ev.map((e) => e.absTick + e.tickLocal)).toEqual([0, 96, 192, 288]);
    expect(ev.every((e) => e.articulation === 'nota' || e.articulation === 'acentuado')).toBe(true);
  });

  it('la negra ocupa el golpe y tres ligaduras, y al reescribir sigue siendo una sola nota', () => {
    const grilla = grillaDeVoz(samba.sections[0].measures[0].voces.surdo_grave, ticksDeCompas(samba.timeSignature), PASO);
    expect(grilla?.[0]).toEqual({ tipo: 'golpe', stroke: 'nota' });
    expect(grilla?.[1].tipo).toBe('liga');
    expect(grilla?.[4].tipo).toBe('silencio');
    expect(grilla?.[8]).toEqual({ tipo: 'golpe', stroke: 'acentuado' });
    const escrito = escribirCelda(samba, 0, 0, 'surdo_grave', 0, 'chapa');
    const otra = eventosMusicales(escrito!).filter((e) => e.absTick === 0 && e.tickLocal === 0);
    expect(otra).toHaveLength(1);
    expect(otra[0].articulation).toBe('chapa');
  });

  it('el conteo corre antes de la música y el metrónomo marca los cuatro pulsos', () => {
    const plan = planificar(samba, { bpm: 60, conteo: true, metronomo: true, soloSeccion: 0 });
    expect(plan.conteo).toBeCloseTo(4, 5);
    expect(plan.eventos.filter((e) => e.tipo === 'click').length).toBe(4 + 8);
    expect(plan.eventos.find((e) => e.tipo === 'nota')!.t).toBeGreaterThanOrEqual(plan.conteo);
  });
});
