import React from "react";
import { Terminal, Code2, FolderArchive } from "lucide-react";

interface AppHeaderProps {
  onTogglePythonLogic: () => void;
  showPythonLogic: boolean;
  onTogglePluginHub: () => void;
  showPluginHub: boolean;
}

export const AppHeader: React.FC<AppHeaderProps> = ({
  onTogglePythonLogic,
  showPythonLogic,
  onTogglePluginHub,
  showPluginHub,
}) => {
  return (
    <header
      id="app-header"
      className="w-full bg-[#fdfcff] text-[#1f1f1f] border-b border-[#e1e7f0] shadow-xs select-none sticky top-0 z-30 transition-colors"
    >
      <div className="max-w-4xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-4">
        {/* Brand & Identity (M3 Headline Medium / Title) */}
        <div className="flex items-center gap-3 min-w-0">
          <div className="w-10 h-10 rounded-2xl bg-[#c2e7ff] text-[#001d35] flex items-center justify-center shrink-0 transition-transform active:scale-95 shadow-xs">
            {/* Google-style dynamic colorful emblem */}
            <svg
              className="w-5 h-5 text-[#0b57d0]"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="2.2"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" />
              <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" />
            </svg>
          </div>
          <div className="min-w-0">
            <div className="flex items-center gap-2">
              <h1 className="font-semibold text-base sm:text-lg leading-snug text-[#1f1f1f] tracking-tight truncate font-sans">
                Link Extractor
              </h1>
              <span className="text-[11px] font-medium px-2 py-0.5 rounded-full bg-[#e9eef6] text-[#444746] tracking-wide shrink-0">
                M3 Clean
              </span>
            </div>
            <p className="text-[12px] text-[#444746] truncate hidden sm:block font-normal">
              Resolve redirects, bypass shortlinks, and extract clean direct links
            </p>
          </div>
        </div>

        {/* M3 Outlined / Tonal Action Button */}
        <div className="flex items-center gap-2 shrink-0">
          <button
            id="header-btn-plugin"
            onClick={onTogglePluginHub}
            className={`h-10 px-3.5 sm:px-4 rounded-full text-xs font-medium flex items-center gap-2 transition-all cursor-pointer ${
              showPluginHub
                ? "bg-[#6750a4] text-white shadow-xs hover:bg-[#533d8c]"
                : "bg-[#e8def8] text-[#4a4458] hover:bg-[#decff5] active:bg-[#d0bbf0]"
            }`}
            title="WordPress Plugin & Automator"
          >
            <FolderArchive className="w-4 h-4" />
            <span className="hidden sm:inline">WordPress Plugin</span>
            <span className="text-[10px] bg-white/40 px-1.5 py-0.5 rounded-full font-semibold">ZIP</span>
          </button>

          <button
            id="header-btn-python"
            onClick={onTogglePythonLogic}
            className={`h-10 px-3.5 sm:px-4 rounded-full text-xs font-medium flex items-center gap-2 transition-all cursor-pointer ${
              showPythonLogic
                ? "bg-[#0b57d0] text-white shadow-xs hover:bg-[#0842a0]"
                : "bg-[#e9eef6] text-[#1f1f1f] hover:bg-[#dfe4ed] active:bg-[#d3e3fd]"
            }`}
            title="Toggle Python engine logic"
          >
            {showPythonLogic ? (
              <Code2 className="w-4 h-4" />
            ) : (
              <Terminal className="w-4 h-4 text-[#444746]" />
            )}
            <span className="hidden sm:inline">Engine Logic</span>
          </button>
        </div>
      </div>
    </header>
  );
};


