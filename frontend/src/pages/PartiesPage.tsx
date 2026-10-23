import { useMutation, useQueryClient } from '@tanstack/react-query';
import { type FormEvent, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useApp } from '../AppContext';
import { api } from '../api';
import { ErrorMessage } from '../components/ErrorMessage';
import { PageTitle } from '../components/PageTitle';

/** Správa part: seznam mých part, přepnutí aktivní party a založení nové. Členy spravuje detail party. */
export function PartiesPage() {
  const { parties, activeParty, setActiveParty } = useApp();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [name, setName] = useState('');

  const create = useMutation({
    mutationFn: () => api.createParty(name.trim()),
    onSuccess: async (party) => {
      setName('');
      await queryClient.invalidateQueries({ queryKey: ['parties'] });
      setActiveParty(party.id);
      navigate(`/parties/${party.id}`);
    },
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    create.mutate();
  };

  return (
    <section className="page">
      <PageTitle>Správa part</PageTitle>
      <ul className="list party-list">
        {parties.map((p) => (
          <li key={p.id} className={`party-row${p.id === activeParty?.id ? ' selected' : ''}`} aria-current={p.id === activeParty?.id ? 'true' : undefined}>
            <Link to={`/parties/${p.id}`} className="party-link">
              <strong className="h5">{p.name}</strong>
              {p.id === activeParty?.id && <small className="muted">aktivní</small>}
            </Link>
            {p.id !== activeParty?.id && (
              <button type="button" className="btn small outline" onClick={() => setActiveParty(p.id)}>Přepnout</button>
            )}
          </li>
        ))}
      </ul>

      <div className="pad">
        <h3>Nová parta</h3>
        <form className="form inline" onSubmit={submit}>
          <input value={name} onChange={(e) => setName(e.target.value)} placeholder="Název nové party" maxLength={100} required aria-label="Název nové party" />
          <button className="btn" type="submit" disabled={create.isPending}>Vytvořit</button>
        </form>
        <ErrorMessage error={create.error} />
      </div>
    </section>
  );
}
