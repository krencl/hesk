import DOMPurify from 'dompurify';

/** Cheat-sheet pochází od ostatních členů skupiny, proto se HTML vždy sanitizuje. */
export function SafeHtml({ html }: { html: string }) {
  return <div className="safe-html" dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(html) }} />;
}
