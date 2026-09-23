#!/usr/bin/env python3
"""
cPanel Shared Hosting Deployment Packager & Release Automation Script
Generates production ZIP packages and synchronizes version metadata and checksums.
"""

import os
import sys
import json
import hashlib
import zipfile
import shutil

ROOT_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CPANEL_DIR = os.path.join(ROOT_DIR, "cpanel-package")
PUBLIC_DIR = os.path.join(ROOT_DIR, "public")
UPDATE_JSON_PUBLIC = os.path.join(PUBLIC_DIR, "releases", "update.json")
UPDATE_JSON_CPANEL = os.path.join(CPANEL_DIR, "releases", "update.json")

EXCLUDE_EXTS = {".pyc", ".swp", ".DS_Store", ".tmp"}
EXCLUDE_DIRS = {"__pycache__", ".git", ".idea", ".vscode"}

def create_zip(target_zip_path):
    print(f"[*] Packaging cPanel files into: {target_zip_path}")
    os.makedirs(os.path.dirname(target_zip_path), exist_ok=True)
    with zipfile.ZipFile(target_zip_path, "w", zipfile.ZIP_DEFLATED) as zf:
        for root, dirs, files in os.walk(CPANEL_DIR):
            dirs[:] = [d for d in dirs if d not in EXCLUDE_DIRS]
            for file in sorted(files):
                ext = os.path.splitext(file)[1]
                if ext in EXCLUDE_EXTS or file.startswith(".DS_"):
                    continue
                full_path = os.path.join(root, file)
                rel_path = os.path.relpath(full_path, CPANEL_DIR)
                zf.write(full_path, rel_path)
                print(f"  + Added: {rel_path}")

def calculate_sha256(file_path):
    h = hashlib.sha256()
    with open(file_path, "rb") as f:
        while chunk := f.read(65536):
            h.update(chunk)
    return h.hexdigest()

def main():
    print("=== Movie Hub HQ Drive - cPanel Packager ===")

    # Step 1: Pack primary archive
    primary_zip = os.path.join(PUBLIC_DIR, "cpanel-app-package.zip")
    create_zip(primary_zip)

    # Step 2: Calculate SHA256 checksum
    checksum = calculate_sha256(primary_zip)
    print(f"[*] Generated Package SHA256: {checksum}")

    # Step 3: Update public/releases/update.json
    if os.path.exists(UPDATE_JSON_PUBLIC):
        with open(UPDATE_JSON_PUBLIC, "r", encoding="utf-8") as f:
            manifest = json.load(f)
        manifest["checksum"] = checksum
        with open(UPDATE_JSON_PUBLIC, "w", encoding="utf-8") as f:
            json.dump(manifest, f, indent=2)
        print(f"[*] Updated public manifest with checksum: {UPDATE_JSON_PUBLIC}")

        # Sync to cpanel-package
        with open(UPDATE_JSON_CPANEL, "w", encoding="utf-8") as f:
            json.dump(manifest, f, indent=2)
        print(f"[*] Synced manifest to: {UPDATE_JSON_CPANEL}")

    # Step 4: Mirror archives to root and aliases
    mirror_targets = [
        os.path.join(ROOT_DIR, "cpanel-app-package.zip"),
        os.path.join(PUBLIC_DIR, "cpanel-shared-hosting.zip"),
        os.path.join(ROOT_DIR, "cpanel-shared-hosting.zip")
    ]
    for mirror in mirror_targets:
        shutil.copy2(primary_zip, mirror)
        print(f"[*] Mirrored bundle to: {mirror}")

    print(f"[✓] Deployment bundle ready! Version: v-4.0.0 | SHA256: {checksum[:12]}...")

if __name__ == "__main__":
    main()
