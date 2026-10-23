import { useRef, useState } from 'react';
import Cropper from 'react-easy-crop';
import { type CropArea } from '../lib/cropImage';
import { Popup } from './Popup';

interface Props {
  /** Adresa vybraného souboru (object URL). */
  src: string;
  /** Uložení: dostane výřez v pixelech původního obrázku; vrácený příslib drží popup „zaneprázdněný“. */
  onConfirm: (area: CropArea) => Promise<void> | void;
  onCancel: () => void;
  busy?: boolean;
  error?: string | null;
}

/**
 * Online editor obrázku ve stejném popupu jako ostatní výběry: tažením se posouvá, posuvníkem (nebo štípnutím) se
 * přibližuje a vždy se vybírá čtverec, který se po „Použít“ ořízne a nahraje. Komponenta se renderuje jen po dobu úprav.
 */
export function ImageCropPopup({ src, onConfirm, onCancel, busy = false, error = null }: Props) {
  const [crop, setCrop] = useState({ x: 0, y: 0 });
  const [zoom, setZoom] = useState(1);
  const [area, setArea] = useState<CropArea | null>(null);
  const cancelRef = useRef<HTMLButtonElement>(null);

  return (
    <Popup title="Oříznout obrázek" onClose={onCancel} busy={busy} initialFocusRef={cancelRef}>
      <div className="crop-area">
        <Cropper
          image={src}
          crop={crop}
          zoom={zoom}
          minZoom={1}
          maxZoom={4}
          aspect={1}
          onCropChange={setCrop}
          onZoomChange={setZoom}
          onCropComplete={(_, pixels) => setArea(pixels)}
        />
      </div>
      <label className="crop-zoom">
        Přiblížení
        <input type="range" min={1} max={4} step={0.01} value={zoom} onChange={(e) => setZoom(Number(e.target.value))} />
      </label>
      <p className="muted hint">Obrázek přetáhni a přibliž tak, aby ve čtverci byla hra.</p>
      {error && <p className="error" role="alert">{error}</p>}
      <div className="confirm-actions">
        <button type="button" className="btn" disabled={busy || !area} onClick={() => area && onConfirm(area)}>{busy ? 'Nahrávám…' : 'Použít'}</button>
        <button type="button" ref={cancelRef} className="btn outline-secondary" disabled={busy} onClick={onCancel}>Zrušit</button>
      </div>
    </Popup>
  );
}
