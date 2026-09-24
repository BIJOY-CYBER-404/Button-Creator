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
  const [adminToken, setAdminToken] = useState<string>(() => {
    return localStorage.getItem("slea_admin_token") || "admin_token_default_session";
  });
  const [isAdmin, setIsAdmin] = useState<boolean>(true);
  const [currentTab, setCurrentTab] = useState<ViewTab>("admin_flow");
  const [activeSlugView, setActiveSlugView] = useState<string | null>(null);
  const [pages, setPages] = useState<ButtonPage[]>([]);
  const [toasts, setToasts] = useState<ToastMessage[]>([]);

  const fetchPages = async () => {
    try {
      const res = await fetch("/api/pages", {
        headers: {
          Authorization: `Bearer ${adminToken}`,
          "x-admin-token": adminToken,
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

  // Detect URL slug for public viewing (e.g. /p/flp-120926 or ?p=flp-120926)
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const qSlug = params.get("slug") || params.get("p");
    if (qSlug) {
      setActiveSlugView(qSlug);
      return;
    }

    const path = window.location.pathname;
    if (path.startsWith("/p/")) {
      const slugFromPath = path.substring(3).replace(/\/$/, "");
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
    setAdminToken("");
    setIsAdmin(false);
    addToast("Logged out of Admin dashboard", "info");
  };

  // If viewing a public button page
  if (activeSlugView) {
    return (
      <div className="w-full min-h-screen bg-[#f8fafd] text-[#1f1f1f] flex flex-col font-sans antialiased">
        <PublicButtonPageView
          slug={activeSlugView}
          onBackToAdmin={() => {
            setActiveSlugView(null);
            // Clean URL bar if needed
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
      {/* Left Icon Sidebar (Generate, Pages, Settings, cPanel, Plugin) */}
      {isAdmin && (
        <AdminSidebar
          currentTab={currentTab}
          onSelectTab={setCurrentTab}
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

        {/* Main Admin / Public Workspace */}
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
                      // Page created callback
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
                  <AnalyticsDashboard pages={pages} onRefresh={fetchPages} />
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
                  <WordPressPluginHub
                    onNotify={addToast}
                    onSimulateSample={(sampleUrl) => {
                      setCurrentTab("admin_flow");
                      addToast(`Loaded sample URL into Generator: ${sampleUrl}`, "info");
                    }}
                  />
                </motion.div>
              )}
            </AnimatePresence>
          )}
        </main>
      </div>

      {/* Material 3 Toast Notifications */}
      <Toast toasts={toasts} onDismiss={removeToast} />
    </div>
  );
}
