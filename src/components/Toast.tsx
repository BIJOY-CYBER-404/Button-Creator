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
      className="fixed top-5 right-5 z-50 flex flex-col items-end gap-3 pointer-events-none max-w-sm w-[calc(100%-2.5rem)]"
    >
      <AnimatePresence>
        {toasts.map((toast) => {
          const type = toast.type || "info";
          return (
            <motion.div
              key={toast.id}
              initial={{ opacity: 0, x: 40, scale: 0.95 }}
              animate={{ opacity: 1, x: 0, scale: 1 }}
              exit={{ opacity: 0, x: 40, scale: 0.95 }}
              transition={{ duration: 0.24, ease: [0.16, 1, 0.3, 1] }}
              className={`pointer-events-auto bg-white rounded-2xl shadow-xl border p-3.5 flex items-start gap-3 w-full ring-1 ${
                type === "success"
                  ? "border-emerald-200 ring-emerald-500/10"
                  : type === "error"
                  ? "border-rose-200 ring-rose-500/10"
                  : "border-blue-200 ring-blue-500/10"
              }`}
            >
              <div
                className={`p-2 rounded-xl shrink-0 mt-0.5 ${
                  type === "success"
                    ? "bg-emerald-50 text-emerald-600"
                    : type === "error"
                    ? "bg-rose-50 text-rose-600"
                    : "bg-blue-50 text-blue-600"
                }`}
              >
                {type === "success" && <CheckCircle2 className="w-4 h-4" />}
                {type === "error" && <AlertCircle className="w-4 h-4" />}
                {type === "info" && <Info className="w-4 h-4" />}
              </div>

              <div className="flex-1 min-w-0 pr-1">
                <div className="text-[11px] font-bold uppercase tracking-wider text-slate-500">
                  {type === "success" ? "Success" : type === "error" ? "Notice / Error" : "Information"}
                </div>
                <div className="text-xs font-medium text-slate-800 leading-snug mt-0.5 break-words">
                  {toast.text}
                </div>
              </div>

              <button
                type="button"
                onClick={() => onDismiss(toast.id)}
                className="p-1 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 shrink-0 transition-colors cursor-pointer"
                title="Dismiss"
              >
                <X className="w-3.5 h-3.5" />
              </button>
            </motion.div>
          );
        })}
      </AnimatePresence>
    </aside>
  );
};

