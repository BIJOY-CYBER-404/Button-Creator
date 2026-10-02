import React, { useState } from "react";
import { Lock, ShieldAlert, ArrowRight } from "lucide-react";

interface AdminLoginCardProps {
  onLoginSuccess: (token: string, username: string) => void;
  onNotify?: (text: string, type: "success" | "error" | "info") => void;
}

export const AdminLoginCard: React.FC<AdminLoginCardProps> = ({
  onLoginSuccess,
  onNotify,
}) => {
  const [username, setUsername] = useState<string>("");
  const [password, setPassword] = useState<string>("");
  const [loading, setLoading] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    try {
      const res = await fetch("/api/auth/login", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ username: username.trim(), password }),
      });

      const data = await res.json();
      if (data.success && data.token) {
        localStorage.setItem("slea_admin_token", data.token);
        localStorage.setItem("slea_admin_user", data.username || "admin");
        onNotify?.("Signed in successfully", "success");
        onLoginSuccess(data.token, data.username || "admin");
      } else {
        setError(data.error || "Invalid username or password.");
        onNotify?.(data.error || "Sign in failed", "error");
      }
    } catch (err: any) {
      setError(err.message || "Failed to reach authentication server.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="w-full max-w-md mx-auto py-6 px-4">
      <div className="bg-white rounded-3xl p-6 sm:p-8 border border-[#e0e4eb] shadow-xs space-y-6">
        {/* Header */}
        <div className="text-center space-y-2">
          <div className="w-12 h-12 rounded-2xl bg-[#e8f0fe] border border-[#c2e7ff] text-[#0b57d0] flex items-center justify-center mx-auto shadow-2xs">
            <Lock className="w-6 h-6 text-[#0b57d0]" />
          </div>
          <h2 className="text-xl font-extrabold text-[#111827] tracking-tight">Sign In</h2>
          <p className="text-xs text-[#5f6368] max-w-xs mx-auto leading-relaxed">
            Enter your credentials to continue.
          </p>
        </div>

        {/* Error Alert */}
        {error && (
          <div className="bg-[#fce8e6] text-[#c5221f] text-xs p-3 rounded-xl flex items-center gap-2 border border-[#fad2cf]">
            <ShieldAlert className="w-4 h-4 shrink-0" />
            <span>{error}</span>
          </div>
        )}

        {/* Form */}
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="space-y-1">
            <label className="text-xs font-semibold text-[#444746] block">
              Username
            </label>
            <input
              type="text"
              value={username}
              onChange={(e) => setUsername(e.target.value)}
              placeholder="Enter username"
              autoComplete="username"
              required
              className="w-full px-3.5 py-2.5 rounded-xl border border-[#c4c7c5] focus:border-[#0b57d0] focus:ring-2 focus:ring-[#0b57d0]/15 outline-none text-sm font-medium transition-all"
            />
          </div>

          <div className="space-y-1">
            <label className="text-xs font-semibold text-[#444746] block">
              Password
            </label>
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="••••••••"
              autoComplete="current-password"
              required
              className="w-full px-3.5 py-2.5 rounded-xl border border-[#c4c7c5] focus:border-[#0b57d0] focus:ring-2 focus:ring-[#0b57d0]/15 outline-none text-sm font-medium transition-all font-mono"
            />
          </div>

          <button
            type="submit"
            disabled={loading}
            className="w-full py-3 px-4 rounded-xl bg-[#0b57d0] hover:bg-[#0842a0] disabled:bg-[#a8c7fa] text-white font-bold text-xs flex items-center justify-center gap-2 cursor-pointer shadow-xs transition-all"
          >
            {loading ? (
              <span className="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            ) : (
              <>
                <span>Sign In</span>
                <ArrowRight className="w-4 h-4" />
              </>
            )}
          </button>
        </form>
      </div>
    </div>
  );
};
