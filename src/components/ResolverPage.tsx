import React, { useState } from "react";
import {
  ArrowUpRight,
  Copy,
  Check,
  RotateCcw,
  ExternalLink,
  Loader2,
  ShieldCheck,
  Route,
  ArrowRight,
  Zap,
  LockOpen,
} from "lucide-react";
import { ResolveResult, ResolveResponse } from "../types";

interface ResolverPageProps {
  onNotify: (text: string, type?: "success" | "error" | "info") => void;
  onNavigateToExtractor: (prefillUrl?: string) => void;
  initialUrl?: string;
}

const SAMPLE_SHORT_URLS = [
  { name: "Fanletter Drama Page (mydverse.com)", url: "https://mydverse.com/2026/09/fanletter-please-korean-drama-in-hindi/" },
  { name: "Fanletter Shortlink (shrt.sohojgyan)", url: "https://shrt.sohojgyan.com/Ij03ndJ" },
  { name: "Safelink (shrt.sohojgyan)", url: "https://shrt.sohojgyan.com/zEgAAfE" },
  { name: "TinyURL Demo", url: "https://tinyurl.com/2p86f345" },
  { name: "Bitly Sample", url: "https://bit.ly/3uL8x2V" },
];

export const ResolverPage: React.FC<ResolverPageProps> = ({
  onNotify,
  onNavigateToExtractor,
  initialUrl,
}) => {
  const [urlInput, setUrlInput] = useState<string>(initialUrl || "");
  const [loading, setLoading] = useState<boolean>(false);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);
  const [result, setResult] = useState<ResolveResult | null>(null);
  const [copied, setCopied] = useState<boolean>(false);
  const [copiedStepIndex, setCopiedStepIndex] = useState<number | null>(null);
  const [activePill, setActivePill] = useState<string>("Short URL Resolver");

  React.useEffect(() => {
    if (initialUrl && initialUrl.trim()) {
      setUrlInput(initialUrl.trim());
      handleResolve(initialUrl.trim());
    }
  }, [initialUrl]);

  const handleCopyStepUrl = async (url: string, stepIndex: number) => {
    try {
      await navigator.clipboard.writeText(url);
      setCopiedStepIndex(stepIndex);
      onNotify(`Step ${stepIndex} URL copied to clipboard`, "success");
      setTimeout(() => setCopiedStepIndex(null), 1500);
    } catch {
      onNotify("Clipboard copy failed", "error");
    }
  };

  const handleResolve = async (customUrl?: string) => {
    const targetUrl = (customUrl !== undefined ? customUrl : urlInput).trim();

    setErrorMsg(null);

    if (!targetUrl) {
      setErrorMsg("Please enter a shortened URL.");
      onNotify("Please enter a shortened URL", "error");
      return;
    }

    setLoading(true);

    try {
      const res = await fetch("/api/resolve", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ url: targetUrl }),
      });

      const data: ResolveResponse = await res.json();

      if (!res.ok || !data.success) {
        throw new Error(data.error || "Unable to resolve this URL.");
      }

      if (data.data) {
        setResult(data.data);
        onNotify(
          `Resolved! ${data.data.redirects} redirect${
            data.data.redirects === 1 ? "" : "s"
          } followed`,
          "success"
        );
      }
    } catch (err: any) {
      setErrorMsg(err.message || "Could not connect to the Python resolver.");
      onNotify(err.message || "Resolution error", "error");
    } finally {
      setLoading(false);
    }
  };

  const handleCopy = async () => {
    if (!result?.final) return;
    try {
      await navigator.clipboard.writeText(result.final);
      setCopied(true);
      onNotify("Final URL copied to clipboard", "success");
      setTimeout(() => setCopied(false), 1500);
    } catch {
      onNotify("Clipboard copy failed", "error");
    }
  };

  const handleReset = () => {
    setUrlInput("");
    setResult(null);
    setErrorMsg(null);
  };

  return (
    <div className="w-full max-w-full space-y-3 sm:space-y-4 overflow-x-hidden">
      {/* Top Cross-Page Link Banner (linking pages together) */}
      <div className="w-full bg-slate-900 text-white rounded-xl p-3 sm:p-3.5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2.5 shadow-sm border border-slate-800">
        <div className="flex items-center gap-2.5 min-w-0">
          <div className="w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0 border border-emerald-500/30">
            <Route className="w-4 h-4" />
          </div>
          <div className="min-w-0">
            <div className="font-semibold text-xs sm:text-sm text-white truncate">
              Connected Tool: Link Extractor
            </div>
            <p className="text-[10px] sm:text-xs text-slate-400 truncate">
              Extract download & action buttons from web pages
            </p>
          </div>
        </div>
        <button
          id="btn-goto-extractor"
          onClick={() => onNavigateToExtractor(result?.final || undefined)}
          className="w-full sm:w-auto px-3 py-1.5 bg-white hover:bg-slate-100 text-slate-900 rounded-lg text-xs font-semibold flex items-center justify-center gap-1.5 transition-all cursor-pointer shrink-0 shadow-xs active:scale-95"
        >
          <span>Open Link Extractor</span>
          <ArrowRight className="w-3.5 h-3.5" />
        </button>
      </div>

      {/* Feature Pills (from uploaded index.html) */}
      <div className="flex items-center gap-2 overflow-x-auto pb-1 text-xs no-scrollbar w-full">
        {["Short URL Resolver", "Redirect Checker", "URL Analyzer"].map((pill) => (
          <button
            key={pill}
            onClick={() => setActivePill(pill)}
            className={`px-3.5 py-1.5 rounded-full font-semibold transition-all shrink-0 cursor-pointer border ${
              activePill === pill
                ? "bg-[#0d0d0d] text-white border-[#0d0d0d] shadow-sm"
                : "bg-white text-[#505050] border-[#dddddd] hover:bg-slate-50"
            }`}
          >
            {pill}
          </button>
        ))}

        <button
          onClick={() => onNavigateToExtractor()}
          className="px-3 py-1.5 rounded-full font-semibold transition-all shrink-0 cursor-pointer border border-emerald-300 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 flex items-center gap-1 text-xs"
        >
          <span>Button Extractor Page →</span>
        </button>
      </div>

      {/* Preset sample short URLs */}
      <div className="flex flex-wrap items-center gap-1.5 text-xs text-slate-600">
        <span className="font-medium text-slate-400 pl-0.5">Test URLs:</span>
        {SAMPLE_SHORT_URLS.map((s) => (
          <button
            key={s.name}
            onClick={() => {
              setUrlInput(s.url);
              handleResolve(s.url);
            }}
            className="px-2.5 py-0.5 rounded-md bg-white border border-slate-200 hover:border-slate-300 text-slate-700 text-[11px] font-medium transition-colors cursor-pointer"
          >
            {s.name}
          </button>
        ))}
      </div>

      {/* Main Card (Styled exactly from uploaded index.html) */}
      <div
        id="resolver-main-card"
        className="w-full bg-white rounded-[21px] p-4 sm:p-6 shadow-[0_8px_30px_rgba(0,0,0,0.06)] border border-[#e6e6e6] overflow-x-hidden"
      >
        <div className="flex items-center justify-between gap-2">
          <h1 className="text-xl sm:text-[25px] font-bold tracking-tight text-[#0d0d0d] leading-tight break-words">
            Short URL Resolver
          </h1>
          <button
            onClick={handleReset}
            className="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer"
            title="Reset"
          >
            <RotateCcw className="w-4 h-4" />
          </button>
        </div>

        <div className="flex flex-wrap items-center gap-2 mt-3">
          <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#f1f1f1] border border-[#dedede] text-[#303030] text-[11px] font-extrabold tracking-wider uppercase">
            <ShieldCheck className="w-3.5 h-3.5 text-emerald-600" />
            PYTHON RESOLUTION ENGINE
          </div>
          <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-indigo-50 border border-indigo-200 text-indigo-700 text-[11px] font-bold">
            <Zap className="w-3 h-3 text-indigo-600" />
            AUTO BYPASS ACTIVE
          </div>
        </div>

        <p className="mt-3 text-[#6f6f6f] text-xs sm:text-sm leading-relaxed break-words">
          Enter any shortened URL to bypass interstitial ads, intermediate safelink steps,
          AdLinkFly tokens, and JavaScript timers to reveal the real destination URL.
        </p>

        {/* URL Input */}
        <label
          htmlFor="resolver-url-input"
          className="block mt-5 mb-2 text-xs font-bold text-[#565656]"
        >
          Shortened URL
        </label>

        <div className="flex items-center border border-[#d8d8d8] rounded-[13px] bg-white transition-all focus-within:border-[#202020] focus-within:ring-2 focus-within:ring-black/5 overflow-hidden">
          <span className="pl-3.5 text-[#8a8a8a] text-sm shrink-0">↗</span>
          <input
            id="resolver-url-input"
            type="url"
            autoComplete="off"
            spellCheck="false"
            value={urlInput}
            onChange={(e) => setUrlInput(e.target.value)}
            onKeyDown={(e) => e.key === "Enter" && handleResolve()}
            placeholder="https://shrt.sohojgyan.com/zEgAAfE"
            className="w-full h-[52px] sm:h-[54px] border-0 outline-none bg-transparent px-3 text-sm text-[#151515] placeholder:text-[#a2a2a2]"
          />
        </div>

        {/* Resolve Button */}
        <button
          id="resolveBtn"
          onClick={() => handleResolve()}
          disabled={loading}
          className="w-full h-[52px] sm:h-[55px] mt-3 rounded-[13px] bg-[#0d0d0d] hover:bg-[#1d1d1d] text-white text-sm sm:text-[15px] font-bold cursor-pointer transition-all active:scale-[0.99] disabled:opacity-55 disabled:cursor-not-allowed flex items-center justify-center gap-2 shadow-xs"
        >
          {loading ? (
            <>
              <Loader2 className="w-4 h-4 animate-spin" />
              <span>Resolving...</span>
            </>
          ) : (
            "Resolve URL"
          )}
        </button>

        {/* Error Box */}
        {errorMsg && (
          <div
            id="resolver-error"
            className="mt-3 p-3.5 rounded-[12px] border border-red-200 bg-[#fff5f5] text-[#b91c1c] text-xs sm:text-[13px] leading-relaxed break-words"
          >
            {errorMsg}
          </div>
        )}

        {/* Result Header */}
        <div className="mt-6 sm:mt-7 flex items-center justify-between">
          <div className="text-xs sm:text-[13px] font-bold text-[#686868]">
            Resolution Result
          </div>
          <button
            id="copyFinalBtn"
            onClick={handleCopy}
            disabled={!result?.final}
            className="px-3 py-2 rounded-[9px] border border-[#ddd] bg-white text-[#444] text-xs font-bold transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-default flex items-center gap-1.5 shadow-2xs hover:bg-slate-50"
          >
            {copied ? (
              <>
                <Check className="w-3.5 h-3.5 text-emerald-600" />
                <span>Copied</span>
              </>
            ) : (
              <>
                <Copy className="w-3.5 h-3.5" />
                <span>Copy Final URL</span>
              </>
            )}
          </button>
        </div>

        {/* Empty State vs Result Box */}
        {!result ? (
          <div
            id="resolver-empty"
            className="mt-3 min-h-[120px] sm:min-h-[130px] border border-dashed border-[#d6d6d6] rounded-[15px] flex items-center justify-center text-center p-6 text-[#8a8a8a] text-xs sm:text-sm leading-relaxed bg-[#fafafa]/60"
          >
            Paste a shortened URL above and click “Resolve URL” to find its destination.
          </div>
        ) : (
          <div id="resolver-result-box" className="mt-3 space-y-3">
            {/* Final Destination Card */}
            <div className="p-4 rounded-[15px] border border-[#dedede] bg-[#fafafa]">
              <div className="text-[10px] font-extrabold text-[#898989] uppercase tracking-wider">
                Final Destination
              </div>
              <div className="mt-2 text-sm sm:text-[15px] font-semibold text-[#151515] break-all leading-relaxed">
                <a
                  href={result.final}
                  target="_blank"
                  rel="noreferrer"
                  className="text-blue-700 hover:underline inline-flex items-start gap-1"
                >
                  <span>{result.final}</span>
                  <ExternalLink className="w-3.5 h-3.5 shrink-0 mt-0.5" />
                </a>
              </div>

              {/* Action: Send this final URL directly to Extractor */}
              <div className="mt-3 pt-3 border-t border-slate-200/70 flex items-center justify-between">
                <span className="text-xs text-slate-500">
                  Scan this destination for download & button links?
                </span>
                <button
                  onClick={() => onNavigateToExtractor(result.final)}
                  className="px-2.5 py-1 bg-slate-900 hover:bg-black text-white rounded-lg text-xs font-semibold flex items-center gap-1 transition-all cursor-pointer shadow-xs active:scale-95 shrink-0"
                >
                  <span>Extract Links</span>
                  <ArrowRight className="w-3 h-3" />
                </button>
              </div>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-2 gap-2.5">
              <div className="p-3.5 rounded-[12px] border border-[#e5e5e5] bg-white">
                <div className="text-[10px] font-bold text-[#8a8a8a] uppercase tracking-wider">
                  Redirects
                </div>
                <div className="mt-1 text-base sm:text-[17px] font-extrabold text-[#111]">
                  {result.redirects}
                </div>
              </div>

              <div className="p-3.5 rounded-[12px] border border-[#e5e5e5] bg-white">
                <div className="text-[10px] font-bold text-[#8a8a8a] uppercase tracking-wider">
                  Status
                </div>
                <div className="mt-1 text-base sm:text-[17px] font-extrabold text-emerald-700">
                  Resolved
                </div>
              </div>
            </div>

            {/* Redirect Chain */}
            <div className="pt-2">
              <div className="mb-2 text-xs font-extrabold text-[#555]">
                Redirect Chain ({result.chain.length} steps)
              </div>
              <div className="rounded-[14px] border border-[#e4e4e4] bg-white overflow-hidden divide-y divide-[#eeeeee]">
                {result.chain.map((step) => (
                  <div key={step.step} className="p-3 sm:p-3.5 hover:bg-slate-50/50 transition-colors">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <div className="flex flex-wrap items-center gap-2">
                        <span className="min-w-[26px] h-[22px] rounded-[6px] bg-[#efefef] text-[#333] text-[10px] font-extrabold flex items-center justify-center shrink-0">
                          {step.step}
                        </span>
                        <span
                          className={`text-[11px] font-bold px-1.5 py-0.5 rounded ${
                            step.status >= 300 && step.status < 400
                              ? "bg-amber-50 text-amber-800 border border-amber-200"
                              : step.status >= 200 && step.status < 300
                              ? "bg-emerald-50 text-emerald-800 border border-emerald-200"
                              : "bg-red-50 text-red-800 border border-red-200"
                          }`}
                        >
                          HTTP {step.status}
                        </span>

                        {step.type && (
                          <span
                            className={`text-[10px] font-semibold px-2 py-0.5 rounded-full flex items-center gap-1 ${
                              step.type.includes("Bypass")
                                ? "bg-purple-50 text-purple-700 border border-purple-200"
                                : step.type.includes("Destination")
                                ? "bg-emerald-50 text-emerald-700 border border-emerald-200"
                                : "bg-slate-100 text-slate-700 border border-slate-200"
                            }`}
                          >
                            {step.type.includes("Bypass") ? (
                              <Zap className="w-2.5 h-2.5 text-purple-600 shrink-0" />
                            ) : null}
                            <span>{step.type}</span>
                          </span>
                        )}
                      </div>

                      {/* Extract Links Button for this step in redirect chain */}
                      <button
                        type="button"
                        onClick={() => onNavigateToExtractor(step.url)}
                        className="px-2.5 py-1 bg-blue-600 hover:bg-blue-700 active:scale-95 text-white rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all cursor-pointer shadow-xs shrink-0"
                        title={`Extract links from Step ${step.step} URL`}
                      >
                        <span>Extract Links</span>
                        <ArrowRight className="w-3 h-3" />
                      </button>
                    </div>

                    <div className="mt-2 ml-0 sm:ml-8 flex flex-col sm:flex-row sm:items-center justify-between gap-2 bg-slate-50 p-2.5 rounded-lg border border-slate-200/70">
                      <div className="text-xs font-mono text-slate-700 break-all leading-relaxed select-all">
                        {step.url}
                      </div>
                      <div className="flex items-center gap-1 shrink-0 self-end sm:self-center">
                        <button
                          type="button"
                          onClick={() => handleCopyStepUrl(step.url, step.step)}
                          className="p-1 text-slate-400 hover:text-slate-700 hover:bg-slate-200/70 rounded transition-colors cursor-pointer"
                          title="Copy this step URL"
                        >
                          {copiedStepIndex === step.step ? (
                            <Check className="w-3.5 h-3.5 text-emerald-600" />
                          ) : (
                            <Copy className="w-3.5 h-3.5" />
                          )}
                        </button>
                        <a
                          href={step.url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="p-1 text-slate-400 hover:text-slate-700 hover:bg-slate-200/70 rounded transition-colors cursor-pointer"
                          title="Open URL in new tab"
                        >
                          <ExternalLink className="w-3.5 h-3.5" />
                        </a>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        )}

        {/* Footer */}
        <div className="mt-5 pt-4 border-t border-[#eeeeee] text-[#888] text-[11px] leading-relaxed break-words">
          Resolution is performed server-side. The browser does not directly open the
          shortened URL during analysis.
        </div>
      </div>
    </div>
  );
};
