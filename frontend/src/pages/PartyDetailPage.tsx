import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useApp } from '../AppContext';
import { api } from '../api';
import { ApiError } from '../api/client';
import { ErrorMessage } from '../components/ErrorMessage';
import { ListSkeleton } from '../components/Skeleton';

/** Detail party: členové a jejich role, pozvánky a opuštění party. Seznam part a zakládání jsou ve správě part. */
export function PartyDetailPage() {
  const id = Number(useParams().id);
  const { me, activeParty, setActiveParty } = useApp();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [inviteLink, setInviteLink] = useState<string | null>(null);
  const detail = useQuery({ queryKey: ['party', id], queryFn: () => api.party(id), retry: false });
  const refresh = () => Promise.all([queryClient.invalidateQueries({ queryKey: ['parties'] }), queryClient.invalidateQueries({ queryKey: ['party'] })]);

  const invite = useMutation({
    mutationFn: () => api.invite(id),
    onSuccess: (r) => setInviteLink(`${window.location.origin}/join/${r.token}`),
  });
  const changeRole = useMutation({ mutationFn: (v: { userId: number; role: 'admin' | 'member' }) => api.changeRole(id, v.userId, v.role), onSuccess: refresh });
  const remove = useMutation({
    mutationFn: (userId: number) => api.removeMember(id, userId),
    onSuccess: async (_, userId) => {
      await refresh();
      if (userId === me.id) navigate('/parties', { replace: true });
    },
  });

  if (detail.error instanceof ApiError && (detail.error.status === 404 || detail.error.status === 403)) {
    return (
      <section className="detail">
        <p className="empty">Tahle parta neexistuje nebo v ní nejsi.</p>
        <div className="nav-actions"><Link className="btn secondary" to="/parties">Správa part</Link></div>
      </section>
    );
  }
  if (!detail.data) return detail.error ? <section className="detail"><ErrorMessage error={detail.error} /></section> : <ListSkeleton rows={3} withImage={false} />;

  const party = detail.data;
  const isAdmin = party.role === 'admin';

  return (
    <section className="flush party-detail">
      <div className="pad">
        <h2>{party.name}</h2>
        <p className="muted">
          {isAdmin ? 'Jsi admin této party.' : 'Jsi člen této party.'}
          {party.id !== activeParty?.id && (
            <> <button type="button" className="link" onClick={() => setActiveParty(party.id)}>Přepnout na tuto partu</button></>
          )}
        </p>
        <h3>Členové</h3>
        <ErrorMessage error={changeRole.error ?? remove.error} />
      </div>
      <ul className="list">
        {party.members.map((m) => (
          <li key={m.userId} className="member">
            <span>{m.nickname} {m.role === 'admin' && <small className="muted">(admin)</small>}</span>
            {isAdmin && (
              <span className="actions">
                <button type="button" className="link" onClick={() => changeRole.mutate({ userId: m.userId, role: m.role === 'admin' ? 'member' : 'admin' })}>
                  {m.role === 'admin' ? 'Odebrat admina' : 'Udělat adminem'}
                </button>
                {m.userId !== me.id && (
                  <button type="button" className="link danger" onClick={() => window.confirm(`Odebrat ${m.nickname}?`) && remove.mutate(m.userId)}>Odebrat</button>
                )}
              </span>
            )}
            {!isAdmin && m.userId === me.id && (
              <button type="button" className="link danger" onClick={() => window.confirm('Opustit partu?') && remove.mutate(m.userId)}>Opustit</button>
            )}
          </li>
        ))}
      </ul>

      <div className="pad">
        {isAdmin && (
          <>
            <button type="button" className="btn secondary" onClick={() => invite.mutate()}>Pozvat hráče</button>
            <ErrorMessage error={invite.error} />
            {inviteLink && (
              <p className="invite">
                Pošli tento odkaz (platí 7 dní):<br />
                <input readOnly value={inviteLink} onFocus={(e) => e.currentTarget.select()} aria-label="Odkaz pozvánky" />
              </p>
            )}
          </>
        )}
        <div className="nav-actions">
          <Link className="btn outline-secondary" to="/parties">Správa part</Link>
        </div>
      </div>
    </section>
  );
}
