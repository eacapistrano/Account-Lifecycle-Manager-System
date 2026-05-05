import { DELETE_CONFIRMATION_PHRASE } from "../../../constants/security";

type Props = {
  suspendOpen: boolean;
  deleteOpen: boolean;
  phraseInput: string;
  selectionCount: number;
  onPhraseChange: (value: string) => void;
  onCloseSuspend: () => void;
  onCloseDelete: () => void;
  onConfirmSuspend: () => void | Promise<void>;
  onConfirmDelete: () => void | Promise<void>;
};

export function StudentDeletionModals({
  suspendOpen,
  deleteOpen,
  phraseInput,
  selectionCount,
  onPhraseChange,
  onCloseSuspend,
  onCloseDelete,
  onConfirmSuspend,
  onConfirmDelete,
}: Props) {
  return (
    <>
      {suspendOpen ? (
        <div className="modal-overlay" role="presentation">
          <div className="modal-panel" role="dialog" aria-modal="true" aria-labelledby="suspend-title">
            <h3 id="suspend-title">Confirm suspend</h3>
            <p className="hint">Queue suspend for {selectionCount} accounts? Jobs update registry records and emit audit events.</p>
            <div className="modal-actions">
              <button type="button" className="secondary" onClick={onCloseSuspend}>
                Cancel
              </button>
              <button type="button" onClick={() => void onConfirmSuspend()}>
                Queue suspend
              </button>
            </div>
          </div>
        </div>
      ) : null}

      {deleteOpen ? (
        <div className="modal-overlay" role="presentation">
          <div className="modal-panel" role="dialog" aria-modal="true" aria-labelledby="delete-title">
            <h3 id="delete-title">Confirm destructive delete</h3>
            <p className="hint">
              Type <code>{DELETE_CONFIRMATION_PHRASE}</code> to queue permanent deletion for {selectionCount} accounts.
            </p>
            <label>
              Confirmation phrase
              <input value={phraseInput} onChange={(ev) => onPhraseChange(ev.target.value)} autoComplete="off" />
            </label>
            <div className="modal-actions">
              <button type="button" className="secondary" onClick={onCloseDelete}>
                Cancel
              </button>
              <button type="button" className="danger-button" onClick={() => void onConfirmDelete()}>
                Queue delete
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </>
  );
}
