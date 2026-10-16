import DOMPurify from 'dompurify';

/** Cizí HTML se vždy sanitizuje (pro cheatsheety viz `renderCheatSheet`, který navíc zpracuje Markdown). */
export function SafeHtml({ html }: { html: string }) {
  return <div className="safe-html" dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(html) }} />;
}
