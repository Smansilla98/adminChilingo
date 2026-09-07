/**
 * Exportaciones: PNG, PDF, MusicXML y MIDI.
 */
import { instrumentoPorId, GOLPES, GOLPES_POR_INSTRUMENTO, midiDeGolpe, sistemasVisuales, UNISONO, vocesDeUnisono } from './instruments.js';
import { TPQ, ticksDeNota, expandirTimeline, ticksDeCompas } from './model.js';

/* ------------------------------------------------------------------ imágenes */

function svgsDe(host) {
    return Array.from(host.querySelectorAll('svg'));
}

async function svgAImagen(svg, escala = 2) {
    const clone = svg.cloneNode(true);
    const w = svg.viewBox?.baseVal?.width || svg.clientWidth || parseInt(svg.getAttribute('width'), 10) || 900;
    const h = svg.viewBox?.baseVal?.height || svg.clientHeight || parseInt(svg.getAttribute('height'), 10) || 200;
    clone.setAttribute('width', String(w));
    clone.setAttribute('height', String(h));
    clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
    const fondo = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
    fondo.setAttribute('width', '100%');
    fondo.setAttribute('height', '100%');
    fondo.setAttribute('fill', '#ffffff');
    clone.insertBefore(fondo, clone.firstChild);

    const xml = new XMLSerializer().serializeToString(clone);
    const url = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(xml);
    const img = new Image();
    await new Promise((res, rej) => {
        img.onload = res;
        img.onerror = rej;
        img.src = url;
    });
    const canvas = document.createElement('canvas');
    canvas.width = Math.ceil(w * escala);
    canvas.height = Math.ceil(h * escala);
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
    return { canvas, w, h };
}

/** PNG: una sola imagen con todos los sistemas apilados. */
export async function exportarPNG(host, score) {
    const svgs = svgsDe(host);
    if (!svgs.length) throw new Error('No hay partitura renderizada.');
    const partes = [];
    for (const svg of svgs) partes.push(await svgAImagen(svg, 2));

    const ancho = Math.max(...partes.map((p) => p.canvas.width));
    const cabecera = 120;
    const alto = cabecera + partes.reduce((s, p) => s + p.canvas.height + 16, 0);
    const canvas = document.createElement('canvas');
    canvas.width = ancho;
    canvas.height = alto;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, ancho, alto);
    ctx.fillStyle = '#1a1a1a';
    ctx.font = '600 42px Inter, sans-serif';
    ctx.fillText(score.title, 24, 60);
    ctx.font = '400 24px Inter, sans-serif';
    ctx.fillStyle = '#555';
    ctx.fillText(`${score.autor || 'La Chilinga'} · ♩=${score.tempo} · ${score.timeSignature.num}/${score.timeSignature.den}`, 24, 96);

    let y = cabecera;
    partes.forEach((p) => {
        ctx.drawImage(p.canvas, 0, y);
        y += p.canvas.height + 16;
    });

    descargarDataUrl(canvas.toDataURL('image/png'), `${slug(score.title)}.png`);
}

/** PDF A4 vertical con los sistemas paginados. */
export async function exportarPDF(host, score) {
    const { jsPDF } = await import('jspdf');
    const svgs = svgsDe(host);
    if (!svgs.length) throw new Error('No hay partitura renderizada.');

    const doc = new jsPDF({ unit: 'mm', format: 'a4', orientation: 'portrait' });
    const pageW = 210;
    const pageH = 297;
    const margen = 12;
    const usableW = pageW - margen * 2;

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(18);
    doc.text(score.title, pageW / 2, 18, { align: 'center' });
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(11);
    doc.text('La Chilinga', pageW / 2, 24, { align: 'center' });
    doc.setFontSize(9);
    doc.text(`♩=${score.tempo} · C`, margen, 30);

    let y = 36;
    for (const svg of svgs) {
        const { canvas, w, h } = await svgAImagen(svg, 2);
        const alto = (h * usableW) / w;
        if (y + alto > pageH - margen - 6) {
            doc.addPage();
            y = margen;
        }
        doc.addImage(canvas.toDataURL('image/jpeg', 0.92), 'JPEG', margen, y, usableW, alto);
        y += alto + 3;
    }

    doc.save(`${slug(score.title)}.pdf`);
}

