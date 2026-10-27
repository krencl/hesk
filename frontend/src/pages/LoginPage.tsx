import { useQueryClient } from '@tanstack/react-query';
import { type FormEvent, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { ApiError, post } from '../api/client';
import { peekInvitationToken } from '../lib/afterLogin';
import { useToasts } from '../components/Toasts';
import { qk } from '../api/queryKeys';

export function LoginPage() {
  const queryClient = useQueryClient();
  const [email, setEmail] = useState('dev@example.com');
  const { showError } = useToasts();
  const registrationDenied = new URLSearchParams(useLocation().search).get('registration') === 'denied';
  const invitation = peekInvitationToken();

  // Pouze ve vývoji: přihlášení bez Googlu (backend endpoint existuje jen v env dev/test).
  const devLogin = async (e: FormEvent) => {
    e.preventDefault();
    try {
      await post('/auth/dev-login', { email, nickname: email.split('@')[0] });
      await queryClient.invalidateQueries({ queryKey: qk.me() });
    } catch (err) {
      showError(err instanceof ApiError ? err.message : 'Přihlášení selhalo.');
    }
  };

  return (
    <div className="login">
      <img className="login-logo" src={`${import.meta.env.BASE_URL}logo.svg`} alt="HESK" />
      <p>Přihlaš se a hraj!</p>
      {registrationDenied && (
        <p role="alert" className="error">Registrace je zatím uzavřená. Účet si můžeš založit jen přes pozvánku od člena party.</p>
      )}
      <a className="btn" href={invitation ? `/api/auth/google?invitation=${encodeURIComponent(invitation)}` : '/api/auth/google'}>Přihlásit přes Google</a>
      {import.meta.env.DEV && (
        <form onSubmit={devLogin} className="dev-login">
          <small>Vývojové přihlášení</small>
          <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} aria-label="E-mail" />
          <button type="submit" className="btn secondary">Přihlásit (dev)</button>
        </form>
      )}
    </div>
  );
}
