import os
import sys
from http.server import BaseHTTPRequestHandler

class handler(BaseHTTPRequestHandler):
    def do_GET(self):
        # Look for the zip file in public, root, or api
        possible_paths = [
            os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "public", "cpanel-app-package.zip"),
            os.path.join(os.path.dirname(os.path.abspath(__file__)), "cpanel-app-package.zip"),
            os.path.join(os.getcwd(), "public", "cpanel-app-package.zip"),
            os.path.join(os.getcwd(), "cpanel-app-package.zip"),
            "/var/task/public/cpanel-app-package.zip",
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
            self.send_header("Content-Disposition", 'attachment; filename="cpanel-app-package.zip"')
            self.send_header("Content-Length", str(len(zip_data)))
            self.send_header("Cache-Control", "public, max-age=3600")
            self.send_header("Access-Control-Allow-Origin", "*")
            self.end_headers()
            self.wfile.write(zip_data)
        else:
            # Fallback redirect to static public asset
            self.send_response(302)
            self.send_header("Location", "/cpanel-app-package.zip")
            self.end_headers()

    def do_OPTIONS(self):
        self.send_response(200)
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Access-Control-Allow-Methods", "GET, OPTIONS")
        self.end_headers()
