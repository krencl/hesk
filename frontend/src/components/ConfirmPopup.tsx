import { type ReactNode, useRef } from 'react';
import { Popup } from './Popup';

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
 * Potvrzení akce v popupu ve stejném designu jako výběr (`SelectPopup`, společný základ `Popup`). Výchozí zaostření má
 * „Zrušit“, takže Enter omylem nic nesmaže. Komponenta se vykresluje jen po dobu, kdy se potvrzuje (rodič ji podmíněně renderuje).
 */
export function ConfirmPopup({ title, children, confirmLabel, onConfirm, onCancel, destructive = false }: Props) {
  const cancelRef = useRef<HTMLButtonElement>(null);
  return (
    <Popup title={title} role="alertdialog" onClose={onCancel} initialFocusRef={cancelRef}>
      <div className="confirm-text">{children}</div>
      <div className="confirm-actions">
        <button type="button" className={`btn${destructive ? ' destructive' : ''}`} onClick={onConfirm}>{confirmLabel}</button>
        <button type="button" ref={cancelRef} className="btn outline-secondary" onClick={onCancel}>Zrušit</button>
      </div>
    </Popup>
  );
}
