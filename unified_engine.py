#!/usr/bin/env python3
"""
Unified Link Resolver & Extractor Engine
Merges URL Shortener / Redirect Resolving and Action Link Extraction into a seamless pipeline.
"""

import sys
import json
from urllib.parse import urlparse
import resolver
import extractor

def run_unified_pipeline(url=None, html=None, base_url=None, button_only=True, auto_resolve=True):
    if not url and not html:
        return {"success": False, "error": "Please provide a valid URL or HTML snippet."}

    try:
        # Case 1: URL provided
        if url:
            url = url.strip()
            if not extractor.valid_http_url(url):
                return {"success": False, "error": f"Invalid URL scheme or format: {url}"}

            # If auto_resolve is enabled, resolve all redirect hops first
            if auto_resolve:
                resolve_res = resolver.resolve_url(url, return_html=True)
                final_dest_url = resolve_res["final"]
                final_html = resolve_res.get("final_html")

                # If final destination HTML was captured in memory during resolution, reuse it!
                if not final_html or len(final_html) < 200:
                    try:
                        final_dest_url, final_html = extractor.fetch_page(final_dest_url)
                    except Exception as fetch_err:
                        # Even if page fetch fails, still return redirect chain
                        return {
                            "success": True,
                            "resolved": True,
                            "original_url": url,
                            "final_url": final_dest_url,
                            "redirects": resolve_res["redirects"],
                            "chain": resolve_res["chain"],
                            "items": [],
                            "count": 0,
                            "bytes": 0,
                            "fetch_warning": str(fetch_err)
                        }

                # Extract action and download links from the final destination HTML
                items = extractor.extract_from_html(final_html, final_dest_url, button_only=button_only)

                return {
                    "success": True,
                    "resolved": True,
                    "original_url": url,
                    "final_url": final_dest_url,
                    "redirects": resolve_res["redirects"],
                    "chain": resolve_res["chain"],
                    "items": items,
                    "count": len(items),
                    "bytes": len(final_html)
                }

            else:
                # Direct extraction without resolver hops
                ext_res = extractor.run_extraction_api(url=url, button_only=button_only)
                if not ext_res.get("success"):
                    return ext_res
                return {
                    "success": True,
                    "resolved": False,
                    "original_url": url,
                    "final_url": ext_res.get("final_url", url),
                    "redirects": 0,
                    "chain": [
                        {
                            "step": 1,
                            "url": ext_res.get("final_url", url),
                            "status": 200,
                            "type": "Direct HTTP Fetch"
                        }
                    ],
                    "items": ext_res.get("items", []),
                    "count": ext_res.get("count", 0),
                    "bytes": ext_res.get("bytes", 0)
                }

        # Case 2: Raw HTML snippet provided
        elif html:
            target_url = (base_url or "https://example.com/page").strip()
            items = extractor.extract_from_html(html, target_url, button_only=button_only)
            return {
                "success": True,
                "resolved": False,
                "original_url": target_url,
                "final_url": target_url,
                "redirects": 0,
                "chain": [],
                "items": items,
                "count": len(items),
                "bytes": len(html)
            }

    except Exception as e:
        return {"success": False, "error": str(e)}

if __name__ == "__main__":
    if len(sys.argv) > 1 and sys.argv[1] == "--json":
        raw_input = sys.stdin.read() if not sys.stdin.isatty() else "{}"
        params = json.loads(raw_input or "{}")
        result = run_unified_pipeline(
            url=params.get("url"),
            html=params.get("html"),
            base_url=params.get("base_url"),
            button_only=params.get("button_only", True),
            auto_resolve=params.get("auto_resolve", True)
        )
        print(json.dumps(result))
        sys.exit(0)
    elif len(sys.argv) > 1 and sys.argv[1].startswith("http"):
        target_url = sys.argv[1]
        result = run_unified_pipeline(url=target_url)
        print(json.dumps(result, indent=2))
        sys.exit(0)
    else:
        print("Unified Link Resolver & Extractor Engine")
        print("Usage: python3 unified_engine.py <URL>")
