import React, { useState } from "react";
import { Terminal, Copy, Check, FileCode2, ShieldCheck, Sparkles } from "lucide-react";

interface PythonLogicViewerProps {
  onRunTestSample?: () => void;
}

export const PythonLogicViewer: React.FC<PythonLogicViewerProps> = () => {
  const [copied, setCopied] = useState(false);
  const [activeTab, setActiveTab] = useState<"unified" | "resolver" | "extractor">("unified");

  const unifiedCode = `#!/usr/bin/env python3
"""
Unified Link Resolver & Extractor Engine
Merges URL Shortener Resolving and Link Extraction into one pipeline.
1. Accepts input URL (post, shortlink, or direct page) or HTML snippet.
2. If URL: resolves all redirect hops, safelinks, action buttons & AdLinkFly tokens.
3. Automatically parses the final destination page HTML and extracts download links.
4. Returns end-to-end trace and extracted episodes in a single response.
"""
import resolver
import extractor

def run_unified_pipeline(url=None, html=None, base_url=None, button_only=True, auto_resolve=True):
    if auto_resolve and url:
        # Step 1: Trace and bypass all redirects & shorteners
        resolve_res = resolver.resolve_url(url, return_html=True)
        final_dest_url = resolve_res["final"]
        final_html = resolve_res.get("final_html")
        
        # Step 2: Extract action & download links from destination page
        items = extractor.extract_from_html(final_html, final_dest_url, button_only=button_only)
        return {
            "success": True,
            "resolved": True,
            "original_url": url,
            "final_url": final_dest_url,
            "redirects": resolve_res["redirects"],
            "chain": resolve_res["chain"],
            "items": items,
            "count": len(items)
        }
`;

  const resolverCode = `#!/usr/bin/env python3
"""
URL Shortener Resolver & Safelink Bypasser
Bypasses:
- HTTP 301, 302, 303, 307, 308 redirects
- AdLinkFly & Sohojgyan shorteners (token decoding & AJAX bypass)
- Blogger safelink interstitial pages (?url= base64 payloads)
- WordPress safelink pages (?url= & delay timers)
- Content post action buttons (btn-slide, get-link, Episode Wise Links)
"""
import urllib.request, urllib.parse, re, base64

def resolve_url(start_url, return_html=False):
    # Cookie jar preserves session tokens across redirects
    # Follows hops, decrypts intermediates, and returns destination
    ...
`;

  const extractorCode = `#!/usr/bin/env python3
"""
Webpage Link & Button Extractor
- HTMLParser for robust AST DOM parsing
- Void element & ancestor stack exclusion tracking
- Skips header, footer, navbars, sidebars, and menus
- Pinpoints high-confidence action buttons (GDrive, Mega, download, stream)
"""
from html.parser import HTMLParser

class ElementCollector(HTMLParser):
    def handle_starttag(self, tag, attrs):
        # Captures <a href>, <button>, <input type="button|submit">
        ...
`;

  const currentCode =
    activeTab === "unified"
      ? unifiedCode
      : activeTab === "resolver"
      ? resolverCode
      : extractorCode;

  const copyCode = async () => {
    try {
      await navigator.clipboard.writeText(currentCode);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      // Fallback
    }
  };

  return (
    <div className="bg-[#fdfcff] rounded-[28px] p-6 m3-elevation-1 border border-[#e1e7f0] space-y-4">
      <div className="flex items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-2xl bg-[#d3e3fd] text-[#041e49] flex items-center justify-center shrink-0">
            <FileCode2 className="w-5 h-5 text-[#0b57d0]" />
          </div>
          <div>
            <h3 className="font-semibold text-[#1f1f1f] text-sm sm:text-base">Python Architecture</h3>
            <p className="text-xs text-[#444746]">Standard Library Engine • Zero 3rd-Party Wheels</p>
          </div>
        </div>
        <button
          onClick={copyCode}
          className="h-9 px-4 rounded-full text-xs font-medium bg-[#e9eef6] text-[#1f1f1f] hover:bg-[#dfe4ed] active:bg-[#d3e3fd] flex items-center gap-1.5 transition-colors cursor-pointer"
        >
          {copied ? <Check className="w-3.5 h-3.5 text-[#0b57d0]" /> : <Copy className="w-3.5 h-3.5" />}
          <span>{copied ? "Copied" : "Copy Code"}</span>
        </button>
      </div>

      {/* M3 Navigation Segmented Buttons / Chips */}
      <div className="flex items-center gap-2 p-1 bg-[#f0f4f9] rounded-full w-fit">
        <button
          onClick={() => setActiveTab("unified")}
          className={`px-3.5 py-1.5 rounded-full text-xs font-medium transition-colors cursor-pointer ${
            activeTab === "unified"
              ? "bg-[#0b57d0] text-white shadow-xs"
              : "text-[#444746] hover:text-[#1f1f1f]"
          }`}
        >
          Unified Engine
        </button>
        <button
          onClick={() => setActiveTab("resolver")}
          className={`px-3.5 py-1.5 rounded-full text-xs font-medium transition-colors cursor-pointer ${
            activeTab === "resolver"
              ? "bg-[#0b57d0] text-white shadow-xs"
              : "text-[#444746] hover:text-[#1f1f1f]"
          }`}
        >
          Resolver Logic
        </button>
        <button
          onClick={() => setActiveTab("extractor")}
          className={`px-3.5 py-1.5 rounded-full text-xs font-medium transition-colors cursor-pointer ${
            activeTab === "extractor"
              ? "bg-[#0b57d0] text-white shadow-xs"
              : "text-[#444746] hover:text-[#1f1f1f]"
          }`}
        >
          Extractor Logic
        </button>
      </div>

      {/* M3 Feature Highlights Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
        <div className="p-3.5 rounded-2xl bg-[#f8fafd] border border-[#e1e7f0] flex items-start gap-2.5">
          <div className="w-6 h-6 rounded-full bg-[#d3e3fd] flex items-center justify-center shrink-0 mt-0.5">
            <ShieldCheck className="w-3.5 h-3.5 text-[#0b57d0]" />
          </div>
          <div>
            <span className="font-semibold text-[#1f1f1f]">DOM Chrome Exclusion</span>
            <p className="text-[#444746] text-[12px] mt-0.5 leading-relaxed">
              Skips &lt;header&gt;, &lt;footer&gt;, &lt;nav&gt;, navbars, sidebars, and menus automatically.
            </p>
          </div>
        </div>
        <div className="p-3.5 rounded-2xl bg-[#f8fafd] border border-[#e1e7f0] flex items-start gap-2.5">
          <div className="w-6 h-6 rounded-full bg-[#c2e7ff] flex items-center justify-center shrink-0 mt-0.5">
            <Sparkles className="w-3.5 h-3.5 text-[#00639b]" />
          </div>
          <div>
            <span className="font-semibold text-[#1f1f1f]">Action Button Scoring</span>
            <p className="text-[#444746] text-[12px] mt-0.5 leading-relaxed">
              Prioritizes downloads, video mirrors, external target hosts, and data-url buttons.
            </p>
          </div>
        </div>
      </div>

      {/* Code Block Container */}
      <div className="relative rounded-2xl overflow-hidden bg-[#1e1f20] text-[#e3e3e3] font-mono text-[12px] border border-[#303030] w-full max-w-full">
        <div className="flex items-center justify-between px-4 py-2.5 bg-[#2a2b2c] border-b border-[#3c3c3c] text-xs text-[#c4c7c5]">
          <span className="flex items-center gap-1.5 font-medium">
            <Terminal className="w-3.5 h-3.5 text-[#a8c7fa]" /> python/{activeTab}.py
          </span>
          <span className="text-[11px] text-[#8e918f]">Python 3.10+</span>
        </div>
        <pre className="p-4 overflow-x-auto leading-relaxed text-[12px]">
          <code>{currentCode}</code>
        </pre>
      </div>
    </div>
  );
};

