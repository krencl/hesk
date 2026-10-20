import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { type FormEvent, useState } from 'react';
import { Link, Navigate, useNavigate, useParams } from 'react-router-dom';
import { api } from '../api';
import { ApiError } from '../api/client';
import type { PartyDetail } from '../api/types';
import { Actions } from '../components/Actions';
import { ErrorMessage } from '../components/ErrorMessage';
import { PartyHeader } from '../components/PartyHeader';
import { ListSkeleton } from '../components/Skeleton';

/** Úprava názvu a zkratky party (jen admin); po uložení se vrací na detail party. */
export function PartyEditPage() {
  const id = Number(useParams().id);
  const detail = useQuery({ queryKey: ['party', id], queryFn: () => api.party(id), retry: false });

  if (detail.error instanceof ApiError && (detail.error.status === 404 || detail.error.status === 403)) {
    return (
      <section className="page pad">
        <p className="empty">Tahle parta neexistuje nebo v ní nejsi.</p>
        <Actions><Link className="btn outline-secondary" to="/parties">Správa part</Link></Actions>
      </section>
    );
  }
  if (!detail.data) return detail.error ? <section className="page pad"><ErrorMessage inline error={detail.error} /></section> : <ListSkeleton rows={2} withImage={false} />;
  if (detail.data.role !== 'admin') return <Navigate to={`/parties/${id}`} replace />;

  return <PartyEditForm party={detail.data} />;
}

function PartyEditForm({ party }: { party: PartyDetail }) {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [name, setName] = useState(party.name);
  const [shortName, setShortName] = useState(party.shortName);

  const update = useMutation({
    mutationFn: () => api.updateParty(party.id, { name: name.trim(), shortName: shortName.trim() }),
    onSuccess: async () => {
      await Promise.all([queryClient.invalidateQueries({ queryKey: ['parties'] }), queryClient.invalidateQueries({ queryKey: ['party'] })]);
      navigate(`/parties/${party.id}`, { replace: true });
    },
  });
  const submit = (e: FormEvent) => {
    e.preventDefault();
    update.mutate();
  };

  return (
    <section className="page">
      <PartyHeader party={party} />
      <form className="pad form" onSubmit={submit}>
        <label>Název<input value={name} maxLength={100} required onChange={(e) => setName(e.target.value)} /></label>
        <label>
          Zkratka
          <input value={shortName} maxLength={3} required onChange={(e) => setShortName(e.target.value)} />
          <small className="muted">1–3 znaky v kulatém tlačítku v hlavičce.</small>
        </label>
        <ErrorMessage error={update.error} />
        <Actions>
          <button className="btn" type="submit" disabled={update.isPending || !name.trim() || !shortName.trim()}>Uložit</button>
          <Link className="btn outline-secondary" to={`/parties/${party.id}`}>Zrušit</Link>
        </Actions>
      </form>
    </section>
  );
}
