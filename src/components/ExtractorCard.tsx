import React, { useState } from "react";
import {
  Copy,
  Download,
  Check,
  ExternalLink,
  Loader2,
  SlidersHorizontal,
  FileCode,
  RotateCcw,
} from "lucide-react";
import { ExtractedItem, ExtractionResponse } from "../types";
import { parseHTMLClientSide, isBlockedTestLink } from "../utils/pythonExtractor";
import { safeFetchJson } from "../utils/safeFetch";
import { SAMPLES } from "../data/samples";
import { ArrowUpRight } from "lucide-react";
import { EpisodeButtonsGenerator } from "./EpisodeButtonsGenerator";

interface ExtractorCardProps {
  onNotify: (text: string, type?: "success" | "error" | "info") => void;
  onOpenPythonLogic?: () => void;
  onNavigateToResolver?: (url?: string) => void;
  initialUrl?: string;
}

export const ExtractorCard: React.FC<ExtractorCardProps> = ({
  onNotify,
  onOpenPythonLogic,
  onNavigateToResolver,
  initialUrl,
}) => {
  const [urlInput, setUrlInput] = useState<string>(initialUrl || "");
  const [htmlInput, setHtmlInput] = useState<string>("");
  const [activeMode, setActiveMode] = useState<"url" | "html">("url");
  const [buttonOnly, setButtonOnly] = useState<boolean>(true);
  const [loading, setLoading] = useState<boolean>(false);
  const [statusMessage, setStatusMessage] = useState<string | null>(null);
  const [isError, setIsError] = useState<boolean>(false);
  const [items, setItems] = useState<ExtractedItem[]>([]);
  const [copiedIndex, setCopiedIndex] = useState<number | null>(null);
  const [copiedAll, setCopiedAll] = useState<boolean>(false);

  const handleScan = async (overrideUrl?: string, overrideHtml?: string) => {
    const targetUrl = (overrideUrl !== undefined ? overrideUrl : urlInput).trim();
    const targetHtml = overrideHtml !== undefined ? overrideHtml : htmlInput;

    if (activeMode === "url" && !overrideHtml) {
      if (!/^https?:\/\//i.test(targetUrl)) {
        setStatusMessage("Please enter a valid http:// or https:// URL.");
        setIsError(true);
        onNotify("Enter a valid http:// or https:// URL", "error");
        return;
      }
    } else {
      if (!targetHtml.trim()) {
        setStatusMessage("Please paste HTML content to extract from.");
        setIsError(true);
        onNotify("HTML content is empty", "error");
        return;
      }
    }

    setLoading(true);
    setIsError(false);
    setStatusMessage("Running Python extraction engine...");

    try {
      // 1. First attempt: call backend Python API (/api/extract)
      const fetchRes = await safeFetchJson<ExtractionResponse>("/api/extract", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          url: activeMode === "url" && !overrideHtml ? targetUrl : undefined,
          html: activeMode === "html" || overrideHtml ? targetHtml : undefined,
          base_url: targetUrl || "https://example.com/page",
          button_only: buttonOnly,
        }),
      });

      if (fetchRes.ok && fetchRes.data?.success && fetchRes.data.items) {
        const data = fetchRes.data;
        const filtered = data.items.filter(
          (it) => !isBlockedTestLink(it.text, it.url)
        );
        setItems(filtered);
        setStatusMessage(
          `Done (Python 3.10 standard library). Final URL: ${
            data.final_url || targetUrl
          } (${data.bytes || 0} bytes parsed)`
        );
        onNotify(
          `Found ${filtered.length} action link${
            filtered.length === 1 ? "" : "s"
          }`,
          "success"
        );
        setLoading(false);
        return;
      } else if (fetchRes.error || fetchRes.data?.error) {
        throw new Error(fetchRes.error || fetchRes.data?.error);
      }
      throw new Error(`Server returned status ${fetchRes.status}`);
    } catch (apiErr: any) {
      // 2. Fallback: If network error (e.g. target website blocks server IP, CORS, or local HTML mode)
      console.warn("Backend Python fetch failed, trying client-side Python logic mirror:", apiErr);

      if (activeMode === "html" || targetHtml) {
        try {
          const parsed = parseHTMLClientSide(
            targetHtml,
            targetUrl || "https://example.com/page"
          );
          setItems(parsed);
          setStatusMessage(
            `Parsed ${parsed.length} action links via client Python parser.`
          );
          setIsError(false);
          onNotify(`Extracted ${parsed.length} items`, "success");
        } catch (e: any) {
          setStatusMessage(`Extraction error: ${e.message}`);
          setIsError(true);
          onNotify(e.message, "error");
        }
      } else {
        setStatusMessage(
          `Python engine notice: ${apiErr.message}. You can also test with Sample Presets or paste HTML directly.`
        );
        setIsError(true);
        onNotify(apiErr.message, "error");
      }
    } finally {
      setLoading(false);
    }
  };

  const loadSample = (sample: (typeof SAMPLES)[0]) => {
    if (sample.html) {
      setActiveMode("html");
      setHtmlInput(sample.html);
      setUrlInput(sample.url);
      handleScan(sample.url, sample.html);
    } else {
      setActiveMode("url");
      setUrlInput(sample.url);
      handleScan(sample.url, undefined);
    }
    onNotify(`Loaded sample: ${sample.name}`, "info");
  };

  const copyUrl = async (url: string, index: number) => {
    try {
      await navigator.clipboard.writeText(url);
      setCopiedIndex(index);
      onNotify("URL copied to clipboard", "success");
      setTimeout(() => setCopiedIndex(null), 1200);
    } catch {
      onNotify("Clipboard copy failed", "error");
    }
  };

  const copyAll = async () => {
    if (!items.length) return;
    const text = items.map((x, i) => `[${i + 1}] ${x.url}`).join("\n");
    try {
      await navigator.clipboard.writeText(text);
      setCopiedAll(true);
      onNotify(`Copied all ${items.length} URLs`, "success");
      setTimeout(() => setCopiedAll(false), 1500);
    } catch {
      onNotify("Clipboard copy failed", "error");
    }
  };

  const downloadTxt = () => {
    if (!items.length) return;
    const text = items
      .map(
        (x, i) =>
          `[${i + 1}] ${x.type.toUpperCase()}\nText: ${x.text}\nURL: ${
            x.url
          }\n${"-".repeat(60)}\n`
      )
      .join("\n");

    const blob = new Blob([text], { type: "text/plain;charset=utf-8" });
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = "extracted_button_links.txt";
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(a.href);
    onNotify("Downloaded extracted_button_links.txt", "success");
  };

  const clearAll = () => {
    setUrlInput("");
    setHtmlInput("");
    setItems([]);
    setStatusMessage(null);
    setIsError(false);
  };

  return (
    <div className="w-full max-w-full space-y-3 sm:space-y-4 overflow-x-hidden">
      {/* Top Cross-Page Link Banner (linking pages together) */}
      {onNavigateToResolver && (
        <div className="w-full bg-slate-900 text-white rounded-xl p-3 sm:p-3.5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2.5 shadow-sm border border-slate-800">
          <div className="flex items-center gap-2.5 min-w-0">
            <div className="w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0 border border-emerald-500/30">
              <ArrowUpRight className="w-4 h-4" />
            </div>
            <div className="min-w-0">
              <div className="font-semibold text-xs sm:text-sm text-white truncate">
                Connected Tool: Short URL Resolver
              </div>
              <p className="text-[10px] sm:text-xs text-slate-400 truncate">
                Trace redirect chains & inspect destination URLs
              </p>
            </div>
          </div>
          <button
            id="btn-goto-resolver"
            onClick={() => onNavigateToResolver(urlInput || undefined)}
            className="w-full sm:w-auto px-3 py-1.5 bg-white hover:bg-slate-100 text-slate-900 rounded-lg text-xs font-semibold flex items-center justify-center gap-1.5 transition-all cursor-pointer shrink-0 shadow-xs active:scale-95"
          >
            <span>Open URL Resolver</span>
            <ArrowUpRight className="w-3.5 h-3.5" />
          </button>
        </div>
      )}

      {/* Main Extractor Card (Styled after the user's provided HTML design) */}
      <div
        id="extractor-main-card"
        className="w-full max-w-full bg-white rounded-[14px] p-4 sm:p-6 shadow-[0_8px_30px_rgba(0,0,0,0.08)] border border-slate-100 transition-all overflow-x-hidden"
      >
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-2">
          <h1 className="text-xl sm:text-[26px] font-bold tracking-tight text-[#1d2329] break-words">
            Webpage Button Link Extractor
          </h1>
          <div className="flex items-center gap-2">
            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 border border-emerald-200 text-[11px] font-semibold text-emerald-700 uppercase tracking-wide shrink-0">
              Python 3.10 Engine
            </span>
          </div>
        </div>

        <p className="text-xs sm:text-sm text-[#69737d] mb-4 sm:mb-5 leading-relaxed break-words">
          Button-only mode: ignores common header, footer, navigation, menu and
          sidebar links. Extracts download, watch, stream, mirror, and custom
          action buttons.
        </p>

        {/* Input Mode Switcher (URL vs Raw HTML) */}
        <div className="flex flex-wrap items-center justify-between gap-2 mb-3">
          <div className="inline-flex rounded-lg bg-[#edf0f2] p-0.5 text-xs font-medium">
            <button
              onClick={() => setActiveMode("url")}
              className={`px-3 py-1 rounded-md transition-all cursor-pointer ${
                activeMode === "url"
                  ? "bg-white text-slate-900 shadow-xs font-semibold"
                  : "text-slate-600 hover:text-slate-900"
              }`}
            >
              Webpage URL
            </button>
            <button
              onClick={() => setActiveMode("html")}
              className={`px-3 py-1 rounded-md transition-all cursor-pointer ${
                activeMode === "html"
                  ? "bg-white text-slate-900 shadow-xs font-semibold"
                  : "text-slate-600 hover:text-slate-900"
              }`}
            >
              Paste HTML Source
            </button>
          </div>

          <label className="flex items-center gap-1.5 text-xs text-slate-600 cursor-pointer select-none">
            <input
              type="checkbox"
              checked={buttonOnly}
              onChange={(e) => setButtonOnly(e.target.checked)}
              className="rounded text-slate-900 focus:ring-0 w-3.5 h-3.5 accent-slate-900 cursor-pointer"
            />
            <span>Button-only filter</span>
          </label>
        </div>

        {/* URL Input Form */}
        {activeMode === "url" ? (
          <div className="flex flex-col sm:flex-row gap-2.5 w-full min-w-0">
            <input
              id="urlInput"
              type="url"
              value={urlInput}
              onChange={(e) => setUrlInput(e.target.value)}
              onKeyDown={(e) => e.key === "Enter" && handleScan()}
              placeholder="https://example.com/page"
              autoComplete="off"
              className="w-full min-w-0 flex-1 px-3.5 py-3 border border-[#ccd3d9] rounded-[9px] outline-none text-sm sm:text-[15px] focus:border-[#111] transition-colors placeholder:text-slate-400"
            />
            <button
              id="scanBtn"
              onClick={() => handleScan()}
              disabled={loading}
              className="w-full sm:w-auto bg-[#111] hover:bg-black text-white font-medium px-5 py-3 rounded-[9px] text-sm cursor-pointer disabled:opacity-55 disabled:cursor-not-allowed flex items-center justify-center gap-2 shrink-0 transition-all active:scale-[0.98]"
            >
              {loading ? (
                <>
                  <Loader2 className="w-4 h-4 animate-spin" />
                  Scanning…
                </>
              ) : (
                "Extract Links"
              )}
            </button>
          </div>
        ) : (
          <div className="space-y-2.5 w-full min-w-0">
            <div className="w-full">
              <input
                type="url"
                value={urlInput}
                onChange={(e) => setUrlInput(e.target.value)}
                placeholder="Optional Base URL (e.g. https://example.com)"
                className="w-full px-3 py-2 border border-[#ccd3d9] rounded-[9px] text-xs outline-none focus:border-[#111]"
              />
            </div>
            <textarea
              value={htmlInput}
              onChange={(e) => setHtmlInput(e.target.value)}
              rows={5}
              placeholder="Paste raw HTML markup here (or choose a preset above)..."
              className="w-full p-3 font-mono text-xs border border-[#ccd3d9] rounded-[9px] outline-none focus:border-[#111] resize-y"
            />
            <button
              onClick={() => handleScan()}
              disabled={loading}
              className="w-full bg-[#111] hover:bg-black text-white font-medium py-3 rounded-[9px] text-sm cursor-pointer disabled:opacity-55 flex items-center justify-center gap-2 transition-all active:scale-[0.98]"
            >
              {loading ? (
                <>
                  <Loader2 className="w-4 h-4 animate-spin" />
                  Parsing with Python…
                </>
              ) : (
                "Parse HTML with Python Engine"
              )}
            </button>
          </div>
        )}

        {/* Status Message Element */}
        {statusMessage && (
          <div
            id="status"
            className={`mt-3.5 px-3.5 py-2.5 rounded-[8px] text-xs sm:text-[13px] leading-snug flex items-start justify-between gap-2 break-words w-full overflow-hidden ${
              isError
                ? "bg-[#fff0f0] text-[#a40000] border border-red-200/60"
                : "bg-[#f0f3f5] text-[#4e5860] border border-slate-200/60"
            }`}
          >
            <span className="break-words min-w-0 flex-1">{statusMessage}</span>
            <button
              onClick={() => setStatusMessage(null)}
              className="text-xs opacity-60 hover:opacity-100 cursor-pointer shrink-0 ml-1"
            >
              ✕
            </button>
          </div>
        )}

        {/* Toolbar */}
        <div className="flex flex-wrap gap-2.5 items-center justify-between mt-5 mb-3 pt-4 border-t border-slate-100 w-full">
          <div id="stats" className="text-xs sm:text-sm font-medium text-[#59636c]">
            {items.length > 0
              ? `${items.length} button/action link${
                  items.length === 1 ? "" : "s"
                } found`
              : "No results"}
          </div>
          <div className="flex flex-wrap items-center gap-2">
            {items.length > 0 && (
              <button
                onClick={clearAll}
                className="px-3 py-1.5 rounded-[9px] text-xs font-medium text-slate-500 hover:text-slate-800 transition-colors cursor-pointer flex items-center gap-1"
                title="Clear results"
              >
                <RotateCcw className="w-3.5 h-3.5" /> Clear
              </button>
            )}
            <button
              id="copyAllBtn"
              onClick={copyAll}
              disabled={items.length === 0}
              className="bg-[#edf0f2] hover:bg-[#e2e6e9] text-[#222] disabled:opacity-50 disabled:cursor-not-allowed font-medium px-3 py-1.5 rounded-[9px] text-xs cursor-pointer flex items-center gap-1.5 transition-colors"
            >
              {copiedAll ? (
                <>
                  <Check className="w-3.5 h-3.5 text-emerald-600" /> Copied!
                </>
              ) : (
                <>
                  <Copy className="w-3.5 h-3.5" /> Copy All
                </>
              )}
            </button>
            <button
              id="downloadBtn"
              onClick={downloadTxt}
              disabled={items.length === 0}
              className="bg-[#edf0f2] hover:bg-[#e2e6e9] text-[#222] disabled:opacity-50 disabled:cursor-not-allowed font-medium px-3 py-1.5 rounded-[9px] text-xs cursor-pointer flex items-center gap-1.5 transition-colors"
            >
              <Download className="w-3.5 h-3.5" /> Download TXT
            </button>
          </div>
        </div>

        {/* Generated Episode Buttons (HTML) Section */}
        {items.length > 0 && (
          <EpisodeButtonsGenerator items={items} onNotify={onNotify} />
        )}

        {/* Results List - strictly vertical stacking */}
        <div id="results" className="grid gap-2.5 mt-3 w-full max-w-full">
          {items.length > 0 && (
            <div className="flex items-center justify-between px-1 text-xs font-semibold text-slate-500 uppercase tracking-wider">
              <span>Extracted Source Links ({items.length})</span>
              <span className="text-[11px] normal-case text-slate-400">Raw URLs & Element Tags</span>
            </div>
          )}
          {items.length === 0 ? (
            <div className="py-7 px-3 text-center text-[#727b83] text-xs sm:text-sm border border-dashed border-[#ccd3d9] rounded-[10px] bg-slate-50/50">
              Paste a webpage URL and click “Extract Links” or choose a preset
              above.
            </div>
          ) : (
            items.map((item, i) => (
              <div
                key={`${item.url}-${i}`}
                className="border border-[#e1e5e8] rounded-[10px] p-3 sm:p-3.5 bg-[#fbfcfd] hover:bg-white hover:border-slate-300 transition-colors w-full min-w-0 overflow-hidden"
              >
                <div className="flex items-center gap-2 mb-1.5 flex-wrap">
                  <span className="font-bold text-xs text-slate-800 shrink-0">
                    [{i + 1}]
                  </span>
                  <span className="inline-block px-2 py-0.5 rounded-full bg-[#eceff1] text-slate-700 text-[10px] font-bold uppercase tracking-wider shrink-0">
                    {item.type}
                  </span>
                  {item.tag && (
                    <span className="text-[10px] font-mono text-slate-400 shrink-0">
                      &lt;{item.tag}&gt;
                    </span>
                  )}
                  {item.id && (
                    <span className="text-[10px] font-mono text-slate-500 bg-slate-100 px-1.5 py-0.2 rounded truncate max-w-[140px]">
                      #{item.id}
                    </span>
                  )}
                </div>

                <div className="font-semibold text-slate-900 text-xs sm:text-sm mb-1.5 break-words">
                  {item.text}
                </div>

                <div className="w-full min-w-0 overflow-hidden mb-2">
                  <a
                    href={item.url}
                    target="_blank"
                    rel="noreferrer"
                    className="text-[#1261a0] hover:underline break-all text-xs font-mono inline-flex items-start gap-1 max-w-full group"
                  >
                    <span className="break-all">{item.url}</span>
                    <ExternalLink className="w-3 h-3 shrink-0 opacity-60 group-hover:opacity-100 mt-0.5" />
                  </a>
                </div>

                <div className="mt-2 flex items-center gap-2 flex-wrap">
                  <button
                    onClick={() => copyUrl(item.url, i)}
                    className="bg-[#111] hover:bg-black text-white text-xs px-3 py-1.5 rounded-[6px] font-medium cursor-pointer transition-colors flex items-center gap-1 active:scale-95"
                  >
                    {copiedIndex === i ? (
                      <>
                        <Check className="w-3 h-3 text-emerald-400" /> Copied
                      </>
                    ) : (
                      <>
                        <Copy className="w-3 h-3" /> Copy URL
                      </>
                    )}
                  </button>

                  {onNavigateToResolver && (
                    <button
                      onClick={() => onNavigateToResolver(item.url)}
                      className={`text-xs px-2.5 py-1.5 rounded-[6px] font-medium cursor-pointer transition-colors flex items-center gap-1.5 ${
                        /shrt\.|tinyurl|bit\.ly|t\.co|goo\.gl|is\.gd|cutt\.ly|rebrand\.ly|sohojgyan/i.test(
                          item.url
                        )
                          ? "bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200"
                          : "bg-slate-100 hover:bg-slate-200 text-slate-700"
                      }`}
                      title="Inspect and resolve full redirect chain in URL Resolver"
                    >
                      <ArrowUpRight className="w-3 h-3 text-current" />
                      <span>
                        {/shrt\.|tinyurl|bit\.ly|t\.co|goo\.gl|is\.gd|cutt\.ly|rebrand\.ly|sohojgyan/i.test(
                          item.url
                        )
                          ? "Resolve Shortlink"
                          : "Trace Redirects"}
                      </span>
                    </button>
                  )}
                </div>
              </div>
            ))
          )}
        </div>

        {/* Footnote */}
        <div className="mt-5 text-xs text-[#737d85] leading-relaxed border-t border-slate-100 pt-3 flex items-start gap-1.5 break-words">
          <SlidersHorizontal className="w-3.5 h-3.5 shrink-0 text-slate-400 mt-0.5" />
          <span>
            Detects normal links, buttons, onclick URLs, data-url/data-href
            attributes, forms and common button-like classes. Powered by
            Python’s standard library HTMLParser & HTTP engine.
          </span>
        </div>
      </div>
    </div>
  );
};
