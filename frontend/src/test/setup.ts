import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach, vi } from 'vitest';

afterEach(() => {
  cleanup();
  vi.unstubAllGlobals(); // `mockApi` nahrazuje `fetch`
  localStorage.clear();
  sessionStorage.clear();
});
