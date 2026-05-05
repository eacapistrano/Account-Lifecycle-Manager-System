/** Mirrors backend default `config('security.delete_confirmation_phrase')`. Override with `VITE_DELETE_CONFIRMATION_PHRASE`. */
export const DELETE_CONFIRMATION_PHRASE =
  import.meta.env.VITE_DELETE_CONFIRMATION_PHRASE ?? "DELETE STUDENT ACCOUNTS";
