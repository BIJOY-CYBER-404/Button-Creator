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
  const [marginSide, setMarginSide] = useState<number>(20);
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
    const totalMargin = marginSide * 2;
    const baseStyle = `width: calc(100% - ${totalMargin}px); max-width: 440px; margin-left: ${marginSide}px; margin-right: ${marginSide}px; height: auto; min-height: 48px; box-sizing: border-box; display: inline-flex; align-items: center; justify-content: center; padding: 13px 24px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 15px; font-weight: 600; text-align: center; text-decoration: none; border-radius: 8px; transition: all 0.2s ease; cursor: pointer; user-select: none; line-height: 1.35;`;

    const buttonsHtml = buttonsData
      .map((btn) => {
        if (btn.isFilled) {
          // First button: Filled Blue (Mobile width, 20px left-right margin, Height: Auto, no icon)
          return `  <a href="${btn.url}"${targetAttr} class="ep-btn ep-btn-filled" style="${baseStyle} background-color: #2563eb; color: #ffffff; border: 1.5px solid #2563eb; box-shadow: 0 2px 5px rgba(37, 99, 235, 0.25);">${btn.label}</a>`;
        } else {
          // Second button: Outlined Blue with low filled color opacity (Mobile width, 20px left-right margin, Height: Auto, no icon)
          return `  <a href="${btn.url}"${targetAttr} class="ep-btn ep-btn-outlined" style="${baseStyle} background-color: rgba(37, 99, 235, 0.08); color: #2563eb; border: 1.5px solid #2563eb;">${btn.label}</a>`;
        }
      })
      .join("\n");

    const sessionEndHtml = sessionEndText
      ? `\n  <div class="ep-session-end" style="margin-top: 14px; margin-left: ${marginSide}px; margin-right: ${marginSide}px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 15px; font-weight: 700; color: #dc2626; text-align: center; letter-spacing: 0.5px;">${sessionEndText}</div>`
      : "";

    if (includeHoverStyle) {
      return `<!-- Episode Buttons (Mobile Width, ${marginSide}px Left-Right Margin, Height: Auto, Blue Combo: Alternating Filled & Outlined) -->
<style>
  .episodes-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
    width: 100%;
    margin: 16px auto;
    box-sizing: border-box;
  }
  .ep-btn {
    width: calc(100% - ${totalMargin}px);
    max-width: 440px;
    margin-left: ${marginSide}px;
    margin-right: ${marginSide}px;
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
  .ep-btn-filled {
    background-color: #2563eb;
    color: #ffffff !important;
    border: 1.5px solid #2563eb;
    box-shadow: 0 2px 5px rgba(37, 99, 235, 0.25);
  }
  .ep-btn-filled:hover {
    background-color: #1d4ed8 !important;
    border-color: #1d4ed8 !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(37, 99, 235, 0.35) !important;
  }
  .ep-btn-outlined {
    background-color: rgba(37, 99, 235, 0.08);
    color: #2563eb !important;
    border: 1.5px solid #2563eb;
  }
  .ep-btn-outlined:hover {
    background-color: rgba(37, 99, 235, 0.16) !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(37, 99, 235, 0.15) !important;
  }
  .ep-session-end {
    margin-top: 14px;
    margin-left: ${marginSide}px;
    margin-right: ${marginSide}px;
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

    return `<!-- Episode Buttons (Mobile Width, ${marginSide}px Left-Right Margin, Height: Auto, Pure HTML) -->
<div style="display: flex; flex-direction: column; align-items: center; gap: 14px; width: 100%; margin: 16px auto; box-sizing: border-box;">
${buttonsHtml}${sessionEndHtml}
</div>`;
  }, [buttonsData, openInNewTab, includeHoverStyle, sessionEndText, marginSide]);

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
      className="w-full bg-[#fdfcff] rounded-[28px] p-5 sm:p-7 border border-[#e1e7f0] m3-elevation-1 mb-4 transition-all"
    >
      {/* Top Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-[#f0f4f9]">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-2xl bg-[#d3e3fd] text-[#041e49] flex items-center justify-center shrink-0">
            <Sparkles className="w-5 h-5 text-[#0b57d0]" />
          </div>
          <div>
            <div className="flex items-center gap-2">
              <h2 className="text-base sm:text-lg font-semibold text-[#1f1f1f] tracking-tight">
                Episode Buttons HTML Generator
              </h2>
              <span className="px-2.5 py-0.5 rounded-full bg-[#c2e7ff] text-[#001d35] text-[11px] font-medium">
                Mobile Width • {marginSide}px Margin
              </span>
            </div>
            <p className="text-xs text-[#444746] mt-0.5">
              Mobile width with {marginSide}px margin L/R • Auto height • Alternating filled &amp; outlined • Red &ldquo;- Session End -&rdquo; footer
            </p>
          </div>
        </div>

        <div className="flex items-center gap-2 shrink-0">
          <button
            id="btn-copy-generated-html"
            onClick={copyHtml}
            className="h-10 px-5 bg-[#0b57d0] hover:bg-[#0842a0] text-white text-xs font-medium rounded-full m3-elevation-1 flex items-center gap-2 cursor-pointer transition-all active:scale-95"
          >
            {copied ? (
              <>
                <Check className="w-4 h-4" />
                <span>HTML Copied!</span>
              </>
            ) : (
              <>
                <Copy className="w-4 h-4" />
                <span>Copy HTML</span>
              </>
            )}
          </button>
          <button
            onClick={downloadHtml}
            className="h-10 px-4 bg-[#f0f4f9] hover:bg-[#e9eef6] text-[#444746] text-xs font-medium rounded-full flex items-center gap-1.5 cursor-pointer transition-colors"
            title="Download HTML snippet"
          >
            <Download className="w-4 h-4 text-[#444746]" />
            <span className="hidden sm:inline">Download</span>
          </button>
          <button
            onClick={() => setIsCollapsed(!isCollapsed)}
            className="p-2 text-[#444746] hover:text-[#1f1f1f] hover:bg-[#f0f4f9] rounded-full cursor-pointer transition-colors"
            title={isCollapsed ? "Expand generator" : "Collapse generator"}
          >
            {isCollapsed ? (
              <ChevronDown className="w-5 h-5" />
            ) : (
              <ChevronUp className="w-5 h-5" />
            )}
          </button>
        </div>
      </div>

      {!isCollapsed && (
        <>
          {/* Controls & Configuration Bar */}
          <div className="flex flex-wrap items-center gap-3.5 pt-4 pb-4 text-xs text-[#444746]">
            <div className="flex items-center gap-1.5 bg-[#f8fafd] px-3 py-1.5 rounded-full border border-[#e1e7f0]">
              <span className="text-[#747775] font-medium">Prefix:</span>
              <input
                type="text"
                value={prefix}
                onChange={(e) => setPrefix(e.target.value)}
                placeholder="Episode"
                className="w-20 px-1 bg-transparent text-xs font-medium text-[#1f1f1f] outline-none"
              />
            </div>

            <div className="flex items-center gap-1.5 bg-[#f8fafd] px-3 py-1.5 rounded-full border border-[#e1e7f0]">
              <span className="text-[#747775] font-medium">Start #:</span>
              <input
                type="number"
                min={0}
                value={startNumber}
                onChange={(e) => setStartNumber(parseInt(e.target.value, 10) || 0)}
                className="w-12 px-1 bg-transparent text-xs font-medium text-[#1f1f1f] outline-none"
              />
            </div>

            <div className="flex items-center gap-1.5 bg-[#f8fafd] px-3 py-1.5 rounded-full border border-[#e1e7f0]">
              <span className="text-[#747775] font-medium">Margin:</span>
              <input
                type="number"
                min={0}
                max={80}
                value={marginSide}
                onChange={(e) => setMarginSide(Math.max(0, parseInt(e.target.value, 10) || 0))}
                className="w-12 px-1 bg-transparent text-xs font-medium text-[#1f1f1f] outline-none"
              />
              <span className="text-[11px] text-[#747775]">px</span>
            </div>

            <label className="flex items-center gap-2 cursor-pointer select-none bg-[#f8fafd] px-3 py-1.5 rounded-full border border-[#e1e7f0]">
              <input
                type="checkbox"
                checked={padZeroes}
                onChange={(e) => setPadZeroes(e.target.checked)}
                className="rounded border-[#747775] text-[#0b57d0] focus:ring-0 w-3.5 h-3.5 accent-[#0b57d0] cursor-pointer"
              />
              <span className="text-[#444746]">Two-digit (01, 02)</span>
            </label>

            <label className="flex items-center gap-2 cursor-pointer select-none bg-[#f8fafd] px-3 py-1.5 rounded-full border border-[#e1e7f0]">
              <input
                type="checkbox"
                checked={openInNewTab}
                onChange={(e) => setOpenInNewTab(e.target.checked)}
                className="rounded border-[#747775] text-[#0b57d0] focus:ring-0 w-3.5 h-3.5 accent-[#0b57d0] cursor-pointer"
              />
              <span className="text-[#444746]">New tab (_blank)</span>
            </label>

            <label className="flex items-center gap-2 cursor-pointer select-none bg-[#f8fafd] px-3 py-1.5 rounded-full border border-[#e1e7f0]">
              <input
                type="checkbox"
                checked={includeHoverStyle}
                onChange={(e) => setIncludeHoverStyle(e.target.checked)}
                className="rounded border-[#747775] text-[#0b57d0] focus:ring-0 w-3.5 h-3.5 accent-[#0b57d0] cursor-pointer"
              />
              <span className="text-[#444746]">&lt;style&gt; hover FX</span>
            </label>

            <div className="flex items-center gap-1.5 bg-[#f8fafd] px-3 py-1.5 rounded-full border border-[#e1e7f0]">
              <span className="text-[#747775] font-medium">End:</span>
              <input
                type="text"
                value={sessionEndText}
                onChange={(e) => setSessionEndText(e.target.value)}
                placeholder="- Session End -"
                className="w-28 px-1 bg-transparent text-xs font-semibold text-[#ba1a1a] outline-none"
              />
            </div>
          </div>

          {/* View Mode Switcher (Visual Preview vs HTML Code) */}
          <div className="flex items-center justify-between gap-3 mb-3">
            <div className="inline-flex rounded-full bg-[#f0f4f9] p-1 text-xs font-medium border border-[#e1e7f0]">
              <button
                onClick={() => setActiveTab("preview")}
                className={`px-3.5 py-1 rounded-full transition-all flex items-center gap-1.5 cursor-pointer ${
                  activeTab === "preview"
                    ? "bg-[#0b57d0] text-white shadow-xs font-medium"
                    : "text-[#444746] hover:text-[#1f1f1f]"
                }`}
              >
                <Eye className="w-3.5 h-3.5" />
                <span>Live Preview</span>
              </button>
              <button
                onClick={() => setActiveTab("code")}
                className={`px-3.5 py-1 rounded-full transition-all flex items-center gap-1.5 cursor-pointer ${
                  activeTab === "code"
                    ? "bg-[#0b57d0] text-white shadow-xs font-medium"
                    : "text-[#444746] hover:text-[#1f1f1f]"
                }`}
              >
                <Code className="w-3.5 h-3.5" />
                <span>HTML Code ({buttonsData.length})</span>
              </button>
            </div>

            <span className="text-xs text-[#747775] font-medium">
              {buttonsData.length} button{buttonsData.length === 1 ? "" : "s"} generated
            </span>
          </div>

          {/* Tab 1: Live Interactive Preview */}
          {activeTab === "preview" && (
            <div className="bg-[#f8fafd] rounded-2xl p-5 sm:p-6 border border-[#e1e7f0]">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 mb-4 border-b border-[#e1e7f0]">
                <div className="flex items-center gap-2">
                  <span className="text-xs font-semibold text-[#444746] uppercase tracking-wider">
                    Rendered Output
                  </span>
                  <span className="text-[#c4c7c5]">•</span>
                  <span className="text-[#0b57d0] text-xs font-medium">
                    Mobile Width ({marginSide}px Margin L/R)
                  </span>
                </div>

                {/* Viewport Simulation Controls */}
                <div className="flex items-center gap-1 bg-[#e9eef6] p-1 rounded-full text-xs self-start sm:self-auto">
                  <button
                    type="button"
                    onClick={() => setPreviewDevice("responsive")}
                    className={`px-3 py-1 rounded-full text-xs font-medium transition-colors cursor-pointer flex items-center gap-1.5 ${
                      previewDevice === "responsive"
                        ? "bg-white text-[#0b57d0] shadow-2xs font-semibold"
                        : "text-[#444746] hover:text-[#1f1f1f]"
                    }`}
                    title="Responsive width with left-right margins"
                  >
                    <span>Auto</span>
                  </button>
                  <button
                    type="button"
                    onClick={() => setPreviewDevice("mobile")}
                    className={`px-3 py-1 rounded-full text-xs font-medium transition-colors cursor-pointer flex items-center gap-1 ${
                      previewDevice === "mobile"
                        ? "bg-white text-[#0b57d0] shadow-2xs font-semibold"
                        : "text-[#444746] hover:text-[#1f1f1f]"
                    }`}
                    title="Preview in 390px mobile viewport"
                  >
                    <Smartphone className="w-3.5 h-3.5" />
                    <span>Mobile (390px)</span>
                  </button>
                  <button
                    type="button"
                    onClick={() => setPreviewDevice("desktop")}
                    className={`px-3 py-1 rounded-full text-xs font-medium transition-colors cursor-pointer flex items-center gap-1 ${
                      previewDevice === "desktop"
                        ? "bg-white text-[#0b57d0] shadow-2xs font-semibold"
                        : "text-[#444746] hover:text-[#1f1f1f]"
                    }`}
                    title="Preview on desktop"
                  >
                    <Monitor className="w-3.5 h-3.5" />
                    <span>Desktop</span>
                  </button>
                </div>
              </div>

              {/* Render the buttons: Mobile width with 20px margin from left-right, Height auto-match */}
              <div className="w-full my-3 flex justify-center">
                <div
                  className={`w-full transition-all duration-200 ${
                    previewDevice === "mobile"
                      ? "max-w-[390px] bg-white border border-[#c4c7c5] rounded-3xl p-4 shadow-sm"
                      : "max-w-2xl"
                  }`}
                >
                  {previewDevice === "mobile" && (
                    <div className="text-[11px] font-medium text-[#747775] pb-2 mb-3 border-b border-[#e1e7f0] flex items-center justify-between px-1 select-none">
                      <span className="text-[#0b57d0] font-mono">|← {marginSide}px</span>
                      <span className="font-semibold text-[#1f1f1f]">390px Viewport</span>
                      <span className="text-[#0b57d0] font-mono">{marginSide}px →|</span>
                    </div>
                  )}

                  <div className="flex flex-col items-center gap-3.5 w-full">
                    {buttonsData.map((btn) => {
                      const buttonStyle: React.CSSProperties = {
                        width: `calc(100% - ${marginSide * 2}px)`,
                        maxWidth: "440px",
                        marginLeft: `${marginSide}px`,
                        marginRight: `${marginSide}px`,
                        boxSizing: "border-box",
                      };

                      if (btn.isFilled) {
                        return (
                          <a
                            key={btn.id}
                            href={btn.url}
                            target={openInNewTab ? "_blank" : undefined}
                            rel={openInNewTab ? "noopener noreferrer" : undefined}
                            className="h-auto min-h-[48px] inline-flex items-center justify-center px-6 py-3 rounded-xl text-sm sm:text-base font-semibold transition-all duration-200 cursor-pointer shadow-sm hover:shadow-md hover:-translate-y-0.5 active:scale-95 select-none text-center"
                            style={{
                              ...buttonStyle,
                              backgroundColor: "#0b57d0",
                              color: "#ffffff",
                              border: "1.5px solid #0b57d0",
                              boxShadow: "0 2px 5px rgba(11, 87, 208, 0.25)",
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
                            className="h-auto min-h-[48px] inline-flex items-center justify-center px-6 py-3 rounded-xl text-sm sm:text-base font-semibold transition-all duration-200 cursor-pointer hover:shadow-xs hover:-translate-y-0.5 active:scale-95 select-none text-center"
                            style={{
                              ...buttonStyle,
                              backgroundColor: "rgba(11, 87, 208, 0.08)",
                              color: "#0b57d0",
                              border: "1.5px solid #0b57d0",
                            }}
                          >
                            <span>{btn.label}</span>
                          </a>
                        );
                      }
                    })}

                    {sessionEndText && (
                      <div
                        className="mt-3 text-base font-bold tracking-wide text-[#ba1a1a] select-none text-center"
                        style={{
                          color: "#ba1a1a",
                          marginLeft: `${marginSide}px`,
                          marginRight: `${marginSide}px`,
                        }}
                      >
                        {sessionEndText}
                      </div>
                    )}
                  </div>
                </div>
              </div>

              <div className="mt-4 pt-3 border-t border-[#e1e7f0] flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 text-xs text-[#747775]">
                <div className="flex flex-wrap items-center gap-3">
                  <span className="flex items-center gap-1.5">
                    <span className="w-2.5 h-2.5 rounded-full bg-[#0b57d0] inline-block"></span>
                    1st: Solid Filled Blue
                  </span>
                  <span className="flex items-center gap-1.5">
                    <span className="w-2.5 h-2.5 rounded-full bg-[#d3e3fd] border border-[#0b57d0] inline-block"></span>
                    2nd: Outlined Low Fill
                  </span>
                  <span className="text-[#c4c7c5]">|</span>
                  <span className="font-medium text-[#444746]">
                    Margin: {marginSide}px L/R • Height: Auto
                  </span>
                </div>
                <button
                  onClick={copyHtml}
                  className="text-[#0b57d0] hover:text-[#0842a0] font-medium cursor-pointer flex items-center gap-1 shrink-0"
                >
                  <Copy className="w-3.5 h-3.5" />
                  <span>Copy HTML</span>
                </button>
              </div>
            </div>
          )}

          {/* Tab 2: Copyable HTML Source Code */}
          {activeTab === "code" && (
            <div className="relative rounded-2xl overflow-hidden border border-[#303030] bg-[#1e1f20] text-[#e3e3e3] font-mono text-xs">
              <div className="flex items-center justify-between px-4 py-2.5 bg-[#2a2b2c] border-b border-[#3c3c3c] text-[#c4c7c5] text-xs">
                <span>HTML Code (Ready to paste)</span>
                <button
                  onClick={copyHtml}
                  className="text-white hover:text-[#a8c7fa] font-sans font-medium cursor-pointer flex items-center gap-1 transition-colors"
                >
                  {copied ? (
                    <>
                      <Check className="w-3.5 h-3.5 text-[#6dd58c]" />
                      <span className="text-[#6dd58c]">Copied!</span>
                    </>
                  ) : (
                    <>
                      <Copy className="w-3.5 h-3.5" />
                      <span>Copy Code</span>
                    </>
                  )}
                </button>
              </div>
              <pre className="p-4 overflow-x-auto max-h-72 leading-relaxed text-[11px] select-all">
                <code>{generatedHtml}</code>
              </pre>
            </div>
          )}
        </>
      )}
    </div>
  );
};
