import DOMPurify from 'dompurify';
import { marked } from 'marked';

/**
 * Taháček → bezpečné HTML. Starý prototyp ukládal taháčky jako Markdown (vykresloval je knihovnou `marked`)
 * a import je převzal beze změny; Markdown zároveň propouští vložené HTML, takže funguje i pro ručně psané HTML.
 * Obsah pochází od ostatních členů skupiny, proto se výstup vždy sanitizuje.
 */
export function renderCheatSheet(source: string): string {
  return DOMPurify.sanitize(marked.parse(source, { async: false, gfm: true, breaks: false }));
}
