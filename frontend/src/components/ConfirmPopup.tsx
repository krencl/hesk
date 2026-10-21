import { type KeyboardEvent, type ReactNode, useEffect, useId, useRef } from 'react';
import { createPortal } from 'react-dom';
import { Icon } from './Icon';

interface Props {
  title: string;
  /** Vysvětlení, co se po potvrzení stane. */
  children: ReactNode;
  confirmLabel: string;
  onConfirm: () => void;
  onCancel: () => void;
  /** Potvrzení nevratné akce se zvýrazní červeně. */
  destructive?: boolean;
}

/**
 * Potvrzení akce v popupu ve stejném designu jako výběr (`SelectPopup`): překryje stránku, křížek stojí na místě
 * tlačítka party. Výchozí zaostření má „Zrušit“, takže Enter omylem nic nesmaže. Komponenta se vykresluje jen
 * po dobu, kdy se potvrzuje (rodič ji podmíněně renderuje).
 */
export function ConfirmPopup({ title, children, confirmLabel, onConfirm, onCancel, destructive = false }: Props) {
  const titleId = useId();
  const cancelRef = useRef<HTMLButtonElement>(null);

  // Po zavření se zaostření vrací tam, kde bylo před otevřením (tlačítko, které popup vyvolalo).
  useEffect(() => {
    const previous = document.activeElement as HTMLElement | null;
    cancelRef.current?.focus();
    return () => previous?.focus?.();
  }, []);

  const onKeyDown = (e: KeyboardEvent) => {
    if (e.key === 'Escape') {
      e.stopPropagation();
      onCancel();
    }
  };

  return createPortal(
    <>
      <div className="select-popup" role="alertdialog" aria-modal="true" aria-labelledby={titleId} onKeyDown={onKeyDown}>
        <h3 id={titleId} className="select-popup-title">{title}</h3>
        <div className="confirm-text">{children}</div>
        <div className="confirm-actions">
          <button type="button" className={`btn${destructive ? ' destructive' : ''}`} onClick={onConfirm}>{confirmLabel}</button>
          <button type="button" ref={cancelRef} className="btn outline-secondary" onClick={onCancel}>Zrušit</button>
        </div>
      </div>
      <button type="button" className="header-button open select-popup-close" aria-label="Zavřít" onClick={onCancel}>
        <Icon name="close" />
      </button>
    </>,
    document.body,
  );
}
