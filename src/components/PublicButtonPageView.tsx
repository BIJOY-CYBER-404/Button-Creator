import React, { useEffect, useState } from "react";
import { CheckCircle2, Download, ExternalLink, ShieldCheck, Copy, ArrowLeft, Eye, Film, Menu, X, Play, Share2, Smartphone, QrCode, Check } from "lucide-react";
import { ButtonPage, SiteIdentity } from "../types";

interface PublicButtonPageViewProps {
  slug: string;
  onBackToAdmin?: () => void;
  onNotify?: (text: string, type: "success" | "error" | "info") => void;
}

export const PublicButtonPageView: React.FC<PublicButtonPageViewProps> = ({
  slug,
  onBackToAdmin,
  onNotify,
}) => {
  const [page, setPage] = useState<ButtonPage | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [mobileMenuOpen, setMobileMenuOpen] = useState<boolean>(false);
  const [copiedLink, setCopiedLink] = useState<boolean>(false);
  const [qrModalOpen, setQrModalOpen] = useState<boolean>(false);
  const [mobileShareSheetOpen, setMobileShareSheetOpen] = useState<boolean>(false);

  const [siteIdentity, setSiteIdentity] = useState<SiteIdentity>(() => {
    const saved = localStorage.getItem("slea_site_identity");
    if (saved) {
      try {
        return JSON.parse(saved);
      } catch {
        // fallback
      }
    }
    return {
      site_name: "Movie Hub HQ Drive",
      site_logo_url: "",
      site_logo_icon: "⚡",
      site_logo_text: "MHQ",
    };
  });

  const [footerText] = useState<string>(() => {
    return (
      localStorage.getItem("slea_footer_text") ||
      "&copy; 2026 Movie Hub HQ Drive 🍿 • Made with ❤️ for Direct Episode Gateway 🎬 • All rights reserved 🚀"
    );
  });

  const defaultMenuItems = [
    { title: "Home", url: "https://moviehubhq.com/" },
    { title: "Korean Drama", url: "https://moviehubhq.com/catagory/korean/" },
    { title: "Chinese Drama", url: "https://moviehubhq.com/catagory/chinese/" },
  ];

  useEffect(() => {
    // Dynamically ensure noindex meta tag is placed in the head
    let robotsMeta = document.querySelector('meta[name="robots"]');
    if (!robotsMeta) {
      robotsMeta = document.createElement("meta");
      robotsMeta.setAttribute("name", "robots");
      document.head.appendChild(robotsMeta);
    }
    robotsMeta.setAttribute("content", "noindex, nofollow, noarchive, nosnippet, noimageindex");

    const fetchPage = async () => {
      setLoading(true);
      setError(null);
      try {
        const res = await fetch(`/api/public/pages/${encodeURIComponent(slug)}`);
        const data = await res.json();
        if (data.success && data.page) {
          setPage(data.page);
        } else {
          setError(data.error || "Episode button page not found.");
        }
      } catch (err: any) {
        setError(err.message || "Failed to load episode page.");
      } finally {
        setLoading(false);
      }
    };

    fetchPage();
  }, [slug]);

  const getCanonicalUrl = () => {
    return window.location.origin + `/p/${slug}`;
  };

  const copyLink = (url: string, label: string) => {
    navigator.clipboard.writeText(url);
    onNotify?.(`Copied ${label} link to clipboard!`, "success");
  };

  const copyPageShareUrl = () => {
    const fullUrl = getCanonicalUrl();
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(fullUrl);
      setCopiedLink(true);
      setTimeout(() => setCopiedLink(false), 2500);
      onNotify?.("Public Button Page link copied to clipboard!", "success");
    } else {
      const temp = document.createElement("input");
      temp.value = fullUrl;
      document.body.appendChild(temp);
      temp.select();
      document.execCommand("copy");
      document.body.removeChild(temp);
      setCopiedLink(true);
      setTimeout(() => setCopiedLink(false), 2500);
      onNotify?.("Public Button Page link copied to clipboard!", "success");
    }
  };

  const triggerNativeShare = () => {
    const fullUrl = getCanonicalUrl();
    const title = page?.title || "Movie Hub HQ Drive";
    if (navigator.share) {
      navigator
        .share({
          title: title,
          text: `Watch & Download: ${title} - Fast Servers:`,
          url: fullUrl,
        })
        .then(() => {
          onNotify?.("Shared successfully!", "success");
        })
        .catch((err) => {
          if (err.name !== "AbortError") {
            copyPageShareUrl();
          }
        });
    } else {
      setMobileShareSheetOpen(true);
    }
  };

  const handleMobileFabShare = () => {
    if (navigator.share) {
      triggerNativeShare();
    } else {
      setMobileShareSheetOpen(true);
    }
  };

  const renderServerIcon = (provider?: string, url?: string, text?: string) => {
    const haystack = `${provider || ""} ${url || ""} ${text || ""}`.toLowerCase();
    if (haystack.includes("gdrive") || haystack.includes("drive.google") || haystack.includes("google")) {
      return (
        <svg className="w-3.5 h-3.5 shrink-0" viewBox="0 0 87.3 78" fill="none">
          <path d="m6.6 66.85 3.85 6.65c.8 1.4 1.95 2.5 3.3 3.3l13.75-23.8H0c0 1.55.4 3.1 1.2 4.5z" fill="#0066da"/>
          <path d="M43.65 25 29.9 1.2c-1.35.8-2.5 1.9-3.3 3.3l-25.4 44C.4 50 0 51.55 0 53.1h27.5z" fill="#00ac47"/>
          <path d="M73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l1.6-2.75 7.65-13.25c.8-1.4 1.2-2.95 1.2-4.5H59.8l5.85 10.1z" fill="#ea4335"/>
          <path d="M43.65 25 57.4 1.2C56.05.4 54.5 0 52.95 0H34.35c-1.55 0-3.1.4-4.45 1.2z" fill="#00832d"/>
          <path d="m59.8 53.1-16.15-28-16.15 28z" fill="#2684fc"/>
          <path d="M73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l-13.75-23.8H35.65l13.75 23.8c1.35.8 2.9 1.2 4.45 1.2h15.25c1.55 0 3.1-.4 4.45-1.1z" fill="#ffba00"/>
        </svg>
      );
    }
    if (haystack.includes("xcloud") || haystack.includes("cloud") || haystack.includes("stream")) {
      return (
        <svg className="w-3.5 h-3.5 text-[#0b57d0] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 00-9.78 2.096A4.001 4.001 0 003 15z"/>
        </svg>
      );
    }
    return (
      <svg className="w-3.5 h-3.5 text-[#0b57d0] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 12h14M12 5l7 7-7 7"/>
      </svg>
    );
  };

  if (loading) {
    return (
      <div className="w-full max-w-xl mx-auto py-16 text-center space-y-3">
        <div className="inline-block w-8 h-8 border-3 border-[#0b57d0] border-t-transparent rounded-full animate-spin"></div>
        <p className="text-sm font-medium text-[#444746]">Loading episode buttons...</p>
      </div>
    );
  }

  if (error || !page) {
    return (
      <div className="w-full max-w-xl mx-auto py-12 px-4">
        <div className="bg-white rounded-3xl p-6 border border-[#fce8e6] text-center shadow-xs space-y-4">
          <div className="w-12 h-12 rounded-full bg-[#fce8e6] text-[#c5221f] flex items-center justify-center mx-auto font-bold">
            !
          </div>
          <h2 className="text-lg font-bold text-[#1f1f1f]">404 - Episode Page Not Found</h2>
          <p className="text-xs text-[#5f6368]">{error || "This episode link page does not exist."}</p>
          {onBackToAdmin && (
            <button
              onClick={onBackToAdmin}
              className="px-4 py-2 bg-[#0b57d0] text-white rounded-full text-xs font-semibold hover:bg-[#0842a0] cursor-pointer inline-flex items-center gap-2"
            >
              <ArrowLeft className="w-3.5 h-3.5" /> Back to Admin
            </button>
          )}
        </div>
      </div>
    );
  }

  return (
    <div className="w-full max-w-xl mx-auto py-4 px-2 sm:px-4 space-y-4">
      {/* Mobile Left-Opening Half-Page Sidebar Drawer & Backdrop */}
      {mobileMenuOpen && (
        <div
          onClick={() => setMobileMenuOpen(false)}
          className="fixed inset-0 bg-black/50 backdrop-blur-xs z-50 md:hidden transition-opacity"
          aria-hidden="true"
        />
      )}

      <aside
        className={`fixed inset-y-0 left-0 z-50 w-1/2 min-w-[240px] max-w-xs h-full bg-white border-r border-[#e0e4eb] shadow-2xl flex flex-col md:hidden transform transition-transform duration-300 ease-in-out ${
          mobileMenuOpen ? "translate-x-0" : "-translate-x-full"
        }`}
        aria-label="Mobile Navigation Sidebar"
      >
        {/* Sidebar Header: Image Logo if available, fallback to Site Text Logo */}
        <div className="p-4 border-b border-[#f0f4f9] flex items-center justify-between shrink-0">
          <div className="flex items-center min-w-0">
            {siteIdentity.site_logo_url ? (
              <img
                src={siteIdentity.site_logo_url}
                alt={siteIdentity.site_name || "Logo"}
                className="h-7 max-w-[140px] object-contain"
              />
            ) : (
              <span className="font-bold text-sm tracking-tight text-[#111827] truncate">
                {siteIdentity.site_name || "Movie Hub HQ Drive"}
              </span>
            )}
          </div>
          <button
            type="button"
            onClick={() => setMobileMenuOpen(false)}
            className="p-1.5 rounded-lg text-[#5f6368] hover:text-[#1f1f1f] hover:bg-[#f0f4f9] transition-colors cursor-pointer"
            aria-label="Close menu"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Vertical Menu Items */}
        <nav className="flex-1 overflow-y-auto p-4 flex flex-col space-y-2">
          {defaultMenuItems.map((item, idx) => (
            <a
              key={idx}
              href={item.url}
              target="_blank"
              rel="noopener noreferrer"
              onClick={() => setMobileMenuOpen(false)}
              className="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-semibold text-[#444746] hover:text-[#0b57d0] bg-[#f8fafd] hover:bg-[#e8f0fe] border border-[#e1e7f0] transition-colors"
            >
              <span>{item.title}</span>
              <span className="text-[#a8adb7] text-xs">›</span>
            </a>
          ))}
        </nav>

        <div className="p-4 border-t border-[#f0f4f9] text-[11px] text-[#747775] text-center shrink-0">
          {siteIdentity.site_name || "Movie Hub HQ Drive"}
        </div>
      </aside>

      {/* Public Header Navigation Bar (Google Material M3 Light Theme) */}
      <header className="bg-white rounded-2xl border border-[#e0e4eb] shadow-2xs overflow-hidden">
        <div className="px-4 h-14 flex items-center justify-between gap-3">
          {/* Left Header: Mobile Hamburger Button + Logo */}
          <div className="flex items-center gap-2.5 min-w-0">
            {/* Mobile 3-Line Hamburger Button */}
            <button
              type="button"
              onClick={() => setMobileMenuOpen(true)}
              className="md:hidden p-1.5 rounded-lg bg-[#f0f4f9] hover:bg-[#e2e8f0] text-[#444746] hover:text-[#111827] transition-colors cursor-pointer"
              aria-label="Toggle navigation menu"
            >
              <Menu className="w-5 h-5" />
            </button>

            {/* Image Logo (fallback to Text Logo) */}
            <a
              href="javascript:void(0)"
              className="flex items-center group min-w-0"
            >
              {siteIdentity.site_logo_url ? (
                <img
                  src={siteIdentity.site_logo_url}
                  alt={siteIdentity.site_name || "Logo"}
                  className="h-8 max-w-[180px] object-contain"
                />
              ) : (
                <span className="font-bold text-sm sm:text-base tracking-tight text-[#111827] group-hover:text-[#0b57d0] transition-colors truncate">
                  {siteIdentity.site_name || "Movie Hub HQ Drive"}
                </span>
              )}
            </a>
          </div>

          {/* Right Header: Desktop Navigation Menu Buttons */}
          <nav className="hidden md:flex flex-row items-center gap-1.5">
            {defaultMenuItems.map((item, idx) => (
              <a
                key={idx}
                href={item.url}
                target="_blank"
                rel="noopener noreferrer"
                className="px-3 py-1.5 rounded-full text-xs font-semibold text-[#444746] hover:text-[#0b57d0] hover:bg-[#f0f4f9] transition-colors whitespace-nowrap"
              >
                {item.title}
              </a>
            ))}
          </nav>
        </div>
      </header>

      {/* Admin Quick Bar */}
      <div className="flex items-center justify-between gap-2 px-1">
        {onBackToAdmin ? (
          <button
            onClick={onBackToAdmin}
            className="text-xs font-medium text-[#444746] hover:text-[#0b57d0] flex items-center gap-1.5 cursor-pointer bg-white px-3 py-1.5 rounded-full border border-[#e1e7f0] shadow-2xs"
          >
            <ArrowLeft className="w-3.5 h-3.5" /> Admin Dashboard
          </button>
        ) : (
          <span className="text-[11px] font-mono text-[#747775]">/p/{page.slug}</span>
        )}

        <button
          onClick={copyPageShareUrl}
          className="text-xs text-[#0b57d0] hover:underline flex items-center gap-1 cursor-pointer font-medium"
        >
          <Copy className="w-3 h-3" /> Share Page
        </button>
      </div>

      {/* Information Header Card */}
      <div className="bg-white rounded-3xl p-6 border border-[#e0e4eb] text-center shadow-xs space-y-3">
        <h1 className="text-xl sm:text-2xl font-black text-[#111827] tracking-tight leading-snug">
          {page.title}
        </h1>

        {page.description ? (
          <p className="text-xs sm:text-sm text-[#5f6368] max-w-md mx-auto leading-relaxed">
            {page.description}
          </p>
        ) : (
          <p className="text-xs text-[#5f6368]">
            Click Watch Now on any episode below to begin streaming or direct mirror downloading.
          </p>
        )}
      </div>

      {/* Social Media & Mobile Display Share Card (Google Material M3 Light Theme) */}
      <div className="bg-white border border-[#e0e4eb] rounded-3xl p-4 sm:p-5 shadow-xs space-y-3">
        <div className="flex items-center justify-between gap-2 flex-wrap pb-0.5">
          <div className="flex items-center gap-2">
            <span className="w-7 h-7 rounded-xl bg-[#e8f0fe] text-[#0b57d0] flex items-center justify-center">
              <Share2 className="w-4 h-4" />
            </span>
            <span className="text-xs font-bold text-[#111827]">Share Episode Page</span>
          </div>
          <span className="text-[11px] text-[#747775]">Copy link or share to social apps</span>
        </div>

        {/* Primary Action Chips */}
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
          {/* 1. Copy Link Button */}
          <button
            type="button"
            onClick={copyPageShareUrl}
            className="flex items-center justify-center gap-2 px-3.5 py-2.5 rounded-2xl bg-[#f0f4f9] hover:bg-[#e8f0fe] text-[#0b57d0] border border-[#d3e3fd] hover:border-[#0b57d0] text-xs font-bold transition-all shadow-2xs cursor-pointer select-none active:scale-95 group"
          >
            {copiedLink ? (
              <>
                <Check className="w-4 h-4 text-emerald-600" />
                <span className="text-emerald-700">Copied!</span>
              </>
            ) : (
              <>
                <Copy className="w-4 h-4 transition-transform group-hover:scale-110" />
                <span className="truncate">Copy Link</span>
              </>
            )}
          </button>

          {/* 2. Mobile Native Share Sheet */}
          <button
            type="button"
            onClick={triggerNativeShare}
            className="flex items-center justify-center gap-2 px-3.5 py-2.5 rounded-2xl bg-[#e8f0fe] hover:bg-[#d3e3fd] text-[#041e49] border border-[#c2e7ff] text-xs font-bold transition-all shadow-2xs cursor-pointer select-none active:scale-95 group"
          >
            <Smartphone className="w-4 h-4 text-[#0b57d0]" />
            <span className="truncate">Share on Mobile</span>
          </button>

          {/* 3. WhatsApp Share */}
          <a
            href={`https://api.whatsapp.com/send?text=${encodeURIComponent(
              `${page.title} - Fast Episode Links: ${getCanonicalUrl()}`
            )}`}
            target="_blank"
            rel="noopener noreferrer"
            className="flex items-center justify-center gap-2 px-3.5 py-2.5 rounded-2xl bg-[#e6f4ea] hover:bg-[#ceead6] text-[#137333] border border-[#a8dab5] text-xs font-bold transition-all shadow-2xs cursor-pointer select-none active:scale-95 group"
          >
            <span className="text-sm">💬</span>
            <span className="truncate">WhatsApp</span>
          </a>

          {/* 4. Telegram Share */}
          <a
            href={`https://t.me/share/url?url=${encodeURIComponent(getCanonicalUrl())}&text=${encodeURIComponent(
              page.title
            )}`}
            target="_blank"
            rel="noopener noreferrer"
            className="flex items-center justify-center gap-2 px-3.5 py-2.5 rounded-2xl bg-[#e8f4fd] hover:bg-[#d0ebfc] text-[#0088cc] border border-[#b8e1fa] text-xs font-bold transition-all shadow-2xs cursor-pointer select-none active:scale-95 group"
          >
            <span className="text-sm">✈️</span>
            <span className="truncate">Telegram</span>
          </a>
        </div>

        {/* Secondary Socials (Facebook, X/Twitter, QR Code) */}
        <div className="flex items-center justify-between pt-1 gap-1.5 flex-wrap">
          <div className="flex items-center gap-1.5 flex-wrap">
            {/* Facebook */}
            <a
              href={`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(getCanonicalUrl())}`}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-[#f0f4f9] hover:bg-[#e4eaf2] text-[#1877f2] border border-[#e1e7f0] text-[11px] font-bold transition-all"
              title="Share on Facebook"
            >
              <span>Facebook</span>
            </a>

            {/* X / Twitter */}
            <a
              href={`https://twitter.com/intent/tweet?text=${encodeURIComponent(
                page.title
              )}&url=${encodeURIComponent(getCanonicalUrl())}`}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-[#f0f4f9] hover:bg-[#e4eaf2] text-[#111827] border border-[#e1e7f0] text-[11px] font-bold transition-all"
              title="Share on X (Twitter)"
            >
              <span>X / Tweet</span>
            </a>
          </div>

          {/* QR Code Display Modal Trigger */}
          <button
            type="button"
            onClick={() => setQrModalOpen(true)}
            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-[#f8fafd] hover:bg-[#e8f0fe] text-[#5f6368] hover:text-[#0b57d0] border border-[#e1e7f0] text-[11px] font-medium transition-all cursor-pointer"
          >
            <QrCode className="w-3.5 h-3.5" />
            <span>Scan QR Code</span>
          </button>
        </div>
      </div>

      {/* Buttons List */}
      <div className="space-y-2.5">
        {page.buttons && page.buttons.length > 0 ? (
          page.buttons.map((btn, idx) => {
            const rawEp = btn.episode || idx + 1;
            const epNum = typeof rawEp === "number" ? String(rawEp).padStart(2, "0") : rawEp;
            const quality = btn.quality || "HD";
            const provider = btn.provider || "GDrive Server";

            return (
              <div
                key={`btn-${idx}`}
                className="bg-white hover:bg-[#fafcff] rounded-2xl p-3.5 sm:p-4 border border-[#e3e7ee] hover:border-[#0b57d0] transition-all flex items-center justify-between gap-3 shadow-2xs group"
              >
                {/* Left Column: Server Icon, Episode 01/02.., Provider, Quality */}
                <div className="flex items-center gap-3 min-w-0 flex-1">
                  <div className="w-10 h-10 rounded-xl bg-[#e8f0fe] text-[#0b57d0] flex items-center justify-center font-extrabold text-xs shrink-0 group-hover:bg-[#0b57d0] group-hover:text-white transition-colors shadow-2xs">
                    E{epNum}
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="text-sm font-bold text-[#111827] truncate group-hover:text-[#0b57d0] transition-colors flex items-center gap-1.5">
                      <span>Episode {epNum}</span>
                      {btn.text && !btn.text.toLowerCase().includes("episode") && (
                        <span className="text-xs text-[#747775] font-normal truncate hidden sm:inline">({btn.text})</span>
                      )}
                    </div>
                    <div className="flex items-center gap-2 text-[11px] text-[#5f6368] mt-0.5">
                      <div className="inline-flex items-center gap-1 font-medium">
                        {renderServerIcon(provider, btn.url, btn.text)}
                        <span>{provider}</span>
                      </div>
                      <span className="px-1.5 py-0.2 rounded bg-[#f1f3f4] text-[#3c4043] font-mono text-[10px] font-bold border border-[#e0e4eb]">
                        {quality}
                      </span>
                    </div>
                  </div>
                </div>

                {/* Right Column: "Watch Now" Button (Google Material M3 Pill) */}
                <div className="flex items-center gap-1.5 shrink-0">
                  <button
                    type="button"
                    onClick={() => copyLink(btn.url, `Episode ${epNum}`)}
                    className="p-2 rounded-lg text-[#5f6368] hover:text-[#0b57d0] hover:bg-[#f0f4f9] border border-transparent hover:border-[#d3e3fd] cursor-pointer transition-colors"
                    title="Copy Direct Link"
                  >
                    <Copy className="w-4 h-4" />
                  </button>

                  <a
                    href={btn.url}
                    target="_blank"
                    rel="noopener noreferrer nofollow"
                    className="px-4 py-2 rounded-full bg-[#0b57d0] hover:bg-[#0842a0] text-white font-bold text-xs flex items-center gap-1.5 cursor-pointer shadow-xs hover:shadow-md transition-all"
                  >
                    <Play className="w-3.5 h-3.5 fill-current" />
                    <span>Watch Now</span>
                  </a>
                </div>
              </div>
            );
          })
        ) : (
          <div className="bg-white rounded-2xl p-8 text-center text-xs text-[#747775] border border-[#e0e4eb]">
            No episode buttons currently available on this page.
          </div>
        )}
      </div>

      {/* Footer with Configured Copyright */}
      <footer className="text-center py-6 text-xs text-[#5f6368] border-t border-[#e0e4eb] mt-6">
        <div dangerouslySetInnerHTML={{ __html: footerText }} />
      </footer>

      {/* Floating Mobile Share Button (FAB) */}
      <div className="fixed bottom-5 right-4 z-40 sm:hidden">
        <button
          type="button"
          onClick={handleMobileFabShare}
          aria-label="Share page"
          className="w-13 h-13 rounded-full bg-[#0b57d0] hover:bg-[#0842a0] text-white flex items-center justify-center shadow-xl transition-transform active:scale-90 border-2 border-white ring-4 ring-[#0b57d0]/20 cursor-pointer"
        >
          <Share2 className="w-6 h-6" />
        </button>
      </div>

      {/* QR Code Display Modal */}
      {qrModalOpen && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl p-6 max-w-xs w-full text-center space-y-4 shadow-2xl border border-[#e0e4eb] animate-in fade-in zoom-in-95 duration-200">
            <div className="flex items-center justify-between pb-2 border-b border-[#f0f4f9]">
              <div className="flex items-center gap-2">
                <span className="w-6 h-6 rounded-lg bg-[#e8f0fe] text-[#0b57d0] flex items-center justify-center">
                  <QrCode className="w-3.5 h-3.5" />
                </span>
                <span className="text-xs font-bold text-[#111827]">Scan to Open</span>
              </div>
              <button
                type="button"
                onClick={() => setQrModalOpen(false)}
                className="p-1 rounded-lg text-[#5f6368] hover:text-[#111827] hover:bg-[#f0f4f9] transition-colors cursor-pointer"
                aria-label="Close"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="flex justify-center p-3 bg-[#f8fafd] rounded-2xl border border-[#e0e4eb]">
              <img
                src={`https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(
                  getCanonicalUrl()
                )}`}
                alt="QR Code"
                className="w-48 h-48 rounded-lg"
              />
            </div>

            <p className="text-[11px] text-[#5f6368] leading-tight">
              Scan with any mobile camera or scanner app to open this page instantly.
            </p>

            <button
              type="button"
              onClick={copyPageShareUrl}
              className="w-full py-2.5 rounded-full bg-[#0b57d0] hover:bg-[#0842a0] text-white font-bold text-xs shadow-xs transition-all cursor-pointer"
            >
              {copiedLink ? "✓ Link Copied!" : "Copy Link to Clipboard"}
            </button>
          </div>
        </div>
      )}

      {/* Mobile Share Sheet Bottom Drawer */}
      {mobileShareSheetOpen && (
        <>
          <div
            onClick={() => setMobileShareSheetOpen(false)}
            className="fixed inset-0 bg-black/50 backdrop-blur-xs z-50 md:hidden animate-in fade-in duration-200"
          />
          <div className="fixed inset-x-0 bottom-0 z-50 bg-white rounded-t-3xl border-t border-[#e0e4eb] p-5 shadow-2xl space-y-4 md:hidden animate-in slide-in-from-bottom duration-300">
            <div className="flex items-center justify-between pb-2 border-b border-[#f0f4f9]">
              <div className="flex items-center gap-2">
                <span className="w-7 h-7 rounded-xl bg-[#e8f0fe] text-[#0b57d0] flex items-center justify-center">
                  <Share2 className="w-4 h-4" />
                </span>
                <span className="text-sm font-bold text-[#111827]">Share Episode Link</span>
              </div>
              <button
                type="button"
                onClick={() => setMobileShareSheetOpen(false)}
                className="p-1.5 rounded-xl text-[#5f6368] hover:bg-[#f0f4f9] transition-colors cursor-pointer"
                aria-label="Close"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="grid grid-cols-4 gap-2 text-center text-xs">
              {/* Native / System Share */}
              <button
                type="button"
                onClick={() => {
                  setMobileShareSheetOpen(false);
                  triggerNativeShare();
                }}
                className="flex flex-col items-center gap-1.5 p-2 rounded-2xl bg-[#f0f4f9] hover:bg-[#e8f0fe] text-[#0b57d0] transition-colors cursor-pointer"
              >
                <div className="w-10 h-10 rounded-full bg-[#0b57d0] text-white flex items-center justify-center shadow-xs">
                  <Share2 className="w-5 h-5" />
                </div>
                <span className="text-[11px] font-semibold text-[#1f1f1f]">More Apps</span>
              </button>

              {/* WhatsApp */}
              <a
                href={`https://api.whatsapp.com/send?text=${encodeURIComponent(
                  `${page.title} - Fast Episode Links: ${getCanonicalUrl()}`
                )}`}
                target="_blank"
                rel="noopener noreferrer"
                onClick={() => setMobileShareSheetOpen(false)}
                className="flex flex-col items-center gap-1.5 p-2 rounded-2xl bg-[#f0f4f9] hover:bg-[#e6f4ea] text-[#137333] transition-colors cursor-pointer"
              >
                <div className="w-10 h-10 rounded-full bg-[#25d366] text-white flex items-center justify-center shadow-xs text-lg">
                  💬
                </div>
                <span className="text-[11px] font-semibold text-[#1f1f1f]">WhatsApp</span>
              </a>

              {/* Telegram */}
              <a
                href={`https://t.me/share/url?url=${encodeURIComponent(getCanonicalUrl())}&text=${encodeURIComponent(
                  page.title
                )}`}
                target="_blank"
                rel="noopener noreferrer"
                onClick={() => setMobileShareSheetOpen(false)}
                className="flex flex-col items-center gap-1.5 p-2 rounded-2xl bg-[#f0f4f9] hover:bg-[#e8f4fd] text-[#0088cc] transition-colors cursor-pointer"
              >
                <div className="w-10 h-10 rounded-full bg-[#0088cc] text-white flex items-center justify-center shadow-xs text-lg">
                  ✈️
                </div>
                <span className="text-[11px] font-semibold text-[#1f1f1f]">Telegram</span>
              </a>

              {/* Facebook */}
              <a
                href={`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(getCanonicalUrl())}`}
                target="_blank"
                rel="noopener noreferrer"
                onClick={() => setMobileShareSheetOpen(false)}
                className="flex flex-col items-center gap-1.5 p-2 rounded-2xl bg-[#f0f4f9] hover:bg-[#e4eaf2] text-[#1877f2] transition-colors cursor-pointer"
              >
                <div className="w-10 h-10 rounded-full bg-[#1877f2] text-white flex items-center justify-center shadow-xs text-sm font-bold">
                  f
                </div>
                <span className="text-[11px] font-semibold text-[#1f1f1f]">Facebook</span>
              </a>
            </div>

            <button
              type="button"
              onClick={copyPageShareUrl}
              className="w-full py-3 rounded-2xl bg-[#f0f4f9] hover:bg-[#e8f0fe] text-[#0b57d0] font-bold text-xs flex items-center justify-center gap-2 border border-[#d3e3fd] transition-all cursor-pointer"
            >
              {copiedLink ? (
                <>
                  <Check className="w-4 h-4 text-emerald-600" />
                  <span className="text-emerald-700">✓ Copied to Clipboard!</span>
                </>
              ) : (
                <>
                  <Copy className="w-4 h-4" />
                  <span>Copy Link to Clipboard</span>
                </>
              )}
            </button>
          </div>
        </>
      )}
    </div>
  );
};
