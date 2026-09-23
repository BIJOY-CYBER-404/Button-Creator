import express from "express";
import path from "path";
import fs from "fs";
import { spawn } from "child_process";
import { createServer as createViteServer } from "vite";

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
  created_at: string;
  updated_at?: string;
}

const DATA_DIR = path.join(process.cwd(), "data");
const PAGES_FILE = path.join(DATA_DIR, "pages.json");

function initDatastore() {
  if (!fs.existsSync(DATA_DIR)) {
    fs.mkdirSync(DATA_DIR, { recursive: true });
  }
  if (!fs.existsSync(PAGES_FILE)) {
    // Seed with a sample page
    const samplePage: ButtonPage = {
      id: "p_sample_flp",
      slug: "flp-120926",
      title: "Fanletter, Please (Korean Drama in Hindi)",
      description: "Direct download links for all episodes in 480p, 720p, and 1080p.",
      source_url: "https://shrt.sohojgyan.com/Ij03ndJ",
      resolved_url: "https://mydverse02.blogspot.com/p/flp-120926.html",
      theme: "indigo",
      views: 12,
      created_at: new Date().toISOString(),
      buttons: [
        { text: "Episode 1 (720p HD)", url: "https://fastdl.example/flp-ep1-720p", quality: "720p", episode: 1, provider: "FastDL" },
        { text: "Episode 2 (720p HD)", url: "https://fastdl.example/flp-ep2-720p", quality: "720p", episode: 2, provider: "FastDL" },
        { text: "Episode 3 (720p HD)", url: "https://fastdl.example/flp-ep3-720p", quality: "720p", episode: 3, provider: "FastDL" },
        { text: "Episode 4 (720p HD)", url: "https://fastdl.example/flp-ep4-720p", quality: "720p", episode: 4, provider: "FastDL" }
      ]
    };
    fs.writeFileSync(PAGES_FILE, JSON.stringify([samplePage], null, 2));
  }
}

function getStoredPages(): ButtonPage[] {
  initDatastore();
  try {
    const raw = fs.readFileSync(PAGES_FILE, "utf-8");
    return JSON.parse(raw);
  } catch {
    return [];
  }
}

function saveStoredPages(pages: ButtonPage[]) {
  initDatastore();
  fs.writeFileSync(PAGES_FILE, JSON.stringify(pages, null, 2));
}

async function startServer() {
  const app = express();
  const PORT = 3000;

  initDatastore();

  app.use(express.json({ limit: "10mb" }));

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

  // In-memory admin tokens for session simulation
  const validAdminTokens = new Set<string>(["admin_token_default_session"]);

  const requireAdmin = (req: express.Request, res: express.Response, next: express.NextFunction) => {
    const authHeader = req.headers.authorization;
    const token = req.headers["x-admin-token"] as string || (authHeader ? authHeader.replace("Bearer ", "") : "");
    if (!token || !validAdminTokens.has(token)) {
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
  app.get("/api/health", (_req, res) => {
    res.json({ status: "ok", python: "available", cpanel_ready: true });
  });

  // Admin Auth: Login
  app.post("/api/auth/login", (req, res) => {
    const { username, password } = req.body;
    if ((username === "admin" || !username) && password === "admin123") {
      const token = `adm_${Math.random().toString(36).substring(2, 12)}_${Date.now()}`;
      validAdminTokens.add(token);
      return res.json({
        success: true,
        token,
        username: "admin",
        message: "Admin authentication successful"
      });
    }
    return res.status(401).json({
      success: false,
      error: "Invalid username or password. Default is admin / admin123"
    });
  });

  // Admin Auth: Check status
  app.get("/api/auth/status", (req, res) => {
    const token = req.headers["x-admin-token"] as string || (req.headers.authorization ? req.headers.authorization.replace("Bearer ", "") : "");
    const isLoggedIn = Boolean(token && validAdminTokens.has(token));
    res.json({ success: true, logged_in: isLoggedIn, username: isLoggedIn ? "admin" : null });
  });

  // Admin Auth: Logout
  app.post("/api/auth/logout", (req, res) => {
    const token = req.headers["x-admin-token"] as string || (req.headers.authorization ? req.headers.authorization.replace("Bearer ", "") : "");
    if (token) {
      validAdminTokens.delete(token);
    }
    res.json({ success: true, message: "Logged out" });
  });

  // Public: Get a single button page by slug (non-indexable)
  app.get("/api/public/pages/:slug", (req, res) => {
    const slug = req.params.slug;
    const pages = getStoredPages();
    const page = pages.find((p) => p.slug === slug || p.id === slug);
    if (!page) {
      return res.status(404).json({ success: false, error: "Button page not found" });
    }
    // Increment views
    page.views = (page.views || 0) + 1;
    saveStoredPages(pages);

    res.json({ success: true, page });
  });

  // Protected: List all pages
  app.get("/api/pages", requireAdmin, (_req, res) => {
    const pages = getStoredPages();
    res.json({ success: true, data: pages });
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
    if (!url || typeof url !== "string") {
      return res.status(400).json({ success: false, error: "Enter a valid shortened URL." });
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

          // Generate unique randomized slug for every page
          const existingPages = getStoredPages();
          const chars = "abcdefghijklmnopqrstuvwxyz0123456789";
          let slug = "";
          do {
            let rand = "";
            for (let i = 0; i < 8; i++) {
              rand += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            slug = `ep-${rand}`;
          } while (existingPages.some((p) => p.slug === slug));

          // Derive title
          const blogspotMatch = finalUrl.match(/\/p\/([a-zA-Z0-9_-]+)\.html/i);
          let pageTitle = title_override || (parsed as any).title || "Episode Download Links";
          if (!title_override && !(parsed as any).title && blogspotMatch) {
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
    if (!url || typeof url !== "string") {
      return res.status(400).json({ success: false, error: "Enter a shortened URL." });
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
      name: "Movie Hub HQ Drive",
      version: "4.0.0",
      release_date: "2026-09-23",
      download_url: "https://raw.githubusercontent.com/BIJOY-CYBER-404/Button-Creator/main/public/cpanel-app-package.zip",
      minimum_php: "8.0",
      release_notes: [
        "Bulk page management with multi-checkbox selection and instant bulk deletion in React and cPanel admin panels",
        "Overhauled pure-PHP shortlink resolver engine with universal Blogspot target matching and CakePHP CSRF compatibility",
        "Automatic page title sanitization: automatically converts 'DramaVerse 2', 'mydverse 2', and 'mydverse' to 'Movie Hub HQ' before publishing",
        "Safe temp directory cookie isolation supporting cPanel open_basedir environments",
        "Top-right corner popup toast notifications with animated entries and auto-dismiss"
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

  // Vite middleware for development
  if (process.env.NODE_ENV !== "production") {
    const vite = await createViteServer({
      server: { middlewareMode: true },
      appType: "spa",
    });
    app.use(vite.middlewares);
  } else {
    const distPath = path.join(process.cwd(), "dist");
    app.use(express.static(distPath));
    app.get("*", (_req, res) => {
      res.sendFile(path.join(distPath, "index.html"));
    });
  }

  app.listen(PORT, "0.0.0.0", () => {
    console.log(`cPanel App Server running on http://0.0.0.0:${PORT}`);
  });
}

startServer();
