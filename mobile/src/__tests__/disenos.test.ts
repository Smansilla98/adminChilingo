import { htmlDelLienzo, leerCanvas } from '@/features/disenos/Lienzo';

it('lee el JSON del editor web y tolera contenido inválido', () => {
  expect(leerCanvas('{no json').objects).toEqual([]);
  expect(leerCanvas('{"objects":[{"type":"rect"}]}').objects).toHaveLength(1);
});

it('exporta la pieza en tamaño real, escapando el texto', () => {
  const html = htmlDelLienzo({ objects: [
    { type: 'rect', left: 0, top: 0, width: 1080, height: 1350, fill: '#000000' },
    { type: 'textbox', left: 10, top: 20, width: 500, text: 'Show <gratis>\n20 h', fill: '#fff', fontSize: 60 },
  ] }, 1080, 1350);
  expect(html).toContain('size:1080px 1350px');
  expect(html).toContain('Show &lt;gratis&gt;<br/>20 h');
  expect(html).toContain('font-size:60px');
});
