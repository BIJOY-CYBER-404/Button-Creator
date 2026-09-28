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

export default function App() {
  const isInitialPublicUrl = () => {
    const params = new URLSearchParams(window.location.search);
    if (params.get("slug") || params.get("p")) return true;
    const path = window.location.pathname;
    return path.startsWith("/p/") || path.startsWith("/page/");
  };

  const [adminToken, setAdminToken] = useState<string>(() => {
    const saved = localStorage.getItem("slea_admin_token");
    if (saved) return saved;
    if (!isInitialPublicUrl()) {
      localStorage.setItem("slea_admin_token", "admin_token_default_session");
      return "admin_token_default_session";
    }
    return "";
  });
  const [isAdmin, setIsAdmin] = useState<boolean>(() => {
    const saved = localStorage.getItem("slea_admin_token");
    if (saved) return true;
    return !isInitialPublicUrl();
  });
  const [currentTab, setCurrentTab] = useState<ViewTab>("admin_flow");
  const [activeSlugView, setActiveSlugView] = useState<string | null>(null);
  const [editingPageId, setEditingPageId] = useState<string | null>(null);
  const [pages, setPages] = useState<ButtonPage[]>([]);
  const [toasts, setToasts] = useState<ToastMessage[]>([]);

  const fetchPages = async () => {
    try {
      const res = await fetch("/api/pages", {
        headers: {
          Authorization: `Bearer ${adminToken || "admin_token_default_session"}`,
          "x-admin-token": adminToken || "admin_token_default_session",
        },
      });
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

  // Detect URL slug for public viewing (/p/{slug} or ?slug={slug})
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const qSlug = params.get("slug") || params.get("p");
    if (qSlug) {
      setActiveSlugView(qSlug);
      return;
    }

    const path = window.location.pathname;
    if (path.startsWith("/p/") || path.startsWith("/page/")) {
      const prefixLen = path.startsWith("/page/") ? 6 : 3;
      const slugFromPath = path.substring(prefixLen).replace(/\/$/, "");
      if (slugFromPath) {
        setActiveSlugView(slugFromPath);
      }
    }
  }, []);

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

  const handleLoginSuccess = (token: string, _username: string) => {
    setAdminToken(token);
    setIsAdmin(true);
    setCurrentTab("admin_flow");
  };

  const handleLogout = () => {
    localStorage.removeItem("slea_admin_token");
    localStorage.removeItem("slea_admin_user");
    setAdminToken("");
    setIsAdmin(false);
    addToast("Logged out of Admin dashboard", "info");
  };

  // If viewing a public button page (/p/{slug})
  if (activeSlugView) {
    return (
      <div className="w-full min-h-screen bg-[#f8fafd] text-[#1f1f1f] flex flex-col font-sans antialiased">
        <PublicButtonPageView
          slug={activeSlugView}
          adminToken={adminToken}
          isAdmin={isAdmin}
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
          onNotify={addToast}
        />
        <Toast toasts={toasts} onDismiss={removeToast} />
      </div>
    );
  }

  return (
    <div className="w-full min-h-screen bg-[#f0f4f9] text-[#1f1f1f] flex flex-col font-sans antialiased overflow-x-hidden selection:bg-[#d3e3fd] selection:text-[#041e49]">
      {/* Left Icon Sidebar (Visible ONLY when Admin is authenticated) */}
      {isAdmin && (
        <AdminSidebar
          currentTab={currentTab}
          onSelectTab={(tab) => setCurrentTab(tab)}
          onLogout={handleLogout}
        />
      )}

      {/* Main App Content Wrapper with Left Margin for Sidebar */}
      <div className={`${isAdmin ? "pl-16 sm:pl-20" : ""} min-h-screen flex flex-col flex-1`}>
        {/* Navigation Bar */}
        <AppHeader
          currentTab={currentTab}
          isAdmin={isAdmin}
          onLogout={handleLogout}
          onShowLogin={() => setIsAdmin(false)}
        />

        {/* Main Admin / Login Workspace */}
        <main className="flex-1 w-full max-w-5xl mx-auto px-3.5 sm:px-6 py-5 sm:py-7">
          {!isAdmin ? (
            <motion.div
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.2 }}
              className="max-w-md mx-auto my-12"
            >
              <AdminLoginCard
                onLoginSuccess={handleLoginSuccess}
                onNotify={addToast}
              />
            </motion.div>
          ) : (
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
                  <SettingsManager onNotify={addToast} />
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
          )}
        </main>

        {/* Footer */}
        <footer className="mt-auto border-t border-[#e1e7f0] bg-white py-3.5 px-6 text-[11px] text-[#5f6368]">
          <div className="max-w-5xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-2">
            <div className="flex items-center gap-2">
              <span className="font-semibold text-[#1f1f1f]">Movie Hub HQ Drive</span>
              <span>•</span>
              <span>Non-Indexable Episode Link System</span>
            </div>
            <div className="font-mono text-[#747775]">
              Public Route: <span className="text-[#0b57d0]">/p/&#123;slug&#125;</span> • Protected Admin Mode
            </div>
          </div>
        </footer>
      </div>

      <Toast toasts={toasts} onDismiss={removeToast} />
    </div>
  );
}