/* ------------------------------------------------------------------ MusicXML 4.0 */

const TIPO_XML = { w: 'whole', h: 'half', q: 'quarter', 8: 'eighth', 16: '16th', 32: '32nd' };
const CABEZA_XML = { x: 'x', circled: 'circle-x', triangle: 'triangle', diamond: 'diamond', slash: 'slash', normal: 'normal' };
const SOUND_FAMILIA = {
    surdo_grave: 'drum.tom',
    surdo_medio: 'drum.tom',
    surdo_agudo: 'drum.tom',
    redoblante: 'drum.snare',
    repique: 'drum.tom',
    timbal: 'drum.tom',
    agogo: 'drum.cowbell',
    palmas: 'drum.clap',
    todos: 'drum.snare',
};

export function generarMusicXML(score) {
    const insts = (score.instruments || [])
        .map((cfg) => ({ cfg, def: instrumentoPorId(cfg.id) }))
        .filter((x) => x.def);
    const sistemas = sistemasVisuales(insts);
    const capacidad = ticksDeCompas(score.timeSignature);
    const ts = score.timeSignature || { num: 4, den: 4 };

    let xml = '<?xml version="1.0" encoding="UTF-8"?>\n';
    xml += '<!DOCTYPE score-partwise PUBLIC "-//Recordare//DTD MusicXML 4.0 Partwise//EN" "http://www.musicxml.org/dtds/partwise.dtd">\n';
    xml += '<score-partwise version="4.0">\n';
    xml += `  <work><work-title>${esc(score.title)}</work-title></work>\n`;
    xml += '  <identification>\n';
    xml += `    <creator type="arranger">${esc(score.autor || 'La Chilinga')}</creator>\n`;
    xml += '    <encoding><software>ITO Partituras</software></encoding>\n';
    xml += '  </identification>\n';
    xml += '  <part-list>\n';

    const partes = sistemas.map((sis, i) => {
        const pid = `P${i + 1}`;
        const members = sis.members;
        const golpes = [];
        members.forEach((m) => {
            (GOLPES_POR_INSTRUMENTO[m.def.id] || ['nota']).forEach((g) => {
                golpes.push({
                    iid: `${pid}-${m.def.id}-${g}`,
                    instId: m.def.id,
                    stroke: g,
                    name: `${m.def.label} ${GOLPES[g]?.label || g}`,
                    midi: midiDeGolpe(m.def.id, g) + 1,
                    sound: SOUND_FAMILIA[m.def.id] || 'drum.tom',
                });
            });
        });
        return { pid, sis, members, golpes };
    });

    partes.forEach((p) => {
        xml += `    <score-part id="${p.pid}">\n`;
        xml += `      <part-name>${esc(p.sis.label)}</part-name>\n`;
        xml += `      <part-abbreviation>${esc(p.members.map((m) => m.def.short).join(' '))}</part-abbreviation>\n`;
        p.golpes.forEach((g) => {
            xml += `      <score-instrument id="${g.iid}">\n`;
            xml += `        <instrument-name>${esc(g.name)}</instrument-name>\n`;
            xml += `        <instrument-sound>${g.sound}</instrument-sound>\n`;
            xml += '      </score-instrument>\n';
        });
        p.golpes.forEach((g) => {
            xml += `      <midi-instrument id="${g.iid}">\n`;
            xml += '        <midi-channel>10</midi-channel>\n';
            xml += `        <midi-unpitched>${g.midi}</midi-unpitched>\n`;
            xml += '      </midi-instrument>\n';
        });
        xml += '    </score-part>\n';
    });
    xml += '  </part-list>\n';

    partes.forEach((p) => {
        xml += `  <part id="${p.pid}">\n`;
        let nro = 0;
        score.sections.forEach((sec, si) => {
            sec.measures.forEach((m, mi) => {
                nro += 1;
                xml += `    <measure number="${nro}">\n`;
                if (nro === 1) {
                    xml += '      <attributes>\n';
                    xml += `        <divisions>${TPQ}</divisions>\n`;
                    xml += `        <time><beats>${ts.num}</beats><beat-type>${ts.den}</beat-type></time>\n`;
                    xml += '        <clef><sign>percussion</sign><line>2</line></clef>\n';
                    xml += `        <staff-details><staff-lines>5</staff-lines></staff-details>\n`;
                    xml += '      </attributes>\n';
                    xml += `      <direction placement="above"><direction-type><metronome><beat-unit>quarter</beat-unit><per-minute>${score.tempo}</per-minute></metronome></direction-type><sound tempo="${score.tempo}"/></direction>\n`;
                }
                if (mi === 0) {
                    xml += `      <direction placement="above"><direction-type><rehearsal>${esc(sec.name)}</rehearsal></direction-type></direction>\n`;
                    if (sec.repeatX > 1) {
                        xml += `      <direction placement="above"><direction-type><words>×${sec.repeatX}</words></direction-type></direction>\n`;
                    }
                }
                if (m.texto) {
                    xml += `      <direction placement="above"><direction-type><words>${esc(m.texto)}</words></direction-type></direction>\n`;
                }
                if (m.repeatBegin || (sec.repeatX > 1 && mi === 0 && !m.repeatBegin)) {
                    xml += '      <barline location="left"><bar-style>heavy-light</bar-style><repeat direction="forward"/></barline>\n';
                }
                if (m.ending) {
                    xml += `      <barline location="left"><ending number="${m.ending}" type="start">${m.ending}.</ending></barline>\n`;
                }

                p.members.forEach((mem, vi) => {
                    if (vi > 0) xml += `      <backup><duration>${capacidad}</duration></backup>\n`;
                    const voz = m.voces[mem.def.id] || [];
                    const notas = voz.length ? voz : [{ dur: 'w', rest: true, dots: 0, stroke: 'nota' }];
                    const beams = calcularBeams(notas, ts);
                    notas.forEach((n, ni) => {
                        xml += notaXML(n, mem.def, {
                            voice: vi + 1,
                            iid: `${p.pid}-${mem.def.id}-${n.stroke || 'nota'}`,
                            beams: beams[ni],
                        });
                    });
                });

                const last = mi === sec.measures.length - 1;
                if (m.repeatEnd || (sec.repeatX > 1 && last)) {
                    const times = sec.repeatX > 1 ? ` times="${sec.repeatX}"` : '';
                    xml += `      <barline location="right"><bar-style>light-heavy</bar-style><repeat direction="backward"${times}/></barline>\n`;
                } else if (last && si === score.sections.length - 1) {
                    xml += '      <barline location="right"><bar-style>light-heavy</bar-style></barline>\n';
                }
                xml += '    </measure>\n';
            });
        });
        xml += '  </part>\n';
    });

    xml += '</score-partwise>\n';
    return xml;
}

