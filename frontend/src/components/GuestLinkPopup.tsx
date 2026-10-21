import { useMutation, useQueryClient } from '@tanstack/react-query';
import { type KeyboardEvent, useEffect, useId, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { api } from '../api';
import type { Member } from '../api/types';
import { ROLE_LABELS } from '../lib/roles';
import { playsLabel } from '../lib/plural';
import { ErrorMessage } from './ErrorMessage';
import { Icon } from './Icon';

interface Props {
  partyId: number;
  guest: { id: number; name: string; playCount: number };
  members: Member[];
  onClose: () => void;
}

type Step = 'menu' | 'pick' | 'confirm' | 'invite';

/**
 * Z hosta se má stát člen: buď ho admin rovnou propojí s členem, který už v partě je (zápisy hosta přejdou na něj),
 * nebo mu pošle pozvánku (po jejím přijetí se host propojí s účtem pozvaného). Stejný vzhled jako ostatní popupy.
 */
export function GuestLinkPopup({ partyId, guest, members, onClose }: Props) {
  const [step, setStep] = useState<Step>('menu');
  const [member, setMember] = useState<Member | null>(null);
  const [copied, setCopied] = useState(false);
  const titleId = useId();
  const dialogRef = useRef<HTMLDivElement>(null);
  const queryClient = useQueryClient();

  useEffect(() => {
    const previous = document.activeElement as HTMLElement | null;
    dialogRef.current?.focus();
    return () => previous?.focus?.();
  }, []);

  const link = useMutation({
    mutationFn: (userId: number) => api.linkGuest(guest.id, userId),
    onSuccess: async () => {
      await queryClient.invalidateQueries(); // jména hráčů se promítají do zápisů, her i statistik
      onClose();
    },
  });
  const invite = useMutation({ mutationFn: () => api.invite(partyId, guest.id) });
  const inviteUrl = invite.data ? `${window.location.origin}/join/${invite.data.token}` : '';

  const onKeyDown = (e: KeyboardEvent) => {
    if (e.key === 'Escape') {
      e.stopPropagation();
      onClose();
    }
  };
  const copy = async () => {
    try {
      await navigator.clipboard.writeText(inviteUrl);
      setCopied(true);
    } catch {
      // schránka nemusí být dostupná; odkaz jde zkopírovat z pole ručně
    }
  };

  const title = {
    menu: `Host ${guest.name}`,
    pick: `Propojit hosta ${guest.name} s členem`,
    confirm: `Propojit ${guest.name} s členem ${member?.nickname ?? ''}?`,
    invite: `Pozvánka pro hosta ${guest.name}`,
  }[step];

  return createPortal(
    <>
      <div ref={dialogRef} tabIndex={-1} className="select-popup" role="dialog" aria-modal="true" aria-labelledby={titleId} onKeyDown={onKeyDown}>
        <h3 id={titleId} className="select-popup-title">{title}</h3>

        {step === 'menu' && (
          <ul className="select-popup-list">
            <li>
              <button type="button" className="select-option choice" onClick={() => setStep('pick')}>
                <Icon name="link" className="select-option-icon" />
                <span><b>Propojit s členem</b><small>Zápisy hosta přejdou na člena, který už v partě je.</small></span>
              </button>
            </li>
            <li>
              <button type="button" className="select-option choice" onClick={() => { setStep('invite'); invite.mutate(); }}>
                <Icon name="person_add" className="select-option-icon" />
                <span><b>Pozvat jako člena</b><small>Pošli odkaz; po přijetí se host sám propojí s účtem pozvaného.</small></span>
              </button>
            </li>
          </ul>
        )}

        {step === 'pick' && (
          <>
            <p className="confirm-text">Vyber člena, na kterého přejdou zápisy hosta ({playsLabel(guest.playCount)}).</p>
            <ul className="select-popup-list" role="listbox" aria-label="Členové">
              {members.map((m) => (
                <li key={m.userId} role="option" aria-selected={false} className="select-option" onClick={() => { setMember(m); setStep('confirm'); }}>
                  <Icon name="radio_button_unchecked" className="select-option-icon" />
                  {m.nickname ?? 'člen'} <small className="muted">{ROLE_LABELS[m.role]}</small>
                </li>
              ))}
              {members.length === 0 && <li className="select-popup-empty">V partě není žádný člen.</li>}
            </ul>
          </>
        )}

        {step === 'confirm' && member && (
          <>
            <div className="confirm-text">
              {guest.playCount > 0 ? (
                <p>Všechny zápisy hosta {guest.name} ({playsLabel(guest.playCount)}) přejdou na člena {member.nickname} a host zmizí ze seznamu hostů.</p>
              ) : (
                <p>Host {guest.name} nemá žádné zápisy, takže jen zmizí ze seznamu hostů.</p>
              )}
              <p>Nejde to vrátit zpět. Pokud oba hráli ve stejném zápisu, propojení se nepovede.</p>
            </div>
            <ErrorMessage error={link.error} />
            <div className="confirm-actions">
              <button type="button" className="btn destructive" disabled={link.isPending} onClick={() => link.mutate(member.userId)}>Propojit</button>
              <button type="button" className="btn outline-secondary" onClick={onClose}>Zrušit</button>
            </div>
          </>
        )}

        {step === 'invite' && (
          <>
            <ErrorMessage error={invite.error} />
            {invite.isPending && <p className="confirm-text">Vytvářím pozvánku…</p>}
            {invite.data && (
              <>
                <div className="confirm-text">
                  <p>Pošli tento odkaz (platí 7 dní). Až se pozvaný přihlásí a přijme ho, host {guest.name} se propojí s jeho účtem a všechny jeho zápisy mu zůstanou.</p>
                  <input readOnly value={inviteUrl} onFocus={(e) => e.currentTarget.select()} aria-label="Odkaz pozvánky" />
                </div>
                <div className="confirm-actions">
                  <button type="button" className="btn" onClick={copy}>{copied ? 'Zkopírováno' : 'Zkopírovat odkaz'}</button>
                  <button type="button" className="btn outline-secondary" onClick={onClose}>Hotovo</button>
                </div>
              </>
            )}
          </>
        )}
      </div>
      <button type="button" className="header-button open select-popup-close" aria-label="Zavřít" onClick={onClose}>
        <Icon name="close" />
      </button>
    </>,
    document.body,
  );
}
