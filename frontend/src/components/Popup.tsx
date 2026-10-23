import { type KeyboardEvent, type ReactNode, type RefObject, useEffect, useId, useRef } from 'react';
import { createPortal } from 'react-dom';
import { Icon } from './Icon';

interface Props {
  title: ReactNode;
  /** `alertdialog` pro potvrzení akce. */
  role?: 'dialog' | 'alertdialog';
  /** Křížek, Esc i „Zrušit“ v obsahu; popup se zavírá tím, že ho rodič přestane vykreslovat. */
  onClose: () => void;
  /** Při práci na pozadí je křížek neaktivní. */
  busy?: boolean;
  /** Prvek, který se po otevření zaostří (výchozí je samotný popup, aby fungoval Esc). */
  initialFocusRef?: RefObject<HTMLElement | null>;
  /** Kam se po zavření vrátí zaostření (výchozí je prvek, který byl zaostřený před otevřením; Safari tlačítko klepnutím nezaostří). */
  returnFocusRef?: RefObject<HTMLElement | null>;
  closeLabel?: string;
  children: ReactNode;
}

/** Kolik popupů je právě otevřených; `#root` je „inert“, dokud je aspoň jeden (popup je v portálu mimo `#root`). */
let openPopups = 0;

/**
 * Společný základ všech popupů (výběr, potvrzení, ořez obrázku, propojení hosta): stránka překrytá přes portál do `body`,
 * nadpis, křížek na místě kulatého tlačítka party, Esc zavře a po zavření se zaostření vrací tam, kde bylo před otevřením.
 * Zbytek stránky (`#root`) je po dobu otevření `inert`, takže se na něj nedá tabulátorem ani klepnutím dostat.
 * Komponenta se vykresluje jen po dobu, kdy je popup otevřený (rodič ji podmíněně renderuje).
 */
export function Popup({ title, role = 'dialog', onClose, busy = false, initialFocusRef, returnFocusRef, closeLabel = 'Zavřít', children }: Props) {
  const titleId = useId();
  const dialogRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const previous = document.activeElement as HTMLElement | null;
    const root = document.getElementById('root');
    openPopups++;
    if (root) root.inert = true;
    (initialFocusRef?.current ?? dialogRef.current)?.focus();
    return () => {
      // nejdřív zpřístupnit stránku, jinak by se zaostření nemělo kam vrátit
      if (--openPopups === 0 && root) root.inert = false;
      (returnFocusRef?.current ?? previous)?.focus?.();
    };
    // zaostření se řeší jen při otevření
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const onKeyDown = (e: KeyboardEvent) => {
    if (e.key === 'Escape') {
      e.stopPropagation();
      onClose();
    }
  };

  return createPortal(
    <>
      <div ref={dialogRef} tabIndex={-1} className="select-popup" role={role} aria-modal="true" aria-labelledby={titleId} onKeyDown={onKeyDown}>
        <h3 id={titleId} className="select-popup-title">{title}</h3>
        {children}
      </div>
      <button type="button" className="header-button open select-popup-close" aria-label={closeLabel} disabled={busy} onClick={onClose}>
        <Icon name="close" />
      </button>
    </>,
    document.body,
  );
}
