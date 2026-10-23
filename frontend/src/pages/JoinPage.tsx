import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useEffect, useRef } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useApp } from '../AppContext';
import { api } from '../api';
import { ErrorMessage } from '../components/ErrorMessage';
import { qk } from '../api/queryKeys';

/** Přijetí pozvánky: po otevření odkazu se přihlášený uživatel rovnou přidá a parta se stane aktivní. */
export function JoinPage() {
  const { token } = useParams();
  const { setActiveParty } = useApp();
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const started = useRef(false);
  const accept = useMutation({
    mutationFn: () => api.acceptInvitation(token!),
    onSuccess: async (party) => {
      await queryClient.invalidateQueries({ queryKey: qk.parties() });
      setActiveParty(party.id);
      navigate('/', { replace: true });
    },
  });

  useEffect(() => {
    if (!started.current) {
      started.current = true;
      accept.mutate();
    }
  }, [accept]);

  return (
    <section className="page pad empty">
      {accept.isError ? (
        <>
          <ErrorMessage error={accept.error} />
          <Link className="btn" to="/">Zpět domů</Link>
        </>
      ) : (
        <p>Připojuji do party…</p>
      )}
    </section>
  );
}
