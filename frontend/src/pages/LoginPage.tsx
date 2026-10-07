import { useQueryClient } from '@tanstack/react-query';
import { type FormEvent, useState } from 'react';
import { ApiError, post } from '../api/client';

export function LoginPage() {
  const queryClient = useQueryClient();
  const [email, setEmail] = useState('dev@example.com');
  const [error, setError] = useState<string | null>(null);

  // Pouze ve vývoji: přihlášení bez Googlu (backend endpoint existuje jen v env dev/test).
  const devLogin = async (e: FormEvent) => {
    e.preventDefault();
    try {
      await post('/auth/dev-login', { email, nickname: email.split('@')[0] });
      await queryClient.invalidateQueries({ queryKey: ['me'] });
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Přihlášení selhalo.');
    }
  };

  return (
    <div className="login">
      <img className="login-logo" src={`${import.meta.env.BASE_URL}logo.svg`} alt="HESK" />
      <p>Zapisuj odehrané deskovky a sleduj, kdo vyhrává.</p>
      <a className="btn" href="/api/auth/google">Přihlásit přes Google</a>
      {import.meta.env.DEV && (
        <form onSubmit={devLogin} className="dev-login">
          <small>Vývojové přihlášení</small>
          <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} aria-label="E-mail" />
          <button type="submit" className="btn secondary">Přihlásit (dev)</button>
          {error && <div className="error">{error}</div>}
        </form>
      )}
    </div>
  );
}
