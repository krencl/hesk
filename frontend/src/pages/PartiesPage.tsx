import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { type FormEvent, useState } from 'react';
import { useApp } from '../AppContext';
import { api } from '../api';
import { ErrorMessage } from '../components/ErrorMessage';

export function PartiesPage() {
  const { me, parties, activeParty, setActiveParty } = useApp();
  const queryClient = useQueryClient();
  const [name, setName] = useState('');
  const [inviteLink, setInviteLink] = useState<string | null>(null);
  const detail = useQuery({ queryKey: ['party', activeParty?.id], queryFn: () => api.party(activeParty!.id), enabled: !!activeParty });
  const refresh = () => Promise.all([queryClient.invalidateQueries({ queryKey: ['parties'] }), queryClient.invalidateQueries({ queryKey: ['party'] })]);

  const create = useMutation({
    mutationFn: () => api.createParty(name.trim()),
    onSuccess: async (p) => { setName(''); await refresh(); setActiveParty(p.id); },
  });
  const invite = useMutation({
    mutationFn: () => api.invite(activeParty!.id),
    onSuccess: (r) => setInviteLink(`${window.location.origin}/join/${r.token}`),
  });
  const changeRole = useMutation({ mutationFn: (v: { userId: number; role: 'admin' | 'member' }) => api.changeRole(activeParty!.id, v.userId, v.role), onSuccess: refresh });
  const remove = useMutation({ mutationFn: (userId: number) => api.removeMember(activeParty!.id, userId), onSuccess: refresh });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    create.mutate();
  };
  const isAdmin = activeParty?.role === 'admin';

  return (
    <section>
      <h2>Party</h2>
      <ul className="list">
        {parties.map((p) => (
          <li key={p.id}>
            <button type="button" className={`list-btn ${p.id === activeParty?.id ? 'active' : ''}`} onClick={() => setActiveParty(p.id)}>
              {p.name} <small className="muted">{p.role === 'admin' ? 'admin' : ''}</small>
            </button>
          </li>
        ))}
      </ul>

      <form className="form inline" onSubmit={submit}>
        <input value={name} onChange={(e) => setName(e.target.value)} placeholder="Název nové party" maxLength={100} required aria-label="Název nové party" />
        <button className="btn" type="submit" disabled={create.isPending}>Vytvořit</button>
      </form>
      <ErrorMessage error={create.error} />

      {activeParty && (
        <>
          <h3>Členové – {activeParty.name}</h3>
          <ErrorMessage error={changeRole.error ?? remove.error} />
          <ul className="list">
            {detail.data?.members.map((m) => (
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
          {isAdmin && (
            <>
              <button type="button" className="btn secondary" onClick={() => invite.mutate()}>Pozvat hráče</button>
              {inviteLink && (
                <p className="invite">
                  Pošli tento odkaz (platí 7 dní):<br />
                  <input readOnly value={inviteLink} onFocus={(e) => e.currentTarget.select()} aria-label="Odkaz pozvánky" />
                </p>
              )}
            </>
          )}
        </>
      )}
    </section>
  );
}
