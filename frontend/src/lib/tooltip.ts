/**
 * Text tooltipu pro prvek, nad kterým je myš / kde je zaostření, nebo `null`, když se tooltip nemá ukázat.
 * Má ho tlačítko či odkaz s ikonou a bez vlastního textu (text je z `aria-label`); `data-tooltip` text přebije
 * (prázdný ho vypne) a funguje i na tlačítku s textem.
 */
export function tooltipTarget(start: EventTarget | null): { element: HTMLElement; text: string } | null {
  if (!(start instanceof Element)) return null;
  const element = start.closest<HTMLElement>('[data-tooltip], button, a');
  if (!element) return null;

  const explicit = element.getAttribute('data-tooltip');
  if (explicit !== null) return explicit.trim() ? { element, text: explicit.trim() } : null;

  const label = element.getAttribute('aria-label')?.trim();
  if (!label || !element.querySelector('.icon')) return null;
  const clone = element.cloneNode(true) as HTMLElement;
  clone.querySelectorAll('.icon').forEach((icon) => icon.remove());
  return clone.textContent?.trim() ? null : { element, text: label };
}

export interface TooltipPlacement {
  left: number;
  top: number;
  placement: 'top' | 'bottom';
  /** Vodorovná poloha špičky šipky uvnitř tooltipu (px). */
  arrowX: number;
}

/** Umístí tooltip nad prvek (nebo pod něj, když nad ním není místo) a vodorovně ho udrží v okně. */
export function placeTooltip(anchor: DOMRect, size: { width: number; height: number }, viewport: { width: number }, gap = 8, margin = 8): TooltipPlacement {
  const placement = anchor.top - size.height - gap < margin ? 'bottom' : 'top';
  const top = placement === 'top' ? anchor.top - size.height - gap : anchor.bottom + gap;
  const center = anchor.left + anchor.width / 2;
  const left = Math.min(Math.max(center - size.width / 2, margin), Math.max(margin, viewport.width - size.width - margin));
  const arrowX = Math.min(Math.max(center - left, 10), size.width - 10);
  return { left, top, placement, arrowX };
}
