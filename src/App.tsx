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
    return localStorage.getItem("slea_admin_token") || "";
  });
  const [isAdmin, setIsAdmin] = useState<boolean>(() => {
    return Boolean(localStorage.getItem("slea_admin_token"));
  });
  const [currentTab, setCurrentTab] = useState<ViewTab>("admin_flow");
  const [activeSlugView, setActiveSlugView] = useState<string | null>(null);
  const [notFoundRoute, setNotFoundRoute] = useState<boolean>(false);
  const [pages, setPages] = useState<ButtonPage[]>([]);
  const [toasts, setToasts] = useState<ToastMessage[]>([]);

  const fetchPages = async () => {
    if (!adminToken) return;
    try {
      const res = await fetch("/api/pages", {
        headers: {
          Authorization: `Bearer ${adminToken}`,
          "x-admin-token": adminToken,
        },
      });
      if (res.status === 401) {
        localStorage.removeItem("slea_admin_token");
        setAdminToken("");
        setIsAdmin(false);
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

  // Verify admin token status on mount
  useEffect(() => {
    if (!adminToken) {
      setIsAdmin(false);
      return;
    }
    fetch("/api/auth/status", {
      headers: {
        Authorization: `Bearer ${adminToken}`,
        "x-admin-token": adminToken,
      },
    })
      .then((r) => r.json())
      .then((data) => {
        if (!data.logged_in) {
          localStorage.removeItem("slea_admin_token");
          setAdminToken("");
          setIsAdmin(false);
        } else {
          setIsAdmin(true);
        }
      })
      .catch(() => {
        // preserve local state if offline
      });
  }, []);

  useEffect(() => {
    if (isAdmin && adminToken) {
      fetchPages();
    }
  }, [adminToken, isAdmin, currentTab]);

  // Detect URL slug for public viewing or admin routes / 404 handling
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const qSlug = params.get("slug") || params.get("p");
    if (qSlug) {
      setActiveSlugView(qSlug);
      setNotFoundRoute(false);
      return;
    }

    const path = window.location.pathname;
    if (path.startsWith("/p/") || path.startsWith("/page/")) {
      const prefixLen = path.startsWith("/page/") ? 6 : 3;
      const slugFromPath = path.substring(prefixLen).replace(/\/$/, "");
      if (slugFromPath) {
        setActiveSlugView(slugFromPath);
        setNotFoundRoute(false);
      } else {
        setNotFoundRoute(true);
      }
      return;
    }

    const cleanPath = path.replace(/\/+$/, "") || "/";
    const adminRouteMap: Record<string, ViewTab> = {
      "/": "admin_flow",
      "/index.php": "admin_flow",
      "/admin": "admin_flow",
      "/admin.php": "admin_flow",
      "/pages": "pages_list",
      "/pages.php": "pages_list",
      "/settings": "settings",
      "/settings.php": "settings",
      "/analytics": "analytics",
      "/analytics.php": "analytics",
      "/update": "updater",
      "/update.php": "updater",
      "/updater": "updater",
      "/cpanel": "cpanel_hub",
      "/plugin": "wp_plugin",
    };

    if (cleanPath === "/login" || cleanPath === "/login.php") {
      setNotFoundRoute(false);
      if (isAdmin && window.history.replaceState) {
        window.history.replaceState({}, "", "/admin.php");
      }
      return;
    }

    if (cleanPath in adminRouteMap) {
      setNotFoundRoute(false);
      setCurrentTab(adminRouteMap[cleanPath]);
      if (!isAdmin && window.history.replaceState) {
        // Redirect unauthenticated visitors on admin pages to login.php
        window.history.replaceState({}, "", "/login.php");
      }
      return;
    }

    // Any other unrecognized route returns 404 Not Found
    setNotFoundRoute(true);
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

  const handleLoginSuccess = (token: string, _username: string) => {
    setAdminToken(token);
    setIsAdmin(true);
    setNotFoundRoute(false);
    setCurrentTab("admin_flow");
    if (window.history.replaceState) {
      window.history.replaceState({}, "", "/admin.php");
    }
  };

  const handleLogout = () => {
    if (adminToken) {
      fetch("/api/auth/logout", {
        method: "POST",
        headers: {
          Authorization: `Bearer ${adminToken}`,
          "x-admin-token": adminToken,
        },
      }).catch(() => {});
    }
    localStorage.removeItem("slea_admin_token");
    localStorage.removeItem("slea_admin_user");
    setAdminToken("");
    setIsAdmin(false);
    if (window.history.replaceState) {
      window.history.replaceState({}, "", "/login.php");
    }
    addToast("Logged out of Admin dashboard", "info");
  };

  // 404 Not Found for invalid/private non-existent routes (Matches cpanel-package/index.php)
  if (notFoundRoute) {
    return (
      <div className="min-h-screen bg-[#f8fafd] text-[#1f1f1f] text-center py-[60px] px-[20px] font-sans antialiased">
        <div className="max-w-[440px] mx-auto bg-white p-8 rounded-[24px] border border-[#e0e4eb] shadow-xs">
          <h1 className="text-[#d93025] text-xl font-bold mt-0 mb-2">404 Not Found</h1>
          <p className="text-[13px] text-[#5f6368] m-0">
            The requested page does not exist or has been removed.
          </p>
        </div>
      </div>
    );
  }

  // If viewing a public button page (/p/{slug})
  if (activeSlugView) {
    return (
      <div className="w-full min-h-screen bg-[#f8fafd] text-[#1f1f1f] flex flex-col font-sans antialiased">
        <PublicButtonPageView
          slug={activeSlugView}
          adminToken={adminToken}
          isAdmin={isAdmin}
          onBackToAdmin={
            isAdmin
              ? () => {
                  setActiveSlugView(null);
                  setCurrentTab("pages_list");
                  if (window.history.pushState) {
                    window.history.pushState({}, "", "/pages.php");
                  }
                }
              : undefined
          }
          onEditPage={
            isAdmin
              ? () => {
                  setActiveSlugView(null);
                  setCurrentTab("pages_list");
                  if (window.history.pushState) {
                    window.history.pushState({}, "", "/pages.php");
                  }
                }
              : undefined
          }
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
          onSelectTab={(tab) => {
            setCurrentTab(tab);
            if (window.history.replaceState) {
              const tabUrlMap: Record<ViewTab, string> = {
                admin_flow: "/admin.php",
                pages_list: "/pages.php",
                settings: "/settings.php",
                analytics: "/analytics.php",
                updater: "/update.php",
                cpanel_hub: "/admin.php",
                wp_plugin: "/admin.php",
              };
              window.history.replaceState({}, "", tabUrlMap[tab] || "/admin.php");
            }
          }}
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
                      if (window.history.pushState) {
                        window.history.pushState({}, "", `/p/${slug}`);
                      }
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
                      if (window.history.pushState) {
                        window.history.pushState({}, "", `/p/${slug}`);
                      }
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
