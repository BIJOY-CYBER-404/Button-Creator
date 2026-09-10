import React, { useState, useMemo } from "react";
import {
  Copy,
  Check,
  Download,
  Code,
  Eye,
  Sparkles,
  ChevronDown,
  ChevronUp,
  Smartphone,
  Monitor,
} from "lucide-react";
import { ExtractedItem } from "../types";

interface EpisodeButtonsGeneratorProps {
  items: ExtractedItem[];
  onNotify: (text: string, type?: "success" | "error" | "info") => void;
}

export const EpisodeButtonsGenerator: React.FC<EpisodeButtonsGeneratorProps> = ({
  items,
  onNotify,
}) => {
  const [prefix, setPrefix] = useState<string>("Episode");
  const [startNumber, setStartNumber] = useState<number>(1);
  const [padZeroes, setPadZeroes] = useState<boolean>(true);
  const [openInNewTab, setOpenInNewTab] = useState<boolean>(true);
  const [copied, setCopied] = useState<boolean>(false);
  const [activeTab, setActiveTab] = useState<"preview" | "code">("preview");
  const [includeHoverStyle, setIncludeHoverStyle] = useState<boolean>(true);
  const [isCollapsed, setIsCollapsed] = useState<boolean>(false);
  const [sessionEndText, setSessionEndText] = useState<string>("- Session End -");
  const [previewDevice, setPreviewDevice] = useState<"responsive" | "mobile" | "desktop">("responsive");

  // Generate button list
  const buttonsData = useMemo(() => {
    return items.map((item, index) => {
      const epNum = startNumber + index;
      const numStr = padZeroes ? String(epNum).padStart(2, "0") : String(epNum);
      const label = `${prefix ? prefix + " " : ""}${numStr}`.trim();
      const isFilled = index % 2 === 0; // First is filled (0, 2, 4...), second is outlined (1, 3, 5...)
      return {
        id: index,
        label,
        url: item.url,
        isFilled,
      };
    });
  }, [items, prefix, startNumber, padZeroes]);

  // Generate self-contained, embeddable HTML string
  const generatedHtml = useMemo(() => {
    if (!buttonsData.length) return "";

    const targetAttr = openInNewTab ? ' target="_blank" rel="noopener noreferrer"' : "";

    const buttonsHtml = buttonsData
      .map((btn) => {
        if (btn.isFilled) {
          // First button: Filled Blue (Mobile: 400px, Desktop: 600px, Height: Auto, no icon)
          return `  <a href="${btn.url}"${targetAttr} class="ep-btn ep-btn-filled" style="width: 400px; max-width: 100%; height: auto; min-height: 48px; box-sizing: border-box; display: inline-flex; align-items: center; justify-content: center; padding: 13px 24px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 15px; font-weight: 600; text-align: center; text-decoration: none; border-radius: 8px; transition: all 0.2s ease; background-color: #2563eb; color: #ffffff; border: 1.5px solid #2563eb; box-shadow: 0 2px 5px rgba(37, 99, 235, 0.25); cursor: pointer;">${btn.label}</a>`;
        } else {
          // Second button: Outlined Blue with low filled color opacity (Mobile: 400px, Desktop: 600px, Height: Auto, no icon)
          return `  <a href="${btn.url}"${targetAttr} class="ep-btn ep-btn-outlined" style="width: 400px; max-width: 100%; height: auto; min-height: 48px; box-sizing: border-box; display: inline-flex; align-items: center; justify-content: center; padding: 13px 24px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 15px; font-weight: 600; text-align: center; text-decoration: none; border-radius: 8px; transition: all 0.2s ease; background-color: rgba(37, 99, 235, 0.08); color: #2563eb; border: 1.5px solid #2563eb; cursor: pointer;">${btn.label}</a>`;
        }
      })
      .join("\n");

    const sessionEndHtml = sessionEndText
      ? `\n  <div class="ep-session-end" style="margin-top: 14px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 15px; font-weight: 700; color: #dc2626; text-align: center; letter-spacing: 0.5px;">${sessionEndText}</div>`
      : "";

    if (includeHoverStyle) {
      return `<!-- Episode Buttons (Mobile: 400px, Desktop: 600px, Height: Auto, Blue Combo: Alternating Filled & Outlined) -->
<style>
  .episodes-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
    width: 100%;
    margin: 16px 0;
  }
  .ep-btn {
    width: 400px;
    max-width: 100%;
    height: auto;
    min-height: 48px;
    box-sizing: border-box;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 13px 24px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    font-size: 15px;
    font-weight: 600;
    text-align: center;
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.2s ease;
    cursor: pointer;
    user-select: none;
    line-height: 1.35;
  }
  @media (min-width: 768px) {
    .ep-btn {
      width: 600px !important;
    }
  }
  .ep-btn-filled:hover {
    background-color: #1d4ed8 !important;
    border-color: #1d4ed8 !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(37, 99, 235, 0.35) !important;
  }
  .ep-btn-outlined:hover {
    background-color: rgba(37, 99, 235, 0.16) !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(37, 99, 235, 0.15) !important;
  }
  .ep-session-end {
    margin-top: 14px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    font-size: 15px;
    font-weight: 700;
    color: #dc2626;
    text-align: center;
    letter-spacing: 0.5px;
  }
</style>
<div class="episodes-container">
${buttonsHtml}${sessionEndHtml}
</div>`;
    }

    return `<!-- Episode Buttons (Mobile: 400px, Desktop: 600px, Height: Auto, Pure HTML) -->
<style>
  @media (min-width: 768px) {
    .ep-btn {
      width: 600px !important;
    }
  }
</style>
<div style="display: flex; flex-direction: column; align-items: center; gap: 14px; width: 100%; margin: 16px 0;">
${buttonsHtml}${sessionEndHtml}
</div>`;
  }, [buttonsData, openInNewTab, includeHoverStyle, sessionEndText]);

  const copyHtml = async () => {
    if (!generatedHtml) return;
    try {
      await navigator.clipboard.writeText(generatedHtml);
      setCopied(true);
      onNotify("Buttons HTML copied to clipboard!", "success");
      setTimeout(() => setCopied(false), 2000);
    } catch {
      onNotify("Failed to copy HTML to clipboard", "error");
    }
  };

  const downloadHtml = () => {
    if (!generatedHtml) return;
    const blob = new Blob([generatedHtml], { type: "text/html;charset=utf-8" });
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = "episode_buttons.html";
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(a.href);
    onNotify("Downloaded episode_buttons.html", "success");
  };

  if (!items.length) {
    return null;
  }

  return (
    <div
      id="episode-buttons-generator-card"
      className="w-full bg-gradient-to-b from-blue-50/70 via-white to-white rounded-[14px] p-4 sm:p-5 border border-blue-200/90 shadow-[0_4px_20px_rgba(37,99,235,0.08)] mb-4 transition-all"
    >
      {/* Top Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 pb-3 border-b border-blue-100">
        <div className="flex items-center gap-2.5">
          <div className="w-8 h-8 rounded-lg bg-blue-600 text-white flex items-center justify-center shrink-0 shadow-sm">
            <Sparkles className="w-4 h-4" />
          </div>
          <div>
            <div className="flex items-center gap-2">
              <h2 className="text-base sm:text-lg font-bold text-slate-900 tracking-tight">
                Generated Episode Buttons HTML
              </h2>
              <span className="px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 text-[11px] font-semibold">
                Mobile 400px • Desktop 600px
              </span>
            </div>
            <p className="text-xs text-slate-600">
              Responsive width (400px mobile, 600px desktop) • Height auto-match • 1 per row • Alternating filled &amp; outlined • Red &ldquo;- Session End -&rdquo; footer
            </p>
          </div>
        </div>

        <div className="flex items-center gap-2 shrink-0">
          <button
            id="btn-copy-generated-html"
            onClick={copyHtml}
            className="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-xs flex items-center gap-1.5 cursor-pointer transition-all active:scale-95"
          >
            {copied ? (
              <>
                <Check className="w-3.5 h-3.5" />
                <span>HTML Copied!</span>
              </>
            ) : (
              <>
                <Copy className="w-3.5 h-3.5" />
                <span>Copy HTML</span>
              </>
            )}
          </button>
          <button
            onClick={downloadHtml}
            className="px-3 py-1.5 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 text-xs font-medium rounded-lg shadow-2xs flex items-center gap-1.5 cursor-pointer transition-colors"
            title="Download HTML snippet"
          >
            <Download className="w-3.5 h-3.5 text-slate-500" />
            <span className="hidden sm:inline">Download</span>
          </button>
          <button
            onClick={() => setIsCollapsed(!isCollapsed)}
            className="p-1.5 text-slate-500 hover:text-slate-800 hover:bg-blue-100/50 rounded-lg cursor-pointer transition-colors"
            title={isCollapsed ? "Expand generator" : "Collapse generator"}
          >
            {isCollapsed ? (
              <ChevronDown className="w-4 h-4" />
            ) : (
              <ChevronUp className="w-4 h-4" />
            )}
          </button>
        </div>
      </div>

      {!isCollapsed && (
        <>
          {/* Controls & Configuration Bar */}
          <div className="flex flex-wrap items-center gap-3 pt-3 pb-3 text-xs text-slate-700">
            <div className="flex items-center gap-1.5">
              <span className="text-slate-500 font-medium">Text Prefix:</span>
              <input
                type="text"
                value={prefix}
                onChange={(e) => setPrefix(e.target.value)}
                placeholder="Episode"
                className="w-24 px-2 py-1 bg-white border border-slate-200 rounded-md text-xs font-medium outline-none focus:border-blue-500"
              />
            </div>

            <div className="flex items-center gap-1.5">
              <span className="text-slate-500 font-medium">Start #:</span>
              <input
                type="number"
                min={0}
                value={startNumber}
                onChange={(e) => setStartNumber(parseInt(e.target.value, 10) || 0)}
                className="w-16 px-2 py-1 bg-white border border-slate-200 rounded-md text-xs font-medium outline-none focus:border-blue-500"
              />
            </div>

            <label className="flex items-center gap-1.5 cursor-pointer select-none">
              <input
                type="checkbox"
                checked={padZeroes}
                onChange={(e) => setPadZeroes(e.target.checked)}
                className="rounded text-blue-600 focus:ring-0 w-3.5 h-3.5 accent-blue-600 cursor-pointer"
              />
              <span className="text-slate-700">Two-digit numbers (01, 02...)</span>
            </label>

            <label className="flex items-center gap-1.5 cursor-pointer select-none">
              <input
                type="checkbox"
                checked={openInNewTab}
                onChange={(e) => setOpenInNewTab(e.target.checked)}
                className="rounded text-blue-600 focus:ring-0 w-3.5 h-3.5 accent-blue-600 cursor-pointer"
              />
              <span className="text-slate-700">Open in new tab (_blank)</span>
            </label>

            <label className="flex items-center gap-1.5 cursor-pointer select-none">
              <input
                type="checkbox"
                checked={includeHoverStyle}
                onChange={(e) => setIncludeHoverStyle(e.target.checked)}
                className="rounded text-blue-600 focus:ring-0 w-3.5 h-3.5 accent-blue-600 cursor-pointer"
              />
              <span className="text-slate-700">Include &lt;style&gt; hover FX</span>
            </label>

            <div className="flex items-center gap-1.5">
              <span className="text-slate-500 font-medium">End Text:</span>
              <input
                type="text"
                value={sessionEndText}
                onChange={(e) => setSessionEndText(e.target.value)}
                placeholder="- Session End -"
                className="w-36 px-2 py-1 bg-white border border-slate-200 rounded-md text-xs font-semibold text-red-600 outline-none focus:border-red-400"
              />
            </div>
          </div>

          {/* View Mode Switcher (Visual Preview vs HTML Code) */}
          <div className="flex items-center justify-between gap-2 mb-2.5">
            <div className="inline-flex rounded-lg bg-slate-100 p-0.5 text-xs font-medium border border-slate-200/70">
              <button
                onClick={() => setActiveTab("preview")}
                className={`px-3 py-1 rounded-md transition-all flex items-center gap-1.5 cursor-pointer ${
                  activeTab === "preview"
                    ? "bg-white text-blue-600 shadow-xs font-semibold"
                    : "text-slate-600 hover:text-slate-900"
                }`}
              >
                <Eye className="w-3.5 h-3.5" />
                <span>Live Buttons Preview</span>
              </button>
              <button
                onClick={() => setActiveTab("code")}
                className={`px-3 py-1 rounded-md transition-all flex items-center gap-1.5 cursor-pointer ${
                  activeTab === "code"
                    ? "bg-white text-blue-600 shadow-xs font-semibold"
                    : "text-slate-600 hover:text-slate-900"
                }`}
              >
                <Code className="w-3.5 h-3.5" />
                <span>HTML Code ({buttonsData.length} buttons)</span>
              </button>
            </div>

            <span className="text-[11px] text-slate-500 font-medium">
              {buttonsData.length} button{buttonsData.length === 1 ? "" : "s"} generated
            </span>
          </div>

          {/* Tab 1: Live Interactive Preview */}
          {activeTab === "preview" && (
            <div className="bg-white rounded-xl p-4 sm:p-5 border border-slate-200/80 shadow-inner">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 pb-3 mb-3 border-b border-slate-100">
                <div className="flex items-center gap-2">
                  <span className="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                    Rendered HTML Output
                  </span>
                  <span className="text-[11px] text-slate-300">•</span>
                  <span className="text-blue-600 text-xs font-medium">
                    Height Auto-matches
                  </span>
                </div>

                {/* Viewport Simulation Controls */}
                <div className="flex items-center gap-1 bg-slate-100 p-1 rounded-lg text-xs self-start sm:self-auto">
                  <button
                    type="button"
                    onClick={() => setPreviewDevice("responsive")}
                    className={`px-2.5 py-1 rounded-md text-xs font-medium transition-colors cursor-pointer flex items-center gap-1.5 ${
                      previewDevice === "responsive"
                        ? "bg-white text-blue-600 shadow-2xs font-semibold"
                        : "text-slate-600 hover:text-slate-900"
                    }`}
                    title="Responsive auto width (400px on mobile, 600px on desktop)"
                  >
                    <span>Auto (Responsive)</span>
                  </button>
                  <button
                    type="button"
                    onClick={() => setPreviewDevice("mobile")}
                    className={`px-2 py-1 rounded-md text-xs font-medium transition-colors cursor-pointer flex items-center gap-1 ${
                      previewDevice === "mobile"
                        ? "bg-white text-blue-600 shadow-2xs font-semibold"
                        : "text-slate-600 hover:text-slate-900"
                    }`}
                    title="Preview mobile width (400px)"
                  >
                    <Smartphone className="w-3.5 h-3.5" />
                    <span>Mobile (400px)</span>
                  </button>
                  <button
                    type="button"
                    onClick={() => setPreviewDevice("desktop")}
                    className={`px-2 py-1 rounded-md text-xs font-medium transition-colors cursor-pointer flex items-center gap-1 ${
                      previewDevice === "desktop"
                        ? "bg-white text-blue-600 shadow-2xs font-semibold"
                        : "text-slate-600 hover:text-slate-900"
                    }`}
                    title="Preview desktop width (600px)"
                  >
                    <Monitor className="w-3.5 h-3.5" />
                    <span>Desktop (600px)</span>
                  </button>
                </div>
              </div>

              {/* Render the buttons: Mobile 400px, Desktop 600px, Height auto-match */}
              <div className="flex flex-col items-center gap-3.5 w-full my-3">
                {buttonsData.map((btn) => {
                  const widthClass =
                    previewDevice === "mobile"
                      ? "w-[400px] max-w-full"
                      : previewDevice === "desktop"
                      ? "w-[600px] max-w-full"
                      : "w-[400px] md:w-[600px] max-w-full";

                  if (btn.isFilled) {
                    return (
                      <a
                        key={btn.id}
                        href={btn.url}
                        target={openInNewTab ? "_blank" : undefined}
                        rel={openInNewTab ? "noopener noreferrer" : undefined}
                        className={`${widthClass} h-auto min-h-[48px] inline-flex items-center justify-center px-6 py-3 rounded-lg text-sm sm:text-base font-semibold transition-all duration-200 cursor-pointer shadow-sm hover:shadow-md hover:-translate-y-0.5 active:scale-95 select-none text-center`}
                        style={{
                          backgroundColor: "#2563eb",
                          color: "#ffffff",
                          border: "1.5px solid #2563eb",
                          boxShadow: "0 2px 5px rgba(37, 99, 235, 0.25)",
                        }}
                      >
                        <span>{btn.label}</span>
                      </a>
                    );
                  } else {
                    return (
                      <a
                        key={btn.id}
                        href={btn.url}
                        target={openInNewTab ? "_blank" : undefined}
                        rel={openInNewTab ? "noopener noreferrer" : undefined}
                        className={`${widthClass} h-auto min-h-[48px] inline-flex items-center justify-center px-6 py-3 rounded-lg text-sm sm:text-base font-semibold transition-all duration-200 cursor-pointer hover:shadow-xs hover:-translate-y-0.5 active:scale-95 select-none text-center`}
                        style={{
                          backgroundColor: "rgba(37, 99, 235, 0.08)",
                          color: "#2563eb",
                          border: "1.5px solid #2563eb",
                        }}
                      >
                        <span>{btn.label}</span>
                      </a>
                    );
                  }
                })}

                {sessionEndText && (
                  <div
                    className="mt-3 text-base font-bold tracking-wide text-red-600 select-none text-center"
                    style={{ color: "#dc2626" }}
                  >
                    {sessionEndText}
                  </div>
                )}
              </div>

              <div className="mt-4 pt-3 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 text-xs text-slate-500">
                <div className="flex flex-wrap items-center gap-3">
                  <span className="flex items-center gap-1">
                    <span className="w-2.5 h-2.5 rounded-full bg-blue-600 inline-block"></span>
                    1st: Solid Filled Blue
                  </span>
                  <span className="flex items-center gap-1">
                    <span className="w-2.5 h-2.5 rounded-full bg-blue-100 border border-blue-600 inline-block"></span>
                    2nd: Outlined + Low Fill Opacity
                  </span>
                  <span className="text-slate-300">|</span>
                  <span className="font-medium text-slate-700">
                    Mobile: 400px • Desktop: 600px • Height: Auto
                  </span>
                </div>
                <button
                  onClick={copyHtml}
                  className="text-blue-600 hover:text-blue-800 font-semibold cursor-pointer flex items-center gap-1 shrink-0"
                >
                  <Copy className="w-3 h-3" />
                  <span>Copy HTML</span>
                </button>
              </div>
            </div>
          )}

          {/* Tab 2: Copyable HTML Source Code */}
          {activeTab === "code" && (
            <div className="relative rounded-xl overflow-hidden border border-slate-800 bg-slate-950 text-slate-200 font-mono text-xs">
              <div className="flex items-center justify-between px-3.5 py-2 bg-slate-900 border-b border-slate-800 text-slate-400 text-[11px]">
                <span>HTML Code (Ready to paste)</span>
                <button
                  onClick={copyHtml}
                  className="text-white hover:text-blue-400 font-sans font-semibold cursor-pointer flex items-center gap-1 transition-colors"
                >
                  {copied ? (
                    <>
                      <Check className="w-3 h-3 text-emerald-400" />
                      <span className="text-emerald-400">Copied!</span>
                    </>
                  ) : (
                    <>
                      <Copy className="w-3 h-3" />
                      <span>Copy Code</span>
                    </>
                  )}
                </button>
              </div>
              <pre className="p-3.5 overflow-x-auto max-h-72 leading-relaxed text-[11px] select-all">
                <code>{generatedHtml}</code>
              </pre>
            </div>
          )}
        </>
      )}
    </div>
  );
};
