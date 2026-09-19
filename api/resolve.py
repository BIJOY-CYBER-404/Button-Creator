import os
import sys
import json
from http.server import BaseHTTPRequestHandler

# Ensure all possible module locations are added to sys.path
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(current_dir)
for p in [current_dir, parent_dir, os.getcwd(), "/var/task", "/var/task/api"]:
    if p and os.path.exists(p) and p not in sys.path:
        sys.path.insert(0, p)

try:
    import resolver
except ImportError:
    sys.path.append(parent_dir)
    import resolver

class handler(BaseHTTPRequestHandler):
    def do_OPTIONS(self):
        self.send_response(200)
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Content-Type, Authorization, X-Requested-With")
        self.send_header("Content-Length", "0")
        self.end_headers()

    def do_POST(self):
        try:
            try:
                content_length = int(self.headers.get("Content-Length", 0))
            except Exception:
                content_length = 0

            post_data = "{}"
            if content_length > 0:
                post_data = self.rfile.read(content_length).decode("utf-8", errors="replace")
            else:
                try:
                    post_data = self.rfile.read().decode("utf-8", errors="replace")
                except Exception:
                    post_data = "{}"

            try:
                body = json.loads(post_data or "{}")
            except Exception:
                body = {}

            url = body.get("url", "").strip()

            result = resolver.run_resolver_api(url)

            response_bytes = json.dumps(result).encode("utf-8")
            status_code = 200 if result.get("success") else 400

            self.send_response(status_code)
            self.send_header("Content-Type", "application/json; charset=utf-8")
            self.send_header("Content-Length", str(len(response_bytes)))
            self.send_header("Access-Control-Allow-Origin", "*")
            self.send_header("Connection", "close")
            self.end_headers()
            self.wfile.write(response_bytes)
        except Exception as exc:
            err_payload = json.dumps({
                "success": False,
                "error": f"Server error: {str(exc)}"
            }).encode("utf-8")
            self.send_response(500)
            self.send_header("Content-Type", "application/json; charset=utf-8")
            self.send_header("Content-Length", str(len(err_payload)))
            self.send_header("Access-Control-Allow-Origin", "*")
            self.send_header("Connection", "close")
            self.end_headers()
            self.wfile.write(err_payload)

    def do_GET(self):
        payload = json.dumps({
            "status": "active",
            "endpoint": "/api/resolve",
            "method": "POST"
        }).encode("utf-8")
        self.send_response(200)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(payload)))
        self.send_header("Access-Control-Allow-Origin", "*")
        self.end_headers()
        self.wfile.write(payload)
