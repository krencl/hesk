import { describe, expect, it } from 'vitest';
import { placeTooltip, tooltipTarget } from './tooltip';

const html = (markup: string): HTMLElement => {
  const root = document.createElement('div');
  root.innerHTML = markup;
  return root;
};
const rect = (left: number, top: number, width = 40, height = 40): DOMRect => ({ left, top, width, height, right: left + width, bottom: top + height, x: left, y: top, toJSON: () => ({}) });

describe('tooltipTarget', () => {
  it('gives the aria-label of a button that contains only an icon', () => {
    const root = html('<button aria-label="Smazat hosta Bob"><span class="icon">delete</span></button>');
    expect(tooltipTarget(root.querySelector('.icon'))?.text).toBe('Smazat hosta Bob');
    expect(tooltipTarget(root.querySelector('button'))?.element).toBe(root.querySelector('button'));
  });

  it('gives nothing for a button with its own text, even with an icon', () => {
    expect(tooltipTarget(html('<button aria-label="X"><span class="icon">delete</span> Smazat</button>').querySelector('button'))).toBeNull();
    expect(tooltipTarget(html('<button aria-label="Menu – Pátek">De</button>').querySelector('button'))).toBeNull();
  });

  it('gives nothing without an icon or without a label, and for non-buttons', () => {
    expect(tooltipTarget(html('<button><span class="icon">close</span></button>').querySelector('button'))).toBeNull();
    expect(tooltipTarget(html('<p aria-label="x"><span class="icon">close</span></p>').querySelector('.icon'))).toBeNull();
    expect(tooltipTarget(null)).toBeNull();
  });

  it('lets data-tooltip override the label, enable a text button and (empty) disable it', () => {
    const root = html(`
      <button id="a" aria-label="Zobrazovat hosta Bob" data-tooltip="Nabízí se při zápisu"><span class="icon">visibility</span></button>
      <button id="b" data-tooltip="Pomoc">Text</button>
      <button id="c" aria-label="Zavřít" data-tooltip=""><span class="icon">close</span></button>`);
    expect(tooltipTarget(root.querySelector('#a'))?.text).toBe('Nabízí se při zápisu');
    expect(tooltipTarget(root.querySelector('#b'))?.text).toBe('Pomoc');
    expect(tooltipTarget(root.querySelector('#c'))).toBeNull();
  });
});

describe('placeTooltip', () => {
  const size = { width: 100, height: 30 };

  it('centres the tooltip above the element', () => {
    expect(placeTooltip(rect(200, 100), size, { width: 400 })).toEqual({ left: 170, top: 62, placement: 'top', arrowX: 50 });
  });

  it('puts it below when there is no room above', () => {
    expect(placeTooltip(rect(200, 10), size, { width: 400 })).toMatchObject({ placement: 'bottom', top: 58 });
  });

  it('keeps it inside the window and moves the arrow to the element', () => {
    const right = placeTooltip(rect(360, 100), size, { width: 400 }); // tlačítko u pravého okraje
    expect(right.left).toBe(292);
    expect(right.arrowX).toBe(88); // střed prvku (380) − left
    const left = placeTooltip(rect(0, 100), size, { width: 400 });
    expect(left.left).toBe(8);
    expect(left.arrowX).toBe(12);
  });
});
