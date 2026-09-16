/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { useState } from "react";
import { motion } from "motion/react";
import { AppHeader } from "./components/AppHeader";
import { Toast, ToastMessage } from "./components/Toast";
import { UnifiedWorkspace } from "./components/UnifiedWorkspace";
import { PythonLogicViewer } from "./components/PythonLogicViewer";
import { PipelineMode } from "./types";

export default function App() {
  const [showPythonLogic, setShowPythonLogic] = useState<boolean>(false);
  const [pipelineMode, setPipelineMode] = useState<PipelineMode>("unified");
  const [prefillUrl, setPrefillUrl] = useState<string>("");
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

  return (
    <div className="w-full min-h-screen bg-[#f0f4f9] text-[#1f1f1f] flex flex-col font-sans antialiased overflow-x-hidden selection:bg-[#d3e3fd] selection:text-[#041e49]">
      {/* Top Navigation Bar */}
      <AppHeader
        onTogglePythonLogic={() => setShowPythonLogic(!showPythonLogic)}
        showPythonLogic={showPythonLogic}
      />

      {/* Main Content Area - Responsive Container with vertical scroll */}
      <main className="flex-1 w-full max-w-4xl mx-auto px-3.5 sm:px-6 py-5 sm:py-7 space-y-5">
        {/* Python Backend Logic Modal / Drawer */}
        {showPythonLogic && (
          <motion.div
            initial={{ opacity: 0, y: -8, scale: 0.99 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: -8, scale: 0.99 }}
            transition={{ duration: 0.2, ease: "easeOut" }}
            className="w-full max-w-full overflow-hidden"
          >
            <PythonLogicViewer
              onRunTestSample={() => {
                setShowPythonLogic(false);
                setPipelineMode("unified");
                setPrefillUrl(
                  "https://mydverse.com/2026/09/fanletter-please-korean-drama-in-hindi/"
                );
                addToast("Loaded Fanletter Drama test sample into Unified Pipeline", "info");
              }}
            />
          </motion.div>
        )}

        {/* Merged Unified Workspace */}
        <div className="w-full max-w-full overflow-hidden">
          <UnifiedWorkspace
            key={`unified-${pipelineMode}-${prefillUrl}`}
            initialUrl={prefillUrl}
            initialMode={pipelineMode}
            onNotify={addToast}
            onOpenPythonLogic={() => setShowPythonLogic(true)}
          />
        </div>
      </main>

      {/* Material 3 Toast Notifications */}
      <Toast toasts={toasts} onDismiss={removeToast} />
    </div>
  );
}
