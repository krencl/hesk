import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { type FormEvent, type KeyboardEvent, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api } from '../api';
import { ApiError } from '../api/client';
import { Actions } from '../components/Actions';
import { ErrorMessage } from '../components/ErrorMessage';
import { Icon } from '../components/Icon';
import { PartyHeader } from '../components/PartyHeader';
import { ListSkeleton } from '../components/Skeleton';
import { playsLabel } from '../lib/plural';

/** Hosté party (hráči bez účtu): přidání, přejmenování a smazání těch, kteří nikde nehráli. */
export function PartyGuestsPage() {
  const id = Number(useParams().id);
  const queryClient = useQueryClient();
  const detail = useQuery({ queryKey: ['party', id], queryFn: () => api.party(id), retry: false });
  const players = useQuery({ queryKey: ['players', id], queryFn: () => api.players(id), enabled: !!detail.data });
  const [edited, setEdited] = useState<Record<number, string>>({});
  const [newName, setNewName] = useState('');

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['players', id] });
  const add = useMutation({
    mutationFn: () => api.createGuest(id, newName.trim()),
    onSuccess: async () => {
      setNewName('');
      await refresh();
    },
  });
  const guests = (players.data ?? []).filter((p) => !p.hasAccount);
  const changed = guests.filter((g) => edited[g.id] !== undefined && edited[g.id].trim() !== g.name);
  const save = useMutation({
    // po jednom, ať se při chybě (např. obsazené jméno) ukáže hned a zbytek se nezmění
    mutationFn: async () => {
      for (const g of changed) await api.renamePlayer(g.id, edited[g.id].trim());
    },
    onSuccess: () => setEdited({}),
    onSettled: refresh,
  });
  const remove = useMutation({ mutationFn: (playerId: number) => api.deletePlayer(playerId), onSuccess: refresh });

  if (detail.error instanceof ApiError && (detail.error.status === 404 || detail.error.status === 403)) {
    return (
      <section className="page pad">
        <p className="empty">Tahle parta neexistuje nebo v ní nejsi.</p>
        <Actions><Link className="btn outline-secondary" to="/parties">Správa part</Link></Actions>
      </section>
    );
  }
  if (!detail.data) return detail.error ? <section className="page pad"><ErrorMessage inline error={detail.error} /></section> : <ListSkeleton rows={3} withImage={false} />;

  const submit = (e: FormEvent) => {
    e.preventDefault();
    if (changed.length > 0) save.mutate();
  };
  const addOnEnter = (e: KeyboardEvent) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      if (newName.trim() && !add.isPending) add.mutate();
    }
  };

  return (
    <section className="page party-guests">
      <PartyHeader party={detail.data} />
      <form className="pad form" onSubmit={submit}>
        <p className="muted">Hosté jsou hráči bez účtu, které zapisuješ do her. Člen, který z party odejde, se stane hostem.</p>
        {players.isPending && <ListSkeleton rows={2} withImage={false} />}
        {players.data && guests.length === 0 && <p className="empty">Zatím tu nejsou žádní hosté.</p>}
        {guests.length > 0 && (
          <ul className="guest-list">
            {guests.map((g) => (
              <li key={g.id} className="guest-row">
                <input
                  value={edited[g.id] ?? g.name}
                  maxLength={100}
                  required
                  aria-label={`Jméno hosta ${g.name}`}
                  onChange={(e) => setEdited({ ...edited, [g.id]: e.target.value })}
                />
                {g.playCount > 0 ? (
                  <span className="guest-plays muted">{playsLabel(g.playCount)}</span>
                ) : (
                  <button
                    type="button"
                    className="icon-button"
                    aria-label={`Smazat hosta ${g.name}`}
                    disabled={remove.isPending}
                    onClick={() => window.confirm(`Smazat hosta ${g.name}?`) && remove.mutate(g.id)}
                  >
                    <Icon name="delete" />
                  </button>
                )}
              </li>
            ))}
          </ul>
        )}
        <div className="guest-add">
          <input value={newName} maxLength={100} placeholder="Nový host" aria-label="Jméno nového hosta" onChange={(e) => setNewName(e.target.value)} onKeyDown={addOnEnter} />
          <button type="button" className="btn outline" disabled={!newName.trim() || add.isPending} onClick={() => add.mutate()}>Přidat</button>
        </div>
        <ErrorMessage error={add.error ?? save.error ?? remove.error} />
        <Actions>
          <button className="btn" type="submit" disabled={changed.length === 0 || save.isPending}>Uložit</button>
          <Link className="btn outline-secondary" to="/parties">Správa part</Link>
        </Actions>
      </form>
    </section>
  );
}
