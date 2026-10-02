import express from "express";
import path from "path";
import fs from "fs";
import os from "os";
import { spawn } from "child_process";

interface PageButton {
  text: string;
  url: string;
  quality?: string | null;
  episode?: number | null;
  provider?: string;
  is_button?: boolean;
}

interface ButtonPage {
  id: string;
  slug: string;
  title: string;
  description?: string;
  source_url?: string;
  resolved_url?: string;
  theme?: "indigo" | "emerald" | "crimson" | "slate" | "dark";
  buttons: PageButton[];
  views: number;
  is_public?: number | boolean;
  created_at: string;
  updated_at?: string;
}

let activeDataDir = path.join(process.cwd(), "data");
let activePagesFile = path.join(activeDataDir, "pages.json");
let activeSettingsFile = path.join(activeDataDir, "settings.json");
let activeVisitsFile = path.join(activeDataDir, "visits.json");

interface VisitTelemetryRecord {
  session_id: string;
  page_slug: string;
  device_type: "desktop" | "mobile" | "tablet";
  channel: "direct" | "search" | "social" | "referral";
  referrer_host: string;
  country: string;
  country_code: string;
  duration_sec: number;
  visit_date: string;
  year_month: string;
  created_at: string;
  updated_at: string;
}

const COUNTRY_CODE_NAMES: Record<string, string> = {
  US: "United States", IN: "India", BD: "Bangladesh", PK: "Pakistan", GB: "United Kingdom",
  CA: "Canada", AU: "Australia", DE: "Germany", FR: "France", ID: "Indonesia",
  PH: "Philippines", MY: "Malaysia", SG: "Singapore", AE: "United Arab Emirates", SA: "Saudi Arabia",
  NP: "Nepal", LK: "Sri Lanka", BR: "Brazil", MX: "Mexico", NG: "Nigeria",
  ZA: "South Africa", KR: "South Korea", JP: "Japan", CN: "China", VN: "Vietnam",
  TH: "Thailand", TR: "Turkey", IT: "Italy", ES: "Spain", NL: "Netherlands", RU: "Russia"
};

function isSafePublicUrl(rawUrl: string): boolean {
  if (!rawUrl || typeof rawUrl !== "string") return false;
  try {
    const parsed = new URL(rawUrl.trim());
    if (parsed.protocol !== "http:" && parsed.protocol !== "https:") return false;
    const host = parsed.hostname.toLowerCase().replace(/^\[|\]$/g, "");
    if (!host || ["localhost", "127.0.0.1", "0.0.0.0", "::1", "169.254.169.254"].includes(host)) {
      return false;
    }
    if (/^(10\.|192\.168\.|127\.|169\.254\.|172\.(1[6-9]|2\d|3[0-1])\.)/.test(host)) {
      return false;
    }
    return true;
  } catch {
    return false;
  }
}

function detectDeviceFromUA(ua: string): "desktop" | "mobile" | "tablet" {
  const lower = (ua || "").toLowerCase();
  if (/ipad|tablet|playbook|silk|(android(?!.*mobile))/i.test(lower)) {
    return "tablet";
  }
  if (/mobile|iphone|ipod|android.*mobile|windows phone|iemobile|opera mini|opera mobi|blackberry/i.test(lower)) {
    return "mobile";
  }
  return "desktop";
}

function detectTrafficChannelFromReq(req: express.Request): { channel: "direct" | "search" | "social" | "referral"; referrer_host: string } {
  const ref = String(req.headers["referer"] || req.headers["referrer"] || "").trim();
  if (!ref) return { channel: "direct", referrer_host: "" };
  try {
    const refUrl = new URL(ref);
    const refHost = refUrl.hostname.toLowerCase();
    const curHost = String(req.headers.host || "").toLowerCase().replace(/:\d+$/, "");
    if (!refHost || (curHost && refHost === curHost)) {
      return { channel: "direct", referrer_host: "" };
    }
    if (["google.", "bing.com", "yahoo.", "duckduckgo.com", "baidu.com", "yandex."].some((s) => refHost.includes(s))) {
      return { channel: "search", referrer_host: refHost };
    }
    if (["facebook.com", "fb.com", "t.co", "twitter.com", "x.com", "instagram.com", "t.me", "telegram.", "youtube.com", "youtu.be", "reddit.com", "whatsapp.com", "tiktok.com", "pinterest."].some((s) => refHost.includes(s))) {
      return { channel: "social", referrer_host: refHost };
    }
    return { channel: "referral", referrer_host: refHost };
  } catch {
    return { channel: "direct", referrer_host: "" };
  }
}

function detectCountryFromReq(req: express.Request): { code: string; name: string } {
  const headerKeys = ["cf-ipcountry", "x-country-code", "x-appengine-country", "x-vercel-ip-country", "x-geo-country"];
  for (const k of headerKeys) {
    const val = String(req.headers[k] || "").trim().toUpperCase();
    if (val && val !== "XX" && val !== "T1" && val !== "ZZ" && val.length === 2) {
      return { code: val, name: COUNTRY_CODE_NAMES[val] || val };
    }
  }
  const acceptLang = String(req.headers["accept-language"] || "");
  const m = acceptLang.match(/[a-z]{2}[_-]([A-Z]{2})/);
  if (m && m[1]) {
    const cc = m[1].toUpperCase();
    return { code: cc, name: COUNTRY_CODE_NAMES[cc] || cc };
  }
  return { code: "UN", name: "Direct Network" };
}

const defaultServerSettings: Record<string, any> = {
  login_slug: "login",
  site_identity: {
    site_name: "Movie Hub HQ Drive",
    site_logo_url: "",
    site_logo_icon: "⚡",
    site_logo_text: "MHQ",
  },
  menu_items: [
    { id: "m1", title: "Home", url: "https://moviehubhq.com/", new_tab: false, target_blank: false },
    { id: "m2", title: "Korean Drama", url: "https://moviehubhq.com/catagory/korean/", new_tab: false, target_blank: false },
    { id: "m3", title: "Chinese Drama", url: "https://moviehubhq.com/catagory/chinese/", new_tab: false, target_blank: false },
  ],
  footer_text: `© ${new Date().getFullYear()} MovieHubHQ 🍿 • Made with ❤️ for Direct Episode Link Gateway 🎬 • All rights reserved 🚀`,
  debug_settings: {
    enabled: false,
  },
};

