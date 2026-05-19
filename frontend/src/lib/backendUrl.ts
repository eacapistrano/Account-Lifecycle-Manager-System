import { API_URL } from "./api";

/** Laravel web routes (OAuth redirect), without the `/api` prefix. */
export const BACKEND_WEB_URL = API_URL.replace(/\/api\/?$/, "");
