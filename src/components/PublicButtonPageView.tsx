import React, { useEffect, useState } from "react";
import { ButtonPage, SiteIdentity } from "../types";

interface PublicButtonPageViewProps {
  slug: string;
  adminToken?: string;
  isAdmin?: boolean;
  onBackToAdmin?: () => void;
  onEditPage?: (pageId: string) => void;
  onNavigateSlug?: (slug: string) => void;
  onNotify?: (text: string, type: "success" | "error" | "info") => void;
}

const THEMES: Record<string, { bg: string; card: string; border: string; primary: string; hover: string }> = {
  indigo: { bg: "#f8fafd", card: "#ffffff", border: "#e0e4eb", primary: "#0b57d0", hover: "#0842a0" },
  emerald: { bg: "#f6fbf7", card: "#ffffff", border: "#cce8d5", primary: "#0f9d58", hover: "#0b8043" },
  crimson: { bg: "#fdf7f7", card: "#ffffff", border: "#f7d0cd", primary: "#d93025", hover: "#c5221f" },
  slate: { bg: "#f8f9fa", card: "#ffffff", border: "#dadce0", primary: "#3c4043", hover: "#202124" },
  dark: { bg: "#f8fafd", card: "#ffffff", border: "#e0e4eb", primary: "#0b57d0", hover: "#0842a0" },
};

