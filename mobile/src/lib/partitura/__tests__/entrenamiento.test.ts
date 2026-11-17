import { bpmDeToques, registrarToque } from '../practica';
import { normalizarPreset, parsearPresets, PRESETS_DEFAULT, tempoDeVuelta } from '../entrenamiento';

describe('entrenamiento de velocidad', () => {
  const medio = PRESETS_DEFAULT.medio; // arranca 70 %, sube 5 % cada 2 vueltas, tope 100 %

  it('arranca al % de inicio y sube cada N vueltas hasta el tope', () => {
    expect(tempoDeVuelta(100, 0, medio)).toBe(70);
    expect(tempoDeVuelta(100, 2, medio)).toBe(75);
    expect(tempoDeVuelta(100, 100, medio)).toBe(100);
  });

  it('respeta los límites 30–260 del tempo', () => {
    expect(tempoDeVuelta(20, 0, medio)).toBe(30);
    expect(tempoDeVuelta(250, 50, { inicio: 100, subida: 20, cada: 1, tope: 150 })).toBe(260);
  });

  it('valores fuera de rango se acotan y los que faltan van de fábrica', () => {
    expect(normalizarPreset({ subida: 99, cada: 0 }, 'leve')).toEqual({ inicio: 80, subida: 20, cada: 1, tope: 100 });
  });

  it('presets guardados se leen y un JSON roto vuelve a fábrica', () => {
    const p = parsearPresets(JSON.stringify({ fuerte: { inicio: 50, subida: 3, cada: 4, tope: 120 } }));
    expect(p.fuerte).toEqual({ inicio: 50, subida: 3, cada: 4, tope: 120 });
    expect(p.leve).toEqual(PRESETS_DEFAULT.leve);
    expect(parsearPresets('{roto')).toEqual(PRESETS_DEFAULT);
    expect(parsearPresets(null)).toEqual(PRESETS_DEFAULT);
  });
});

describe('tap tempo', () => {
  it('calcula el tempo con la mediana de los intervalos', () => {
    // toques cada 500 ms = 120 bpm
    expect(bpmDeToques([0, 500, 1000, 1500, 2000])).toBe(120);
  });

  it('con un solo intervalo no alcanza', () => {
    expect(bpmDeToques([0, 500])).toBeNull();
  });

  it('una pausa larga reinicia la cuenta', () => {
    let t: number[] = [];
    [0, 500, 1000].forEach((ms) => { t = registrarToque(t, ms); });
    t = registrarToque(t, 10000);
    expect(t).toEqual([10000]);
  });

  it('acota al rango de tap', () => {
    expect(bpmDeToques([0, 100, 200])).toBe(180);
    expect(bpmDeToques([0, 1900, 3800])).toBe(40);
  });
});
