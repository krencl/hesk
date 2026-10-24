import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { api } from '../api';
import { qk } from '../api/queryKeys';
import type { Party } from '../api/types';
import { describeMove } from '../lib/playMove';
import { ErrorMessage } from './ErrorMessage';
import { Icon } from './Icon';
import { Popup } from './Popup';

interface Props {
  playId: number;
  gameName: string;
  /** Party, kam jde zápis přesunout (správce je v nich i v té současné). */
  targets: Party[];
  /** Zavření popupu; `moved` je `true`, když se zápis přesunul (stránka zápisu už pak neplatí). */
  onClose: (moved: boolean) => void;
}

/**
 * Přesun zápisu do jiné party (ADR 0028): výběr party → vysvětlení, co se stane → výsledek (kteří hosté vznikli).
 * Stejný vzhled jako ostatní popupy (`GuestLinkPopup`).
 */
export function MovePlayPopup({ playId, gameName, targets, onClose }: Props) {
  const [target, setTarget] = useState<Party | null>(null);
  const queryClient = useQueryClient();
  const move = useMutation({
    mutationFn: (partyId: number) => api.movePlay(playId, partyId),
    // všechno, co z party vychází (seznamy, statistiky, hosté, seance), se změnilo v obou partách; náhled přesunu už neplatí
    onSuccess: () => queryClient.invalidateQueries({ predicate: (q) => q.queryKey[0] !== qk.movePreview(0, 0)[0] }),
  });
  // co se stane s hráči, ukáže backend stejným pravidlem, jakým pak přesun provede
  const preview = useQuery({ queryKey: qk.movePreview(playId, target?.id ?? 0), queryFn: () => api.movePlayPreview(playId, target!.id), enabled: !!target, staleTime: 0, gcTime: 0 });
  const created = move.data?.createdGuests ?? [];
  const close = () => onClose(move.isSuccess);

  return (
    <Popup title={move.data ? 'Zápis přesunut' : target ? `Přesunout do party ${target.name}?` : 'Přesunout zápis do jiné party'} onClose={close} busy={move.isPending}>
      {!target && (
        <>
          <p className="confirm-text">Vyber partu, do které se zápis hry {gameName} přesune. Zobrazují se jen party, kde jsi správce.</p>
          <ul className="select-popup-list" role="listbox" aria-label="Party">
            {targets.map((p) => (
              <li key={p.id} role="presentation">
                <button type="button" role="option" aria-selected={false} className="select-option" onClick={() => setTarget(p)}>
                  <Icon name="radio_button_unchecked" className="select-option-icon" />
                  {p.name}
                </button>
              </li>
            ))}
          </ul>
        </>
      )}

      {target && !move.data && (
        <>
          <div className="confirm-text">
            <p>Zápis přejde do party {target.name} a z této party zmizí, včetně příspěvku do statistik.</p>
            {preview.data && (
              <>
                <p>Hráči se převedou takto (host se stejným jménem se bere jako táž osoba):</p>
                <ul className="move-preview" aria-label="Převod hráčů">
                  {preview.data.players.map((p) => (
                    <li key={p.playerId}>
                      <span>{p.name} → {p.targetName}</span>
                      <small>{describeMove(p)}</small>
                    </li>
                  ))}
                </ul>
                {preview.data.gameAdded && <p>Hra {gameName} se do party {target.name} přidá.</p>}
              </>
            )}
            {preview.isPending && <p>Zjišťuji, co se s hráči stane…</p>}
          </div>
          <ErrorMessage error={preview.error} />
          <ErrorMessage error={move.error} />
          <div className="confirm-actions">
            <button type="button" className="btn destructive" disabled={move.isPending || !preview.data} onClick={() => move.mutate(target.id)}>Přesunout</button>
            <button type="button" className="btn outline-secondary" disabled={move.isPending} onClick={() => { move.reset(); setTarget(null); }}>Zpět</button>
          </div>
        </>
      )}

      {move.data && (
        <>
          <div className="confirm-text">
            <p>Zápis je v partě {move.data.party.name}.</p>
            {created.length > 0 && <p>Jako hosté tam vznikli: {created.join(', ')}.</p>}
          </div>
          <div className="confirm-actions">
            <button type="button" className="btn" onClick={close}>Hotovo</button>
          </div>
        </>
      )}
    </Popup>
  );
}