export const PublicButtonPageView: React.FC<PublicButtonPageViewProps> = ({
  slug,
  adminToken: propAdminToken,
  isAdmin: propIsAdmin,
  onBackToAdmin,
  onEditPage,
  onNavigateSlug,
  onNotify,
}) => {
  const rawLower = slug.trim().toLowerCase();
  const normalizedSlugLower =
    rawLower === "privacy" ? "privacy-policy" : rawLower === "about" ? "about-us" : rawLower;
  const legalSlug = ["dmca", "disclaimer", "about-us", "privacy-policy"].includes(normalizedSlugLower)
    ? (normalizedSlugLower as "dmca" | "disclaimer" | "about-us" | "privacy-policy")
    : null;
  const [page, setPage] = useState<ButtonPage | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [errorTitle, setErrorTitle] = useState<string>("404 - Episode Page Not Found in Database");
  const [errorHttpCode, setErrorHttpCode] = useState<number>(404);
  const [debugMode, setDebugMode] = useState<boolean>(() => {
    try {
      const saved = localStorage.getItem("slea_debug_settings");
      if (saved) return Boolean(JSON.parse(saved)?.enabled);
    } catch {
      // ignore
    }
    return false;
  });
  const [mobileMenuOpen, setMobileMenuOpen] = useState<boolean>(false);
  const [copiedLink, setCopiedLink] = useState<boolean>(false);
  const [qrModalOpen, setQrModalOpen] = useState<boolean>(false);
  const [mobileShareSheetOpen, setMobileShareSheetOpen] = useState<boolean>(false);
  const [adBlocked, setAdBlocked] = useState<boolean>(false);
  const [jumpSlugInput, setJumpSlugInput] = useState<string>(() =>
    slug && !slug.startsWith("__") ? slug : ""
  );

  useEffect(() => {
    setJumpSlugInput(slug && !slug.startsWith("__") ? slug : "");
  }, [slug]);

  const effectiveToken = propAdminToken !== undefined ? propAdminToken : localStorage.getItem("slea_admin_token") || "";
  const isPreviewVisitor =
    typeof window !== "undefined" && new URLSearchParams(window.location.search).get("preview_visitor") === "1";
  const isAdmin = isPreviewVisitor ? false : propIsAdmin !== undefined ? propIsAdmin : Boolean(effectiveToken);

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

  const filterSafePublicMenuItems = (
    items: { title: string; url: string; new_tab?: boolean; target_blank?: boolean }[],
    loginSlug = "login"
  ) => {
    const blocked = new Set([
      "admin",
      "pages",
      "settings",
      "analytics",
      "update",
      "updater",
      "login",
      "logout",
      "setup",
      "api",
      "cpanel",
      "plugin",
      loginSlug.toLowerCase(),
    ]);
    return items.filter((item) => {
      const u = String(item?.url || "").trim();
      if (!u || u === "#") return true;
      try {
        const parsed = new URL(u, window.location.origin);
        const segs = parsed.pathname.replace(/^\/+|\/+$/g, "").split("/");
        const first = (segs[0] || "").replace(/\.php$/i, "").toLowerCase();
        if (blocked.has(first)) return false;
      } catch {
        // ignore
      }
      return true;
    });
  };

  const [menuItems, setMenuItems] = useState<{ title: string; url: string; new_tab?: boolean; target_blank?: boolean }[]>(() => {
    const saved = localStorage.getItem("slea_menu_items");
    if (saved) {
      try {
        const parsed = JSON.parse(saved);
        if (Array.isArray(parsed)) return filterSafePublicMenuItems(parsed);
      } catch {
        // fallback
      }
    }
    return [
      { title: "Home", url: "https://moviehubhq.com/", new_tab: false },
      { title: "Korean Drama", url: "https://moviehubhq.com/catagory/korean/", new_tab: false },
      { title: "Chinese Drama", url: "https://moviehubhq.com/catagory/chinese/", new_tab: false },
    ];
  });

  const [footerText, setFooterText] = useState<string>(() => {
    return (
      localStorage.getItem("slea_footer_text") ||
      `&copy; ${new Date().getFullYear()} MovieHubHQ 🍿 • Made with &#10084;&#65039; for Direct Episode Link Gateway 🎬 • All rights reserved 🚀`
    );
  });

  useEffect(() => {
    const syncDebugFromLocal = () => {
      try {
        const saved = localStorage.getItem("slea_debug_settings");
        if (saved) {
          setDebugMode(Boolean(JSON.parse(saved)?.enabled));
        }
      } catch {
        // ignore
      }
    };
    window.addEventListener("storage", syncDebugFromLocal);
    window.addEventListener("debug_settings_updated", syncDebugFromLocal);

    fetch("/api/settings/public")
      .then((r) => r.json())
      .then((data) => {
        if (data.success && data.settings) {
          const s = data.settings;
          if (s.site_identity) {
            setSiteIdentity(s.site_identity);
          }
          if (Array.isArray(s.menu_items)) {
            setMenuItems(filterSafePublicMenuItems(s.menu_items, s.login_slug || "login"));
          }
          if (s.footer_text) {
            setFooterText(s.footer_text);
          }
          if (s.debug_settings && typeof s.debug_settings === "object") {
            const dbgEnabled = Boolean(s.debug_settings.enabled);
            setDebugMode(dbgEnabled);
            localStorage.setItem("slea_debug_settings", JSON.stringify({ enabled: dbgEnabled }));
          }
        }
      })
      .catch(() => {});

    return () => {
      window.removeEventListener("storage", syncDebugFromLocal);
      window.removeEventListener("debug_settings_updated", syncDebugFromLocal);
    };
  }, []);

  const [adSettings] = useState<any>(() => {
    const saved = localStorage.getItem("slea_ad_settings");
    if (saved) {
      try {
        return JSON.parse(saved);
      } catch {
        // fallback
      }
    }
    return {
      adsense_publisher_id: "",
      adsense_auto_ads: false,
      banner_top: "",
      banner_middle: "",
      banner_bottom: "",
    };
  });

  const [shareSettings] = useState<{ enabled: boolean; show_in_page: boolean; show_mobile_fab: boolean }>(() => {
    const saved = localStorage.getItem("slea_share_settings");
    if (saved) {
      try {
        return { enabled: true, show_in_page: true, ...JSON.parse(saved), show_mobile_fab: false };
      } catch {
        // fallback
      }
    }
    return { enabled: true, show_in_page: true, show_mobile_fab: false };
  });

  // Check for ad blocker / private DNS
  useEffect(() => {
    let isMounted = true;
    const detectAdBlocker = async () => {
      const bait = document.createElement("div");
      bait.className = "adsbox pub_300x250 pub_728x90 text-ad textAd text_ad text-ads ad-banner adsbygoogle";
      bait.style.cssText = "position:absolute;top:-9999px;left:-9999px;width:1px;height:1px;pointer-events:none;";
      document.body.appendChild(bait);
      await new Promise((resolve) => setTimeout(resolve, 80));
      const isHidden =
        !bait ||
        bait.offsetParent === null ||
        bait.offsetHeight === 0 ||
        bait.offsetLeft === 0 ||
        window.getComputedStyle(bait).display === "none" ||
        window.getComputedStyle(bait).visibility === "hidden";
      if (bait.parentNode) bait.parentNode.removeChild(bait);
      if (isHidden) {
        if (isMounted) setAdBlocked(true);
        return;
      }
      try {
        await fetch("https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js", {
          method: "HEAD",
          mode: "no-cors",
          cache: "no-store",
        });
      } catch {
        if (isMounted) setAdBlocked(true);
      }
    };
    detectAdBlocker();
    return () => {
      isMounted = false;
    };
  }, []);

  useEffect(() => {
    if (adSettings?.adsense_auto_ads && adSettings?.adsense_publisher_id) {
      const pubId = adSettings.adsense_publisher_id.trim();
      const existingScript = document.querySelector(`script[src*="googlesyndication.com"]`);
      if (!existingScript) {
        const script = document.createElement("script");
        script.async = true;
        script.src = `https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=${encodeURIComponent(pubId)}`;
        script.crossOrigin = "anonymous";
        document.head.appendChild(script);
      }
    }
  }, [adSettings]);

  useEffect(() => {
    let robotsMeta = document.querySelector('meta[name="robots"]');
    if (!robotsMeta) {
      robotsMeta = document.createElement("meta");
      robotsMeta.setAttribute("name", "robots");
      document.head.appendChild(robotsMeta);
    }
    robotsMeta.setAttribute("content", "noindex, nofollow, noarchive, nosnippet, noimageindex");

    const fetchPage = async () => {
      if (slug === "__login_restricted__") {
        setPage(null);
        setErrorTitle("403 - Login Page Restricted");
        setErrorHttpCode(403);
        setError(
          "Actual Error [HTTP 403]: The login page cannot be accessed or viewed while an administrator account is already logged in."
        );
        setLoading(false);
        return;
      }

      if (slug === "__missing_slug__" || !slug.trim()) {
        setPage(null);
        setErrorTitle("404 - Route Not Found (Missing Episode Slug)");
        setErrorHttpCode(404);
        setError(
          `Actual Error [HTTP 404]: Request to '${window.location.pathname || "/"}' failed because no episode page slug (?slug= or /p/{slug}) was provided in the request URL.`
        );
        setLoading(false);
        return;
      }

      if (slug === "__admin_restricted__") {
        setPage(null);
        setErrorTitle("403 / 404 - Protected Admin Route Restricted");
        setErrorHttpCode(404);
        setError(
          `Actual Error [Unauthorized Access]: Access to protected admin endpoint '${window.location.pathname}' was denied because no active administrator session was found.`
        );
        setLoading(false);
        return;
      }

      if (legalSlug) {
        const legalTitles: Record<string, string> = {
          dmca: "DMCA Copyright Policy – Movie Hub HQ",
          disclaimer: "Site Disclaimer – Movie Hub HQ",
          "about-us": "About Us – Movie Hub HQ",
          "privacy-policy": "Privacy Policy – Movie Hub HQ",
        };
        setPage({
          id: `legal_${legalSlug}`,
          slug: legalSlug,
          title: legalTitles[legalSlug] || "Official Information – Movie Hub HQ",
          description: "Official information and policies for Movie Hub HQ (moviehubhq.com)",
          source_url: "",
          resolved_url: "",
          theme: "indigo",
          buttons: [],
          views: 0,
          is_public: 1,
          created_at: new Date().toISOString(),
        });
        setError(null);
        setLoading(false);
        return;
      }

      setLoading(true);
      setError(null);
      try {
        const headers: Record<string, string> = {};
        if (effectiveToken) {
          headers["Authorization"] = `Bearer ${effectiveToken}`;
          headers["x-admin-token"] = effectiveToken;
        }
        const searchParams = new URLSearchParams(window.location.search);
        const previewParam = searchParams.get("preview_visitor") === "1";
        const querySuffix = previewParam ? "?preview_visitor=1" : "";
        const res = await fetch(`/api/public/pages/${encodeURIComponent(slug)}${querySuffix}`, { headers });
        const data = await res.json();

        if (typeof data.debug_mode === "boolean") {
          setDebugMode(data.debug_mode);
          localStorage.setItem("slea_debug_settings", JSON.stringify({ enabled: data.debug_mode }));
        }

        if (res.ok && data.success && data.page) {
          const isPub = data.page.is_public === undefined ? true : Boolean(Number(data.page.is_public));
          if (!isPub && !isAdmin) {
            setErrorTitle("403 / 404 - Private Episode Page Restricted");
            setErrorHttpCode(404);
            setError(
              `Actual Error [Private Page]: Episode page '${slug}' (ID #${data.page.id}) exists in the database, but its visibility is set to Private (is_public = 0) and visitor is not logged in as administrator.`
            );
            setPage(null);
          } else {
            setPage(data.page);
          }
        } else {
          const code = Number(data.http_code || res.status || 404);
          setErrorHttpCode(code);
          setErrorTitle(
            data.error_title && data.debug_mode
              ? data.error_title
              : code === 500
              ? "500 - Uncaught Application Exception"
              : "404 - Episode Page Not Found in Database"
          );
          const altSlug = slug.startsWith("ep-") ? slug.substring(3) : `ep-${slug}`;
          setError(
            data.actual_error ||
              `Actual Error [HTTP ${code}]: No episode page record matched slug '${slug}' (or fallback '${altSlug}') in the database.`
          );
          setPage(null);
        }
      } catch (err: any) {
        setErrorHttpCode(500);
        setErrorTitle("500 - Network / Runtime Error");
        setError(`Actual Error [Network/Runtime]: ${err.message || "Failed to load episode page."}`);
      } finally {
        setLoading(false);
      }
    };

    fetchPage();
  }, [slug, effectiveToken, isAdmin]);

  // Real-time visitor dwell duration heartbeat
  useEffect(() => {
    const startTs = Date.now();
    const targetSlug = legalSlug || page?.slug || slug;
    const sendHeartbeat = () => {
      const elapsedSec = Math.max(1, Math.round((Date.now() - startTs) / 1000));
      const payload = JSON.stringify({ slug: targetSlug, duration_sec: elapsedSec });
      fetch("/api/analytics/heartbeat", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: payload,
        keepalive: true,
      }).catch(() => {});
    };
    const initTimer = setTimeout(sendHeartbeat, 1500);
    const intervalTimer = setInterval(sendHeartbeat, 5000);
    const onVisChange = () => {
      if (document.visibilityState === "hidden") sendHeartbeat();
    };
    document.addEventListener("visibilitychange", onVisChange);
    window.addEventListener("pagehide", sendHeartbeat);
    return () => {
      clearTimeout(initTimer);
      clearInterval(intervalTimer);
      document.removeEventListener("visibilitychange", onVisChange);
      window.removeEventListener("pagehide", sendHeartbeat);
    };
  }, [slug, legalSlug, page?.slug]);

  const [maintenanceSettings] = useState<{
    enabled: boolean;
    message: string;
    end_time?: string;
    end_timestamp?: number;
  }>(() => {
    const saved = localStorage.getItem("slea_maintenance_settings");
    if (saved) {
      try {
        return {
          enabled: false,
          message: "The website is currently undergoing scheduled maintenance. We will be back shortly!",
          end_time: "",
          end_timestamp: 0,
          ...JSON.parse(saved),
        };
      } catch {
        // fallback
      }
    }
    return {
      enabled: false,
      message: "The website is currently undergoing scheduled maintenance. We will be back shortly!",
      end_time: "",
      end_timestamp: 0,
    };
  });

  const [maintCountdown, setMaintCountdown] = useState({
    days: "00",
    hours: "00",
    mins: "00",
    secs: "00",
    formattedTarget: "Scheduled Maintenance Window",
  });

  useEffect(() => {
    if (!maintenanceSettings.enabled || isAdmin) return;

    const resolveTargetDate = (): Date => {
      const nowMs = Date.now();
      if (maintenanceSettings.end_timestamp && Number(maintenanceSettings.end_timestamp) > nowMs) {
        const dTs = new Date(Number(maintenanceSettings.end_timestamp));
        if (!isNaN(dTs.getTime())) return dTs;
      }
      if (maintenanceSettings.end_time) {
        const clean = maintenanceSettings.end_time.trim();
        const m = clean.match(/^(\d{4})-(\d{2})-(\d{2})[T\s](\d{2}):(\d{2})(?::(\d{2}))?$/);
        if (m) {
          const d = new Date(
            Number(m[1]),
            Number(m[2]) - 1,
            Number(m[3]),
            Number(m[4]),
            Number(m[5]),
            Number(m[6] || 0)
          );
          if (!isNaN(d.getTime()) && d.getTime() > nowMs) return d;
        }
      }
      const savedFb = Number(localStorage.getItem("slea_maintenance_fallback_ts") || "0");
      if (savedFb > nowMs) return new Date(savedFb);
      const nextFb = nowMs + 2 * 3600 * 1000;
      localStorage.setItem("slea_maintenance_fallback_ts", String(nextFb));
      return new Date(nextFb);
    };

    const targetDate = resolveTargetDate();

    const tick = () => {
      const diff = Math.max(0, targetDate.getTime() - Date.now());
      const totalSecs = Math.floor(diff / 1000);
      const days = Math.floor(totalSecs / 86400);
      const hours = Math.floor((totalSecs % 86400) / 3600);
      const mins = Math.floor((totalSecs % 3600) / 60);
      const secs = totalSecs % 60;
      let formattedTarget = targetDate.toLocaleString();
      try {
        formattedTarget = targetDate.toLocaleString([], { dateStyle: "medium", timeStyle: "short" });
      } catch {
        // ignore
      }
      setMaintCountdown({
        days: String(days).padStart(2, "0"),
        hours: String(hours).padStart(2, "0"),
        mins: String(mins).padStart(2, "0"),
        secs: String(secs).padStart(2, "0"),
        formattedTarget,
      });
    };

    tick();
    const timer = setInterval(tick, 1000);
    return () => clearInterval(timer);
  }, [maintenanceSettings, isAdmin]);

  const getCanonicalUrl = () => {
    const cleanSlug = (page?.slug || slug).replace(/^ep-/, "");
    return `${window.location.origin}/p/${encodeURIComponent(cleanSlug)}`;
  };

  const copyPageShareUrl = () => {
    const fullUrl = getCanonicalUrl();
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(fullUrl);
      setCopiedLink(true);
      setTimeout(() => setCopiedLink(false), 2500);
      onNotify?.("Episode link copied to clipboard!", "success");
    } else {
      const temp = document.createElement("input");
      temp.value = fullUrl;
      document.body.appendChild(temp);
      temp.select();
      document.execCommand("copy");
      document.body.removeChild(temp);
      setCopiedLink(true);
      setTimeout(() => setCopiedLink(false), 2500);
      onNotify?.("Episode link copied to clipboard!", "success");
    }
  };

  const triggerNativeShare = () => {
    const fullUrl = getCanonicalUrl();
    const title = page?.title || "Watch & Download Episodes";
    if (navigator.share) {
      navigator
        .share({
          title,
          text: `Watch & Download: ${title}`,
          url: fullUrl,
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

  const toggleAdminStatus = async () => {
    if (!page || !isAdmin) return;
    try {
      const res = await fetch("/api/pages/toggle-status", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${effectiveToken}`,
          "x-admin-token": effectiveToken,
        },
        body: JSON.stringify({ id: page.id }),
      });
      const data = await res.json();
      if (data.success && data.page) {
        const isPub = Number(data.page.is_public) === 1;
        setPage({ ...page, is_public: isPub ? 1 : 0 });
        onNotify?.(
          isPub ? "Page visibility changed to Public" : "Page visibility changed to Private",
          isPub ? "success" : "info"
        );
      } else {
        onNotify?.("Could not update status: " + (data.error || "Unknown error"), "error");
      }
    } catch (err: any) {
      onNotify?.("Request failed: " + err.message, "error");
    }
  };

  const resolveServerInfo = (provider?: string, url?: string, btnText?: string) => {
    const haystack = `${provider || ""} ${url || ""} ${btnText || ""}`.toLowerCase();

    // 1. Google Drive / GDrive
    if (
      haystack.includes("gdrive") ||
      haystack.includes("google drive") ||
      haystack.includes("drive.google") ||
      haystack.includes("gd ")
    ) {
      const name = provider && provider.toLowerCase() !== "fastdl" ? provider : "GDrive Fast Server";
      const icon = (
        <svg className="w-4 h-4 shrink-0" viewBox="0 0 87.3 78" fill="none">
          <path d="m6.6 66.85 3.85 6.65c.8 1.4 1.95 2.5 3.3 3.3l13.75-23.8H0c0 1.55.4 3.1 1.2 4.5z" fill="#0066da" />
          <path d="M43.65 25 29.9 1.2c-1.35.8-2.5 1.9-3.3 3.3l-25.4 44C.4 50 0 51.55 0 53.1h27.5z" fill="#00ac47" />
          <path d="M73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l1.6-2.75 7.65-13.25c.8-1.4 1.2-2.95 1.2-4.5H59.8l5.85 10.1z" fill="#ea4335" />
          <path d="M43.65 25 57.4 1.2C56.05.4 54.5 0 52.95 0H34.35c-1.55 0-3.1.4-4.45 1.2z" fill="#00832d" />
          <path d="m59.8 53.1-16.15-28-16.15 28z" fill="#2684fc" />
          <path d="M73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l-13.75-23.8H35.65l13.75 23.8c1.35.8 2.9 1.2 4.45 1.2h15.25c1.55 0 3.1-.4 4.45-1.1z" fill="#ffba00" />
        </svg>
      );
      return { name, icon };
    }

    // 2. xcloud / Cloud / OneDrive
    if (
      haystack.includes("xcloud") ||
      haystack.includes("cloud") ||
      haystack.includes("onedrive") ||
      haystack.includes("azure")
    ) {
      const name = provider || "xcloud Mirror";
      const icon = (
        <svg className="w-4 h-4 text-[#0b57d0] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 00-9.78 2.096A4.001 4.001 0 003 15z" />
        </svg>
      );
      return { name, icon };
    }

    // 3. Mega / Mediafire / Fast Server
    if (haystack.includes("mega") || haystack.includes("mediafire")) {
      const name = provider || "High Speed Mirror";
      const icon = (
        <svg className="w-4 h-4 text-[#d93025] shrink-0" fill="currentColor" viewBox="0 0 24 24">
          <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 14h-2v-2h2v2zm0-4h-2V7h2v5z" />
        </svg>
      );
      return { name, icon };
    }

    // Default Fast Server
    const name = provider || "Fast Server";
    const icon = (
      <svg className="w-4 h-4 text-[#0b57d0] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 12h14M12 5l7 7-7 7" />
      </svg>
    );
    return { name, icon };
  };

  if (maintenanceSettings.enabled && !isAdmin) {
    return (
      <div className="bg-[#f8fafd] text-[#1f1f1f] min-h-screen flex items-center justify-center p-4 sm:p-6 selection:bg-[#d3e3fd] select-none">
        <div className="max-w-lg w-full bg-white rounded-3xl border border-[#e0e4eb] p-6 sm:p-8 shadow-xs text-center space-y-6 relative overflow-hidden">
          <div className="relative w-full aspect-[4/3] rounded-2xl overflow-hidden bg-[#fafbfc] border border-[#f0f4f9]">
            <img
              src="/assets/images/maintenance_illustration.jpg"
              alt="Under Maintenance"
              className="relative z-10 w-full h-full object-cover bg-[#fafbfc] transition-transform hover:scale-105 duration-700"
              referrerPolicy="no-referrer"
            />
          </div>

          <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#fef7e0] border border-[#feebc8] text-[#b06000] text-xs font-bold font-mono">
            <span className="w-2 h-2 rounded-full bg-[#b06000] animate-ping"></span>
            <span>SYSTEM MAINTENANCE</span>
          </div>

          <div className="space-y-2">
            <h1 className="text-xl sm:text-2xl font-extrabold tracking-tight text-[#111827]">
              We'll Be Right Back
            </h1>
            <p className="text-xs sm:text-sm text-[#444746] leading-relaxed max-w-md mx-auto">
              {maintenanceSettings.message ||
                "The website is currently undergoing scheduled maintenance. We will be back shortly!"}
            </p>
          </div>

          <div className="bg-[#f8fafd] rounded-2xl p-4 sm:p-5 border border-[#e1e7f0] space-y-3 shadow-2xs">
            <div className="flex items-center justify-between text-xs font-semibold text-[#5f6368]">
              <span className="flex items-center gap-1.5 text-[#0b57d0] font-bold">
                <svg className="w-4 h-4 text-[#0b57d0]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <circle cx="12" cy="12" r="10" strokeWidth="2" />
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6v6l4 2" />
                </svg>
                <span>Estimated Time Remaining</span>
              </span>
              <span className="text-[10px] font-mono font-bold px-2 py-0.5 rounded-full bg-[#e8f0fe] text-[#0b57d0]">
                LIVE COUNTDOWN
              </span>
            </div>

            <div className="grid grid-cols-4 gap-2 text-center font-mono">
              <div className="bg-white rounded-xl p-2 sm:p-3 border border-[#dadce0] shadow-2xs">
                <div className="text-lg sm:text-2xl font-black text-[#0b57d0]">{maintCountdown.days}</div>
                <div className="text-[9px] sm:text-[10px] text-[#5f6368] font-sans font-semibold uppercase mt-0.5">Days</div>
              </div>
              <div className="bg-white rounded-xl p-2 sm:p-3 border border-[#dadce0] shadow-2xs">
                <div className="text-lg sm:text-2xl font-black text-[#0b57d0]">{maintCountdown.hours}</div>
                <div className="text-[9px] sm:text-[10px] text-[#5f6368] font-sans font-semibold uppercase mt-0.5">Hours</div>
              </div>
              <div className="bg-white rounded-xl p-2 sm:p-3 border border-[#dadce0] shadow-2xs">
                <div className="text-lg sm:text-2xl font-black text-[#0b57d0]">{maintCountdown.mins}</div>
                <div className="text-[9px] sm:text-[10px] text-[#5f6368] font-sans font-semibold uppercase mt-0.5">Minutes</div>
              </div>
              <div className="bg-white rounded-xl p-2 sm:p-3 border border-[#dadce0] shadow-2xs">
                <div className="text-lg sm:text-2xl font-black text-[#0b57d0]">{maintCountdown.secs}</div>
                <div className="text-[9px] sm:text-[10px] text-[#5f6368] font-sans font-semibold uppercase mt-0.5">Seconds</div>
              </div>
            </div>

            <div className="text-[11px] text-[#5f6368] font-medium text-center">
              Target End Time: <span className="font-bold text-[#1f1f1f]">{maintCountdown.formattedTarget}</span>
            </div>
          </div>
        </div>
      </div>
    );
  }

  if (loading) {
    return (
      <div className="min-h-screen bg-[#f8fafd] flex items-center justify-center p-6">
        <div className="text-center space-y-3">
          <div className="inline-block w-8 h-8 border-3 border-[#0b57d0] border-t-transparent rounded-full animate-spin"></div>
          <p className="text-sm font-medium text-[#444746]">Loading episode buttons...</p>
        </div>
      </div>
    );
  }

  const renderPublicErrorScreen = (actualTitle: string, actualErrorMsg: string, httpCode = 404) => {
    const safeTitle = httpCode === 404 ? "Episode Link Not Found" : "Service Temporarily Unavailable";
    const safeDesc =
      httpCode === 404
        ? "The episode link you followed does not exist, may have been moved or expired, or is currently set to private."
        : "Something went wrong while loading this page. Please try again in a moment.";
    const displayTitle = debugMode ? actualTitle : safeTitle;
    const displayMsg = safeDesc;
    const siteNameStr = siteIdentity.site_name || "Movie Hub HQ Drive";
    const homeUrl = menuItems[0]?.url && menuItems[0].url !== "#" ? menuItems[0].url : "https://moviehubhq.com/";
    const reqPath =
      typeof window !== "undefined" ? window.location.pathname + window.location.search : `/p/${slug}`;
    const statusKicker =
      httpCode === 404 ? "HTTP 404 · GATEWAY ROUTE NOT FOUND" : `HTTP ${httpCode} · GATEWAY RUNTIME ERROR`;

    const handleEpisodeJumpSubmit = (e: React.FormEvent) => {
      e.preventDefault();
      const raw = jumpSlugInput.trim();
      if (!raw) return;
      let targetSlug = raw;
      const pMatch = raw.match(/\/(?:p|page)\/([a-zA-Z0-9_-]+)/i);
      const qMatch = raw.match(/[?&](?:slug|p)=([a-zA-Z0-9_-]+)/i);
      if (pMatch && pMatch[1]) {
        targetSlug = pMatch[1];
      } else if (qMatch && qMatch[1]) {
        targetSlug = qMatch[1];
      } else {
        targetSlug = raw
          .replace(/^https?:\/\/[^/]+\/?/i, "")
          .replace(/^\/+|\/+$/g, "")
          .replace(/[^a-zA-Z0-9_-]/g, "");
      }
      if (!targetSlug) return;
      if (onNavigateSlug) {
        onNavigateSlug(targetSlug);
      } else {
        window.location.href = `/p/${encodeURIComponent(targetSlug)}`;
      }
    };

    const navigateLegal = (e: React.MouseEvent, targetLegal: string) => {
      if (onNavigateSlug) {
        e.preventDefault();
        onNavigateSlug(targetLegal);
      }
    };

    return (
      <div className="min-h-screen flex flex-col bg-[#f4f6fb] text-[#0f172a] font-sans antialiased">
        {/* Admin Session Bar (Only shown when logged in as admin) */}
        {isAdmin && onBackToAdmin && (
          <div className="bg-[#0f172a] text-[#f8fafc] px-4 py-2 text-xs border-b border-[#1e293b]">
            <div className="max-w-[980px] mx-auto flex items-center justify-between gap-3 flex-wrap">
              <span className="font-mono text-[11px] text-[#94a3b8]">
                Admin Session Active · HTTP {httpCode} Response
              </span>
              <div className="flex items-center gap-3">
                <button
                  type="button"
                  onClick={onBackToAdmin}
                  className="text-[#f8fafc] bg-[#1e293b] hover:bg-[#334155] px-2.5 py-1 rounded-md border border-[#334155] font-semibold cursor-pointer transition-colors"
                >
                  ← Admin Dashboard
                </button>
              </div>
            </div>
          </div>
        )}

        {/* Site Header */}
        <header className="bg-white border-b border-[#e2e8f0] sticky top-0 z-30">
          <div className="max-w-[980px] mx-auto px-5 py-3 flex items-center justify-between gap-4 flex-wrap">
            <a href={homeUrl} className="flex items-center gap-2.5 text-[#0f172a] font-extrabold text-[15px] no-underline">
              <div className="w-9 h-9 rounded-[10px] bg-[#0f172a] text-white flex items-center justify-center text-xs font-extrabold overflow-hidden shrink-0">
                {siteIdentity.site_logo_url ? (
                  <img
                    src={siteIdentity.site_logo_url}
                    alt={siteNameStr}
                    className="w-full h-full object-contain"
                  />
                ) : (
                  <span>{(siteIdentity.site_logo_text || "MHQ").slice(0, 3)}</span>
                )}
              </div>
              <span>{siteNameStr}</span>
            </a>
            <nav className="flex items-center gap-4 flex-wrap">
              {menuItems.map((item, idx) => (
                <a
                  key={idx}
                  href={item.url || "#"}
                  target={item.new_tab || item.target_blank ? "_blank" : undefined}
                  rel={item.new_tab || item.target_blank ? "noopener noreferrer" : undefined}
                  className="text-[#475569] hover:text-[#0b57d0] text-[13px] font-semibold transition-colors"
                >
                  {item.title}
                </a>
              ))}
            </nav>
          </div>
        </header>

        {/* Main 404 Content Area */}
        <main className="flex-1 flex items-center justify-center px-5 py-9">
          <div className="max-w-[760px] w-full bg-white border border-[#dce3f0] rounded-[20px] overflow-hidden shadow-[0_12px_32px_-12px_rgba(15,23,42,0.08)]">
            {/* Dark Hero Banner */}
            <div className="bg-gradient-to-br from-[#0f172a] to-[#1e293b] text-[#f8fafc] px-6 sm:px-8 py-7 flex items-center justify-between gap-5 flex-wrap border-b border-[#1e293b]">
              <div className="flex-1 min-w-[240px]">
                <div
                  className={`font-mono text-[11px] font-bold tracking-wider mb-2 flex items-center gap-2 ${
                    httpCode === 404 ? "text-[#38bdf8]" : "text-[#fb7185]"
                  }`}
                >
                  <span
                    className={`w-1.5 h-1.5 rounded-full inline-block ${
                      httpCode === 404 ? "bg-[#38bdf8]" : "bg-[#fb7185]"
                    }`}
                  />
                  <span>{statusKicker}</span>
                </div>
                <h1 className="text-xl sm:text-2xl font-extrabold text-white tracking-tight leading-snug mb-1.5">
                  {displayTitle}
                </h1>
                <div className="font-mono text-xs text-[#94a3b8] break-all">
                  Requested Path: {reqPath}
                </div>
              </div>
              <div className="font-mono text-4xl sm:text-[54px] font-extrabold leading-none tracking-tighter text-[#38bdf8] bg-[#38bdf8]/10 border border-[#38bdf8]/25 px-5 py-3.5 rounded-2xl select-none">
                {httpCode}
              </div>
            </div>

            {/* Functional Body Area */}
            <div className="p-6 sm:p-8">
              <p className="text-sm leading-relaxed text-[#475569] mb-6">{displayMsg}</p>

              {/* Direct Episode Lookup Form */}
              <form
                onSubmit={handleEpisodeJumpSubmit}
                className="bg-[#f8fafc] border border-[#e2e8f0] rounded-[14px] p-4 sm:p-5 mb-6"
              >
                <label
                  htmlFor="epCodeInputReact"
                  className="block text-xs font-bold text-[#1e293b] mb-2"
                >
                  Open Episode Page by Slug or Link
                </label>
                <div className="flex gap-2.5 flex-wrap">
                  <input
                    id="epCodeInputReact"
                    type="text"
                    value={jumpSlugInput}
                    onChange={(e) => setJumpSlugInput(e.target.value)}
                    placeholder="Enter episode slug (e.g. flp-120926) or paste /p/ link..."
                    autoComplete="off"
                    className="flex-1 min-w-[200px] px-3.5 py-2.5 rounded-[10px] border border-[#cbd5e1] bg-white font-mono text-[13px] text-[#0f172a] outline-none focus:border-[#0b57d0] focus:ring-2 focus:ring-[#0b57d0]/15 transition-all"
                  />
                  <button
                    type="submit"
                    className="px-5 py-2.5 rounded-[10px] bg-[#0b57d0] hover:bg-[#0842a0] text-white text-[13px] font-bold cursor-pointer transition-colors whitespace-nowrap"
                  >
                    Open Episode →
                  </button>
                </div>
                <div className="text-[11px] text-[#64748b] mt-2">
                  If you have a valid episode code or mistyped the URL, enter it above to jump directly to the download page.
                </div>
              </form>

              {/* Navigation & Recovery Actions */}
              <div className="flex items-center gap-2.5 flex-wrap pb-5 border-b border-[#f1f5f9]">
                <a
                  href={homeUrl}
                  className="inline-flex items-center gap-2 px-4 py-2.5 rounded-[10px] bg-[#0f172a] hover:bg-[#1e293b] text-white text-[13px] font-bold transition-colors"
                >
                  <span>Return to Main Website</span>
                  <span aria-hidden="true">↗</span>
                </a>
                <button
                  type="button"
                  onClick={() => {
                    if (window.history.length > 1) {
                      window.history.back();
                    } else {
                      window.location.href = homeUrl;
                    }
                  }}
                  className="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-[10px] bg-[#f1f5f9] hover:bg-[#e2e8f0] text-[#334155] hover:text-[#0f172a] border border-[#e2e8f0] text-[13px] font-semibold cursor-pointer transition-colors"
                >
                  <span>← Go Back</span>
                </button>
                <button
                  type="button"
                  onClick={() => window.location.reload()}
                  className="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-[10px] bg-[#f1f5f9] hover:bg-[#e2e8f0] text-[#334155] hover:text-[#0f172a] border border-[#e2e8f0] text-[13px] font-semibold cursor-pointer transition-colors"
                >
                  <span>↻ Try Again</span>
                </button>
              </div>

              {/* Browse Website Sections */}
              <div className="mt-5">
                <div className="text-[11px] font-bold text-[#64748b] uppercase tracking-wider mb-2.5">
                  Browse Website Sections
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
                  {menuItems.map((item, idx) => (
                    <a
                      key={idx}
                      href={item.url || "#"}
                      target={item.new_tab || item.target_blank ? "_blank" : undefined}
                      rel={item.new_tab || item.target_blank ? "noopener noreferrer" : undefined}
                      className="flex items-center justify-between px-3.5 py-2.5 rounded-[10px] bg-[#f8fafc] border border-[#e2e8f0] hover:border-[#0b57d0] hover:bg-[#f0f6ff] text-[#1e293b] hover:text-[#0b57d0] text-xs font-semibold transition-all"
                    >
                      <span>{item.title}</span>
                      <span aria-hidden="true">→</span>
                    </a>
                  ))}
                </div>
              </div>

              {/* Developer Diagnostics (Debug Mode ON) */}
              {debugMode && (
                <div className="mt-6 bg-[#0f172a] border border-[#334155] rounded-[14px] p-4 text-[#e2e8f0] font-mono text-[11px] leading-relaxed text-left space-y-2">
                  <div className="flex items-center justify-between gap-2 flex-wrap font-bold text-[#fda4af] uppercase tracking-wider">
                    <span>Developer Diagnostics (Debug Mode: ON)</span>
                    <span className="bg-[#ef4444] text-white px-2 py-0.5 rounded text-[10px]">
                      HTTP {httpCode}
                    </span>
                  </div>
                  <div className="bg-[#1e293b] border border-[#475569] rounded-lg px-3 py-2.5 text-[#fecdd3] break-words">
                    <strong>Actual Error:</strong> {actualErrorMsg}
                  </div>
                  <div className="text-[#94a3b8] space-y-1">
                    <div>
                      <strong className="text-[#cbd5e1]">Request:</strong> GET {reqPath}
                    </div>
                    <div>
                      <strong className="text-[#cbd5e1]">Handler:</strong> view.php ·{" "}
                      <strong className="text-[#cbd5e1]">Debug Mode:</strong> Enabled
                    </div>
                    <div>
                      <strong className="text-[#cbd5e1]">Timestamp:</strong> {new Date().toISOString()}
                    </div>
                  </div>
                </div>
              )}
            </div>
          </div>
        </main>

        {/* Site Footer */}
        <footer className="p-5 text-center text-xs text-[#64748b] border-t border-[#e2e8f0] bg-white">
          <div className="flex items-center justify-center gap-3.5 flex-wrap mb-2">
            <a
              href="/dmca"
              onClick={(e) => navigateLegal(e, "dmca")}
              className="text-[#475569] hover:text-[#0b57d0] font-medium"
            >
              DMCA
            </a>
            <span>·</span>
            <a
              href="/disclaimer"
              onClick={(e) => navigateLegal(e, "disclaimer")}
              className="text-[#475569] hover:text-[#0b57d0] font-medium"
            >
              Disclaimer
            </a>
            <span>·</span>
            <a
              href="/about-us"
              onClick={(e) => navigateLegal(e, "about-us")}
              className="text-[#475569] hover:text-[#0b57d0] font-medium"
            >
              About Us
            </a>
            <span>·</span>
            <a
              href="/privacy-policy"
              onClick={(e) => navigateLegal(e, "privacy-policy")}
              className="text-[#475569] hover:text-[#0b57d0] font-medium"
            >
              Privacy Policy
            </a>
          </div>
          <div dangerouslySetInnerHTML={{ __html: footerText }} />
        </footer>
      </div>
    );
  };

  // Error screen controlled by Debug Mode (Off = generic safe message, On = actual error reason)
  if (error || !page) {
    return renderPublicErrorScreen(
      errorTitle,
      error || `Actual Error [HTTP 404]: No episode page record matched slug '${slug}' in the database.`,
      errorHttpCode
    );
  }

  const isPublic = page.is_public === undefined ? true : Boolean(Number(page.is_public));
  if (!isPublic && !isAdmin) {
    return renderPublicErrorScreen(
      "403 / 404 - Private Episode Page Restricted",
      `Actual Error [Private Page]: Episode page '${slug}' exists in the database, but its visibility is set to Private (is_public = 0) and visitor is not logged in as administrator.`,
      404
    );
  }

  const themeObj = THEMES[page.theme || "indigo"] || THEMES.indigo;
  const siteName = siteIdentity.site_name || "Movie Hub HQ Drive";
  const canonicalUrl = getCanonicalUrl();
  const shareEncodedUrl = encodeURIComponent(canonicalUrl);
  const shareEncodedTitle = encodeURIComponent(page.title || "Watch & Download Episodes");
  const shareEncodedMsg = encodeURIComponent(`${page.title || "Watch & Download"} - Fast Episode Links: ${canonicalUrl}`);

  return (
    <div
      className="min-h-screen flex flex-col antialiased selection:bg-[#d3e3fd] selection:text-[#041e49]"
      style={{ backgroundColor: themeObj.bg, color: "#1f1f1f" }}
    >
      {/* Admin Quick Controls Bar (Only shown when active admin account logged-in state is found) */}
      {isAdmin && !legalSlug && (
        <div className="w-full bg-white text-[#1f1f1f] border-b border-[#e0e4eb] px-3 sm:px-4 py-2 text-xs z-50 shadow-xs">
          <div className="max-w-4xl mx-auto flex flex-row flex-nowrap items-center justify-between gap-3 overflow-x-auto whitespace-nowrap">
            <div className="flex flex-row flex-nowrap items-center gap-2.5 sm:gap-3 shrink-0">
              <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-[#e8f0fe] text-[#0b57d0] border border-[#c2e7ff] uppercase tracking-wider shrink-0">
                <span className="w-1.5 h-1.5 rounded-full bg-[#137333] animate-pulse" />
                Admin View
              </span>
              <div className="flex items-center gap-1.5 sm:gap-2 bg-[#f8fafd] px-2.5 py-1 rounded-xl border border-[#e0e4eb] shrink-0">
                <span className="text-[11px] text-[#444746] font-semibold">Visibility:</span>
                <button
                  type="button"
                  onClick={toggleAdminStatus}
                  role="switch"
                  aria-checked={isPublic}
                  title="Click to toggle Public / Private visibility"
                  className={`relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none ${
                    isPublic ? "bg-[#137333]" : "bg-slate-300"
                  }`}
                >
                  <span
                    className={`pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
                      isPublic ? "translate-x-4" : "translate-x-0"
                    }`}
                  />
                </button>
                <span
                  className={`px-2 py-0.5 rounded text-[10px] font-bold ${
                    isPublic
                      ? "bg-[#e6f4ea] text-[#137333] border border-[#a8dab5]"
                      : "bg-[#fff0d4] text-[#b06000] border border-[#ffd599]"
                  }`}
                >
                  {isPublic ? "Public" : "Private"}
                </span>
              </div>
              <span className="text-[#c4c7c5]">•</span>
              <span className="text-[#444746] font-mono text-[11px] font-medium shrink-0">
                👁️ {Number(page.views || 0).toLocaleString()} views
              </span>
            </div>
            <div className="flex flex-row flex-nowrap items-center gap-2 shrink-0">
              <button
                type="button"
                onClick={() => (onEditPage ? onEditPage(page.id) : onBackToAdmin?.())}
                className="px-3 py-1.5 rounded-xl bg-[#0b57d0] hover:bg-[#0842a0] text-white font-semibold text-xs transition-colors flex items-center gap-1 cursor-pointer shadow-2xs"
              >
                <span>✏️ Edit Page</span>
              </button>
              <button
                type="button"
                onClick={() => (onBackToAdmin ? onBackToAdmin() : (window.location.href = "/"))}
                className="px-3 py-1.5 rounded-xl bg-[#f0f4f9] hover:bg-[#e8f0fe] text-[#1f1f1f] hover:text-[#0b57d0] font-semibold text-xs transition-colors border border-[#e0e4eb] cursor-pointer shadow-2xs"
              >
                Pages Manager
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Public Header Navigation Bar (Google Material M3 Light Theme - No links or buttons to admin/private pages) */}
      <header className="w-full bg-white/95 border-b border-[#e1e7f0] sticky top-0 z-40 backdrop-blur-md shadow-2xs">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 py-3 sm:py-3.5 flex items-center justify-between gap-3 min-w-0">
          {/* Left Header: Mobile 3-Line Hamburger Button + Site Logo/Name */}
          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={() => setMobileMenuOpen((prev) => !prev)}
              aria-label="Toggle navigation menu"
              className="md:hidden p-2 rounded-xl bg-[#f0f4f9] border border-[#e1e7f0] text-[#1f1f1f] hover:bg-[#e8f0fe] hover:text-[#0b57d0] focus:outline-none cursor-pointer transition-colors"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
              </svg>
            </button>

            <a href="#top" onClick={(e) => e.preventDefault()} className="flex items-center group no-underline">
              {siteIdentity.site_logo_url ? (
                <img
                  src={siteIdentity.site_logo_url}
                  alt={siteName}
                  className="h-8 max-w-[180px] object-contain"
                />
              ) : (
                <span className="font-bold text-base sm:text-lg tracking-tight text-[#111827] group-hover:text-[#0b57d0] transition-colors">
                  {siteName}
                </span>
              )}
            </a>
          </div>

          {/* Right Header: Desktop Navigation Menu Buttons (Google Material M3 Chips) */}
          <nav className="hidden md:flex flex-row items-center gap-2">
            {menuItems.map((item, idx) => {
              const openNewTab = Boolean(item.new_tab || item.target_blank);
              return (
                <a
                  key={idx}
                  href={item.url || "#"}
                  target={openNewTab ? "_blank" : undefined}
                  rel={openNewTab ? "noopener noreferrer" : undefined}
                  className="px-3.5 py-1.5 rounded-full text-xs font-semibold text-[#444746] hover:text-[#0b57d0] bg-[#f0f4f9] hover:bg-[#e8f0fe] border border-[#e1e7f0] hover:border-[#c2e7ff] shadow-2xs transition-all whitespace-nowrap"
                >
                  {item.title}
                </a>
              );
            })}
          </nav>
        </div>
      </header>

      {/* Mobile Sidebar Drawer (Opens from Left Side, Occupies Half of the Page - Matches cpanel-package/view.php lines 494-536) */}
      {mobileMenuOpen && (
        <div
          onClick={() => setMobileMenuOpen(false)}
          className="fixed inset-0 bg-black/40 backdrop-blur-xs z-50 transition-opacity duration-300 md:hidden"
          aria-hidden="true"
        />
      )}

      <aside
        className={`fixed inset-y-0 left-0 z-50 w-1/2 min-w-[250px] max-w-[340px] h-full bg-white border-r border-[#e1e7f0] shadow-2xl flex flex-col transform transition-transform duration-300 ease-in-out md:hidden ${
          mobileMenuOpen ? "translate-x-0" : "-translate-x-full"
        }`}
        aria-label="Mobile Navigation Drawer"
      >
        <div className="p-4 border-b border-[#f0f4f9] flex items-center justify-between shrink-0">
          <div className="flex items-center min-w-0">
            {siteIdentity.site_logo_url ? (
              <img
                src={siteIdentity.site_logo_url}
                alt={siteName}
                className="h-7 max-w-[140px] object-contain"
              />
            ) : (
              <span className="font-bold text-sm tracking-tight text-[#111827] truncate">
                {siteName}
              </span>
            )}
          </div>
          <button
            type="button"
            onClick={() => setMobileMenuOpen(false)}
            className="p-1.5 rounded-xl text-[#5f6368] hover:text-[#111827] hover:bg-[#f0f4f9] transition-colors cursor-pointer"
            aria-label="Close menu"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <nav className="flex-1 overflow-y-auto p-4 flex flex-col space-y-2">
          {menuItems.map((item, idx) => (
            <a
              key={idx}
              href={item.url || "#"}
              target={item.new_tab !== false ? "_blank" : undefined}
              rel={item.new_tab !== false ? "noopener noreferrer" : undefined}
              onClick={() => setMobileMenuOpen(false)}
              className="flex items-center justify-between px-4 py-3 rounded-2xl text-xs sm:text-sm font-semibold text-[#1f1f1f] hover:text-[#0b57d0] bg-[#f8fafd] hover:bg-[#e8f0fe] border border-[#e0e4eb] hover:border-[#c2e7ff] shadow-2xs transition-all"
            >
              <span>{item.title}</span>
              <svg className="w-4 h-4 text-[#5f6368] opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7" />
              </svg>
            </a>
          ))}
        </nav>

        <div className="p-4 border-t border-[#f0f4f9] text-[11px] text-[#747775] text-center shrink-0">
          {siteName}
        </div>
      </aside>

      {/* Main Episode Content Area (Google Material M3 Light Theme - Matches cpanel-package/view.php lines 539-788) */}
      <main className="flex-1 w-full max-w-2xl mx-auto px-4 sm:px-6 py-6 sm:py-8 space-y-5">
        {legalSlug === "dmca" ? (
          <article className="bg-white border border-[#e0e4eb] rounded-3xl p-6 sm:p-8 shadow-xs space-y-5 text-[#1f1f1f]">
            <div className="space-y-2 border-b border-[#f0f4f9] pb-4">
              <span className="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-bold bg-[#e8f0fe] text-[#0b57d0] border border-[#c2e7ff]">
                DMCA COPYRIGHT POLICY
              </span>
              <h1 className="text-xl sm:text-2xl font-extrabold text-[#111827] tracking-tight">
                Movie Hub HQ – DMCA Policy
              </h1>
              <p className="text-xs text-[#5f6368]">
                We respect creative work and respond promptly to valid copyright removal requests.
              </p>
            </div>

            <div className="space-y-4 text-xs sm:text-sm text-[#444746] leading-relaxed">
              <p>
                At <strong className="text-[#111827]">Movie Hub HQ</strong> (
                <a href="https://moviehubhq.com" className="text-[#0b57d0] hover:underline font-medium">
                  https://moviehubhq.com
                </a>
                ), we deeply respect the hard work of content creators, studios, and copyright holders around the world.
                We comply fully with the Digital Millennium Copyright Act (17 U.S.C. § 512) and promptly investigate
                every legitimate copyright notice we receive.
              </p>
              <p>
                Please note that <strong className="text-[#111827]">Movie Hub HQ</strong> does not host media files on
                its own servers; our pages only index and organize publicly available third-party links. However, if you
                are a copyright owner (or authorized representative) and believe that any link or content on our website
                points to material that infringes your copyright, simply reach out to us and we will remove it as
                quickly as possible.
              </p>
              <div className="space-y-2">
                <h2 className="text-sm sm:text-base font-bold text-[#111827]">
                  What to include in your takedown request:
                </h2>
                <p className="text-xs text-[#5f6368]">
                  To help us locate and remove the content right away, please include these details in your email:
                </p>
                <ul className="list-disc pl-5 space-y-2">
                  <li>
                    <strong className="text-[#111827]">Who you are:</strong> Your full name, organization (if
                    applicable), mailing address, and a valid email address where we can reply to you.
                  </li>
                  <li>
                    <strong className="text-[#111827]">Proof of authority:</strong> A brief confirmation that you are
                    the copyright owner or are legally authorized to act on the owner’s behalf.
                  </li>
                  <li>
                    <strong className="text-[#111827]">Exact link(s) to remove:</strong> The specific URL(s) or search
                    terms on <strong className="text-[#111827]">Movie Hub HQ</strong> (
                    <span className="font-mono text-xs">moviehubhq.com</span>) where the material appears.
                  </li>
                  <li>
                    <strong className="text-[#111827]">Good-faith statement:</strong> A statement confirming that you
                    believe in good faith that the disputed use is not authorized by the copyright owner, its agent, or
                    the law.
                  </li>
                  <li>
                    <strong className="text-[#111827]">Accuracy confirmation:</strong> A statement that the information
                    in your notice is accurate and, under penalty of perjury, that you are authorized to act for the
                    copyright owner, along with your physical or electronic signature.
                  </li>
                </ul>
              </div>
              <div className="bg-[#f8fafd] border border-[#e0e4eb] rounded-2xl p-4 space-y-1.5">
                <p className="font-bold text-[#111827]">
                  📧 Send DMCA notices directly to:{" "}
                  <a href="mailto:contact@moviehubhq.com" className="text-[#0b57d0] hover:underline font-mono">
                    contact@moviehubhq.com
                  </a>
                </p>
                <p className="text-xs text-[#5f6368]">
                  We review all requests personally and typically respond within <strong>1 to 3 business days</strong>.
                  Sending your notice directly to this email is the fastest way to get a link removed.
                </p>
              </div>
            </div>
          </article>
        ) : legalSlug === "disclaimer" ? (
          <article className="bg-white border border-[#e0e4eb] rounded-3xl p-6 sm:p-8 shadow-xs space-y-5 text-[#1f1f1f]">
            <div className="space-y-2 border-b border-[#f0f4f9] pb-4">
              <span className="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-bold bg-[#e8f0fe] text-[#0b57d0] border border-[#c2e7ff]">
                DISCLAIMER
              </span>
              <h1 className="text-xl sm:text-2xl font-extrabold text-[#111827] tracking-tight">
                Disclaimer for Movie Hub HQ
              </h1>
              <p className="text-xs text-[#5f6368]">
                Clear, plain-English information about how our site works and what to expect.
              </p>
            </div>

            <div className="space-y-4 text-xs sm:text-sm text-[#444746] leading-relaxed">
              <div className="space-y-1.5">
                <h2 className="text-sm sm:text-base font-bold text-[#111827]">1. General Information</h2>
                <p>
                  Everything shared on <strong className="text-[#111827]">Movie Hub HQ</strong> (
                  <a href="https://moviehubhq.com" className="text-[#0b57d0] hover:underline font-medium">
                    https://moviehubhq.com
                  </a>
                  ) is provided in good faith for general entertainment and informational purposes. While we work hard
                  to keep episode guides and links organized and up to date, we cannot guarantee that every piece of
                  information on the site is always 100% complete, accurate, or available at all times.
                </p>
              </div>

              <div className="space-y-1.5">
                <h2 className="text-sm sm:text-base font-bold text-[#111827]">
                  2. External Links &amp; Third-Party Servers
                </h2>
                <p>
                  Our pages include links that take you to external cloud drives, video mirrors, or third-party
                  websites. We do not own, control, or host those external servers. Because third-party sites can change
                  their content or policies at any time without notice,{" "}
                  <strong className="text-[#111827]">Movie Hub HQ</strong> cannot endorse or take responsibility for the
                  content, privacy practices, or availability of any external website you visit after leaving our page.
                </p>
              </div>

              <div className="space-y-1.5">
                <h2 className="text-sm sm:text-base font-bold text-[#111827]">3. No Professional or Legal Advice</h2>
                <p>
                  The content on this site is meant purely for entertainment and general reference. Nothing on{" "}
                  <strong className="text-[#111827]">Movie Hub HQ</strong> should be taken as legal, financial, or
                  professional advice. Any action you take based on the information found on our website is strictly at
                  your own discretion and risk.
                </p>
              </div>

              <div className="space-y-1.5">
                <h2 className="text-sm sm:text-base font-bold text-[#111827]">4. Advertising &amp; Affiliate Links</h2>
                <p>
                  To keep <strong className="text-[#111827]">Movie Hub HQ</strong> free for drama fans, our pages may
                  display third-party advertisements or occasional affiliate links. If you click on an advertiser’s link
                  or make a purchase on a partner site, we may earn a small commission at no extra cost to you.
                </p>
              </div>

              <div className="space-y-1.5">
                <h2 className="text-sm sm:text-base font-bold text-[#111827]">5. Errors, Omissions &amp; Fair Use</h2>
                <p>
                  Even though we double-check our posts, broken links or typos can occasionally happen. All content on{" "}
                  <strong className="text-[#111827]">Movie Hub HQ</strong> is provided on an “as-is” basis without
                  warranties of any kind. Any poster thumbnails, titles, or drama descriptions belong to their
                  respective copyright owners and are used strictly for identification and review commentary under fair
                  use principles.
                </p>
              </div>

              <div className="space-y-1.5">
                <h2 className="text-sm sm:text-base font-bold text-[#111827]">6. Limitation of Liability</h2>
                <p>
                  Under no circumstances shall <strong className="text-[#111827]">Movie Hub HQ</strong> or its team be
                  held liable for any direct, indirect, or incidental damages resulting from your use of the website or
                  reliance on any external links shared here.
                </p>
              </div>

              <div className="bg-[#f8fafd] border border-[#e0e4eb] rounded-2xl p-4 space-y-1">
                <h2 className="text-sm font-bold text-[#111827]">7. Have a Question?</h2>
                <p className="text-xs sm:text-sm text-[#444746]">
                  If you ever have questions about this Disclaimer or want to report a broken link, feel free to email
                  us anytime at:{" "}
                  <a href="mailto:contact@moviehubhq.com" className="text-[#0b57d0] hover:underline font-mono font-bold">
                    contact@moviehubhq.com
                  </a>
                </p>
              </div>
            </div>
          </article>
        ) : legalSlug === "about-us" ? (
          <article className="bg-white border border-[#e0e4eb] rounded-3xl p-6 sm:p-8 shadow-xs space-y-5 text-[#1f1f1f]">
            <div className="space-y-2 border-b border-[#f0f4f9] pb-4">
              <span className="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-bold bg-[#e8f0fe] text-[#0b57d0] border border-[#c2e7ff]">
                ABOUT US
              </span>
              <h1 className="text-xl sm:text-2xl font-extrabold text-[#111827] tracking-tight">
                About Us – Movie Hub HQ
              </h1>
              <p className="text-xs text-[#5f6368]">
                Your friendly destination for Hindi &amp; Urdu dubbed Asian and international dramas.
              </p>
            </div>

            <div className="space-y-4 text-xs sm:text-sm text-[#444746] leading-relaxed">
              <p>
                Welcome to <strong className="text-[#111827]">Movie Hub HQ</strong> (
                <a href="https://moviehubhq.com" className="text-[#0b57d0] hover:underline font-medium">
                  https://moviehubhq.com
                </a>
                )! <strong className="text-[#111827]">Movie Hub HQ</strong> shares{" "}
                <strong className="text-[#111827]">Korean Drama</strong>,{" "}
                <strong className="text-[#111827]">Chinese Drama</strong>,{" "}
                <strong className="text-[#111827]">Turkish Drama</strong>, and other popular international dramas in{" "}
                <strong className="text-[#111827]">Urdu and Hindi Dubbed</strong>. You can easily watch and enjoy any
                drama in clear Hindi Dubbed voice without complicated steps.
              </p>

              <p>
                We created <strong className="text-[#111827]">Movie Hub HQ</strong> for drama lovers who want a clean,
                fast, and mobile-friendly way to find their favorite episodes. Whether you love romantic K-Dramas,
                historical C-Dramas, thrilling Turkish series, or action-packed mini-dramas, we organize episode links
                by quality (480p, 720p, and 1080p HD) across reliable servers so you can start watching in seconds.
              </p>

              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
                <div className="p-4 rounded-2xl bg-[#f8fafd] border border-[#e0e4eb] space-y-1">
                  <div className="text-xs font-extrabold text-[#0b57d0]">🎬 Dubbed Dramas</div>
                  <p className="text-[11px] text-[#5f6368] leading-normal">
                    Korean, Chinese, Turkish &amp; global series in Hindi and Urdu dubbed audio.
                  </p>
                </div>
                <div className="p-4 rounded-2xl bg-[#f8fafd] border border-[#e0e4eb] space-y-1">
                  <div className="text-xs font-extrabold text-[#0b57d0]">⚡ Fast Episode Links</div>
                  <p className="text-[11px] text-[#5f6368] leading-normal">
                    Clean, clutter-free episode button pages that work smoothly on any phone or PC.
                  </p>
                </div>
                <div className="p-4 rounded-2xl bg-[#f8fafd] border border-[#e0e4eb] space-y-1">
                  <div className="text-xs font-extrabold text-[#0b57d0]">💬 Viewer First</div>
                  <p className="text-[11px] text-[#5f6368] leading-normal">
                    We listen to our community and keep episode links updated and easy to access.
                  </p>
                </div>
              </div>

              <p>
                If you have any queries regarding the site, content, advertisements, broken links, or any other issues,
                please feel free to contact us anytime. We’re always happy to hear from our visitors!
              </p>

              <div className="bg-[#f8fafd] border border-[#e0e4eb] rounded-2xl p-4 space-y-1.5">
                <p className="font-bold text-[#111827]">
                  📬 Contact Mail:{" "}
                  <a href="mailto:contact@moviehubhq.com" className="text-[#0b57d0] hover:underline font-mono">
                    contact@moviehubhq.com
                  </a>
                </p>
                <p className="text-xs text-[#5f6368]">
                  Thank you for visiting <strong className="text-[#111827]">Movie Hub HQ</strong> and being part of our
                  drama-loving community!
                </p>
              </div>
            </div>
          </article>
        ) : legalSlug === "privacy-policy" ? (
          <article className="bg-white border border-[#e0e4eb] rounded-3xl p-6 sm:p-8 shadow-xs space-y-5 text-[#1f1f1f]">
            <div className="space-y-2 border-b border-[#f0f4f9] pb-4">
              <span className="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-bold bg-[#e8f0fe] text-[#0b57d0] border border-[#c2e7ff]">
                PRIVACY POLICY
              </span>
              <h1 className="text-xl sm:text-2xl font-extrabold text-[#111827] tracking-tight">
                Privacy Policy for Movie Hub HQ
              </h1>
              <p className="text-xs text-[#5f6368]">
                How we protect your privacy and keep your browsing experience safe.
              </p>
            </div>

            <div className="space-y-4 text-xs sm:text-sm text-[#444746] leading-relaxed">
              <p>
                At <strong className="text-[#111827]">Movie Hub HQ</strong> (
                <a href="https://moviehubhq.com" className="text-[#0b57d0] hover:underline font-medium">
                  https://moviehubhq.com
                </a>
                ), your privacy matters to us. We believe you shouldn’t have to give up your personal life just to watch
                your favorite dramas. This Privacy Policy explains in plain, simple language what basic information is
                collected when you visit our site and how it is used.
              </p>
              <p>
                If you ever have any questions about your privacy or anything in this policy, you can reach out to us
                directly at{" "}
                <a href="mailto:contact@moviehubhq.com" className="text-[#0b57d0] hover:underline font-mono font-bold">
                  contact@moviehubhq.com
                </a>
                .
              </p>

              <div className="space-y-1.5">
                <h2 className="text-sm sm:text-base font-bold text-[#111827]">
                  1. No Account Required &amp; Minimal Data
                </h2>
                <p>
                  You do not need to create an account or give us your name, phone number, or home address to use{" "}
                  <strong className="text-[#111827]">Movie Hub HQ</strong>. Like virtually all websites, our server
                  automatically logs basic, non-personal technical details—such as browser type, device type (mobile or
                  desktop), approximate country, and which episode page was viewed—solely to keep the site running
                  smoothly and fix broken pages.
                </p>
              </div>

              <div className="space-y-1.5">
                <h2 className="text-sm sm:text-base font-bold text-[#111827]">2. Cookies and Web Beacons</h2>
                <p>
                  Like most websites, <strong className="text-[#111827]">Movie Hub HQ</strong> uses small browser files
                  called “cookies” to remember basic preferences and understand which pages are most helpful to our
                  visitors. You are always in full control and can disable or clear cookies at any time in your browser
                  settings.
                </p>
              </div>

              <div className="space-y-1.5">
                <h2 className="text-sm sm:text-base font-bold text-[#111827]">
                  3. Google DoubleClick DART Cookie &amp; Ads
                </h2>
                <p>
                  Google and other third-party advertising partners may serve ads on our site using cookies (such as
                  DART cookies) to show relevant advertisements based on your visit to{" "}
                  <span className="font-mono text-xs">moviehubhq.com</span> and other sites across the web. You can
                  easily opt out of personalized DART cookies anytime by visiting Google’s Ad Settings Policy at:{" "}
                  <a
                    href="https://policies.google.com/technologies/ads"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-[#0b57d0] hover:underline break-all"
                  >
                    https://policies.google.com/technologies/ads
                  </a>
                </p>
              </div>

              <div className="space-y-1.5">
                <h2 className="text-sm sm:text-base font-bold text-[#111827]">4. Third-Party Links &amp; Advertisers</h2>
                <p>
                  Our episode buttons link to external video hosts and cloud storage providers, and our pages may
                  display third-party banners. Please keep in mind that{" "}
                  <strong className="text-[#111827]">Movie Hub HQ</strong> has no control over cookies or data collected
                  by external websites once you leave our domain. We recommend checking the privacy policies of any
                  third-party sites you visit.
                </p>
              </div>

              <div className="space-y-1.5">
                <h2 className="text-sm sm:text-base font-bold text-[#111827]">5. Children’s Privacy</h2>
                <p>
                  Protecting children online is very important to us.{" "}
                  <strong className="text-[#111827]">Movie Hub HQ</strong> does not knowingly collect any personal
                  information from children under the age of 13. If you are a parent or guardian and believe your child
                  has shared personal details with us, please email us at{" "}
                  <a href="mailto:contact@moviehubhq.com" className="text-[#0b57d0] hover:underline font-mono">
                    contact@moviehubhq.com
                  </a>{" "}
                  and we will remove it immediately.
                </p>
              </div>

              <div className="space-y-1.5">
                <h2 className="text-sm sm:text-base font-bold text-[#111827]">6. Online Privacy Policy Scope</h2>
                <p>
                  This Privacy Policy applies only to online activities on{" "}
                  <strong className="text-[#111827]">Movie Hub HQ</strong> (
                  <span className="font-mono text-xs">moviehubhq.com</span>) and does not apply to information collected
                  offline or on other websites.
                </p>
              </div>

              <div className="bg-[#f8fafd] border border-[#e0e4eb] rounded-2xl p-4 space-y-1">
                <h2 className="text-sm font-bold text-[#111827]">Your Consent</h2>
                <p className="text-xs sm:text-sm text-[#444746]">
                  By using our website, you consent to this Privacy Policy and agree to its terms.
                </p>
              </div>
            </div>
          </article>
        ) : (
          <>
            {/* Private Page Notice Banner for Admin */}
            {isAdmin && !isPublic && (
          <div className="rounded-2xl bg-[#fff8e6] border border-[#ffe082] p-3.5 flex items-center justify-between gap-3 text-xs text-[#7c5e10] shadow-2xs">
            <div className="flex items-center gap-2">
              <span className="text-base">🔒</span>
              <div>
                <span className="font-bold text-[#b06000]">Private Page Mode: </span>
                <span>Only logged-in administrators can view this page. Public visitors see a 404 screen.</span>
              </div>
            </div>
          </div>
        )}

        {/* Top Banner Ad Placement */}
        {adSettings?.banner_top && (
          <div className="ad-slot-container w-full text-center space-y-1 my-2">
            <div className="ad-label text-[10px] font-semibold tracking-wider text-[#747775] uppercase select-none">
              - Advertisement -
            </div>
            {!adBlocked ? (
              <div
                className="ad-content-box w-full flex justify-center items-center overflow-hidden rounded-2xl bg-white border border-[#e0e4eb] p-2"
                dangerouslySetInnerHTML={{ __html: adSettings.banner_top }}
              />
            ) : (
              <div className="w-full rounded-2xl bg-[#fff8e6] border border-[#ffe082] p-4 text-center select-none shadow-2xs">
                <div className="flex flex-col items-center justify-center gap-1">
                  <span className="text-xs font-bold text-[#b78103] flex items-center gap-1.5">
                    ⚠️ Ad Blocker / DNS Filter Detected
                  </span>
                  <p className="text-xs text-[#7c5e10] max-w-md">
                    Please turn off your ad blocker or private DNS to keep our site free and support fast streaming links.
                  </p>
                </div>
              </div>
            )}
          </div>
        )}

        {/* Title & Information Card (Google Material M3 Card - Matches cpanel-package/view.php lines 560-569) */}
        <div className="bg-white border border-[#e0e4eb] rounded-3xl p-6 sm:p-7 text-center space-y-3 shadow-xs">
          <h1 className="text-xl sm:text-2xl font-extrabold text-[#111827] leading-snug tracking-tight">
            {page.title}
          </h1>

          <p className="text-xs sm:text-sm text-[#5f6368] max-w-lg mx-auto leading-relaxed">
            {page.description
              ? page.description
              : "Select your episode below to stream or download via high-speed server mirrors."}
          </p>
        </div>

        {/* Social Media & Mobile Display Share Card (Matches cpanel-package/view.php lines 571-637) */}
        {shareSettings.enabled && shareSettings.show_in_page && (
          <div className="bg-white border border-[#e0e4eb] rounded-3xl p-4 sm:p-5 shadow-xs space-y-3">
            <div className="flex items-center justify-between gap-2 flex-wrap pb-0.5">
              <div className="flex items-center gap-2">
                <span className="w-7 h-7 rounded-xl bg-[#e8f0fe] text-[#0b57d0] flex items-center justify-center">
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth="2"
                      d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"
                    />
                  </svg>
                </span>
                <span className="text-xs font-bold text-[#111827]">Share Episode Page</span>
              </div>
              <span className="text-[11px] text-[#747775]">Copy link or share to social apps</span>
            </div>

            {/* Uniform Action Chips: Copy Link, WhatsApp, Telegram, Facebook, X, and QR Code */}
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
              {/* 1. Copy Link Button */}
              <button
                type="button"
                onClick={copyPageShareUrl}
                className="flex items-center justify-center gap-2 px-3 py-2.5 rounded-2xl bg-[#f0f4f9] hover:bg-[#e8f0fe] text-[#0b57d0] border border-[#d3e3fd] hover:border-[#0b57d0] text-xs font-bold transition-all shadow-2xs cursor-pointer select-none active:scale-95 group"
              >
                <svg
                  className="w-4 h-4 shrink-0 transition-transform group-hover:scale-110"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth="2"
                    d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                  />
                </svg>
                <span className="truncate">{copiedLink ? "✓ Copied!" : "Copy Link"}</span>
              </button>

              {/* 2. WhatsApp Share */}
              <a
                href={`https://api.whatsapp.com/send?text=${shareEncodedMsg}`}
                target="_blank"
                rel="noopener noreferrer"
                className="flex items-center justify-center gap-2 px-3 py-2.5 rounded-2xl bg-[#e6f4ea] hover:bg-[#ceead6] text-[#137333] border border-[#a8dab5] text-xs font-bold transition-all shadow-2xs cursor-pointer select-none active:scale-95 group"
              >
                <svg className="w-4 h-4 shrink-0 fill-current" viewBox="0 0 24 24">
                  <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z" />
                </svg>
                <span className="truncate">WhatsApp</span>
              </a>

              {/* 3. Telegram Share */}
              <a
                href={`https://t.me/share/url?url=${shareEncodedUrl}&text=${shareEncodedTitle}`}
                target="_blank"
                rel="noopener noreferrer"
                className="flex items-center justify-center gap-2 px-3 py-2.5 rounded-2xl bg-[#e8f4fd] hover:bg-[#d0ebfc] text-[#0088cc] border border-[#b8e1fa] text-xs font-bold transition-all shadow-2xs cursor-pointer select-none active:scale-95 group"
              >
                <svg className="w-4 h-4 shrink-0 fill-current" viewBox="0 0 24 24">
                  <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z" />
                </svg>
                <span className="truncate">Telegram</span>
              </a>

              {/* 4. Facebook Share */}
              <a
                href={`https://www.facebook.com/sharer/sharer.php?u=${shareEncodedUrl}`}
                target="_blank"
                rel="noopener noreferrer"
                className="flex items-center justify-center gap-2 px-3 py-2.5 rounded-2xl bg-[#ebf3ff] hover:bg-[#dbeafe] text-[#1877f2] border border-[#bfdbfe] text-xs font-bold transition-all shadow-2xs cursor-pointer select-none active:scale-95 group"
              >
                <svg className="w-4 h-4 shrink-0 fill-current" viewBox="0 0 24 24">
                  <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                </svg>
                <span className="truncate">Facebook</span>
              </a>

              {/* 5. X / Twitter Share */}
              <a
                href={`https://twitter.com/intent/tweet?text=${shareEncodedTitle}&url=${shareEncodedUrl}`}
                target="_blank"
                rel="noopener noreferrer"
                className="flex items-center justify-center gap-2 px-3 py-2.5 rounded-2xl bg-[#f3f4f6] hover:bg-[#e5e7eb] text-[#111827] border border-[#d1d5db] text-xs font-bold transition-all shadow-2xs cursor-pointer select-none active:scale-95 group"
              >
                <svg className="w-3.5 h-3.5 shrink-0 fill-current" viewBox="0 0 24 24">
                  <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" />
                </svg>
                <span className="truncate">X / Tweet</span>
              </a>

              {/* 6. QR Code Button */}
              <button
                type="button"
                onClick={() => setQrModalOpen(true)}
                className="flex items-center justify-center gap-2 px-3 py-2.5 rounded-2xl bg-[#fdf4ff] hover:bg-[#fae8ff] text-[#9333ea] border border-[#f0abfc] text-xs font-bold transition-all shadow-2xs cursor-pointer select-none active:scale-95 group"
              >
                <svg className="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth="2"
                    d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"
                  />
                </svg>
                <span className="truncate">QR Code</span>
              </button>
            </div>
          </div>
        )}

        {/* Episode Buttons List (Server Icon, Episode 01/02.., Watch Now Button - Matches cpanel-package/view.php lines 660-767) */}
        <div className="space-y-2.5">
          {!page.buttons || page.buttons.length === 0 ? (
            <div className="bg-white border border-[#e0e4eb] rounded-2xl p-8 text-center space-y-3">
              <div className="text-xs text-[#747775]">
                No episode download buttons currently available for this title.
              </div>
              {debugMode && (
                <div className="text-left bg-rose-50 border border-rose-200 rounded-2xl p-4 font-mono text-[11px] text-rose-900 leading-relaxed space-y-1 max-w-xl mx-auto">
                  <div className="font-bold uppercase tracking-wider text-rose-700 flex items-center justify-between">
                    <span>🛠️ Debug Mode: ON (Actual Error Details)</span>
                    <span className="bg-rose-100 px-2 py-0.5 rounded border border-rose-200">EMPTY BUTTONS</span>
                  </div>
                  <div>
                    <strong>Actual Error:</strong> Actual Error [Empty Episode Buttons]: Episode page '{slug}' (ID #{page.id}) loaded from datastore, but <code>buttons</code> array contains 0 valid episode links.
                  </div>
                  <div className="text-rose-800">
                    <strong>Source URL:</strong> {page.source_url || "None"} • <strong>Resolved URL:</strong>{" "}
                    {page.resolved_url || "None"}
                  </div>
                </div>
              )}
            </div>
          ) : (
            page.buttons.map((btn, idx) => {
              const rawEp = btn.episode || idx + 1;
              const epNumPadded =
                typeof rawEp === "number" || /^\d+$/.test(String(rawEp))
                  ? String(Number(rawEp)).padStart(2, "0")
                  : String(rawEp);
              const epLabel = `Episode ${epNumPadded}`;
              const btnQuality = btn.quality || "HD";
              const serverInfo = resolveServerInfo(btn.provider, btn.url, btn.text);

              return (
                <React.Fragment key={`btn-wrap-${idx}`}>
                  <div className="bg-white hover:bg-[#fafcff] border border-[#e0e4eb] hover:border-[#0b57d0] rounded-2xl p-3.5 sm:p-4 flex items-center justify-between gap-3 transition-all shadow-2xs group">
                    {/* Left Column: Server Icon, Episode 01/02.., Provider & Quality Tags */}
                    <div className="flex items-center gap-3 min-w-0 flex-1">
                      <div className="w-10 h-10 rounded-xl bg-[#e8f0fe] text-[#0b57d0] flex items-center justify-center font-extrabold text-xs shrink-0 group-hover:bg-[#0b57d0] group-hover:text-white transition-colors shadow-2xs">
                        E{epNumPadded}
                      </div>

                      <div className="min-w-0 flex-1">
                        <div className="text-sm font-bold text-[#111827] truncate group-hover:text-[#0b57d0] transition-colors flex items-center gap-1.5 flex-wrap">
                          <span>{epLabel}</span>
                          {btn.text && !btn.text.toLowerCase().includes("episode") && (
                            <span className="text-xs text-[#747775] font-normal truncate hidden sm:inline">
                              ({btn.text})
                            </span>
                          )}
                        </div>

                        <div className="flex items-center gap-2 text-[11px] text-[#5f6368] mt-0.5">
                          <div className="inline-flex items-center gap-1 font-medium">
                            {serverInfo.icon}
                            <span>{serverInfo.name}</span>
                          </div>
                          <span className="px-2 py-0.5 rounded-md bg-[#f1f3f4] text-[#3c4043] font-mono font-bold text-[10px] border border-[#e0e4eb]">
                            {btnQuality}
                          </span>
                        </div>
                      </div>
                    </div>

                    {/* Right Column: "Watch Now" Button (Google Material M3 Rounded Pill) */}
                    <div className="shrink-0 flex items-center gap-2">
                      <a
                        href={btn.url || "#"}
                        target="_blank"
                        rel="noopener noreferrer nofollow"
                        className="px-4 py-2 rounded-full bg-[#0b57d0] hover:bg-[#0842a0] text-white font-bold text-xs flex items-center gap-1.5 shadow-xs hover:shadow-md transition-all cursor-pointer"
                      >
                        <svg className="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
                          <path d="M8 5v14l11-7z" />
                        </svg>
                        <span>Watch Now</span>
                      </a>
                    </div>
                  </div>

                  {/* In-Feed Responsive Banner Ad space after every 4 buttons */}
                  {(idx + 1) % 4 === 0 &&
                    idx < page.buttons.length - 1 &&
                    (Boolean(adSettings?.banner_middle) ||
                      Boolean(adSettings?.adsense_auto_ads && adSettings?.adsense_publisher_id)) && (
                      <div className="my-3 w-full text-center space-y-1">
                        <div className="text-[10px] font-semibold tracking-wider text-[#747775] uppercase select-none">
                          - Advertisement -
                        </div>
                        {!adBlocked ? (
                          <div className="overflow-hidden rounded-2xl bg-white border border-[#e0e4eb] p-2 text-center">
                            {adSettings?.banner_middle ? (
                              <div dangerouslySetInnerHTML={{ __html: adSettings.banner_middle }} />
                            ) : (
                              <div className="py-3 text-center space-y-1">
                                <ins
                                  className="adsbygoogle block w-full"
                                  data-ad-client={adSettings.adsense_publisher_id}
                                  data-ad-format="auto"
                                  data-full-width-responsive="true"
                                />
                                <span className="text-[10px] font-bold tracking-wider text-[#0b57d0] uppercase bg-[#e8f0fe] px-2 py-0.5 rounded">
                                  AdSense Auto Ad
                                </span>
                              </div>
                            )}
                          </div>
                        ) : (
                          <div className="w-full rounded-2xl bg-[#fff8e6] border border-[#ffe082] p-4 text-center select-none shadow-2xs">
                            <div className="flex flex-col items-center justify-center gap-1">
                              <span className="text-xs font-bold text-[#b78103] flex items-center gap-1.5">
                                ⚠️ Ad Blocker / DNS Filter Detected
                              </span>
                              <p className="text-xs text-[#7c5e10] max-w-md">
                                Please turn off your ad blocker or private DNS to keep our site free and support fast streaming links.
                              </p>
                            </div>
                          </div>
                        )}
                      </div>
                    )}
                </React.Fragment>
              );
            })
          )}
        </div>

        {/* Bottom Banner Ad Placement */}
        {adSettings?.banner_bottom && (
          <div className="w-full text-center space-y-1 my-2">
            <div className="text-[10px] font-semibold tracking-wider text-[#747775] uppercase select-none">
              - Advertisement -
            </div>
            {!adBlocked ? (
              <div
                className="w-full flex justify-center items-center overflow-hidden rounded-2xl bg-white border border-[#e0e4eb] p-2"
                dangerouslySetInnerHTML={{ __html: adSettings.banner_bottom }}
              />
            ) : (
              <div className="w-full rounded-2xl bg-[#fff8e6] border border-[#ffe082] p-4 text-center select-none shadow-2xs">
                <div className="flex flex-col items-center justify-center gap-1">
                  <span className="text-xs font-bold text-[#b78103] flex items-center gap-1.5">
                    ⚠️ Ad Blocker / DNS Filter Detected
                  </span>
                  <p className="text-xs text-[#7c5e10] max-w-md">
                    Please turn off your ad blocker or private DNS to keep our site free and support fast streaming links.
                  </p>
                </div>
              </div>
            )}
          </div>
        )}
          </>
        )}
      </main>

      {/* Public Footer (Google Material M3 Light Theme - Matches cpanel-package/view.php lines 791-798) */}
      <footer className="w-full bg-white border-t border-[#e1e7f0] mt-auto py-6 px-4">
        <div className="max-w-4xl mx-auto text-center text-xs text-[#5f6368]">
          <div className="leading-relaxed" dangerouslySetInnerHTML={{ __html: footerText }} />
          {/* Horizontal Legal Page Links below copyright text */}
          <div className="mt-2.5 pt-2.5 border-t border-[#f0f4f9] flex items-center justify-center flex-wrap gap-x-5 gap-y-1.5 text-xs font-medium text-[#5f6368]">
            <a
              href="/dmca"
              onClick={(e) => {
                if (onNavigateSlug) {
                  e.preventDefault();
                  onNavigateSlug("dmca");
                }
              }}
              className={`hover:text-[#0b57d0] hover:underline transition-colors ${
                legalSlug === "dmca" ? "text-[#0b57d0] font-bold" : ""
              }`}
            >
              DMCA
            </a>
            <span className="text-[#c4c7c5] select-none">•</span>
            <a
              href="/disclaimer"
              onClick={(e) => {
                if (onNavigateSlug) {
                  e.preventDefault();
                  onNavigateSlug("disclaimer");
                }
              }}
              className={`hover:text-[#0b57d0] hover:underline transition-colors ${
                legalSlug === "disclaimer" ? "text-[#0b57d0] font-bold" : ""
              }`}
            >
              Disclaimer
            </a>
            <span className="text-[#c4c7c5] select-none">•</span>
            <a
              href="/about-us"
              onClick={(e) => {
                if (onNavigateSlug) {
                  e.preventDefault();
                  onNavigateSlug("about-us");
                }
              }}
              className={`hover:text-[#0b57d0] hover:underline transition-colors ${
                legalSlug === "about-us" ? "text-[#0b57d0] font-bold" : ""
              }`}
            >
              About Us
            </a>
            <span className="text-[#c4c7c5] select-none">•</span>
            <a
              href="/privacy-policy"
              onClick={(e) => {
                if (onNavigateSlug) {
                  e.preventDefault();
                  onNavigateSlug("privacy-policy");
                }
              }}
              className={`hover:text-[#0b57d0] hover:underline transition-colors ${
                legalSlug === "privacy-policy" ? "text-[#0b57d0] font-bold" : ""
              }`}
            >
              Privacy Policy
            </a>
          </div>
        </div>
      </footer>

      {/* QR Code Display Modal (Matches cpanel-package/view.php lines 815-843) */}
      {qrModalOpen && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl p-6 max-w-xs w-full text-center space-y-4 shadow-2xl border border-[#e0e4eb]">
            <div className="flex items-center justify-between pb-2 border-b border-[#f0f4f9]">
              <div className="flex items-center gap-2">
                <span className="w-6 h-6 rounded-lg bg-[#e8f0fe] text-[#0b57d0] flex items-center justify-center">
                  <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth="2"
                      d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"
                    />
                  </svg>
                </span>
                <span className="text-xs font-bold text-[#111827]">Scan to Open</span>
              </div>
              <button
                type="button"
                onClick={() => setQrModalOpen(false)}
                className="p-1 rounded-lg text-[#5f6368] hover:text-[#111827] hover:bg-[#f0f4f9] transition-colors cursor-pointer"
                aria-label="Close"
              >
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <div className="flex justify-center p-3 bg-[#f8fafd] rounded-2xl border border-[#e0e4eb]">
              <img
                src={`https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${shareEncodedUrl}`}
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
              {copiedLink ? "✓ Copied!" : "Copy Link to Clipboard"}
            </button>
          </div>
        </div>
      )}

      {/* Mobile Share Sheet Bottom Drawer (Matches cpanel-package/view.php lines 845-901) */}
      {mobileShareSheetOpen && (
        <>
          <div
            onClick={() => setMobileShareSheetOpen(false)}
            className="fixed inset-0 bg-black/50 backdrop-blur-xs z-50 md:hidden"
          />
          <div className="fixed inset-x-0 bottom-0 z-50 bg-white rounded-t-3xl border-t border-[#e0e4eb] p-5 shadow-2xl space-y-4 md:hidden">
            <div className="flex items-center justify-between pb-2 border-b border-[#f0f4f9]">
              <div className="flex items-center gap-2">
                <span className="w-7 h-7 rounded-xl bg-[#e8f0fe] text-[#0b57d0] flex items-center justify-center">
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth="2"
                      d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"
                    />
                  </svg>
                </span>
                <span className="text-sm font-bold text-[#111827]">Share Episode Link</span>
              </div>
              <button
                type="button"
                onClick={() => setMobileShareSheetOpen(false)}
                className="p-1.5 rounded-xl text-[#5f6368] hover:bg-[#f0f4f9] transition-colors cursor-pointer"
                aria-label="Close"
              >
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <div className="grid grid-cols-4 gap-2 text-center text-xs">
              <button
                type="button"
                onClick={() => {
                  setMobileShareSheetOpen(false);
                  triggerNativeShare();
                }}
                className="flex flex-col items-center gap-1.5 p-2 rounded-2xl bg-[#f0f4f9] hover:bg-[#e8f0fe] text-[#0b57d0] transition-colors cursor-pointer"
              >
                <div className="w-10 h-10 rounded-full bg-[#0b57d0] text-white flex items-center justify-center shadow-xs">
                  <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth="2"
                      d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"
                    />
                  </svg>
                </div>
                <span className="text-[11px] font-semibold text-[#1f1f1f]">More Apps</span>
              </button>

              <a
                href={`https://api.whatsapp.com/send?text=${shareEncodedMsg}`}
                target="_blank"
                rel="noopener noreferrer"
                onClick={() => setMobileShareSheetOpen(false)}
                className="flex flex-col items-center gap-1.5 p-2 rounded-2xl bg-[#f0f4f9] hover:bg-[#e6f4ea] text-[#137333] transition-colors cursor-pointer"
              >
                <div className="w-10 h-10 rounded-full bg-[#25d366] text-white flex items-center justify-center shadow-xs">
                  <svg className="w-5 h-5 fill-current" viewBox="0 0 24 24">
                    <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z" />
                  </svg>
                </div>
                <span className="text-[11px] font-semibold text-[#1f1f1f]">WhatsApp</span>
              </a>

              <a
                href={`https://t.me/share/url?url=${shareEncodedUrl}&text=${shareEncodedTitle}`}
                target="_blank"
                rel="noopener noreferrer"
                onClick={() => setMobileShareSheetOpen(false)}
                className="flex flex-col items-center gap-1.5 p-2 rounded-2xl bg-[#f0f4f9] hover:bg-[#e8f4fd] text-[#0088cc] transition-colors cursor-pointer"
              >
                <div className="w-10 h-10 rounded-full bg-[#0088cc] text-white flex items-center justify-center shadow-xs">
                  <svg className="w-5 h-5 fill-current" viewBox="0 0 24 24">
                    <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z" />
                  </svg>
                </div>
                <span className="text-[11px] font-semibold text-[#1f1f1f]">Telegram</span>
              </a>

              <a
                href={`https://www.facebook.com/sharer/sharer.php?u=${shareEncodedUrl}`}
                target="_blank"
                rel="noopener noreferrer"
                onClick={() => setMobileShareSheetOpen(false)}
                className="flex flex-col items-center gap-1.5 p-2 rounded-2xl bg-[#f0f4f9] hover:bg-[#e4eaf2] text-[#1877f2] transition-colors cursor-pointer"
              >
                <div className="w-10 h-10 rounded-full bg-[#1877f2] text-white flex items-center justify-center shadow-xs">
                  <svg className="w-5 h-5 fill-current" viewBox="0 0 24 24">
                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                  </svg>
                </div>
                <span className="text-[11px] font-semibold text-[#1f1f1f]">Facebook</span>
              </a>
            </div>

            <button
              type="button"
              onClick={copyPageShareUrl}
              className="w-full py-3 rounded-2xl bg-[#f0f4f9] hover:bg-[#e8f0fe] text-[#0b57d0] font-bold text-xs flex items-center justify-center gap-2 border border-[#d3e3fd] transition-all cursor-pointer"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth="2"
                  d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                />
              </svg>
              <span>{copiedLink ? "✓ Copied!" : "Copy Link"}</span>
            </button>
          </div>
        </>
      )}
    </div>
  );
};
