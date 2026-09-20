import React from "react";
import { Download, Server, ShieldCheck, FileCode, CheckCircle, ExternalLink, Terminal, HardDrive } from "lucide-react";

interface CPanelDeploymentHubProps {
  onNotify?: (text: string, type: "success" | "error" | "info") => void;
}

export const CPanelDeploymentHub: React.FC<CPanelDeploymentHubProps> = ({ onNotify }) => {
  const downloadCPanelZip = () => {
    window.location.href = "/api/download-cpanel-zip";
    onNotify?.("Starting download of cPanel Deployment Package (ZIP)...", "success");
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
                v-3.9.7
              </span>
            </div>
            <p className="text-xs text-[#5f6368]">
              Deploy the self-contained PHP application with zero SQL database setup, Apache clean routing, and private admin endpoints.
            </p>
          </div>

          <button
            onClick={downloadCPanelZip}
            className="px-5 py-3 rounded-xl bg-[#0b57d0] hover:bg-[#0842a0] text-white font-bold text-xs sm:text-sm flex items-center gap-2 cursor-pointer shadow-xs transition-all self-start sm:self-auto shrink-0"
          >
            <Download className="w-4 h-4" />
            <span>Download cPanel ZIP</span>
          </button>
        </div>

        {/* Feature Highlights Grid */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div className="bg-[#f8fafd] border border-[#d3e3fd] p-4 rounded-xl space-y-1.5">
            <div className="flex items-center gap-2 text-xs font-bold text-[#0b57d0]">
              <ShieldCheck className="w-4 h-4" />
              <span>Secure Session Auth</span>
            </div>
            <p className="text-[11px] text-[#5f6368]">
              Extractor, Resolver, and Datastore APIs are guarded by session authentication.
            </p>
          </div>

          <div className="bg-[#f6faf7] border border-[#a8dab5] p-4 rounded-xl space-y-1.5">
            <div className="flex items-center gap-2 text-xs font-bold text-[#137333]">
              <HardDrive className="w-4 h-4" />
              <span>Zero-Config Datastore</span>
            </div>
            <p className="text-[11px] text-[#5f6368]">
              Uses high-speed JSON flat-file storage with file-locking. Works instantly on any shared host.
            </p>
          </div>

          <div className="bg-[#fff8e1] border border-[#ffe082] p-4 rounded-xl space-y-1.5">
            <div className="flex items-center gap-2 text-xs font-bold text-[#b06000]">
              <FileCode className="w-4 h-4" />
              <span>Fast Gateway Resolver</span>
            </div>
            <p className="text-[11px] text-[#5f6368]">
              Resolves multi-step shortlinks and extracts download buttons cleanly in seconds.
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
              <strong className="text-[#111827]">Step 3: (Optional) Set Admin Password</strong> — Edit <code>config.php</code> to change <code>ADMIN_PASSWORD</code> from default <code>admin123</code> to your secret password.
            </li>
            <li className="bg-[#fdfdff] p-3 rounded-xl border border-[#e3e7ee]">
              <strong className="text-[#111827]">Step 4: Launch & Generate</strong> — Visit <code>https://yourdomain.com/admin.php</code> in your browser. Paste shortened links, create episode pages, and share the generated public links!
            </li>
          </ol>
        </div>

        {/* Included Files Overview */}
        <div className="bg-[#f8f9fa] rounded-xl p-4 border border-[#e0e4eb] space-y-2">
          <div className="flex items-center justify-between">
            <div className="text-xs font-bold text-[#1f1f1f]">Package Contents Summary:</div>
            <span className="text-[11px] font-mono font-bold text-[#0b57d0]">Build: v-3.9.7 (Latest)</span>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px] font-mono text-[#5f6368]">
            <div>• <code>admin.php</code> (Private Admin Interface)</div>
            <div>• <code>view.php</code> (Public Non-Indexable Viewer)</div>
            <div>• <code>pages.php</code> (Page Management & Visibility)</div>
            <div>• <code>settings.php</code> (Site Identity & Customization)</div>
            <div>• <code>update.php</code> (One-Click Application Update System)</div>
            <div>• <code>api.php</code> (Protected Admin AJAX API)</div>
            <div>• <code>config.php</code> (Credentials & Configuration)</div>
            <div>• <code>.htaccess</code> (Clean URL /p/ slug rules)</div>
            <div>• <code>robots.txt</code> (Crawler exclusion rules)</div>
            <div>• <code>includes/class-resolver.php</code> (Gateway bypass)</div>
            <div>• <code>includes/class-extractor.php</code> (Link parser)</div>
            <div>• <code>includes/class-datastore.php</code> (Data layer)</div>
          </div>
        </div>
      </div>
    </div>
  );
};
