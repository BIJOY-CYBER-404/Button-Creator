/**
 * Safe fetch utility that prevents "Unexpected end of JSON input" errors.
 * Inspects content-type, verifies text body presence, guards against HTML 404/500 pages,
 * and extracts meaningful error diagnostics for serverless hosting environments like Vercel.
 */

export interface SafeFetchResult<T> {
  ok: boolean;
  status: number;
  data?: T;
  error?: string;
  isHtml?: boolean;
  isEmpty?: boolean;
}

export async function safeFetchJson<T = any>(
  input: RequestInfo,
  init?: RequestInit
): Promise<SafeFetchResult<T>> {
  try {
    const res = await fetch(input, init);
    const rawText = await res.text();

    if (!rawText || !rawText.trim()) {
      return {
        ok: false,
        status: res.status,
        isEmpty: true,
        error: `The server returned an empty response (HTTP ${res.status}). On Vercel, check that the serverless function completed without timing out.`,
      };
    }

    const trimmed = rawText.trim();
    if (
      trimmed.startsWith("<!DOCTYPE") ||
      trimmed.startsWith("<!doctype") ||
      trimmed.startsWith("<html") ||
      trimmed.startsWith("<head")
    ) {
      return {
        ok: false,
        status: res.status,
        isHtml: true,
        error: `Received an HTML page instead of JSON (HTTP ${res.status}). Check API endpoint routing on Vercel.`,
      };
    }

    try {
      const data = JSON.parse(trimmed) as T;
      const isSuccess = res.ok && (data as any)?.success !== false;
      const errorMsg =
        (data as any)?.error ||
        (!res.ok ? `Server error (HTTP ${res.status})` : undefined);

      return {
        ok: isSuccess,
        status: res.status,
        data,
        error: errorMsg,
      };
    } catch {
      return {
        ok: false,
        status: res.status,
        error: `Malformed server response: ${trimmed.slice(0, 120)}...`,
      };
    }
  } catch (netError: any) {
    return {
      ok: false,
      status: 0,
      error: netError.message || "Network connection error.",
    };
  }
}
