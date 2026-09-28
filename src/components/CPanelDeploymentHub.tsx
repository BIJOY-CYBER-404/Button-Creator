import React, { useState } from "react";
import { Download, Server, ShieldCheck, FileCode, CheckCircle, ExternalLink, Terminal, HardDrive, Copy, Check, RefreshCw, Database } from "lucide-react";

interface CPanelDeploymentHubProps {
  onNotify?: (text: string, type: "success" | "error" | "info") => void;
}

export const CPanelDeploymentHub: React.FC<CPanelDeploymentHubProps> = ({ onNotify }) => {
  const [copiedZipUrl, setCopiedZipUrl] = useState(false);

  const handleCopyDownloadUrl = async () => {
    try {
      const url = `${window.location.origin}/api/download-cpanel-zip`;
      await navigator.clipboard.writeText(url);
      setCopiedZipUrl(true);
      onNotify?.("cPanel .zip direct URL copied to clipboard!", "success");
      setTimeout(() => setCopiedZipUrl(false), 2000);
    } catch {
      onNotify?.("Failed to copy URL", "error");
    }
  };

  return (
    <div className="w-full space-y-5">
      <div className="bg-white rounded-2xl p-6 sm:p-7 border border-[#e0e4eb] shadow-xs space-y-6">
        {/* Banner */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-[#f0f4f9]">
          <div className="space-y-1">
            <div className="flex items-center gap-2 flex-wrap">
              <Server className="w-5 h-5 text-[#0b57d0]" />
              <h2 className="text-lg font-bold text-[#111827]">
                cPanel Shared Hosting Deployment Package
              </h2>
              <span className="px-2 py-0.5 rounded-full text-xs font-bold font-mono bg-[#e8f0fe] text-[#0b57d0] border border-[#c2e7ff]">
                v-15.0.0
              </span>
            </div>
            <p className="text-xs text-[#5f6368]">
              Self-contained standalone PHP application featuring Backup & Restore Center, One-Click In-App Updater, MySQL & SQLite auto-fallback, and Apache clean routing.
            </p>
          </div>

          <div className="flex items-center gap-2 shrink-0">
            <a
              id="btn-download-cpanel-zip"
              href="/api/download-cpanel-zip"
              download="cpanel-app-package.zip"
              onClick={() => onNotify?.("Downloading cPanel deployment ZIP package...", "info")}
              className="px-5 py-3 rounded-xl bg-[#0b57d0] hover:bg-[#0842a0] text-white font-bold text-xs sm:text-sm flex items-center gap-2 cursor-pointer shadow-xs transition-all active:scale-95 shrink-0"
            >
              <Download className="w-4 h-4" />
              <span>Download cPanel ZIP</span>
            </a>

            <button
              id="btn-copy-cpanel-link"
              onClick={handleCopyDownloadUrl}
              className="p-3 bg-white hover:bg-[#f0f4f9] text-[#444746] border border-[#e1e7f0] rounded-xl transition-colors cursor-pointer"
              title="Copy direct download link"
            >
              {copiedZipUrl ? (
                <Check className="w-4 h-4 text-emerald-600" />
              ) : (
                <Copy className="w-4 h-4" />
              )}
            </button>
          </div>
        </div>

        {/* Feature Highlights Grid */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div className="bg-[#f8fafd] border border-[#d3e3fd] p-4 rounded-xl space-y-1.5">
            <div className="flex items-center gap-2 text-xs font-bold text-[#0b57d0]">
              <ShieldCheck className="w-4 h-4" />
              <span>Secure Session Auth & Guard</span>
            </div>
            <p className="text-[11px] text-[#5f6368]">
              Extractor, Resolver, Settings, and Datastore APIs are protected by strict session authentication.
            </p>
          </div>

          <div className="bg-[#f6faf7] border border-[#a8dab5] p-4 rounded-xl space-y-1.5">
            <div className="flex items-center gap-2 text-xs font-bold text-[#137333]">
              <Database className="w-4 h-4" />
              <span>MySQL + SQLite Dual Support</span>
            </div>
            <p className="text-[11px] text-[#5f6368]">
              Works seamlessly with cPanel MySQL databases, or falls back instantly to zero-config file storage.
            </p>
          </div>

          <div className="bg-[#fff8e1] border border-[#ffe082] p-4 rounded-xl space-y-1.5">
            <div className="flex items-center gap-2 text-xs font-bold text-[#b06000]">
              <RefreshCw className="w-4 h-4" />
              <span>One-Click System Updates</span>
            </div>
            <p className="text-[11px] text-[#5f6368]">
              Update your live site directly from <code>update.php</code> with automated backups and rollbacks.
            </p>
          </div>
        </div>

        {/* Step by Step Instructions */}
        <div className="space-y-3">
          <h3 className="text-sm font-bold text-[#111827]">
            How to Install on cPanel in 3 Minutes:
          </h3>

          <ol className="space-y-2.5 text-xs text-[#444746] list-decimal list-inside">
            <li className="bg-[#fdfdff] p-3 rounded-xl border border-[#e3e7ee]">
              <strong className="text-[#111827]">Step 1: Upload to cPanel</strong> — Log in to your cPanel dashboard, open <strong>File Manager</strong>, navigate to <code>public_html</code> (or your custom subdomain folder), and upload <code>cpanel-app-package.zip</code>.
            </li>
            <li className="bg-[#fdfdff] p-3 rounded-xl border border-[#e3e7ee]">
              <strong className="text-[#111827]">Step 2: Extract Files</strong> — Right-click the zip file in cPanel File Manager and click <strong>Extract</strong>.
            </li>
            <li className="bg-[#fdfdff] p-3 rounded-xl border border-[#e3e7ee]">
              <strong className="text-[#111827]">Step 3: (Optional) Set Admin Password or MySQL Credentials</strong> — Edit <code>config.php</code> or create <code>config.local.php</code> to customize your database and admin password (default: <code>admin123</code>).
            </li>
            <li className="bg-[#fdfdff] p-3 rounded-xl border border-[#e3e7ee]">
              <strong className="text-[#111827]">Step 4: Launch & Generate</strong> — Visit <code>https://yourdomain.com/admin.php</code> in your browser. Paste shortened links, create episode pages, manage backups in <code>settings.php</code>, or run one-click updates in <code>update.php</code>!
            </li>
          </ol>
        </div>

        {/* Included Files Overview */}
        <div className="bg-[#f8f9fa] rounded-xl p-4 border border-[#e0e4eb] space-y-2">
          <div className="flex items-center justify-between">
            <div className="text-xs font-bold text-[#1f1f1f]">Package Contents Summary:</div>
            <span className="text-[11px] font-mono font-bold text-[#0b57d0]">Build: v-14.0.0 (Latest)</span>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px] font-mono text-[#5f6368]">
            <div>• <code>admin.php</code> (Admin Generator & URL Bypass Interface)</div>
            <div>• <code>view.php</code> (Public Non-Indexable Episode Viewer)</div>
            <div>• <code>pages.php</code> (Page Management, Bulk Actions & Search)</div>
            <div>• <code>settings.php</code> (Site Identity, Branding & Backup & Restore Center)</div>
            <div>• <code>update.php</code> (One-Click Application Update System)</div>
            <div>• <code>analytics.php</code> (Monthly Statistics & View Dashboard)</div>
            <div>• <code>api.php</code> (Secure AJAX API with Auto-Detect Backup Restore)</div>
            <div>• <code>config.php</code> (Database Credentials & App Config)</div>
            <div>• <code>.htaccess</code> (Apache Clean URL /p/ slug routing)</div>
            <div>• <code>robots.txt</code> (Crawler exclusion rules)</div>
            <div>• <code>includes/class-updater.php</code> (In-App One-Click Updater Engine)</div>
            <div>• <code>includes/class-datastore.php</code> (MySQL & SQLite Multi-Format Data Layer)</div>
          </div>
        </div>
      </div>
    </div>
  );
};
