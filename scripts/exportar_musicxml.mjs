#!/usr/bin/env node
/**
 * Exporta MusicXML 4.0 desde los JSON v4.
 * No es partitura oficial: sale de la transcripción del cuadernillo.
 */
import { readFileSync, writeFileSync, mkdirSync, readdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { JSDOM } from 'jsdom';
import { generarMusicXML } from '../resources/js/partitura/exporters.js';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const srcDir = join(root, 'database/data/partituras-v4');
const outDir = join(srcDir, 'investigacion/musicxml');

const dom = new JSDOM('<!DOCTYPE html>', { url: 'http://localhost' });
global.window = dom.window;
global.document = dom.window.document;
global.DOMParser = dom.window.DOMParser;
global.XMLSerializer = dom.window.XMLSerializer;

mkdirSync(outDir, { recursive: true });
const files = readdirSync(srcDir).filter((f) => /^\d{2}-.+\.json$/.test(f)).sort();
for (const f of files) {
    const score = JSON.parse(readFileSync(join(srcDir, f), 'utf8'));
    const xml = generarMusicXML(score);
    const dest = join(outDir, f.replace(/\.json$/, '.musicxml'));
    writeFileSync(dest, xml, 'utf8');
    console.log(`${f} → ${dest.split('/').pop()} (${xml.length} chars)`);
}
console.log(`${files.length} MusicXML`);
