/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { useState } from "react";
import { motion } from "motion/react";
import { AppHeader } from "./components/AppHeader";
import { Toast, ToastMessage } from "./components/Toast";
import { ExtractorCard } from "./components/ExtractorCard";
import { ResolverPage } from "./components/ResolverPage";
import { PythonLogicViewer } from "./components/PythonLogicViewer";
import { ActivePage } from "./types";
import { Download, ArrowUpRight } from "lucide-react";

export default function App() {
  const [showPythonLogic, setShowPythonLogic] = useState<boolean>(false);
  const [activePage, setActivePage] = useState<ActivePage>("extractor");
  const [prefillExtractorUrl, setPrefillExtractorUrl] = useState<string>("");
  const [toasts, setToasts] = useState<ToastMessage[]>([]);

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

  const navigateToExtractor = (url?: string) => {
    if (url) {
      setPrefillExtractorUrl(url);
      addToast("Transferred resolved URL to Link Extractor", "info");
    }
    setActivePage("extractor");
  };

  const navigateToResolver = (url?: string) => {
    setActivePage("resolver");
    if (url) {
      addToast("Opened URL Resolver", "info");
    }
  };

  return (
    <div className="w-full min-h-screen bg-[#f4f6f8] text-[#1d2329] flex flex-col font-sans antialiased overflow-x-hidden">
      {/* Top Navigation Bar */}
      <AppHeader
        onTogglePythonLogic={() => setShowPythonLogic(!showPythonLogic)}
        showPythonLogic={showPythonLogic}
        activePage={activePage}
        onNavigatePage={(page) => {
          setActivePage(page);
          addToast(
            `Switched to ${page === "extractor" ? "Link Extractor" : "URL Resolver"}`,
            "info"
          );
        }}
      />

      {/* Main Content Area - Responsive Container with vertical scroll */}
      <main className="flex-1 w-full max-w-4xl mx-auto px-3 sm:px-6 py-4 sm:py-6 space-y-4">
        {/* Python Backend Logic Modal / Drawer */}
        {showPythonLogic && (
          <motion.div
            initial={{ opacity: 0, y: -10 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -10 }}
            className="w-full max-w-full overflow-hidden"
          >
            <PythonLogicViewer
              onRunTestSample={() => {
                setShowPythonLogic(false);
                setActivePage("extractor");
                addToast("Switched to Extractor to run sample test", "info");
              }}
            />
          </motion.div>
        )}

        {/* Active Tool View */}
        <div className="w-full max-w-full overflow-hidden">
          {activePage === "extractor" ? (
            <ExtractorCard
              key={`extractor-${prefillExtractorUrl}`}
              initialUrl={prefillExtractorUrl}
              onNotify={addToast}
              onOpenPythonLogic={() => setShowPythonLogic(true)}
              onNavigateToResolver={navigateToResolver}
            />
          ) : (
            <ResolverPage
              key="resolver"
              onNotify={addToast}
              onNavigateToExtractor={navigateToExtractor}
            />
          )}
        </div>
      </main>

      {/* Modern Toast Notifications */}
      <Toast toasts={toasts} onDismiss={removeToast} />
    </div>
  );
}
