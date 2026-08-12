/**
 * Exportaciones: PNG, PDF, MusicXML y MIDI.
 */
import { instrumentoPorId, GOLPES } from './instruments.js';
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
    doc.text(score.title, margen, 20);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(10);
    doc.text(`${score.autor || 'La Chilinga'} · ♩=${score.tempo} · ${score.timeSignature.num}/${score.timeSignature.den}`, margen, 26);

    let y = 34;
    for (const svg of svgs) {
        const { canvas, w, h } = await svgAImagen(svg, 2);
        const alto = (h * usableW) / w;
        if (y + alto > pageH - margen) {
            doc.addPage();
            y = margen;
        }
        // JPEG: el PNG de un pentagrama grande genera PDFs de decenas de MB
        doc.addImage(canvas.toDataURL('image/jpeg', 0.92), 'JPEG', margen, y, usableW, alto);
        y += alto + 3;
    }

    doc.save(`${slug(score.title)}.pdf`);
}

/* ------------------------------------------------------------------ MusicXML */

const TIPO_XML = { w: 'whole', h: 'half', q: 'quarter', 8: 'eighth', 16: '16th', 32: '32nd' };

export function generarMusicXML(score) {
    const partes = score.instruments.map((cfg, i) => ({ cfg, def: instrumentoPorId(cfg.id), pid: `P${i + 1}` })).filter((p) => p.def);
    const capacidad = ticksDeCompas(score.timeSignature);

    let xml = '<?xml version="1.0" encoding="UTF-8"?>\n';
    xml += '<!DOCTYPE score-partwise PUBLIC "-//Recordare//DTD MusicXML 3.1 Partwise//EN" "http://www.musicxml.org/dtds/partwise.dtd">\n';
    xml += '<score-partwise version="3.1">\n';
    xml += `  <work><work-title>${esc(score.title)}</work-title></work>\n`;
    xml += `  <identification><creator type="composer">${esc(score.autor || 'La Chilinga')}</creator>\n`;
    xml += '    <encoding><software>ITO Partituras</software></encoding></identification>\n';
    xml += '  <part-list>\n';
    partes.forEach((p) => {
        xml += `    <score-part id="${p.pid}"><part-name>${esc(p.def.label)}</part-name>`;
        xml += `<score-instrument id="${p.pid}-I1"><instrument-name>${esc(p.def.label)}</instrument-name></score-instrument>`;
        xml += `<midi-instrument id="${p.pid}-I1"><midi-channel>10</midi-channel><midi-unpitched>${p.def.midi + 1}</midi-unpitched></midi-instrument>`;
        xml += '</score-part>\n';
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
                    xml += `        <time><beats>${score.timeSignature.num}</beats><beat-type>${score.timeSignature.den}</beat-type></time>\n`;
                    xml += '        <clef><sign>percussion</sign><line>2</line></clef>\n';
                    xml += '      </attributes>\n';
                }
                if (mi === 0) {
                    xml += `      <direction placement="above"><direction-type><words>${esc(sec.name)}${sec.repeatX > 1 ? ` ×${sec.repeatX}` : ''}</words></direction-type></direction>\n`;
                }
                if (nro === 1) {
                    xml += `      <direction placement="above"><direction-type><metronome><beat-unit>quarter</beat-unit><per-minute>${score.tempo}</per-minute></metronome></direction-type><sound tempo="${score.tempo}"/></direction>\n`;
                }
                if (m.repeatBegin) xml += '      <barline location="left"><bar-style>heavy-light</bar-style><repeat direction="forward"/></barline>\n';
                if (m.ending) xml += `      <barline location="left"><ending number="${m.ending}" type="start">${m.ending}.</ending></barline>\n`;
                if (m.texto) xml += `      <direction placement="above"><direction-type><words>${esc(m.texto)}</words></direction-type></direction>\n`;

                let acum = 0;
                (m.voces[p.cfg.id] || []).forEach((n) => {
                    xml += notaXML(n, p.def);
                    acum += ticksDeNota(n);
                });
                if (acum < capacidad) {
                    xml += `      <note><rest measure="no"/><duration>${capacidad - acum}</duration><voice>1</voice></note>\n`;
                }

                if (m.repeatEnd) xml += '      <barline location="right"><bar-style>light-heavy</bar-style><repeat direction="backward"/></barline>\n';
                else if (mi === sec.measures.length - 1 && si === score.sections.length - 1) {
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

function notaXML(n, def) {
    const dur = ticksDeNota(n);
    const tipo = TIPO_XML[n.dur] || 'quarter';
    let s = '      <note>\n';
    if (n.rest) {
        s += '        <rest/>\n';
    } else {
        s += `        <unpitched><display-step>${def.pitch.split('/')[0].toUpperCase()}</display-step><display-octave>${def.pitch.split('/')[1]}</display-octave></unpitched>\n`;
    }
    s += `        <duration>${dur}</duration>\n`;
    s += '        <voice>1</voice>\n';
    s += `        <type>${tipo}</type>\n`;
    for (let d = 0; d < (n.dots || 0); d++) s += '        <dot/>\n';
    if (n.tuplet) {
        s += `        <time-modification><actual-notes>${n.tuplet.num}</actual-notes><normal-notes>${n.tuplet.den}</normal-notes></time-modification>\n`;
    }
    if (!n.rest) {
        const g = GOLPES[n.stroke] || GOLPES.nota;
        const cabezas = { x: 'x', circled: 'circle-x', triangle: 'triangle', diamond: 'diamond', slash: 'slash', normal: 'normal' };
        s += `        <notehead>${cabezas[g.cabeza] || 'normal'}</notehead>\n`;
        if (g.articulacion === 'a>' || g.articulacion === 'a^' || g.articulacion === 'a-') {
            const art = g.articulacion === 'a>' ? 'accent' : g.articulacion === 'a^' ? 'strong-accent' : 'tenuto';
            s += `        <notations><articulations><${art}/></articulations></notations>\n`;
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
            const def = instrumentoPorId(cfg.id);
            if (!def || cfg.mute) return;
            let local = 0;
            (m.voces[cfg.id] || []).forEach((n) => {
                if (!n.rest) {
                    const golpe = GOLPES[n.stroke] || GOLPES.nota;
                    const vel = Math.max(20, Math.min(127, Math.round(96 * golpe.gain * (cfg.volume || 1))));
                    eventos.push({ t: cursor + local, on: true, note: def.midi, vel });
                    eventos.push({ t: cursor + local + 6, on: false, note: def.midi, vel: 0 });
                }
                local += ticksDeNota(n);
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
