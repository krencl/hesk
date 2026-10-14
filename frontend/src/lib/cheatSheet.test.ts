import { describe, expect, it } from 'vitest';
import { renderCheatSheet } from './cheatSheet';

describe('renderCheatSheet', () => {
  it('renders Markdown from the legacy app (headings, lists, tables)', () => {
    const html = renderCheatSheet('# Start hry\n\n- vybrat postavu\n- 2 mince\n\n| a | b |\n|---|---|\n| 1 | 2 |\n');
    expect(html).toContain('<h1>Start hry</h1>');
    expect(html).toContain('<li>vybrat postavu</li>');
    expect(html).toContain('<table>');
    expect(html).toContain('<td>2</td>');
  });

  it('keeps plain HTML working', () => {
    expect(renderCheatSheet('<h2>Pravidla</h2><p>Text</p>')).toContain('<h2>Pravidla</h2>');
  });

  it('strips scripts, event handlers and javascript: links', () => {
    const html = renderCheatSheet('# Nadpis\n\n<script>window.hacked = 1</script>\n\n<img src="x" onerror="window.hacked = 1">\n\n[x](javascript:alert(1))\n\n<a href="javascript:alert(2)">y</a>');
    expect(html).not.toContain('<script');
    expect(html).not.toContain('onerror');
    expect(html).not.toMatch(/href\s*=\s*["']?\s*javascript/i);
    expect(html).toContain('<h1>Nadpis</h1>');
  });

  it('handles an empty sheet', () => {
    expect(renderCheatSheet('')).toBe('');
  });
});
