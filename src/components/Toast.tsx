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
      className="fixed bottom-5 right-5 z-50 flex flex-col items-end gap-2 pointer-events-none max-w-sm w-[calc(100%-2.5rem)]"
    >
      <AnimatePresence>
        {toasts.map((toast) => (
          <motion.div
            key={toast.id}
            initial={{ opacity: 0, y: 15, scale: 0.95 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: 10, scale: 0.95 }}
            transition={{ duration: 0.2 }}
            onClick={() => onDismiss(toast.id)}
            className="pointer-events-auto bg-slate-900/95 backdrop-blur-md text-white text-xs px-3.5 py-2.5 rounded-xl shadow-xl border border-slate-700/70 flex items-center justify-between gap-2.5 w-full cursor-pointer hover:bg-slate-800/95 transition-colors"
          >
            <div className="flex items-center gap-2 min-w-0">
              {toast.type === "success" && (
                <CheckCircle2 className="w-4 h-4 text-emerald-400 shrink-0" />
              )}
              {toast.type === "error" && (
                <AlertCircle className="w-4 h-4 text-rose-400 shrink-0" />
              )}
              {(!toast.type || toast.type === "info") && (
                <Info className="w-4 h-4 text-blue-400 shrink-0" />
              )}
              <span className="truncate leading-tight font-medium">{toast.text}</span>
            </div>
            <X className="w-3.5 h-3.5 text-slate-400 hover:text-white shrink-0" />
          </motion.div>
        ))}
      </AnimatePresence>
    </aside>
  );
};
