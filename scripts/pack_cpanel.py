#!/usr/bin/env python3
"""
cPanel Shared Hosting Deployment Packager & Release Automation Script
Generates production ZIP packages, syncs cpanel-shared-hosting directory,
and synchronizes version metadata and SHA-256 checksums across all manifests.
"""

import os
import sys
import json
import hashlib
import zipfile
import shutil

ROOT_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CPANEL_DIR = os.path.join(ROOT_DIR, "cpanel-package")
SHARED_HOSTING_DIR = os.path.join(ROOT_DIR, "cpanel-shared-hosting")
PUBLIC_DIR = os.path.join(ROOT_DIR, "public")
UPDATE_JSON_PUBLIC = os.path.join(PUBLIC_DIR, "releases", "update.json")
UPDATE_JSON_CPANEL = os.path.join(CPANEL_DIR, "releases", "update.json")
UPDATE_JSON_ROOT = os.path.join(ROOT_DIR, "releases", "update.json")
UPDATE_JSON_SHARED = os.path.join(SHARED_HOSTING_DIR, "releases", "update.json")

EXCLUDE_EXTS = {".pyc", ".swp", ".DS_Store", ".tmp"}
EXCLUDE_DIRS = {"__pycache__", ".git", ".idea", ".vscode", "backups", "temp", "data", "releases", "public"}
EXCLUDE_FILES = {"config.local.php", ".maintenance"}

PROTECTED_GUARD_FILES = [
    os.path.join("data", ".htaccess"),
    os.path.join("data", "index.php"),
    os.path.join("backups", ".htaccess"),
    os.path.join("backups", "index.php"),
    os.path.join("temp", ".htaccess"),
    os.path.join("temp", "index.php"),
]

def create_zip(target_zip_path):
    print(f"[*] Packaging cPanel files into: {target_zip_path}")
    os.makedirs(os.path.dirname(target_zip_path), exist_ok=True)
    with zipfile.ZipFile(target_zip_path, "w", zipfile.ZIP_DEFLATED) as zf:
        for root, dirs, files in os.walk(CPANEL_DIR):
            dirs[:] = [d for d in dirs if d not in EXCLUDE_DIRS]
            for file in sorted(files):
                ext = os.path.splitext(file)[1]
                if ext in EXCLUDE_EXTS or file.startswith(".DS_") or file in EXCLUDE_FILES:
                    continue
                full_path = os.path.join(root, file)
                rel_path = os.path.relpath(full_path, CPANEL_DIR)
                zf.write(full_path, rel_path)
                print(f"  + Added: {rel_path}")

        # Include only security guard files (.htaccess & index.php) inside data/, backups/, and temp/
        for guard_rel in PROTECTED_GUARD_FILES:
            guard_full = os.path.join(CPANEL_DIR, guard_rel)
            if os.path.exists(guard_full):
                zf.write(guard_full, guard_rel)
                print(f"  + Added Guard: {guard_rel}")

def sync_shared_hosting_dir():
    if not os.path.isdir(SHARED_HOSTING_DIR):
        os.makedirs(SHARED_HOSTING_DIR, exist_ok=True)
    for root, dirs, files in os.walk(CPANEL_DIR):
        dirs[:] = [d for d in dirs if d not in EXCLUDE_DIRS]
        rel_dir = os.path.relpath(root, CPANEL_DIR)
        target_dir = SHARED_HOSTING_DIR if rel_dir == "." else os.path.join(SHARED_HOSTING_DIR, rel_dir)
        os.makedirs(target_dir, exist_ok=True)
        for file in sorted(files):
            ext = os.path.splitext(file)[1]
            if ext in EXCLUDE_EXTS or file.startswith(".DS_") or file in EXCLUDE_FILES:
                continue
            src_file = os.path.join(root, file)
            dst_file = os.path.join(target_dir, file)
            shutil.copy2(src_file, dst_file)

    for guard_rel in PROTECTED_GUARD_FILES:
        src_guard = os.path.join(CPANEL_DIR, guard_rel)
        dst_guard = os.path.join(SHARED_HOSTING_DIR, guard_rel)
        if os.path.exists(src_guard):
            os.makedirs(os.path.dirname(dst_guard), exist_ok=True)
            shutil.copy2(src_guard, dst_guard)

def calculate_sha256(file_path):
    h = hashlib.sha256()
    with open(file_path, "rb") as f:
        while chunk := f.read(65536):
            h.update(chunk)
    return h.hexdigest()

def main():
    print("=== Movie Hub HQ Drive - cPanel Packager ===")

    # Step 0: Sync cpanel-shared-hosting directory
    sync_shared_hosting_dir()

    # Step 1: Pack primary archive
    primary_zip = os.path.join(PUBLIC_DIR, "cpanel-app-package.zip")
    create_zip(primary_zip)

    # Step 2: Calculate SHA256 checksum
    checksum = calculate_sha256(primary_zip)
    print(f"[*] Generated Package SHA256: {checksum}")

    # Step 3: Update all update.json manifests
    if os.path.exists(UPDATE_JSON_PUBLIC):
        with open(UPDATE_JSON_PUBLIC, "r", encoding="utf-8") as f:
            manifest = json.load(f)
        manifest["checksum"] = checksum
        manifest["sha256"] = checksum

        for manifest_path in [UPDATE_JSON_PUBLIC, UPDATE_JSON_CPANEL, UPDATE_JSON_ROOT, UPDATE_JSON_SHARED]:
            os.makedirs(os.path.dirname(manifest_path), exist_ok=True)
            with open(manifest_path, "w", encoding="utf-8") as f:
                json.dump(manifest, f, indent=2)
            print(f"[*] Synced manifest to: {manifest_path}")

    # Step 4: Mirror archives to root and aliases
    mirror_targets = [
        os.path.join(ROOT_DIR, "cpanel-app-package.zip"),
        os.path.join(ROOT_DIR, "api", "cpanel-app-package.zip"),
        os.path.join(PUBLIC_DIR, "cpanel-shared-hosting.zip"),
        os.path.join(ROOT_DIR, "cpanel-shared-hosting.zip")
    ]
    for mirror in mirror_targets:
        os.makedirs(os.path.dirname(mirror), exist_ok=True)
        shutil.copy2(primary_zip, mirror)
        print(f"[*] Mirrored bundle to: {mirror}")

    ver = manifest.get("version", "25.0") if "manifest" in locals() else "25.0"
    clean_ver = "v-" + ver.lstrip("v-")
    print(f"[✓] Deployment bundle ready! Version: {clean_ver} | SHA256: {checksum}")

if __name__ == "__main__":
    main()