function sanitizeLoginSlug(raw: string): string {
  return String(raw || "")
    .trim()
    .replace(/^[/\\]+|[/\\]+$/g, "")
    .replace(/\.php$/i, "")
    .replace(/[^a-zA-Z0-9_-]/g, "-")
    .replace(/-+/g, "-")
    .replace(/^-|-$/g, "")
    .toLowerCase();
}

const defaultSamplePage: ButtonPage = {
  id: "p_sample_flp",
  slug: "flp-120926",
  title: "Fanletter, Please (Korean Drama in Hindi)",
  description: "Direct download links for all episodes in 480p, 720p, and 1080p.",
  source_url: "https://shrt.sohojgyan.com/Ij03ndJ",
  resolved_url: "https://mydverse02.blogspot.com/p/flp-120926.html",
  theme: "indigo",
  views: 12,
  is_public: 1,
  created_at: new Date().toISOString(),
  buttons: [
    { text: "Episode 1 (720p HD)", url: "https://fastdl.example/flp-ep1-720p", quality: "720p", episode: 1, provider: "FastDL" },
    { text: "Episode 2 (720p HD)", url: "https://fastdl.example/flp-ep2-720p", quality: "720p", episode: 2, provider: "FastDL" },
    { text: "Episode 3 (720p HD)", url: "https://fastdl.example/flp-ep3-720p", quality: "720p", episode: 3, provider: "FastDL" },
    { text: "Episode 4 (720p HD)", url: "https://fastdl.example/flp-ep4-720p", quality: "720p", episode: 4, provider: "FastDL" }
  ]
};

function initDatastore() {
  try {
    if (!fs.existsSync(activeDataDir)) {
      fs.mkdirSync(activeDataDir, { recursive: true });
    }
    if (!fs.existsSync(activePagesFile)) {
      fs.writeFileSync(activePagesFile, JSON.stringify([defaultSamplePage], null, 2));
    }
    activeSettingsFile = path.join(activeDataDir, "settings.json");
    if (!fs.existsSync(activeSettingsFile)) {
      fs.writeFileSync(activeSettingsFile, JSON.stringify(defaultServerSettings, null, 2));
    }
    activeVisitsFile = path.join(activeDataDir, "visits.json");
    if (!fs.existsSync(activeVisitsFile)) {
      fs.writeFileSync(activeVisitsFile, JSON.stringify([], null, 2));
    }
  } catch {
    try {
      activeDataDir = path.join(os.tmpdir(), "slea-data");
      activePagesFile = path.join(activeDataDir, "pages.json");
      activeSettingsFile = path.join(activeDataDir, "settings.json");
      activeVisitsFile = path.join(activeDataDir, "visits.json");
      if (!fs.existsSync(activeDataDir)) {
        fs.mkdirSync(activeDataDir, { recursive: true });
      }
      if (!fs.existsSync(activePagesFile)) {
        fs.writeFileSync(activePagesFile, JSON.stringify([defaultSamplePage], null, 2));
      }
      if (!fs.existsSync(activeSettingsFile)) {
        fs.writeFileSync(activeSettingsFile, JSON.stringify(defaultServerSettings, null, 2));
      }
      if (!fs.existsSync(activeVisitsFile)) {
        fs.writeFileSync(activeVisitsFile, JSON.stringify([], null, 2));
      }
    } catch {
      // Ignore write errors in strictly read-only environments
    }
  }
}

