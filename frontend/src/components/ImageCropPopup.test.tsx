import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { ImageCropPopup } from './ImageCropPopup';

describe('ImageCropPopup', () => {
  it('focuses the cancel button on open so that Escape closes the popup', () => {
    const onCancel = vi.fn();
    render(<ImageCropPopup src="blob:test" onConfirm={vi.fn()} onCancel={onCancel} />);
    expect(screen.getByRole('button', { name: 'Zrušit' })).toHaveFocus();
    fireEvent.keyDown(document.activeElement!, { key: 'Escape' });
    expect(onCancel).toHaveBeenCalledTimes(1);
  });
});
