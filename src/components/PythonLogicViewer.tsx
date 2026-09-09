import React, { useState } from "react";
import { Terminal, Copy, Check, Play, FileCode2, ShieldCheck, Sparkles } from "lucide-react";

interface PythonLogicViewerProps {
  onRunTestSample?: () => void;
}

export const PythonLogicViewer: React.FC<PythonLogicViewerProps> = ({ onRunTestSample }) => {
  const [copied, setCopied] = useState(false);

  const pythonCode = `#!/usr/bin/env python3
"""
Webpage Link & Button Extractor
Python standard library only:
- HTMLParser for robust AST parsing
- urllib for HTTP fetching
- Regex for inline JS URLs (location.href, window.open, data-url)
- Excludes site chrome (header, footer, nav, sidebar, menus)
- Excludes test/audit tools (Rich Results Test, PageSpeed Insights)
- Pinpoints high-confidence action buttons (download, stream, watch, servers)
"""

import re
import sys
from html.parser import HTMLParser
from urllib.parse import urljoin, urlparse, unquote
from urllib.request import Request, urlopen

ACTION_WORDS = {
    "download", "watch", "stream", "play", "open", "direct",
    "server", "link", "get", "view", "continue", "mirror",
    "xcloud", "filemoon", "streamtape", "doodstream", "mixdrop",
}

class ElementCollector(HTMLParser):
    """Extracts links/buttons from <body> while skipping site chrome."""
    EXCLUDED_TAGS = {"header", "footer", "nav", "aside", "script", "style", "noscript"}
    EXCLUDED_MARKERS = (
        "header", "footer", "navbar", "navigation", "nav-menu", "navmenu",
        "main-menu", "mainmenu", "menu", "menus", "sidebar", "site-header",
        "site-footer", "topbar", "top-bar", "bottombar", "bottom-bar"
    )

    def handle_starttag(self, tag, attrs):
        # Captures <a href>, <button>, <input type="button|submit">,
        # onclick handlers and data-url attributes while excluding navigation chrome.
        ...
`;

  const copyCode = async () => {
    try {
      await navigator.clipboard.writeText(pythonCode);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      // Fallback
    }
  };

  return (
    <div className="bg-white rounded-2xl p-5 shadow-sm border border-slate-200/80 space-y-4">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <div className="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center">
            <FileCode2 className="w-4 h-4" />
          </div>
          <div>
            <h3 className="font-semibold text-slate-900 text-sm">Python Architecture</h3>
            <p className="text-xs text-slate-500">Standard Library Engine • No 3rd-Party Wheels</p>
          </div>
        </div>
        <button
          onClick={copyCode}
          className="flex items-center gap-1.5 text-xs font-medium px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition-colors cursor-pointer"
        >
          {copied ? <Check className="w-3.5 h-3.5 text-emerald-600" /> : <Copy className="w-3.5 h-3.5" />}
          {copied ? "Copied" : "Copy Code"}
        </button>
      </div>

      {/* Feature cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
        <div className="p-2.5 rounded-xl bg-slate-50 border border-slate-100 flex items-start gap-2">
          <ShieldCheck className="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
          <div>
            <span className="font-semibold text-slate-800">Chrome Exclusion:</span>
            <p className="text-slate-500 text-[11px] mt-0.5">
              Skips &lt;header&gt;, &lt;footer&gt;, &lt;nav&gt;, navbars, sidebars, and menus.
            </p>
          </div>
        </div>
        <div className="p-2.5 rounded-xl bg-slate-50 border border-slate-100 flex items-start gap-2">
          <Sparkles className="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
          <div>
            <span className="font-semibold text-slate-800">Action Scoring:</span>
            <p className="text-slate-500 text-[11px] mt-0.5">
              Prioritizes downloads, video mirrors, external target hosts, and data-url buttons.
            </p>
          </div>
        </div>
      </div>

      {/* Code Block */}
      <div className="relative rounded-xl overflow-hidden bg-slate-950 text-slate-300 font-mono text-[11px] border border-slate-800 w-full max-w-full">
        <div className="bg-slate-900/90 px-3 py-1.5 flex items-center justify-between border-b border-slate-800 text-[10px] text-slate-400">
          <span className="flex items-center gap-1.5">
            <Terminal className="w-3 h-3 text-emerald-400" /> extractor.py
          </span>
          <span>Python 3.10</span>
        </div>
        <pre className="p-3 overflow-y-auto overflow-x-hidden whitespace-pre-wrap break-all leading-relaxed max-h-56 text-[11px] w-full max-w-full">
          <code>{pythonCode}</code>
        </pre>
      </div>

      {onRunTestSample && (
        <button
          onClick={onRunTestSample}
          className="w-full py-2.5 px-4 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-semibold flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
        >
          <Play className="w-3.5 h-3.5 fill-current" /> Run Python Sample Demo
        </button>
      )}
    </div>
  );
};
