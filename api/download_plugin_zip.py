import os
import sys
from http.server import BaseHTTPRequestHandler

class handler(BaseHTTPRequestHandler):
    def do_GET(self):
        # Look for the zip file in public or root
        possible_paths = [
            os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "public", "source-link-episode-automator.zip"),
            os.path.join(os.path.dirname(os.path.abspath(__file__)), "source-link-episode-automator.zip"),
            os.path.join(os.getcwd(), "public", "source-link-episode-automator.zip"),
            "/var/task/public/source-link-episode-automator.zip",
        ]

        zip_data = None
        for p in possible_paths:
            if os.path.exists(p):
                try:
                    with open(p, "rb") as f:
                        zip_data = f.read()
                    break
                except Exception:
                    pass

        if zip_data:
            self.send_response(200)
            self.send_header("Content-Type", "application/zip")
            self.send_header("Content-Disposition", 'attachment; filename="source-link-episode-automator.zip"')
            self.send_header("Content-Length", str(len(zip_data)))
            self.send_header("Cache-Control", "public, max-age=3600")
            self.send_header("Access-Control-Allow-Origin", "*")
            self.end_headers()
            self.wfile.write(zip_data)
        else:
            # Fallback redirect to static public asset
            self.send_response(302)
            self.send_header("Location", "/source-link-episode-automator.zip")
            self.end_headers()

    def do_OPTIONS(self):
        self.send_response(200)
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Access-Control-Allow-Methods", "GET, OPTIONS")
        self.end_headers()
