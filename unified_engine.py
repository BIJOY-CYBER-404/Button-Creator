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
                # Check if input URL is already the target Blogspot destination
                if resolver.is_target_destination(url):
                    _, final_html = extractor.fetch_page(url)
                    items = extractor.extract_from_html(final_html, url, button_only=button_only) if final_html else []
                    page_title = extractor.extract_page_title(final_html, url) if final_html else extractor.derive_title_from_url(url)
                    return {
                        "success": True,
                        "resolved": True,
                        "input_type": "target_url",
                        "identified_shorten_url": None,
                        "original_url": url,
                        "final_url": url,
                        "page_title": page_title,
                        "redirects": 0,
                        "target_destination_verified": True,
                        "chain": [{
                            "step": 1,
                            "url": url,
                            "status": 200,
                            "type": "Target Destination (Blogspot Episode Page)"
                        }],
                        "items": items,
                        "count": len(items),
                        "bytes": len(final_html) if final_html else 0
                    }

                # Check if input URL is already a shortened URL
                parsed_in = urlparse(url)
                host_in = (parsed_in.hostname or "").lower()
                path_in = (parsed_in.path or "").strip("/")
                query_in = (parsed_in.query or "").lower()
                is_direct_shortlink = (
                    any(k in host_in for k in [
                        "shrt.sohojgyan", "safe.sohojgyan", "go.sohojgyan", "bit.ly", "tinyurl",
                        "ouo.io", "droplink", "gplinks", "shrinkme", "cutt.ly", "is.gd", "v.gd", "t.co", "adlinkfly"
                    ])
                    or ("sohojgyan.com" in host_in and len(path_in.split("/")) == 1 and path_in != "")
                    or any(qp in query_in for qp in ["code=", "safelink=", "token=", "url=", "dest="])
                )

                if is_direct_shortlink:
                    # Flow A: Shorten URL pasted -> resolve shorten url -> extract buttons -> generate button pages
                    resolve_res = resolver.resolve_shortlink_until_target(url, max_retries=4, return_html=True)
                    final_dest_url = resolve_res["final"]
                    final_html = resolve_res.get("final_html")
                    if not final_html or len(final_html) < 200 or resolver.is_target_destination(final_dest_url):
                        try:
                            _, dest_page_html = extractor.fetch_page(final_dest_url)
                            if dest_page_html and len(dest_page_html) > len(final_html or ""):
                                final_html = dest_page_html
                        except Exception:
                            pass

                    items = extractor.extract_from_html(final_html, final_dest_url, button_only=button_only) if final_html else []
                    is_target = resolver.is_target_destination(final_dest_url)
                    page_title = extractor.extract_page_title(final_html, final_dest_url) if final_html else extractor.derive_title_from_url(final_dest_url)
                    return {
                        "success": True,
                        "resolved": True,
                        "input_type": "shorten_url",
                        "identified_shorten_url": url,
                        "original_url": url,
                        "final_url": final_dest_url,
                        "page_title": page_title,
                        "target_destination_verified": is_target,
                        "redirects": resolve_res.get("redirects", 0),
                        "chain": resolve_res.get("chain", []),
                        "items": items,
                        "count": len(items),
                        "bytes": len(final_html) if final_html else 0
                    }

                # Flow B: Post URL pasted (e.g. https://mydverse.com/2026/07/the-princess-and-the-werewolf-chinese-hindi/)
                # Step 1: Fetch the Post URL page to identify the shorten URL from the "Episode Wise Links" button
                post_final_url = url
                post_html = ""
                try:
                    fetched_post_url, fetched_post_html = extractor.fetch_page(url)
                    if fetched_post_url:
                        post_final_url = fetched_post_url
                    if fetched_post_html:
                        post_html = fetched_post_html
                except Exception:
                    pass

                identified_shorten_url = None
                final_dest_url = post_final_url
                final_html = post_html
                combined_chain = [{
                    "step": 1,
                    "url": post_final_url,
                    "status": 200,
                    "type": "Source Post URL"
                }]

                if post_html and not resolver.is_target_destination(post_final_url):
                    import re
                    from urllib.parse import urljoin

                    def is_valid_shortlink(cand_url):
                        if not cand_url:
                            return False
                        low = cand_url.lower()
                        if low.startswith(("javascript:", "mailto:", "tel:", "sms:", "#")):
                            return False
                        if "#respond" in low or cand_url.rstrip("/") == post_final_url.rstrip("/"):
                            return False
                        if any(b in low for b in ["movihubhq.com", "moviehubhq.com", "movihub", "payout-rates", "privacy", "disclaimer", "dmca", "terms", "contact", "about", "pages/"]):
                            return False
                        return True

                    candidates = []
                    fallback_btn_slide = []
                    fallback_shortlinks = []

                    # Parse all <a>...</a> tags without crossing </a> boundaries
                    for m_a in re.finditer(r'<a\s+([^>]*?)href=[\'"]([^\'"]+)[\'"]([^>]*)>((?:(?!</a>)[\s\S])*?)</a>', post_html, re.I):
                        attrs = (m_a.group(1) or "") + " " + (m_a.group(3) or "")
                        raw_href = (m_a.group(2) or "").strip()
                        inner_html = m_a.group(4) or ""
                        if not raw_href or raw_href.startswith(("#", "javascript:", "mailto:", "tel:")):
                            continue
                        if "comment-reply" in attrs.lower():
                            continue
                        cand = urljoin(post_final_url, raw_href)
                        if not is_valid_shortlink(cand):
                            continue

                        text_clean = re.sub(r"<[^>]+>", " ", inner_html)
                        text_clean = re.sub(r"\s+", " ", text_clean).strip()

                        # Priority 1: Explicit "Episode Wise Links" / "Episode Wise" / "Download Episodes" button text
                        if re.search(r'(?:Episode[\s_-]*Wise[\s_-]*Links?|Episode[\s_-]*Wise|Download[\s_-]*Episodes?|Episode[\s_-]*Links?)', text_clean, re.I):
                            if cand not in candidates:
                                candidates.append(cand)
                        # Priority 2: Button with class="btn-slide" (used on mydverse post pages for Episode Wise Links)
                        elif "btn-slide" in attrs.lower():
                            if cand not in fallback_btn_slide:
                                fallback_btn_slide.append(cand)
                        # Priority 3: Direct shortener link (safe.sohojgyan.com, shrt.sohojgyan.com, go.sohojgyan.com) or target Blogspot link
                        elif any(k in cand.lower() for k in ["safe.sohojgyan", "shrt.sohojgyan", "go.sohojgyan", "bit.ly", "tinyurl", "ouo.io"]) or resolver.is_target_destination(cand):
                            if cand not in fallback_shortlinks:
                                fallback_shortlinks.append(cand)

                    # Contextual nearby check if not yet found
                    m_near = re.search(r'(?:Episode[\s_-]*Wise[\s_-]*Links?|Episode[\s_-]*Wise)(?:(?!</a>)[\s\S]){0,350}?<a\s+[^>]*href=[\'"]([^\'"]+)[\'"]', post_html, re.I)
                    if m_near:
                        near_cand = urljoin(post_final_url, m_near.group(1).strip())
                        if is_valid_shortlink(near_cand) and near_cand not in candidates:
                            candidates.append(near_cand)

                    for c in fallback_btn_slide + fallback_shortlinks:
                        if c not in candidates:
                            candidates.append(c)

                    # Direct search in scripts for https://mydverse02.blogspot.com/p/*.html
                    for m_script in re.finditer(r'[\'"](https?://(?:www\.)?mydverse02\.blogspot\.[a-z.]+/p/[a-zA-Z0-9_-]+\.html)[\'"]', post_html, re.I):
                        c = m_script.group(1).strip()
                        if resolver.is_target_destination(c) and c not in candidates:
                            candidates.append(c)

                    # Step 2: Resolve the identified shorten URL -> extract buttons -> generate button pages
                    for shortlink_candidate in candidates:
                        if shortlink_candidate == url or shortlink_candidate == post_final_url:
                            continue

                        identified_shorten_url = shortlink_candidate

                        # If candidate is already the target destination
                        if resolver.is_target_destination(shortlink_candidate):
                            final_dest_url = shortlink_candidate
                            combined_chain.append({
                                "step": len(combined_chain) + 1,
                                "url": shortlink_candidate,
                                "status": 200,
                                "type": "Target Destination (Blogspot Episode Page)"
                            })
                            try:
                                _, sub_html = extractor.fetch_page(final_dest_url)
                                if sub_html:
                                    final_html = sub_html
                            except Exception:
                                pass
                            break

                        # Resolve shortened URL with retry until destination matches Blogspot structure
                        sub_resolve = resolver.resolve_shortlink_until_target(shortlink_candidate, max_retries=4, return_html=True)
                        sub_final_url = sub_resolve.get("final", "")
                        sub_final_html = sub_resolve.get("final_html")

                        if resolver.is_target_destination(sub_final_url) or sub_final_url:
                            if not sub_final_html or len(sub_final_html) < 200:
                                try:
                                    _, fetched_html = extractor.fetch_page(sub_final_url)
                                    if fetched_html:
                                        sub_final_html = fetched_html
                                except Exception:
                                    pass

                            combined_chain.append({
                                "step": len(combined_chain) + 1,
                                "url": shortlink_candidate,
                                "status": 200,
                                "type": "Identified Episode Wise Shortened URL"
                            })
                            for sc in sub_resolve.get("chain", []):
                                if sc.get("url") == shortlink_candidate and len(combined_chain) > 1:
                                    continue
                                combined_chain.append({
                                    "step": len(combined_chain) + 1,
                                    "url": sc.get("url"),
                                    "status": sc.get("status"),
                                    "type": sc.get("type")
                                })

                            final_dest_url = sub_final_url
                            final_html = sub_final_html
                            if resolver.is_target_destination(sub_final_url):
                                break

                # Fallback if post_html didn't yield candidates directly
                if not resolver.is_target_destination(final_dest_url) and not identified_shorten_url:
                    resolve_res = resolver.resolve_url(url, return_html=True)
                    final_dest_url = resolve_res.get("final", url)
                    final_html = resolve_res.get("final_html") or final_html
                    combined_chain = resolve_res.get("chain", combined_chain)

                # If final destination HTML was not captured, fetch it now
                if not final_html or len(final_html) < 200 or resolver.is_target_destination(final_dest_url):
                    try:
                        _, dest_page_html = extractor.fetch_page(final_dest_url)
                        if dest_page_html and len(dest_page_html) > len(final_html or ""):
                            final_html = dest_page_html
                    except Exception:
                        pass

                # Extract action and download links from the final destination HTML
                items = extractor.extract_from_html(final_html, final_dest_url, button_only=button_only) if final_html else []
                is_target = resolver.is_target_destination(final_dest_url)

                page_title = extractor.extract_page_title(final_html, final_dest_url) if final_html else ""
                if (not page_title or page_title == "Episode Download Links") and post_html:
                    post_title = extractor.extract_page_title(post_html, post_final_url)
                    if post_title:
                        page_title = post_title
                if not page_title:
                    page_title = extractor.derive_title_from_url(final_dest_url)

                return {
                    "success": True,
                    "resolved": True,
                    "input_type": "post_url",
                    "identified_shorten_url": identified_shorten_url,
                    "original_url": url,
                    "final_url": final_dest_url,
                    "page_title": page_title,
                    "target_destination_verified": is_target,
                    "redirects": max(0, len(combined_chain) - 1),
                    "chain": combined_chain,
                    "items": items,
                    "count": len(items),
                    "bytes": len(final_html) if final_html else 0
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
                    "page_title": ext_res.get("page_title") or extractor.derive_title_from_url(ext_res.get("final_url", url)),
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
