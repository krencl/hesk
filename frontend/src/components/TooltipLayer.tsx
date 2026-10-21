import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { placeTooltip, tooltipTarget } from '../lib/tooltip';

/** Prodleva, než se tooltip po najetí myší ukáže (zaostření klávesnicí ho ukáže hned). */
export const TOOLTIP_DELAY_MS = 350;

/**
 * Jediná globální vrstva tooltipů: sama sleduje najetí myší a zaostření a ukáže popisek u každého tlačítka s ikonou
 * (viz `tooltipTarget`). Je v portálu, takže ho nic neořízne, a drží se v rámci okna. Na dotykových zařízeních se nezobrazuje.
 */
export function TooltipLayer() {
  const [tip, setTip] = useState<{ element: HTMLElement; text: string } | null>(null);
  const [visible, setVisible] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    let timer: number | undefined;
    // poslední způsob ovládání: po kliknutí myší (zaostření až po pointerdown) tooltip neukazujeme, po klávese ano
    let usingPointer = false;
    const hide = () => {
      window.clearTimeout(timer);
      setTip(null);
      setVisible(false);
    };
    const show = (target: { element: HTMLElement; text: string }, delay: number) => {
      window.clearTimeout(timer);
      timer = window.setTimeout(() => setTip(target), delay);
    };

    const over = (e: Event) => {
      if ((e as PointerEvent).pointerType === 'touch') return;
      const target = tooltipTarget(e.target);
      if (!target) return hide();
      setTip((current) => (current?.element === target.element ? current : null));
      show(target, TOOLTIP_DELAY_MS);
    };
    const focus = (e: Event) => {
      const target = tooltipTarget(e.target);
      if (target && !usingPointer) show(target, 0);
      else hide();
    };
    const pressed = () => {
      usingPointer = true;
      hide();
    };
    const key = (e: Event) => {
      usingPointer = false;
      if ((e as KeyboardEvent).key === 'Escape') hide();
    };

    document.addEventListener('pointerover', over);
    document.addEventListener('pointerdown', pressed);
    document.addEventListener('focusin', focus);
    document.addEventListener('focusout', hide);
    document.addEventListener('keydown', key);
    window.addEventListener('scroll', hide, true);
    window.addEventListener('blur', hide);
    return () => {
      window.clearTimeout(timer);
      document.removeEventListener('pointerover', over);
      document.removeEventListener('pointerdown', pressed);
      document.removeEventListener('focusin', focus);
      document.removeEventListener('focusout', hide);
      document.removeEventListener('keydown', key);
      window.removeEventListener('scroll', hide, true);
      window.removeEventListener('blur', hide);
    };
  }, []);

  // Po vykreslení se tooltip změří, umístí k prvku a teprve pak se plynule objeví.
  useLayoutEffect(() => {
    const node = ref.current;
    if (!tip || !node) return;
    const rect = node.getBoundingClientRect();
    const place = placeTooltip(tip.element.getBoundingClientRect(), { width: rect.width, height: rect.height }, { width: window.innerWidth });
    node.style.left = `${place.left}px`;
    node.style.top = `${place.top}px`;
    node.style.setProperty('--arrow-x', `${place.arrowX}px`);
    node.dataset.placement = place.placement;
    setVisible(true);
  }, [tip]);

  // Prvek zmizel z DOM (např. po smazání) → tooltip taky.
  useEffect(() => {
    if (!tip) return;
    const gone = window.setInterval(() => !tip.element.isConnected && setTip(null), 300);
    return () => window.clearInterval(gone);
  }, [tip]);

  if (!tip) return null;
  return createPortal(
    <div ref={ref} className={`tooltip${visible ? ' visible' : ''}`} role="tooltip">
      {tip.text}
    </div>,
    document.body,
  );
}
