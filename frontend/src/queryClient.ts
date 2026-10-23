import { QueryClient } from '@tanstack/react-query';

/** Výchozí chování dotazů aplikace; sdílí ho `main.tsx` i testy (ty smějí přepsat jen to, co potřebují). */
export function createQueryClient(overrides: { retry?: boolean | number } = {}): QueryClient {
  return new QueryClient({ defaultOptions: { queries: { retry: false, refetchOnWindowFocus: false, ...overrides } } });
}
