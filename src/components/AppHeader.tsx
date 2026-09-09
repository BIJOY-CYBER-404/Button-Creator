import React from "react";
import {
  Terminal,
  Link2,
  Route,
  ArrowUpRight,
  Download,
  Code2,
} from "lucide-react";
import { ActivePage } from "../types";

interface AppHeaderProps {
  onTogglePythonLogic: () => void;
  showPythonLogic: boolean;
  activePage: ActivePage;
  onNavigatePage: (page: ActivePage) => void;
}

export const AppHeader: React.FC<AppHeaderProps> = ({
  onTogglePythonLogic,
  showPythonLogic,
  activePage,
  onNavigatePage,
}) => {
  return (
    <header
      id="app-header"
      className="w-full bg-[#111827] text-white border-b border-slate-800 shadow-sm select-none"
    >
      <div className="max-w-4xl mx-auto px-4 py-3 flex items-center justify-between gap-3">
        {/* Brand & Identity */}
        <div className="flex items-center gap-2.5 min-w-0">
          <div className="w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center border border-emerald-500/30 shrink-0">
            {activePage === "extractor" ? (
              <Download className="w-4 h-4 text-emerald-400" />
            ) : (
              <Route className="w-4 h-4 text-emerald-400" />
            )}
          </div>
          <div className="min-w-0">
            <div className="flex items-center gap-2">
              <h1 className="font-bold text-sm sm:text-base leading-tight text-white tracking-tight truncate">
                {activePage === "extractor" ? "Link Extractor" : "URL Shortener Resolver"}
              </h1>
              <span className="hidden xs:inline-block text-[10px] font-mono px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-semibold shrink-0">
                PRO
              </span>
            </div>
            <p className="text-[11px] text-slate-400 truncate hidden sm:block">
              {activePage === "extractor"
                ? "Extract action & download links from HTML and live web pages"
                : "Bypass shorteners, safelinks, and trace full redirect chains"}
            </p>
          </div>
        </div>

        {/* Navigation & Utilities */}
        <div className="flex items-center gap-2 shrink-0">
          {/* Page switch tabs */}
          <div className="flex items-center bg-slate-800/90 p-0.5 rounded-lg border border-slate-700/60">
            <button
              id="header-btn-extractor"
              onClick={() => onNavigatePage("extractor")}
              className={`px-2.5 py-1 rounded-md text-xs font-semibold flex items-center gap-1.5 transition-all cursor-pointer ${
                activePage === "extractor"
                  ? "bg-emerald-600 text-white shadow-xs"
                  : "text-slate-300 hover:text-white"
              }`}
            >
              <Download className="w-3.5 h-3.5" />
              <span className="hidden sm:inline">Extractor</span>
            </button>
            <button
              id="header-btn-resolver"
              onClick={() => onNavigatePage("resolver")}
              className={`px-2.5 py-1 rounded-md text-xs font-semibold flex items-center gap-1.5 transition-all cursor-pointer ${
                activePage === "resolver"
                  ? "bg-emerald-600 text-white shadow-xs"
                  : "text-slate-300 hover:text-white"
              }`}
            >
              <ArrowUpRight className="w-3.5 h-3.5" />
              <span className="hidden sm:inline">Resolver</span>
            </button>
          </div>

          {/* Python Logic Toggle */}
          <button
            id="header-btn-python"
            onClick={onTogglePythonLogic}
            className={`px-2.5 py-1.5 rounded-lg text-xs font-medium flex items-center gap-1.5 transition-colors cursor-pointer border ${
              showPythonLogic
                ? "bg-emerald-600 border-emerald-500 text-white"
                : "bg-slate-800 border-slate-700 text-slate-300 hover:bg-slate-700"
            }`}
            title="Inspect Python backend logic and source code"
          >
            <Terminal className="w-3.5 h-3.5 text-emerald-400" />
            <span className="hidden md:inline">Python Logic</span>
          </button>
        </div>
      </div>
    </header>
  );
};
