import { useMutation, useQueryClient } from '@tanstack/react-query';
import { type FormEvent, useState } from 'react';
import { Link, Navigate, useNavigate, useParams } from 'react-router-dom';
import { api } from '../api';
import type { PartyDetail } from '../api/types';
import { Actions } from '../components/Actions';
import { ErrorMessage } from '../components/ErrorMessage';
import { PartyHeader } from '../components/PartyHeader';
import { LoadingOrError, PartyNotFound, isNotFound } from '../components/PageState';
import { usePartyDetail } from '../lib/usePartyDetail';
import { LEAVE_PARTY_MANAGEMENT_PATH } from '../lib/partyPath';
import { qk } from '../api/queryKeys';

/** Úprava názvu a zkratky party (jen admin); po uložení se vrací na detail party. */
export function PartyEditPage() {
  const id = Number(useParams().id);
  const detail = usePartyDetail(id);

  if (isNotFound(detail.error)) return <PartyNotFound />;
  if (!detail.data) return <LoadingOrError error={detail.error} rows={2} withImage={false} />;
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
      await Promise.all([queryClient.invalidateQueries({ queryKey: qk.parties() }), queryClient.invalidateQueries({ queryKey: qk.party() })]);
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
          <Link className="btn outline-secondary" to={LEAVE_PARTY_MANAGEMENT_PATH}>Zrušit</Link>
        </Actions>
      </form>
    </section>
  );
}