function nivelesBeam(dur) {
    if (dur === '8') return 1;
    if (dur === '16') return 2;
    if (dur === '32') return 3;
    return 0;
}

function ticksPorGrupoBeam(ts) {
    if (ts.den === 8 && ts.num % 3 === 0) return 72;
    return 48;
}

/** Beams explícitos por tiempo de negra (o grupos de 3 en 6/8). */
export function calcularBeams(notas, ts) {
    const grupo = ticksPorGrupoBeam(ts || { num: 4, den: 4 });
    const out = notas.map(() => null);
    let tick = 0;
    let i = 0;
    while (i < notas.length) {
        const n = notas[i];
        const t = ticksDeNota(n);
        const niv = n.rest ? 0 : nivelesBeam(n.dur);
        if (!niv) {
            tick += t;
            i += 1;
            continue;
        }
        const g0 = Math.floor(tick / grupo);
        const idxs = [i];
        let acc = tick + t;
        let j = i + 1;
        while (j < notas.length) {
            const n2 = notas[j];
            const t2 = ticksDeNota(n2);
            const niv2 = n2.rest ? 0 : nivelesBeam(n2.dur);
            if (!niv2 || Math.floor(acc / grupo) !== g0) break;
            idxs.push(j);
            acc += t2;
            j += 1;
        }
        if (idxs.length >= 2) {
            idxs.forEach((idx, k) => {
                const levels = nivelesBeam(notas[idx].dur);
                const tags = [];
                for (let lv = 1; lv <= levels; lv++) {
                    const tipo = k === 0 ? 'begin' : k === idxs.length - 1 ? 'end' : 'continue';
                    tags.push({ number: lv, tipo });
                }
                out[idx] = tags;
            });
        }
        idxs.forEach((idx) => { tick += ticksDeNota(notas[idx]); });
        i = j;
    }
    return out;
}

