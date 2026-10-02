/**
 * Cookie-based Login State Utility (Browser Cookie + Local Cookie State)
 * Stores and validates login state with a 2-hour (7200 seconds) expiration.
 * If the cookie is valid, admin and private pages are accessible without re-logging in on every visit.
 * If expired, invalid, or unavailable, clears state so the app redirects to the login page.
 */

export const AUTH_COOKIE_NAME = "slea_admin_token";
export const AUTH_ALT_COOKIE_NAME = "slea_auth_cookie";
export const AUTH_STATE_COOKIE_NAME = "slea_login_state";
export const AUTH_USER_COOKIE_NAME = "slea_admin_user";
export const AUTH_EXP_COOKIE_NAME = "slea_admin_exp";
export const LOCAL_COOKIE_BACKUP_KEY = "slea_browser_cookie_state";
export const AUTH_COOKIE_MAX_AGE_SEC = 7200; // 2 hours (1-2 hours window)

export function getCookieValue(name: string): string {
  if (typeof document === "undefined") return "";
  const cookies = document.cookie ? document.cookie.split("; ") : [];
  const prefix = `${encodeURIComponent(name)}=`;
  for (const c of cookies) {
    if (c.startsWith(prefix)) {
      try {
        return decodeURIComponent(c.substring(prefix.length));
      } catch {
        return c.substring(prefix.length);
      }
    }
  }
  return "";
}

function writeBrowserCookies(token: string, username: string, expiresAtMs: number): void {
  if (typeof document === "undefined") return;
  const maxAgeSec = Math.max(1, Math.floor((expiresAtMs - Date.now()) / 1000));
  const expiresUtc = new Date(expiresAtMs).toUTCString();
  const secureFlag =
    typeof window !== "undefined" && window.location.protocol === "https:" ? "; Secure" : "";

  document.cookie = `${encodeURIComponent(AUTH_COOKIE_NAME)}=${encodeURIComponent(
    token
  )}; Max-Age=${maxAgeSec}; Expires=${expiresUtc}; Path=/; SameSite=Lax${secureFlag}`;

  document.cookie = `${encodeURIComponent(AUTH_ALT_COOKIE_NAME)}=${encodeURIComponent(
    token
  )}; Max-Age=${maxAgeSec}; Expires=${expiresUtc}; Path=/; SameSite=Lax${secureFlag}`;

  document.cookie = `${encodeURIComponent(AUTH_STATE_COOKIE_NAME)}=1; Max-Age=${maxAgeSec}; Expires=${expiresUtc}; Path=/; SameSite=Lax${secureFlag}`;

  document.cookie = `${encodeURIComponent(AUTH_USER_COOKIE_NAME)}=${encodeURIComponent(
    username
  )}; Max-Age=${maxAgeSec}; Expires=${expiresUtc}; Path=/; SameSite=Lax${secureFlag}`;

  document.cookie = `${encodeURIComponent(AUTH_EXP_COOKIE_NAME)}=${encodeURIComponent(
    String(expiresAtMs)
  )}; Max-Age=${maxAgeSec}; Expires=${expiresUtc}; Path=/; SameSite=Lax${secureFlag}`;
}

export function setLoginCookies(
  token: string,
  username = "admin",
  maxAgeSec: number = AUTH_COOKIE_MAX_AGE_SEC
): void {
  const now = Date.now();
  const expiresAtMs = now + maxAgeSec * 1000;
  writeBrowserCookies(token, username, expiresAtMs);

  try {
    localStorage.setItem(
      LOCAL_COOKIE_BACKUP_KEY,
      JSON.stringify({ token, username, expMs: expiresAtMs })
    );
    localStorage.removeItem("slea_admin_token");
    localStorage.removeItem("slea_admin_user");
  } catch {
    // ignore storage errors
  }
}

export function clearLoginCookies(): void {
  if (typeof document !== "undefined") {
    const pastUtc = "Thu, 01 Jan 1970 00:00:00 GMT";
    for (const name of [
      AUTH_COOKIE_NAME,
      AUTH_ALT_COOKIE_NAME,
      AUTH_STATE_COOKIE_NAME,
      AUTH_USER_COOKIE_NAME,
      AUTH_EXP_COOKIE_NAME,
    ]) {
      document.cookie = `${encodeURIComponent(name)}=; Max-Age=0; Expires=${pastUtc}; Path=/; SameSite=Lax`;
    }
  }
  try {
    localStorage.removeItem(LOCAL_COOKIE_BACKUP_KEY);
    localStorage.removeItem("slea_admin_token");
    localStorage.removeItem("slea_admin_user");
  } catch {
    // ignore
  }
}

function isTokenTimestampValid(token: string, expMs: number): boolean {
  if (!token || token.length < 6) return false;
  const now = Date.now();
  if (expMs > 0 && now > expMs) {
    return false;
  }
  const match = token.match(/^adm_[a-z0-9]{4,16}_(\d{13})$/);
  if (match) {
    const issuedAt = Number(match[1]);
    if (isNaN(issuedAt) || now - issuedAt > AUTH_COOKIE_MAX_AGE_SEC * 1000) {
      return false;
    }
  }
  return true;
}

export function getValidAdminToken(): string {
  const now = Date.now();
  const cookieToken = (
    getCookieValue(AUTH_COOKIE_NAME) || getCookieValue(AUTH_ALT_COOKIE_NAME)
  ).trim();
  const expStr = getCookieValue(AUTH_EXP_COOKIE_NAME).trim();
  const cookieExpMs = expStr ? Number(expStr) : 0;

  if (cookieToken) {
    if (!isNaN(cookieExpMs) && cookieExpMs > 0 && now > cookieExpMs) {
      clearLoginCookies();
      return "";
    }
    if (!isTokenTimestampValid(cookieToken, cookieExpMs)) {
      clearLoginCookies();
      return "";
    }
    return cookieToken;
  }

  // Fallback to local browser cookie state if within 2-hour expiration window
  try {
    const rawBackup = localStorage.getItem(LOCAL_COOKIE_BACKUP_KEY);
    if (rawBackup) {
      const parsed = JSON.parse(rawBackup);
      const bToken = String(parsed?.token || "").trim();
      const bUser = String(parsed?.username || "admin").trim();
      const bExpMs = Number(parsed?.expMs || 0);
      if (bToken && bExpMs > now && isTokenTimestampValid(bToken, bExpMs)) {
        writeBrowserCookies(bToken, bUser, bExpMs);
        return bToken;
      } else {
        clearLoginCookies();
        return "";
      }
    }
  } catch {
    // ignore
  }

  return "";
}
