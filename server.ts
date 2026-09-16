import express from "express";
import path from "path";
import { spawn } from "child_process";
import { createServer as createViteServer } from "vite";

async function startServer() {
  const app = express();
  const PORT = 3000;

  app.use(express.json({ limit: "10mb" }));

  // API: Health check
  app.get("/api/health", (_req, res) => {
    res.json({ status: "ok", python: "available" });
  });

  // API: Python extractor endpoint
  app.post("/api/extract", async (req, res) => {
    const { url, html, base_url, button_only = true } = req.body;

    if (!url && !html) {
      return res.status(400).json({
        success: false,
        error: "Please provide a valid URL or HTML content.",
      });
    }

    try {
      const pythonProcess = spawn("python3", ["extractor.py", "--json"]);

      let stdoutData = "";
      let stderrData = "";

      pythonProcess.stdout.on("data", (data) => {
        stdoutData += data.toString();
      });

      pythonProcess.stderr.on("data", (data) => {
        stderrData += data.toString();
      });

      pythonProcess.on("close", (code) => {
        if (code !== 0 && !stdoutData.trim()) {
          return res.status(500).json({
            success: false,
            error: stderrData.trim() || `Python process exited with code ${code}`,
          });
        }

        try {
          const parsed = JSON.parse(stdoutData.trim());
          return res.json(parsed);
        } catch (e: any) {
          return res.status(500).json({
            success: false,
            error: `Failed to parse Python output: ${e.message}`,
            raw: stdoutData,
          });
        }
      });

      // Send JSON payload to Python's stdin
      pythonProcess.stdin.write(
        JSON.stringify({
          url,
          html,
          base_url,
          button_only,
        })
      );
      pythonProcess.stdin.end();
    } catch (err: any) {
      res.status(500).json({
        success: false,
        error: `Failed to spawn Python process: ${err.message}`,
      });
    }
  });

  // API: Python URL Resolver endpoint (from uploaded app.py)
  app.post("/api/resolve", async (req, res) => {
    const { url } = req.body;

    if (!url || typeof url !== "string") {
      return res.status(400).json({
        success: false,
        error: "Enter a shortened URL.",
      });
    }

    try {
      const pythonProcess = spawn("python3", ["resolver.py", "--json"]);

      let stdoutData = "";
      let stderrData = "";

      pythonProcess.stdout.on("data", (data) => {
        stdoutData += data.toString();
      });

      pythonProcess.stderr.on("data", (data) => {
        stderrData += data.toString();
      });

      pythonProcess.on("close", (code) => {
        if (code !== 0 && !stdoutData.trim()) {
          return res.status(500).json({
            success: false,
            error: stderrData.trim() || `Resolver process exited with code ${code}`,
          });
        }

        try {
          const parsed = JSON.parse(stdoutData.trim());
          return res.json(parsed);
        } catch (e: any) {
          return res.status(500).json({
            success: false,
            error: `Failed to parse Resolver output: ${e.message}`,
            raw: stdoutData,
          });
        }
      });

      pythonProcess.stdin.write(JSON.stringify({ url: url.trim() }));
      pythonProcess.stdin.end();
    } catch (err: any) {
      res.status(500).json({
        success: false,
        error: `Failed to spawn Python resolver: ${err.message}`,
      });
    }
  });

  // API: Unified Resolver & Link Extractor pipeline
  app.post("/api/unified", async (req, res) => {
    const {
      url,
      html,
      base_url,
      button_only = true,
      auto_resolve = true,
    } = req.body;

    if (!url && !html) {
      return res.status(400).json({
        success: false,
        error: "Please provide a valid URL or HTML content.",
      });
    }

    try {
      const pythonProcess = spawn("python3", ["unified_engine.py", "--json"]);

      let stdoutData = "";
      let stderrData = "";

      pythonProcess.stdout.on("data", (data) => {
        stdoutData += data.toString();
      });

      pythonProcess.stderr.on("data", (data) => {
        stderrData += data.toString();
      });

      pythonProcess.on("close", (code) => {
        if (code !== 0 && !stdoutData.trim()) {
          return res.status(500).json({
            success: false,
            error:
              stderrData.trim() || `Unified engine exited with code ${code}`,
          });
        }

        try {
          const parsed = JSON.parse(stdoutData.trim());
          return res.json(parsed);
        } catch (e: any) {
          return res.status(500).json({
            success: false,
            error: `Failed to parse Unified output: ${e.message}`,
            raw: stdoutData,
          });
        }
      });

      pythonProcess.stdin.write(
        JSON.stringify({
          url: url ? url.trim() : "",
          html: html || "",
          base_url: base_url || "",
          button_only: Boolean(button_only),
          auto_resolve: Boolean(auto_resolve),
        })
      );
      pythonProcess.stdin.end();
    } catch (err: any) {
      res.status(500).json({
        success: false,
        error: `Failed to spawn unified engine: ${err.message}`,
      });
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
    console.log(`Android App Server running on http://0.0.0.0:${PORT}`);
  });
}

startServer();