function notaXML(n, def, opts = {}) {
    const dur = ticksDeNota(n);
    const tipo = TIPO_XML[n.dur] || 'quarter';
    const voice = opts.voice || 1;
    let s = '      <note>\n';
    if (n.rest) {
        s += '        <rest/>\n';
    } else {
        const step = (def.pitch.split('/')[0] || 'b').toUpperCase();
        const oct = def.pitch.split('/')[1] || '4';
        s += `        <unpitched><display-step>${step}</display-step><display-octave>${oct}</display-octave></unpitched>\n`;
        if (opts.iid) s += `        <instrument id="${opts.iid}"/>\n`;
    }
    s += `        <duration>${dur}</duration>\n`;
    s += `        <voice>${voice}</voice>\n`;
    s += `        <type>${tipo}</type>\n`;
    for (let d = 0; d < (n.dots || 0); d++) s += '        <dot/>\n';
    if (n.tuplet) {
        s += `        <time-modification><actual-notes>${n.tuplet.num}</actual-notes><normal-notes>${n.tuplet.den}</normal-notes></time-modification>\n`;
    }
    (opts.beams || []).forEach((b) => {
        s += `        <beam number="${b.number}">${b.tipo}</beam>\n`;
    });
    if (!n.rest) {
        const g = GOLPES[n.stroke] || GOLPES.nota;
        s += `        <notehead>${CABEZA_XML[g.cabeza] || 'normal'}</notehead>\n`;
        const arts = [];
        if (g.articulacion === 'a>' || n.stroke === 'acentuado') {
            const below = def.id === 'redoblante' || def.id === 'repique';
            arts.push(`<accent${below ? ' placement="below"' : ''}/>`);
        }
        if (g.articulacion === 'a-' || n.stroke === 'tapado' || n.stroke === 'presionado') {
            arts.push('<tenuto/>');
        }
        const tech = (n.digitacion === 'D' || n.digitacion === 'I')
            ? `<technical><fingering>${n.digitacion}</fingering></technical>`
            : '';
        if (arts.length || tech) {
            s += '        <notations>';
            if (arts.length) s += `<articulations>${arts.join('')}</articulations>`;
            s += tech;
            s += '</notations>\n';
        }
        if (n.tuplet) {
            /* tuplet type start/stop se omite: time-modification alcanza en MuseScore */
        }
    }
    s += '      </note>\n';
    return s;
}

export function exportarMusicXML(score) {
    const xml = generarMusicXML(score);
    descargarBlob(new Blob([xml], { type: 'application/vnd.recordare.musicxml+xml' }), `${slug(score.title)}.musicxml`);
}

/* ------------------------------------------------------------------ MIDI */

