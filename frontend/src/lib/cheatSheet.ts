import DOMPurify from 'dompurify';
import { marked } from 'marked';

/**
 * Cheatsheet → bezpečné HTML. Starý prototyp ukládal cheatsheety jako Markdown (vykresloval je knihovnou `marked`)
 * a import je převzal beze změny; Markdown zároveň propouští vložené HTML, takže funguje i pro ručně psané HTML.
 * Obsah pochází od ostatních členů party, proto se výstup vždy sanitizuje.
 */
export function renderCheatSheet(source: string): string {
  return DOMPurify.sanitize(marked.parse(source, { async: false, gfm: true, breaks: false }));
}
