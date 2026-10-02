import { ApiError } from '../api/client';

export function ErrorMessage({ error }: { error: unknown }) {
  if (!error) return null;
  const e = error instanceof ApiError ? error : null;
  return (
    <div className="error" role="alert">
      {e ? e.message : 'Něco se pokazilo. Zkus to prosím znovu.'}
      {e?.violations.length ? (
        <ul>
          {e.violations.map((v, i) => (
            <li key={i}>{v.message}</li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