export function generarMIDI(score) {
    const division = TPQ;
    const eventos = [];
    const capacidad = ticksDeCompas(score.timeSignature);
    let cursor = 0;

    expandirTimeline(score).forEach((pos) => {
        const m = score.sections[pos.sectionIdx].measures[pos.measureIdx];
        score.instruments.forEach((cfg) => {
            if (cfg.mute) return;
            const destinos = cfg.id === UNISONO ? vocesDeUnisono(score) : [cfg.id];
            destinos.forEach((id) => {
                const def = instrumentoPorId(id);
                if (!def) return;
                let local = 0;
                (m.voces[cfg.id] || []).forEach((n) => {
                    if (!n.rest) {
                        const golpe = GOLPES[n.stroke] || GOLPES.nota;
                        const noteMidi = midiDeGolpe(id, n.stroke);
                        const vel = Math.max(20, Math.min(127, Math.round(96 * golpe.gain * (cfg.volume || 1))));
                        eventos.push({ t: cursor + local, on: true, note: noteMidi, vel });
                        eventos.push({ t: cursor + local + 6, on: false, note: noteMidi, vel: 0 });
                    }
                    local += ticksDeNota(n);
                });
            });
        });
        cursor += capacidad;
    });

    eventos.sort((a, b) => a.t - b.t || (a.on ? 1 : -1));

    const track = [];
    // tempo meta
    const usPorNegra = Math.round(60000000 / (score.tempo || 100));
    track.push(...varLen(0), 0xff, 0x51, 0x03, (usPorNegra >> 16) & 0xff, (usPorNegra >> 8) & 0xff, usPorNegra & 0xff);
    track.push(...varLen(0), 0xff, 0x58, 0x04, score.timeSignature.num, Math.log2(score.timeSignature.den), 24, 8);
    track.push(...varLen(0), 0xff, 0x03, ...textoBytes(score.title.slice(0, 40)));

    let ultimo = 0;
    eventos.forEach((ev) => {
        const delta = Math.max(0, Math.round(ev.t - ultimo));
        ultimo = ev.t;
        track.push(...varLen(delta), ev.on ? 0x99 : 0x89, ev.note & 0x7f, ev.vel & 0x7f);
    });
    track.push(...varLen(0), 0xff, 0x2f, 0x00);

    const header = [
        0x4d, 0x54, 0x68, 0x64, 0, 0, 0, 6, 0, 0, 0, 1,
        (division >> 8) & 0xff, division & 0xff,
    ];
    const len = track.length;
    const trackHeader = [0x4d, 0x54, 0x72, 0x6b, (len >> 24) & 0xff, (len >> 16) & 0xff, (len >> 8) & 0xff, len & 0xff];
    return new Uint8Array([...header, ...trackHeader, ...track]);
}

export function exportarMIDI(score) {
    const bytes = generarMIDI(score);
    descargarBlob(new Blob([bytes], { type: 'audio/midi' }), `${slug(score.title)}.mid`);
}

function varLen(n) {
    const bytes = [n & 0x7f];
    let v = n >> 7;
    while (v > 0) {
        bytes.unshift((v & 0x7f) | 0x80);
        v >>= 7;
    }
    return bytes;
}

function textoBytes(s) {
    const enc = new TextEncoder().encode(s);
    return [enc.length, ...enc];
}

/* ------------------------------------------------------------------ utilidades */

export function descargarBlob(blob, nombre) {
    const url = URL.createObjectURL(blob);
    descargarDataUrl(url, nombre);
    setTimeout(() => URL.revokeObjectURL(url), 4000);
}

function descargarDataUrl(url, nombre) {
    const a = document.createElement('a');
    a.href = url;
    a.download = nombre;
    document.body.appendChild(a);
    a.click();
    a.remove();
}

function slug(s) {
    return String(s || 'partitura')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)/g, '') || 'partitura';
}

function esc(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&apos;' }[c]));
}