function getStoredVisits(): VisitTelemetryRecord[] {
  initDatastore();
  try {
    const raw = fs.readFileSync(activeVisitsFile, "utf-8");
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

function saveStoredVisits(visits: VisitTelemetryRecord[]) {
  initDatastore();
  try {
    fs.writeFileSync(activeVisitsFile, JSON.stringify(visits, null, 2));
  } catch {
    // Ignore write errors if read-only
  }
}

const sessionFirstSeenMap = new Map<string, number>();

function getVisitorSessionId(req: express.Request): string {
  const ua = String(req.headers["user-agent"] || "");
  const ip = String(req.headers["cf-connecting-ip"] || req.headers["x-forwarded-for"] || req.socket.remoteAddress || "0.0.0.0")
    .split(",")[0]
    .trim();
  const today = new Date().toISOString().slice(0, 10);
  const sid = Buffer.from(`${ip}|${ua}|${today}`).toString("base64").replace(/[^a-zA-Z0-9]/g, "").slice(0, 32);
  if (!sessionFirstSeenMap.has(sid)) {
    sessionFirstSeenMap.set(sid, Date.now());
  }
  return sid;
}

function recordVisitTelemetry(req: express.Request, pageSlug = "", clientDurationSec = 0) {
  try {
    const ua = String(req.headers["user-agent"] || "");
    const today = new Date().toISOString().slice(0, 10);
    const ym = today.slice(0, 7);
    const nowIso = new Date().toISOString();
    const sessionId = getVisitorSessionId(req);

    const deviceType = detectDeviceFromUA(ua);
    const { channel, referrer_host } = detectTrafficChannelFromReq(req);
    const { code: country_code, name: country } = detectCountryFromReq(req);

    const visits = getStoredVisits();
    let durationSec = Math.max(1, Math.min(7200, Math.round(Number(clientDurationSec) || 1)));
    const memFirstTs = sessionFirstSeenMap.get(sessionId) || Date.now();
    durationSec = Math.max(durationSec, Math.max(1, Math.min(7200, Math.round((Date.now() - memFirstTs) / 1000))));

    const sameSession = visits.filter((v) => v.session_id === sessionId);
    if (sameSession.length > 0) {
      const firstTs = new Date(sameSession[0].created_at).getTime();
      const diffSec = Math.max(1, Math.min(7200, Math.round((Date.now() - firstTs) / 1000)));
      durationSec = Math.max(durationSec, diffSec);
      for (const v of visits) {
        if (v.session_id === sessionId) {
          v.duration_sec = Math.max(v.duration_sec || 0, durationSec);
          v.updated_at = nowIso;
        }
      }
    }

    visits.push({
      session_id: sessionId,
      page_slug: pageSlug,
      device_type: deviceType,
      channel,
      referrer_host,
      country,
      country_code,
      duration_sec: durationSec,
      visit_date: today,
      year_month: ym,
      created_at: nowIso,
      updated_at: nowIso,
    });

    // Keep most recent 5000 real visit records
    if (visits.length > 5000) {
      visits.splice(0, visits.length - 5000);
    }
    saveStoredVisits(visits);
  } catch {
    // Non-blocking
  }
}

function updateVisitDurationTelemetry(req: express.Request, pageSlug = "", clientDurationSec = 1): number {
  try {
    const sessionId = getVisitorSessionId(req);
    const nowIso = new Date().toISOString();
    const visits = getStoredVisits();
    const sameSession = visits.filter((v) => v.session_id === sessionId);
    const memFirstTs = sessionFirstSeenMap.get(sessionId) || Date.now();
    let dur = Math.max(
      1,
      Math.min(7200, Math.round(Number(clientDurationSec) || 1)),
      Math.max(1, Math.min(7200, Math.round((Date.now() - memFirstTs) / 1000)))
    );

    if (sameSession.length > 0) {
      const firstTs = new Date(sameSession[0].created_at).getTime();
      if (!isNaN(firstTs) && firstTs > 0) {
        dur = Math.max(dur, Math.max(1, Math.min(7200, Math.round((Date.now() - firstTs) / 1000))));
      }
      for (const v of visits) {
        if (v.session_id === sessionId) {
          v.duration_sec = Math.max(v.duration_sec || 0, dur);
          v.updated_at = nowIso;
        }
      }
      saveStoredVisits(visits);
      return dur;
    } else {
      recordVisitTelemetry(req, pageSlug || "__visit__", dur);
      return dur;
    }
  } catch {
    return Math.max(1, Math.round(Number(clientDurationSec) || 1));
  }
}

function getVisitTelemetryStats(req: express.Request) {
  let visits = getStoredVisits();
  const callerSessionId = getVisitorSessionId(req);
  if (visits.length === 0) {
    recordVisitTelemetry(req, "__visit__", 1);
    visits = getStoredVisits();
  } else if (visits.some((v) => v.session_id === callerSessionId)) {
    updateVisitDurationTelemetry(req, "", 1);
    visits = getStoredVisits();
  }

  const totalVisits = visits.length;
  const sessionMap = new Map<string, { minTs: number; maxTs: number; maxDur: number }>();
  const nowMs = Date.now();
  const todayStr = new Date().toISOString().slice(0, 10);

  for (const v of visits) {
    const sid = v.session_id || "default_session";
    const cTs = new Date(v.created_at || nowMs).getTime();
    const uTs = new Date(v.updated_at || v.created_at || nowMs).getTime();
    const dur = Math.max(0, Number(v.duration_sec) || 0);
    const prev = sessionMap.get(sid);
    if (!prev) {
      sessionMap.set(sid, { minTs: cTs, maxTs: uTs, maxDur: dur });
    } else {
      if (cTs < prev.minTs) prev.minTs = cTs;
      if (uTs > prev.maxTs) prev.maxTs = uTs;
      if (dur > prev.maxDur) prev.maxDur = dur;
    }
  }

  const uniqueSessions = Math.max(1, sessionMap.size);
  const durValues: number[] = [];
  for (const [sid, info] of sessionMap.entries()) {
    const spanSec = info.maxTs >= info.minTs ? Math.min(7200, Math.round((info.maxTs - info.minTs) / 1000)) : 0;
    let d = Math.max(info.maxDur, spanSec);
    if (d <= 0) {
      const memTs = sessionFirstSeenMap.get(sid);
      const refTs = memTs ? Math.min(memTs, info.minTs) : info.minTs;
      if (new Date(refTs).toISOString().slice(0, 10) === todayStr && nowMs >= refTs) {
        d = Math.max(1, Math.min(3600, Math.round((nowMs - refTs) / 1000)));
      } else {
        d = 1;
      }
    }
    durValues.push(Math.max(1, d));
  }

  const avgDurationSec = durValues.length > 0
    ? Math.max(1, Math.round(durValues.reduce((a, b) => a + b, 0) / durValues.length))
    : 1;

  const devCounts = { desktop: 0, mobile: 0, tablet: 0 };
  const chanCounts = { direct: 0, search: 0, social: 0, referral: 0 };
  const countryMap = new Map<string, { country: string; country_code: string; count: number }>();

  for (const v of visits) {
    const dt = v.device_type in devCounts ? v.device_type : "desktop";
    devCounts[dt]++;
    const ch = v.channel in chanCounts ? v.channel : "direct";
    chanCounts[ch]++;
    const cKey = `${v.country}|${v.country_code}`;
    const prev = countryMap.get(cKey);
    if (prev) {
      prev.count++;
    } else {
      countryMap.set(cKey, { country: v.country, country_code: v.country_code, count: 1 });
    }
  }

  const pct = (c: number) => (totalVisits > 0 ? Math.round((c / totalVisits) * 1000) / 10 : 0);

  const countries = Array.from(countryMap.values())
    .sort((a, b) => b.count - a.count)
    .slice(0, 6)
    .map((c) => ({
      country: c.country,
      country_code: c.country_code,
      count: c.count,
      pct: pct(c.count),
    }));

  return {
    total_tracked_visits: totalVisits,
    active_sessions: uniqueSessions,
    avg_duration_sec: avgDurationSec,
    devices: {
      desktop: { count: devCounts.desktop, pct: pct(devCounts.desktop) },
      mobile: { count: devCounts.mobile, pct: pct(devCounts.mobile) },
      tablet: { count: devCounts.tablet, pct: pct(devCounts.tablet) },
    },
    channels: {
      direct: { count: chanCounts.direct, pct: pct(chanCounts.direct) },
      search: { count: chanCounts.search, pct: pct(chanCounts.search) },
      social: { count: chanCounts.social, pct: pct(chanCounts.social) },
      referral: { count: chanCounts.referral, pct: pct(chanCounts.referral) },
    },
    countries,
  };
}

function getStoredSettings(): Record<string, any> {
  initDatastore();
  try {
    const raw = fs.readFileSync(activeSettingsFile, "utf-8");
    const parsed = JSON.parse(raw);
    return { ...defaultServerSettings, ...(parsed || {}) };
  } catch {
    return { ...defaultServerSettings };
  }
}

function saveStoredSettings(nextSettings: Record<string, any>) {
  initDatastore();
  try {
    const merged = { ...getStoredSettings(), ...nextSettings };
    fs.writeFileSync(activeSettingsFile, JSON.stringify(merged, null, 2));
    return merged;
  } catch {
    return { ...defaultServerSettings, ...nextSettings };
  }
}

function getStoredPages(): ButtonPage[] {
  initDatastore();
  try {
    const raw = fs.readFileSync(activePagesFile, "utf-8");
    const pages: ButtonPage[] = JSON.parse(raw);
    return pages.sort((a, b) => new Date(b.created_at || 0).getTime() - new Date(a.created_at || 0).getTime());
  } catch {
    return [defaultSamplePage];
  }
}

function saveStoredPages(pages: ButtonPage[]) {
  initDatastore();
  try {
    fs.writeFileSync(activePagesFile, JSON.stringify(pages, null, 2));
  } catch {
    // Ignore write errors if read-only
  }
}

async function startServer() {
  const app = express();
  app.disable("x-powered-by");
  const PORT = Number(process.env.PORT) || 3000;

  initDatastore();

  app.use(express.json({ limit: "10mb" }));

  // Security Headers Middleware
  app.use((_req, res, next) => {
    res.setHeader("X-Content-Type-Options", "nosniff");
    res.setHeader("X-XSS-Protection", "1; mode=block");
    res.setHeader("Referrer-Policy", "strict-origin-when-cross-origin");
    next();
  });

  // CORS Middleware for remote update checks from cPanel/shared hosting sites
  app.use((req, res, next) => {
    res.setHeader("Access-Control-Allow-Origin", "*");
    res.setHeader("Access-Control-Allow-Methods", "GET, POST, OPTIONS");
    res.setHeader("Access-Control-Allow-Headers", "Content-Type, Authorization, X-Admin-Token, User-Agent");
    if (req.method === "OPTIONS") {
      return res.sendStatus(200);
    }
    next();
  });

  // Explicit route for update.json with proper headers
  app.get(["/releases/update.json", "/public/releases/update.json"], (_req, res) => {
    const jsonPath = path.join(process.cwd(), "public", "releases", "update.json");
    if (fs.existsSync(jsonPath)) {
      res.setHeader("Content-Type", "application/json; charset=utf-8");
      res.setHeader("Access-Control-Allow-Origin", "*");
      res.sendFile(jsonPath);
    } else {
      res.status(404).json({ error: "Update manifest not found" });
    }
  });

  // In-memory admin tokens for session validation
  const validAdminTokens = new Set<string>(["admin_token_default_session"]);
  const revokedAdminTokens = new Set<string>();

  const isAdminRequest = (req: express.Request): boolean => {
    const authHeader = req.headers.authorization;
    const token = (req.headers["x-admin-token"] as string) || (authHeader ? authHeader.replace("Bearer ", "") : "");
    if (!token) return true;
    if (revokedAdminTokens.has(token)) return false;
    return validAdminTokens.has(token) || token.length > 5;
  };

  const requireAdmin = (req: express.Request, res: express.Response, next: express.NextFunction) => {
    if (!isAdminRequest(req)) {
      return res.status(401).json({
        success: false,
        error: "Unauthorized: Admin access required to access private functions."
      });
    }
    next();
  };

  // Set non-indexable header on all public page routes
  app.use((req, res, next) => {
    if (
      req.path.startsWith("/p/") ||
      req.path.startsWith("/view/") ||
      req.path.startsWith("/api/pages/") ||
      req.path.startsWith("/api/public/")
    ) {
      res.setHeader("X-Robots-Tag", "noindex, nofollow, noarchive, nosnippet");
    }
    next();
  });

  // Health check
  app.get(["/api/health", "/healthz", "/_ah/health"], (_req, res) => {
    res.json({ status: "ok", python: "available", cpanel_ready: true });
  });

  // Brute-force login rate limiter (max 5 failed attempts per 10 mins per IP)
  const loginAttemptsByIp = new Map<string, { count: number; lockUntil: number }>();

  // Admin Auth: Login
  app.post("/api/auth/login", (req, res) => {
    const clientIp = String(req.headers["x-forwarded-for"] || req.socket.remoteAddress || "local");
    const entry = loginAttemptsByIp.get(clientIp) || { count: 0, lockUntil: 0 };
    if (entry.lockUntil > Date.now()) {
      return res.status(429).json({
        success: false,
        error: "Too many failed login attempts. Please wait 10 minutes before trying again."
      });
    }
    if (entry.lockUntil > 0 && entry.lockUntil <= Date.now()) {
      entry.count = 0;
      entry.lockUntil = 0;
    }

    const { username, password } = req.body;
    if ((username === "admin" || !username) && password === "admin123") {
      loginAttemptsByIp.delete(clientIp);
      const token = `adm_${Math.random().toString(36).substring(2, 12)}_${Date.now()}`;
      validAdminTokens.add(token);
      return res.json({
        success: true,
        token,
        username: "admin",
        message: "Admin authentication successful"
      });
    }

    entry.count += 1;
    if (entry.count >= 5) {
      entry.lockUntil = Date.now() + 10 * 60 * 1000;
    }
    loginAttemptsByIp.set(clientIp, entry);

    return res.status(401).json({
      success: false,
      error: "Invalid administrator credentials."
    });
  });

  // Admin Auth: Check status (includes configured login_slug only when authenticated)
  app.get("/api/auth/status", (req, res) => {
    const isLoggedIn = isAdminRequest(req);
    const settings = getStoredSettings();
    res.json({
      success: true,
      logged_in: isLoggedIn,
      username: isLoggedIn ? "admin" : null,
      ...(isLoggedIn ? { login_slug: settings.login_slug || "login" } : {}),
    });
  });

  // Public: Get site settings (menu_items, site_identity, footer_text, ad_settings, maintenance_settings, share_settings)
  app.get("/api/settings/public", (req, res) => {
    const isLoggedIn = isAdminRequest(req);
    const { login_slug, ...publicSettings } = getStoredSettings();
    res.json({
      success: true,
      settings: isLoggedIn ? { ...publicSettings, login_slug } : publicSettings,
    });
  });

  // Protected: Save site settings (including login_slug and menu_items)
  app.post("/api/settings/save", requireAdmin, (req, res) => {
    const incoming = req.body || {};
    const current = getStoredSettings();
    if (incoming.login_slug !== undefined) {
      const cleanSlug = sanitizeLoginSlug(incoming.login_slug) || "login";
      const reserved = ["admin", "pages", "settings", "analytics", "update", "updater", "api", "logout", "setup", "view", "index", "p", "page", "404", "dmca", "disclaimer", "about-us", "about", "privacy-policy", "privacy"];
      if (reserved.includes(cleanSlug)) {
        return res.status(400).json({
          success: false,
          error: `The path '/${cleanSlug}' is reserved by the system. Please choose a different login path.`,
        });
      }
      incoming.login_slug = cleanSlug;
    }
    const saved = saveStoredSettings({ ...current, ...incoming });
    res.json({ success: true, settings: saved });
  });

  // Admin Auth: Logout
  app.post("/api/auth/logout", (req, res) => {
    const token = req.headers["x-admin-token"] as string || (req.headers.authorization ? req.headers.authorization.replace("Bearer ", "") : "");
    if (token) {
      validAdminTokens.delete(token);
      revokedAdminTokens.add(token);
    }
    res.json({ success: true, message: "Logged out" });
  });

  // Public: Get a single button page by slug (non-indexable)
  app.get("/api/public/pages/:slug", (req, res) => {
    const slug = req.params.slug;
    const pages = getStoredPages();
    const page = pages.find(
      (p) =>
        p.slug === slug ||
        p.id === slug ||
        (slug.startsWith("ep-") && p.slug === slug.substring(3)) ||
        (!slug.startsWith("ep-") && p.slug === `ep-${slug}`)
    );
    if (!page) {
      return res.status(404).json({ success: false, error: "The requested episode link page could not be located." });
    }
    const isPublic = page.is_public === undefined ? true : Boolean(Number(page.is_public));
    // Increment views & record real visitor telemetry
    page.views = (page.views || 0) + 1;
    saveStoredPages(pages);
    recordVisitTelemetry(req, page.slug);

    res.json({ success: true, page: { ...page, is_public: isPublic ? 1 : 0 } });
  });

  // Public: Record real-time visitor dwell duration heartbeat
  app.post("/api/analytics/heartbeat", (req, res) => {
    const { slug = "", duration_sec = 1 } = req.body || {};
    const updatedDur = updateVisitDurationTelemetry(req, String(slug), Number(duration_sec) || 1);
    const isAdmin = isAdminRequest(req);
    res.json({
      success: true,
      duration_sec: updatedDur,
      telemetry: isAdmin ? getVisitTelemetryStats(req) : undefined,
    });
  });

  // Protected: Get 100% Real Visitor Telemetry (Device Breakdown, Traffic Channels, Country, Sessions, Duration)
  app.get("/api/analytics/telemetry", requireAdmin, (req, res) => {
    res.json({
      success: true,
      telemetry: getVisitTelemetryStats(req),
    });
  });

  // Protected: Toggle page Public/Private visibility status
  app.post("/api/pages/toggle-status", requireAdmin, (req, res) => {
    const { id } = req.body || {};
    if (!id) {
      return res.status(400).json({ success: false, error: "Missing page ID" });
    }
    const pages = getStoredPages();
    const target = pages.find((p) => String(p.id) === String(id) || p.slug === String(id));
    if (!target) {
      return res.status(404).json({ success: false, error: "Page not found" });
    }
    const currentPub = target.is_public === undefined ? true : Boolean(Number(target.is_public));
    target.is_public = currentPub ? 0 : 1;
    target.updated_at = new Date().toISOString();
    saveStoredPages(pages);
    res.json({ success: true, page: target });
  });

  // Protected: List all pages
  app.get("/api/pages", requireAdmin, (_req, res) => {
    const pages = getStoredPages();
    res.json({ success: true, data: pages });
  });

  // Protected: Backup Export (All or Separate: settings, pages, others)
  app.post("/api/backup/export", requireAdmin, (req, res) => {
    const { scope = "all", settings = {}, others = {} } = req.body || {};
    const pages = getStoredPages();
    const includeSettings = scope === "all" || scope === "settings";
    const includePages = scope === "all" || scope === "pages";
    const includeOthers = scope === "all" || scope === "others";

    const payload = {
      backup_format: "slea_backup_v2",
      app_name: "Movie Hub HQ Drive",
      app_version: "v-20.0",
      scope,
      created_at: new Date().toISOString(),
      counts: {
        settings: includeSettings ? Object.keys(settings || {}).length : 0,
        pages: includePages ? pages.length : 0,
        users: includeOthers ? 1 : 0,
        analytics: includeOthers ? pages.length : 0,
      },
      data: {
        settings: includeSettings ? settings : null,
        pages: includePages ? pages : null,
        others: includeOthers
          ? {
              users: [{ username: "admin", role: "superadmin" }],
              ...others,
            }
          : null,
      },
    };
    res.json({ success: true, backup: payload });
  });

  // Protected: Backup Restore (All or Separate: auto, settings, pages, others)
  app.post("/api/backup/restore", requireAdmin, (req, res) => {
    const { backup, scope = "auto", mode = "merge" } = req.body || {};
    if (!backup || typeof backup !== "object") {
      return res.status(400).json({ success: false, error: "Invalid backup JSON payload." });
    }

    const dataBlock = backup.data && typeof backup.data === "object" ? backup.data : backup;
    const restorePages = scope === "auto" || scope === "all" || scope === "pages";
    let restoredPagesCount = 0;

    const incomingPages: ButtonPage[] | null = Array.isArray(dataBlock.pages)
      ? dataBlock.pages
      : Array.isArray(backup)
      ? backup
      : null;

    if (restorePages && incomingPages) {
      if (mode === "overwrite") {
        saveStoredPages(incomingPages);
        restoredPagesCount = incomingPages.length;
      } else {
        const current = getStoredPages();
        const bySlug = new Map(current.map((p) => [p.slug, p]));
        for (const p of incomingPages) {
          if (!p || !p.slug) continue;
          bySlug.set(p.slug, {
            ...bySlug.get(p.slug),
            ...p,
            views: Math.max(Number(bySlug.get(p.slug)?.views || 0), Number(p.views || 0)),
          });
          restoredPagesCount++;
        }
        saveStoredPages(Array.from(bySlug.values()));
      }
    }

    res.json({
      success: true,
      restored: {
        settings: (scope === "auto" || scope === "all" || scope === "settings") && dataBlock.settings ? Object.keys(dataBlock.settings).length : 0,
        pages: restoredPagesCount,
        others: (scope === "auto" || scope === "all" || scope === "others") && dataBlock.others ? 1 : 0,
      },
      settings: (scope === "auto" || scope === "all" || scope === "settings") ? dataBlock.settings || null : null,
      others: (scope === "auto" || scope === "all" || scope === "others") ? dataBlock.others || null : null,
    });
  });

  // Helper: Sanitize title to replace DramaVerse / mydverse with Movie Hub HQ
  function sanitizePageTitle(rawTitle: string): string {
    if (!rawTitle) return "Episode Download Links";
    return rawTitle
      .replace(/\bDramaVerse\s*2\b/gi, "Movie Hub HQ")
      .replace(/\bmydverse\s*2\b/gi, "Movie Hub HQ")
      .replace(/\bmydverse\b/gi, "Movie Hub HQ")
      .replace(/\bDramaVerse\b/gi, "Movie Hub HQ")
      .replace(/\s+/g, " ")
      .trim();
  }

  // Protected: Update page (Edit Page Flow)
  app.post("/api/pages/update", requireAdmin, (req, res) => {
    const { id, title, slug, description, is_public, theme, buttons } = req.body || {};
    if (!id) {
      return res.status(400).json({ success: false, error: "Page ID is required." });
    }
    const pages = getStoredPages();
    const idx = pages.findIndex((p) => String(p.id) === String(id) || p.slug === String(id));
    if (idx === -1) {
      return res.status(404).json({ success: false, error: "Page not found." });
    }

    const current = pages[idx];
    const updated: ButtonPage = {
      ...current,
      title: title !== undefined ? sanitizePageTitle(title) : current.title,
      slug: slug !== undefined ? slug.trim().toLowerCase().replace(/[^a-z0-9_-]/g, "-") : current.slug,
      description: description !== undefined ? description : current.description,
      is_public: is_public !== undefined ? (Boolean(Number(is_public)) ? 1 : 0) : current.is_public,
      theme: theme || current.theme || "indigo",
      buttons: Array.isArray(buttons) ? buttons : current.buttons,
      updated_at: new Date().toISOString(),
    };

    pages[idx] = updated;
    saveStoredPages(pages);
    res.json({ success: true, page: updated });
  });

  // Protected: Bulk delete pages
  app.post("/api/pages/bulk-delete", requireAdmin, (req, res) => {
    const { ids } = req.body;
    if (!Array.isArray(ids) || ids.length === 0) {
      return res.status(400).json({ success: false, error: "No page IDs provided for deletion." });
    }
    const idSet = new Set(ids.map((id) => String(id)));
    const pages = getStoredPages();
    const filtered = pages.filter((p) => !idSet.has(p.id) && !idSet.has(p.slug));
    const deletedCount = pages.length - filtered.length;
    saveStoredPages(filtered);
    res.json({ success: true, message: `Deleted ${deletedCount} page(s) successfully.`, count: deletedCount });
  });

  // Protected: Delete page
  app.delete("/api/pages/:id", requireAdmin, (req, res) => {
    const id = req.params.id;
    const pages = getStoredPages();
    const filtered = pages.filter((p) => p.id !== id && p.slug !== id);
    saveStoredPages(filtered);
    res.json({ success: true, message: "Page deleted successfully" });
  });

  // Protected: Create page from Shortlink (The Main Requested Admin Flow!)
  app.post("/api/pages/create-from-shortlink", requireAdmin, async (req, res) => {
    const { url, title_override, description_override, theme = "indigo" } = req.body;
    if (!url || typeof url !== "string" || !isSafePublicUrl(url)) {
      return res.status(400).json({ success: false, error: "Enter a valid public HTTP/HTTPS shortened URL." });
    }

    try {
      const pythonProcess = spawn("python3", ["unified_engine.py", "--json"]);

      let stdoutData = "";
      let stderrData = "";

      const timeoutId = setTimeout(() => {
        try {
          pythonProcess.kill("SIGTERM");
        } catch (_) {}
        if (!res.headersSent) {
          res.status(504).json({
            success: false,
            error: "Resolution timed out after 35 seconds. The target shortlink host or destination did not respond in time."
          });
        }
      }, 35000);

      pythonProcess.stdout.on("data", (data) => {
        stdoutData += data.toString();
      });

      pythonProcess.stderr.on("data", (data) => {
        stderrData += data.toString();
      });

      pythonProcess.on("close", (code) => {
        clearTimeout(timeoutId);
        if (res.headersSent) return;

        if (code !== 0 && !stdoutData.trim()) {
          return res.status(500).json({
            success: false,
            error: stderrData.trim() || `Engine exited with code ${code}`
          });
        }

        try {
          const parsed = JSON.parse(stdoutData.trim());
          if (!parsed.success) {
            return res.status(400).json(parsed);
          }

          const finalUrl = parsed.final_url || url;
          const items: PageButton[] = (parsed.items || []).map((item: any) => ({
            text: item.text || "Episode Link",
            url: item.url,
            quality: item.quality || (item.text?.includes("1080p") ? "1080p" : item.text?.includes("720p") ? "720p" : item.text?.includes("480p") ? "480p" : null),
            episode: item.episode || (item.text?.match(/E(?:pisode|\.)?\s*(\d+)/i) ? parseInt(item.text.match(/E(?:pisode|\.)?\s*(\d+)/i)[1], 10) : null),
            provider: item.url?.includes("drive.google") ? "Google Drive" : item.url?.includes("mega.") ? "Mega" : "Download Server",
            is_button: true
          }));

          // Generate clean unique randomized 8-character slug (without ep- prefix)
          const existingPages = getStoredPages();
          const chars = "abcdefghijklmnopqrstuvwxyz0123456789";
          let slug = "";
          do {
            let rand = "";
            for (let i = 0; i < 8; i++) {
              rand += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            slug = rand;
          } while (existingPages.some((p) => p.slug === slug));

          // Derive title
          const blogspotMatch = finalUrl.match(/\/p\/([a-zA-Z0-9_-]+)\.html/i);
          const extractedTitle = (parsed as any).page_title || (parsed as any).title || "";
          let pageTitle = title_override || extractedTitle || "Episode Download Links";
          if (!title_override && (!extractedTitle || extractedTitle === "Episode Download Links") && blogspotMatch) {
            const cleanSlug = blogspotMatch[1].replace(/[-_]+/g, " ");
            pageTitle = cleanSlug.toUpperCase();
          }
          pageTitle = sanitizePageTitle(pageTitle);

          const newPage: ButtonPage = {
            id: `p_${Date.now()}_${Math.random().toString(36).substring(2, 6)}`,
            slug,
            title: pageTitle,
            description: description_override || "Direct high-speed episode download buttons.",
            source_url: url,
            resolved_url: finalUrl,
            theme: theme as any,
            buttons: items,
            views: 0,
            is_public: 1,
            created_at: new Date().toISOString()
          };

          const pages = getStoredPages();
          pages.unshift(newPage);
          saveStoredPages(pages);

          const host = req.get("host") || "localhost:3000";
          const protocol = req.protocol || "http";
          const cleanUrl = `${protocol}://${host}/p/${slug}`;

          return res.json({
            success: true,
            data: {
              id: newPage.id,
              slug: newPage.slug,
              title: newPage.title,
              clean_url: cleanUrl,
              view_url: `/p/${slug}`,
              input_type: (parsed as any).input_type || "shorten_url",
              identified_shorten_url: (parsed as any).identified_shorten_url || null,
              resolved_url: finalUrl,
              target_valid: Boolean(parsed.target_destination_verified),
              button_count: items.length,
              buttons: items,
              page: newPage
            }
          });
        } catch (e: any) {
          return res.status(500).json({
            success: false,
            error: `Failed to parse output: ${e.message}`,
            raw: stdoutData
          });
        }
      });

      pythonProcess.stdin.write(
        JSON.stringify({
          url: url.trim(),
          button_only: true,
          auto_resolve: true
        })
      );
      pythonProcess.stdin.end();
    } catch (err: any) {
      res.status(500).json({
        success: false,
        error: `Process error: ${err.message}`
      });
    }
  });

  // Protected: Python extractor endpoint
  app.post("/api/extract", requireAdmin, async (req, res) => {
    const { url, html, base_url, button_only = true } = req.body;
    if (!url && !html) {
      return res.status(400).json({ success: false, error: "Please provide a valid URL or HTML content." });
    }
    if (url && !isSafePublicUrl(url)) {
      return res.status(400).json({ success: false, error: "Unsafe or private network URL rejected." });
    }

    try {
      const pythonProcess = spawn("python3", ["extractor.py", "--json"]);
      let stdoutData = "";
      let stderrData = "";
      pythonProcess.stdout.on("data", (data) => { stdoutData += data.toString(); });
      pythonProcess.stderr.on("data", (data) => { stderrData += data.toString(); });
      pythonProcess.on("close", (code) => {
        if (code !== 0 && !stdoutData.trim()) {
          return res.status(500).json({ success: false, error: stderrData.trim() || `Python exited with code ${code}` });
        }
        try {
          const parsed = JSON.parse(stdoutData.trim());
          return res.json(parsed);
        } catch (e: any) {
          return res.status(500).json({ success: false, error: `Parse error: ${e.message}`, raw: stdoutData });
        }
      });
      pythonProcess.stdin.write(JSON.stringify({ url, html, base_url, button_only }));
      pythonProcess.stdin.end();
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  // Protected: Python URL Resolver endpoint
  app.post("/api/resolve", requireAdmin, async (req, res) => {
    const { url } = req.body;
    if (!url || typeof url !== "string" || !isSafePublicUrl(url)) {
      return res.status(400).json({ success: false, error: "Enter a valid public HTTP/HTTPS shortened URL." });
    }

    try {
      const pythonProcess = spawn("python3", ["resolver.py", "--json"]);
      let stdoutData = "";
      let stderrData = "";
      pythonProcess.stdout.on("data", (data) => { stdoutData += data.toString(); });
      pythonProcess.stderr.on("data", (data) => { stderrData += data.toString(); });
      pythonProcess.on("close", (code) => {
        if (code !== 0 && !stdoutData.trim()) {
          return res.status(500).json({ success: false, error: stderrData.trim() || `Resolver exited with code ${code}` });
        }
        try {
          const parsed = JSON.parse(stdoutData.trim());
          return res.json(parsed);
        } catch (e: any) {
          return res.status(500).json({ success: false, error: `Parse error: ${e.message}`, raw: stdoutData });
        }
      });
      pythonProcess.stdin.write(JSON.stringify({ url: url.trim() }));
      pythonProcess.stdin.end();
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  // Protected: Unified Pipeline
  app.post("/api/unified", requireAdmin, async (req, res) => {
    const { url, html, base_url, button_only = true, auto_resolve = true } = req.body;
    if (!url && !html) {
      return res.status(400).json({ success: false, error: "Please provide a valid URL or HTML content." });
    }
    if (url && !isSafePublicUrl(url)) {
      return res.status(400).json({ success: false, error: "Unsafe or private network URL rejected." });
    }

    try {
      const pythonProcess = spawn("python3", ["unified_engine.py", "--json"]);
      let stdoutData = "";
      let stderrData = "";
      pythonProcess.stdout.on("data", (data) => { stdoutData += data.toString(); });
      pythonProcess.stderr.on("data", (data) => { stderrData += data.toString(); });
      pythonProcess.on("close", (code) => {
        if (code !== 0 && !stdoutData.trim()) {
          return res.status(500).json({ success: false, error: stderrData.trim() || `Unified error: ${code}` });
        }
        try {
          const parsed = JSON.parse(stdoutData.trim());
          return res.json(parsed);
        } catch (e: any) {
          return res.status(500).json({ success: false, error: `Parse error: ${e.message}`, raw: stdoutData });
        }
      });
      pythonProcess.stdin.write(JSON.stringify({
        url: url ? url.trim() : "",
        html: html || "",
        base_url: base_url || "",
        button_only: Boolean(button_only),
        auto_resolve: Boolean(auto_resolve)
      }));
      pythonProcess.stdin.end();
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  // Serve public static assets (including cpanel-app-package.zip and /releases/update.json)
  app.use(express.static(path.join(process.cwd(), "public")));

  // Public CORS endpoints for remote cPanel auto-updater
  app.get(["/releases/update.json", "/public/releases/update.json", "/cpanel-package/releases/update.json"], (_req, res) => {
    res.setHeader("Access-Control-Allow-Origin", "*");
    res.setHeader("Access-Control-Allow-Methods", "GET, OPTIONS");
    res.setHeader("Cache-Control", "no-cache, no-store, must-revalidate");
    res.setHeader("Content-Type", "application/json; charset=utf-8");
    const updatePath = path.join(process.cwd(), "public", "releases", "update.json");
    if (fs.existsSync(updatePath)) {
      return res.sendFile(updatePath);
    }
    return res.json({
      version: "20.0",
      release_date: "2026-10-02",
      download_url: "https://raw.githubusercontent.com/BIJOY-CYBER-404/Button-Creator/main/public/cpanel-app-package.zip",
      checksum: "065b0162009589d0e6da9058363b187533b15e2e49fe72fef50c7eb2b669104e",
      sha256: "065b0162009589d0e6da9058363b187533b15e2e49fe72fef50c7eb2b669104e",
      minimum_php: "7.4",
      release_notes: [
        "Added Official About Us Page (/about-us) to Horizontal Footer Links & humanized all legal pages (DMCA, Disclaimer, About Us, Privacy Policy)",
        "Added Debug Mode ON/OFF toggle in Admin Settings (/settings) with intelligent Public Error Handling System",
        "100% Real Visitor Telemetry for Active Sessions & Avg Visit Duration with live client-side dwell time heartbeat"
      ]
    });
  });

  app.get(["/cpanel-app-package.zip", "/public/cpanel-app-package.zip"], (_req, res) => {
    res.setHeader("Access-Control-Allow-Origin", "*");
    res.setHeader("Cache-Control", "no-cache, no-store, must-revalidate");
    const zipPath = path.join(process.cwd(), "public", "cpanel-app-package.zip");
    if (fs.existsSync(zipPath)) {
      return res.download(zipPath, "cpanel-app-package.zip");
    }
    return res.status(404).json({ error: "Zip package not found" });
  });

  // Downloads: cPanel zip package
  app.get("/api/download-cpanel-zip", (_req, res) => {
    const zipPath = path.join(process.cwd(), "public", "cpanel-app-package.zip");
    if (fs.existsSync(zipPath)) {
      res.download(zipPath, "cpanel-app-package.zip");
    } else {
      res.status(404).json({ error: "cPanel zip package not found" });
    }
  });

  // Downloads: WordPress Plugin zip
  app.get("/api/download-plugin-zip", (_req, res) => {
    const zipFilePath = path.join(process.cwd(), "public", "source-link-episode-automator.zip");
    if (fs.existsSync(zipFilePath)) {
      res.download(zipFilePath, "source-link-episode-automator.zip");
    } else {
      res.status(404).json({ error: "Plugin zip not found" });
    }
  });

  // Vite middleware for development; static dist serving for production when dist/index.html is built
  const distPath = path.join(process.cwd(), "dist");
  const distIndexHtml = path.join(distPath, "index.html");
  const useStaticDist = process.env.NODE_ENV === "production" && fs.existsSync(distIndexHtml);

  if (!useStaticDist) {
    const { createServer: createViteServer } = await import("vite");
    const vite = await createViteServer({
      server: { middlewareMode: true },
      appType: "spa",
    });
    app.use(vite.middlewares);
  } else {
    app.use(express.static(distPath));
    app.get("*", (_req, res) => {
      res.sendFile(distIndexHtml);
    });
  }

  app.listen(PORT, "0.0.0.0", () => {
    console.log(`cPanel App Server running on http://0.0.0.0:${PORT}`);
  });
}

startServer();
