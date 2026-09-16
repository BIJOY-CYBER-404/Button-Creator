import React from "react";
import { motion, AnimatePresence } from "motion/react";
import { CheckCircle2, AlertCircle, Info, X } from "lucide-react";

export interface ToastMessage {
  id: string;
  text: string;
  type?: "success" | "error" | "info";
}

interface ToastProps {
  toasts: ToastMessage[];
  onDismiss: (id: string) => void;
}

export const Toast: React.FC<ToastProps> = ({ toasts, onDismiss }) => {
  return (
    <aside
      aria-label="Notifications"
      className="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-2.5 pointer-events-none max-w-sm w-[calc(100%-3rem)]"
    >
      <AnimatePresence>
        {toasts.map((toast) => (
          <motion.div
            key={toast.id}
            initial={{ opacity: 0, y: 20, scale: 0.96 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: 15, scale: 0.96 }}
            transition={{ duration: 0.22, ease: "easeOut" }}
            onClick={() => onDismiss(toast.id)}
            className="pointer-events-auto bg-[#303030] text-[#f2f2f2] text-xs px-4 py-3 rounded-2xl m3-elevation-2 flex items-center justify-between gap-3 w-full cursor-pointer hover:bg-[#3c3c3c] transition-colors"
          >
            <div className="flex items-center gap-2.5 min-w-0">
              {toast.type === "success" && (
                <CheckCircle2 className="w-4 h-4 text-[#c2e7ff] shrink-0" />
              )}
              {toast.type === "error" && (
                <AlertCircle className="w-4 h-4 text-[#ffb4ab] shrink-0" />
              )}
              {(!toast.type || toast.type === "info") && (
                <Info className="w-4 h-4 text-[#c2e7ff] shrink-0" />
              )}
              <span className="truncate leading-normal font-normal text-[13px] tracking-wide">{toast.text}</span>
            </div>
            <button
              type="button"
              className="p-1 rounded-full text-[#c4c7c5] hover:text-white hover:bg-white/10 shrink-0 transition-colors"
            >
              <X className="w-3.5 h-3.5" />
            </button>
          </motion.div>
        ))}
      </AnimatePresence>
    </aside>
  );
};

