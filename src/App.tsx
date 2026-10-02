/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { useState, useEffect } from "react";
import { motion, AnimatePresence } from "motion/react";
import { AppHeader } from "./components/AppHeader";
import { AdminSidebar } from "./components/AdminSidebar";
import { SettingsManager } from "./components/SettingsManager";
import { Toast, ToastMessage } from "./components/Toast";
import { AdminLoginCard } from "./components/AdminLoginCard";
import { AdminFlowGenerator } from "./components/AdminFlowGenerator";
import { PagesManager } from "./components/PagesManager";
import { OneClickUpdaterHub } from "./components/OneClickUpdaterHub";
import { CPanelDeploymentHub } from "./components/CPanelDeploymentHub";
import { WordPressPluginHub } from "./components/WordPressPluginHub";
import { AnalyticsDashboard } from "./components/AnalyticsDashboard";
import { PublicButtonPageView } from "./components/PublicButtonPageView";
import { ButtonPage, ViewTab } from "./types";
import { getValidAdminToken, setLoginCookies, clearLoginCookies } from "./utils/authCookie";

export default function App() {
  const getConfiguredLoginSlug = () => {
    return (
      (localStorage.getItem("slea_login_slug") || "login")
        .trim()
        .toLowerCase()
        .replace(/^[/\\]+|[/\\]+$/g, "")
        .replace(/\.php$/i, "") || "login"
    );
  };

  const [adminToken, setAdminToken] = useState<string>(() => {
    return getValidAdminToken();
  });
  const [isAdmin, setIsAdmin] = useState<boolean>(() => {
    return Boolean(getValidAdminToken());
  });
  const [currentTab, setCurrentTab] = useState<ViewTab>("admin_flow");
  const [activeSlugView, setActiveSlugView] = useState<string | null>(null);
  const [pendingRedirectSlug, setPendingRedirectSlug] = useState<string | null>(null);
  const [editingPageId, setEditingPageId] = useState<string | null>(null);
  const [pages, setPages] = useState<ButtonPage[]>([]);
  const [toasts, setToasts] = useState<ToastMessage[]>([]);

  const redirectToLoginPage = (returnSlug?: string) => {
    clearLoginCookies();
    setAdminToken("");
    setIsAdmin(false);
    setActiveSlugView(null);
    if (returnSlug) {
      setPendingRedirectSlug(returnSlug);
    }
    const loginSlug = getConfiguredLoginSlug();
    if (window.history.replaceState) {
      window.history.replaceState({}, "", `/${loginSlug}`);
    }
  };

  // Automatically check cookie expiration every 15s and redirect to login page if the 2-hour cookie expired
  useEffect(() => {
    if (!isAdmin) return;
    const interval = setInterval(() => {
      const validTok = getValidAdminToken();
      if (!validTok) {
        redirectToLoginPage();
      }
    }, 15000);
    return () => clearInterval(interval);
  }, [isAdmin]);

  const fetchPages = async () => {
    try {
      const currentValidToken = getValidAdminToken() || adminToken;
      if (!currentValidToken) {
        redirectToLoginPage();
        return;
      }
      const res = await fetch("/api/pages", {
        credentials: "same-origin",
        headers: {
          Authorization: `Bearer ${currentValidToken}`,
          "x-admin-token": currentValidToken,
        },
      });
      if (res.status === 401) {
        redirectToLoginPage();
        return;
      }
      const data = await res.json();
      if (data.success && Array.isArray(data.data)) {
        setPages(data.data);
      }
    } catch {
      // ignore
    }
  };

  useEffect(() => {
    if (isAdmin) {
      fetchPages();
    }
  }, [adminToken, isAdmin, currentTab]);

  // Detect URL slug for public viewing (/p/{slug} or ?slug={slug}) or redirect to login page when cookie is expired/unavailable
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const qSlug = params.get("slug") || params.get("p");
    const configuredLoginSlug = getConfiguredLoginSlug();

    if (qSlug) {
      const qClean = qSlug.trim().toLowerCase().replace(/\.php$/i, "");
      if (qClean === "login" || qClean === configuredLoginSlug) {
        setActiveSlugView(null);
        if (isAdmin) {
          setCurrentTab("admin_flow");
          if (window.history.replaceState) {
            window.history.replaceState({}, "", "/admin.php");
          }
        }
        return;
      }
      setActiveSlugView(qSlug);
      return;
    }

    const path = window.location.pathname;
    const cleanPath = path.replace(/\/$/, "").toLowerCase();
    const pathSlug = cleanPath.replace(/^\/+/, "").replace(/\.php$/i, "");

    // Login route handling: if already logged in via valid cookie -> go to admin.php; if not -> show login screen
    if (pathSlug === "login" || (configuredLoginSlug && pathSlug === configuredLoginSlug)) {
      setActiveSlugView(null);
      if (isAdmin) {
        setCurrentTab("admin_flow");
        if (window.history.replaceState) {
          window.history.replaceState({}, "", "/admin.php");
        }
      }
      return;
    }

    if (["/dmca", "/disclaimer", "/about-us", "/about", "/privacy-policy", "/privacy"].includes(cleanPath)) {
      const legalSlug =
        cleanPath === "/privacy"
          ? "privacy-policy"
          : cleanPath === "/about"
          ? "about-us"
          : cleanPath.substring(1);
      setActiveSlugView(legalSlug);
      return;
    }
    if (path.startsWith("/p/") || path.startsWith("/page/")) {
      const prefixLen = path.startsWith("/page/") ? 6 : 3;
      const slugFromPath = path.substring(prefixLen).replace(/\/$/, "");
      if (slugFromPath) {
        setActiveSlugView(slugFromPath);
        return;
      }
    }

    // Admin routes: if cookie is expired, not valid, or not available -> redirect to login page
    if (!isAdmin) {
      if (window.history.replaceState) {
        window.history.replaceState({}, "", `/${configuredLoginSlug}`);
      }
    } else if (cleanPath === "" || cleanPath === "/") {
      if (window.history.replaceState) {
        window.history.replaceState({}, "", "/admin.php");
      }
    }
  }, [isAdmin]);

  const addToast = (text: string, type: "success" | "error" | "info" = "info") => {
    const id = Math.random().toString(36).substring(2, 9);
    setToasts((prev) => [...prev, { id, text, type }]);
    setTimeout(() => {
      setToasts((prev) => prev.filter((t) => t.id !== id));
    }, 3200);
  };

  const removeToast = (id: string) => {
    setToasts((prev) => prev.filter((t) => t.id !== id));
  };

  const handleLoginSuccess = (token: string, username: string) => {
    // Regenerate 2-hour (7200s) login state cookie
    setLoginCookies(token, username || "admin", 7200);
    setAdminToken(token);
    setIsAdmin(true);
    if (pendingRedirectSlug) {
      const targetSlug = pendingRedirectSlug;
      setPendingRedirectSlug(null);
      setActiveSlugView(targetSlug);
      if (window.history.replaceState) {
        window.history.replaceState({}, "", `/p/${targetSlug}`);
      }
    } else {
      setActiveSlugView(null);
      setCurrentTab("admin_flow");
      if (window.history.replaceState) {
        window.history.replaceState({}, "", "/admin.php");
      }
    }
  };

  const handleLogout = () => {
    fetch("/api/auth/logout", {
      method: "POST",
      credentials: "same-origin",
      headers: adminToken ? { "x-admin-token": adminToken } : {},
    }).catch(() => {});
    redirectToLoginPage();
    addToast("Signed out successfully", "info");
  };

  const [loginMobileMenuOpen, setLoginMobileMenuOpen] = useState<boolean>(false);
  const [publicSiteIdentity] = useState(() => {
    try {
      const saved = localStorage.getItem("slea_site_identity");
      if (saved) return JSON.parse(saved);
    } catch {
      // ignore
    }
    return {
      site_name: "Movie Hub HQ Drive",
      site_logo_url: "",
      site_logo_icon: "⚡",
      site_logo_text: "MHQ",
    };
  });
  const [publicMenuItems] = useState<{ title: string; url: string; new_tab?: boolean; target_blank?: boolean }[]>(() => {
    const defaultMenu = [
      { title: "Home", url: "https://moviehubhq.com/", new_tab: false },
      { title: "Korean Drama", url: "https://moviehubhq.com/catagory/korean/", new_tab: false },
      { title: "Chinese Drama", url: "https://moviehubhq.com/catagory/chinese/", new_tab: false },
    ];
    try {
      const saved = localStorage.getItem("slea_menu_items");
      if (saved) {
        const parsed = JSON.parse(saved);
        if (Array.isArray(parsed)) {
          return parsed.filter((item) => {
            const t = String(item?.title || "").toLowerCase();
            const u = String(item?.url || "").toLowerCase();
            if (/admin|login|dashboard|cpanel|setup/.test(t)) return false;
            if (/\/(admin|login|pages|settings|analytics|update|setup)/.test(u)) return false;
            return true;
          });
        }
      }
    } catch {
      // ignore
    }
    return defaultMenu;
  });
  const [publicFooterText] = useState<string>(() => {
    return (
      localStorage.getItem("slea_footer_text") ||
      `&copy; ${new Date().getFullYear()} MovieHubHQ 🍿 • Made with &#10084;&#65039; for Direct Episode Link Gateway 🎬 • All rights reserved 🚀`
    );
  });

  // If viewing a public button page (/p/{slug})
  if (activeSlugView) {
    return (
      <div className="w-full min-h-screen bg-[#f8fafd] text-[#1f1f1f] flex flex-col font-sans antialiased">
        <PublicButtonPageView
          slug={activeSlugView}
          adminToken={adminToken}
          isAdmin={isAdmin}
          onRequireLogin={(returnSlug) => {
            redirectToLoginPage(returnSlug);
          }}
          onEditPage={(pageId) => {
            setActiveSlugView(null);
            setEditingPageId(pageId);
            setCurrentTab("pages_list");
            if (window.history.pushState) {
              window.history.pushState({}, "", "/");
            }
          }}
          onBackToAdmin={() => {
            setActiveSlugView(null);
            setEditingPageId(null);
            setCurrentTab("pages_list");
            if (window.history.pushState) {
              window.history.pushState({}, "", "/");
            }
          }}
          onNavigateSlug={(nextSlug) => {
            setActiveSlugView(nextSlug);
            if (window.history.pushState) {
              const targetPath = ["dmca", "disclaimer", "about-us", "privacy-policy"].includes(nextSlug)
                ? `/${nextSlug}`
                : `/p/${nextSlug}`;
              window.history.pushState({}, "", targetPath);
            }
            window.scrollTo({ top: 0, behavior: "smooth" });
          }}
          onNotify={addToast}
        />
        <Toast toasts={toasts} onDismiss={removeToast} />
      </div>
    );
  }

  if (!isAdmin) {
    const publicSiteName = publicSiteIdentity.site_name || "Movie Hub HQ Drive";
    return (
      <div className="w-full min-h-screen bg-[#f8fafd] text-[#1f1f1f] flex flex-col font-sans antialiased overflow-x-hidden selection:bg-[#d3e3fd] selection:text-[#041e49]">
        {/* Public Header Navigation Bar (Google Material M3 Light Theme) */}
        <header className="w-full bg-white/95 border-b border-[#e1e7f0] sticky top-0 z-40 backdrop-blur-md shadow-2xs">
          <div className="max-w-4xl mx-auto px-4 sm:px-6 py-3 sm:py-3.5 flex items-center justify-between gap-3 min-w-0">
            <div className="flex items-center gap-3">
              <button
                type="button"
                onClick={() => setLoginMobileMenuOpen((prev) => !prev)}
                aria-label="Toggle navigation menu"
                className="md:hidden p-2 rounded-xl bg-[#f0f4f9] border border-[#e1e7f0] text-[#1f1f1f] hover:bg-[#e8f0fe] hover:text-[#0b57d0] focus:outline-none cursor-pointer transition-colors"
              >
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
              </button>

              <a href="#top" onClick={(e) => e.preventDefault()} className="flex items-center group no-underline">
                {publicSiteIdentity.site_logo_url ? (
                  <img
                    src={publicSiteIdentity.site_logo_url}
                    alt={publicSiteName}
                    className="h-8 max-w-[180px] object-contain"
                  />
                ) : (
                  <span className="font-bold text-base sm:text-lg tracking-tight text-[#111827] group-hover:text-[#0b57d0] transition-colors">
                    {publicSiteName}
                  </span>
                )}
              </a>
            </div>

            <nav className="hidden md:flex flex-row items-center gap-2">
              {publicMenuItems.map((item, idx) => {
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

        {/* Mobile Sidebar Drawer */}
        {loginMobileMenuOpen && (
          <div
            onClick={() => setLoginMobileMenuOpen(false)}
            className="fixed inset-0 bg-black/40 backdrop-blur-xs z-50 transition-opacity duration-300 md:hidden"
            aria-hidden="true"
          />
        )}

        <aside
          className={`fixed inset-y-0 left-0 z-50 w-1/2 min-w-[250px] max-w-[340px] h-full bg-white border-r border-[#e1e7f0] shadow-2xl flex flex-col transform transition-transform duration-300 ease-in-out md:hidden ${
            loginMobileMenuOpen ? "translate-x-0" : "-translate-x-full"
          }`}
          aria-label="Mobile Navigation Drawer"
        >
          <div className="p-4 border-b border-[#f0f4f9] flex items-center justify-between shrink-0">
            <div className="flex items-center min-w-0">
              {publicSiteIdentity.site_logo_url ? (
                <img
                  src={publicSiteIdentity.site_logo_url}
                  alt={publicSiteName}
                  className="h-7 max-w-[140px] object-contain"
                />
              ) : (
                <span className="font-bold text-sm tracking-tight text-[#111827] truncate">
                  {publicSiteName}
                </span>
              )}
            </div>
            <button
              type="button"
              onClick={() => setLoginMobileMenuOpen(false)}
              className="p-1.5 rounded-xl text-[#5f6368] hover:text-[#111827] hover:bg-[#f0f4f9] transition-colors cursor-pointer"
              aria-label="Close menu"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <nav className="flex-1 overflow-y-auto p-4 flex flex-col space-y-2">
            {publicMenuItems.map((item, idx) => (
              <a
                key={idx}
                href={item.url || "#"}
                target={item.new_tab !== false ? "_blank" : undefined}
                rel={item.new_tab !== false ? "noopener noreferrer" : undefined}
                onClick={() => setLoginMobileMenuOpen(false)}
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
            {publicSiteName}
          </div>
        </aside>

        {/* Main Login Area */}
        <main className="flex-1 w-full max-w-2xl mx-auto px-4 sm:px-6 py-8 flex items-center justify-center">
          <motion.div
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.2 }}
            className="w-full max-w-md mx-auto"
          >
            <AdminLoginCard onLoginSuccess={handleLoginSuccess} onNotify={addToast} />
          </motion.div>
        </main>

        {/* Public Footer */}
        <footer className="w-full bg-white border-t border-[#e1e7f0] mt-auto py-6 px-4">
          <div className="max-w-4xl mx-auto text-center text-xs text-[#5f6368]">
            <div className="leading-relaxed" dangerouslySetInnerHTML={{ __html: publicFooterText }} />
            <div className="mt-2.5 pt-2.5 border-t border-[#f0f4f9] flex items-center justify-center flex-wrap gap-x-5 gap-y-1.5 text-xs font-medium text-[#5f6368]">
              <a
                href="/dmca"
                onClick={(e) => {
                  e.preventDefault();
                  setActiveSlugView("dmca");
                  if (window.history.pushState) window.history.pushState({}, "", "/dmca");
                }}
                className="hover:text-[#0b57d0] hover:underline transition-colors"
              >
                DMCA
              </a>
              <span className="text-[#c4c7c5] select-none">•</span>
              <a
                href="/disclaimer"
                onClick={(e) => {
                  e.preventDefault();
                  setActiveSlugView("disclaimer");
                  if (window.history.pushState) window.history.pushState({}, "", "/disclaimer");
                }}
                className="hover:text-[#0b57d0] hover:underline transition-colors"
              >
                Disclaimer
              </a>
              <span className="text-[#c4c7c5] select-none">•</span>
              <a
                href="/about-us"
                onClick={(e) => {
                  e.preventDefault();
                  setActiveSlugView("about-us");
                  if (window.history.pushState) window.history.pushState({}, "", "/about-us");
                }}
                className="hover:text-[#0b57d0] hover:underline transition-colors"
              >
                About Us
              </a>
              <span className="text-[#c4c7c5] select-none">•</span>
              <a
                href="/privacy-policy"
                onClick={(e) => {
                  e.preventDefault();
                  setActiveSlugView("privacy-policy");
                  if (window.history.pushState) window.history.pushState({}, "", "/privacy-policy");
                }}
                className="hover:text-[#0b57d0] hover:underline transition-colors"
              >
                Privacy Policy
              </a>
            </div>
          </div>
        </footer>

        <Toast toasts={toasts} onDismiss={removeToast} />
      </div>
    );
  }

  return (
    <div className="w-full min-h-screen bg-[#f0f4f9] text-[#1f1f1f] flex flex-col font-sans antialiased overflow-x-hidden selection:bg-[#d3e3fd] selection:text-[#041e49]">
      {/* Left Icon Sidebar (Visible ONLY when Admin is authenticated) */}
      <AdminSidebar
        currentTab={currentTab}
        onSelectTab={(tab) => setCurrentTab(tab)}
        onLogout={handleLogout}
      />

      {/* Main App Content Wrapper with Left Margin for Sidebar */}
      <div className="pl-16 sm:pl-20 min-h-screen flex flex-col flex-1">
        {/* Navigation Bar */}
        <AppHeader
          currentTab={currentTab}
          isAdmin={isAdmin}
          onLogout={handleLogout}
        />

        {/* Main Admin Workspace */}
        <main className="flex-1 w-full max-w-5xl mx-auto px-3.5 sm:px-6 py-5 sm:py-7">
          <AnimatePresence mode="wait">
            {currentTab === "admin_flow" && (
              <motion.div
                key="tab-admin-flow"
                initial={{ opacity: 0, y: 6 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -6 }}
                transition={{ duration: 0.18 }}
              >
                <AdminFlowGenerator
                  adminToken={adminToken}
                  onPageCreated={(_newPage: ButtonPage) => {
                    fetchPages();
                  }}
                  onViewPage={(slug: string) => {
                    setActiveSlugView(slug);
                  }}
                  onNotify={addToast}
                />
              </motion.div>
            )}

            {currentTab === "pages_list" && (
              <motion.div
                key="tab-pages-list"
                initial={{ opacity: 0, y: 6 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -6 }}
                transition={{ duration: 0.18 }}
              >
                <PagesManager
                  adminToken={adminToken}
                  initialEditPageId={editingPageId}
                  onClearEditPageId={() => setEditingPageId(null)}
                  onViewPage={(slug: string) => {
                    setActiveSlugView(slug);
                  }}
                  onNotify={addToast}
                />
              </motion.div>
            )}

            {currentTab === "settings" && (
              <motion.div
                key="tab-settings"
                initial={{ opacity: 0, y: 6 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -6 }}
                transition={{ duration: 0.18 }}
              >
                <SettingsManager
                  onNotify={addToast}
                  onPreviewPage={(slug: string) => {
                    setActiveSlugView(slug);
                  }}
                />
              </motion.div>
            )}

            {currentTab === "updater" && (
              <motion.div
                key="tab-updater"
                initial={{ opacity: 0, y: 6 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -6 }}
                transition={{ duration: 0.18 }}
              >
                <OneClickUpdaterHub onNotify={addToast} />
              </motion.div>
            )}

            {currentTab === "analytics" && (
              <motion.div
                key="tab-analytics"
                initial={{ opacity: 0, y: 6 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -6 }}
                transition={{ duration: 0.18 }}
              >
                <AnalyticsDashboard pages={pages} onRefresh={fetchPages} onNotify={addToast} />
              </motion.div>
            )}

            {currentTab === "cpanel_hub" && (
              <motion.div
                key="tab-cpanel-hub"
                initial={{ opacity: 0, y: 6 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -6 }}
                transition={{ duration: 0.18 }}
              >
                <CPanelDeploymentHub onNotify={addToast} />
              </motion.div>
            )}

            {currentTab === "wp_plugin" && (
              <motion.div
                key="tab-wp-plugin"
                initial={{ opacity: 0, y: 6 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -6 }}
                transition={{ duration: 0.18 }}
              >
                <WordPressPluginHub onNotify={addToast} />
              </motion.div>
            )}
          </AnimatePresence>
        </main>

        {/* Footer */}
        <footer className="mt-auto border-t border-[#e1e7f0] bg-white py-3.5 px-6 text-[11px] text-[#5f6368]">
          <div className="max-w-5xl mx-auto flex flex-col items-center gap-2">
            <div className="w-full flex flex-col sm:flex-row items-center justify-between gap-2">
              <div className="flex items-center gap-2">
                <span className="font-semibold text-[#1f1f1f]">Movie Hub HQ Drive</span>
                <span>•</span>
                <span>Non-Indexable Episode Link System</span>
              </div>
              <div className="font-mono text-[#747775]">
                Public Route: <span className="text-[#0b57d0]">/p/&#123;slug&#125;</span> • Protected Admin Mode
              </div>
            </div>
            <div className="w-full pt-2 border-t border-[#f0f4f9] flex items-center justify-center flex-wrap gap-x-5 gap-y-1.5 text-xs font-medium text-[#5f6368]">
              <a
                href="/dmca"
                onClick={(e) => {
                  e.preventDefault();
                  setActiveSlugView("dmca");
                  if (window.history.pushState) window.history.pushState({}, "", "/dmca");
                }}
                className="hover:text-[#0b57d0] hover:underline transition-colors"
              >
                DMCA
              </a>
              <span className="text-[#c4c7c5] select-none">•</span>
              <a
                href="/disclaimer"
                onClick={(e) => {
                  e.preventDefault();
                  setActiveSlugView("disclaimer");
                  if (window.history.pushState) window.history.pushState({}, "", "/disclaimer");
                }}
                className="hover:text-[#0b57d0] hover:underline transition-colors"
              >
                Disclaimer
              </a>
              <span className="text-[#c4c7c5] select-none">•</span>
              <a
                href="/about-us"
                onClick={(e) => {
                  e.preventDefault();
                  setActiveSlugView("about-us");
                  if (window.history.pushState) window.history.pushState({}, "", "/about-us");
                }}
                className="hover:text-[#0b57d0] hover:underline transition-colors"
              >
                About Us
              </a>
              <span className="text-[#c4c7c5] select-none">•</span>
              <a
                href="/privacy-policy"
                onClick={(e) => {
                  e.preventDefault();
                  setActiveSlugView("privacy-policy");
                  if (window.history.pushState) window.history.pushState({}, "", "/privacy-policy");
                }}
                className="hover:text-[#0b57d0] hover:underline transition-colors"
              >
                Privacy Policy
              </a>
            </div>
          </div>
        </footer>
      </div>

      <Toast toasts={toasts} onDismiss={removeToast} />
    </div>
  );
}
