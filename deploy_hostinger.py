#!/usr/bin/env python3
"""
Airnova Hostinger Deployment Script
Deploys the static atmospheric suite to airnova.space via Hostinger Developers API.
"""

import os
import sys
import json
import base64
import time
import zipfile
import urllib.request
import urllib.error
from pathlib import Path

DOMAIN = os.environ.get("HOSTINGER_DOMAIN", "airnova.space")
USERNAME = os.environ.get("HOSTINGER_USERNAME", "u658435137")
TOKEN = os.environ.get("HOSTINGER_API_TOKEN", "")

if not TOKEN and len(sys.argv) > 1:
    TOKEN = sys.argv[1].strip()

if not TOKEN:
    print("\n[!] Error: Hostinger API Token is required.")
    print("Usage: python deploy_hostinger.py <HOSTINGER_API_TOKEN>")
    print("Or set environment variable: $env:HOSTINGER_API_TOKEN=\"your_token\"\n")
    sys.exit(1)

ROOT = Path(__file__).resolve().parent
TIMESTAMP = int(time.time())
PKG_NAME = f"airnova_deploy_{TIMESTAMP}.zip"
ZIP_PATH = ROOT / PKG_NAME

# Files and directories to include in deployment
INCLUDES = [
    "index.html",
    "aqi.html",
    "station.html",
    "weather.html",
    "airvision.html",
    "404.html",
    ".htaccess",
    "_redirects",
    "styles.css",
    "vivid.css",
    "atmosphere.css",
    "site-theme.css",
    "app.js",
    "aqi-overview.js",
    "atmosphere.js",
    "site-nav.js",
    "assets",
    "pollutants",
    "prediction",
]

print(f"\n=======================================================")
print(f"  Deploying Airnova to Hostinger ({DOMAIN})")
print(f"=======================================================")

# Step 1: Package
print(f"\n[1/5] Creating deployment archive: {PKG_NAME}...")
with zipfile.ZipFile(ZIP_PATH, "w", zipfile.ZIP_DEFLATED) as zf:
    for item in INCLUDES:
        p = ROOT / item
        if p.is_file():
            zf.write(p, p.name)
        elif p.is_dir():
            for fp in p.rglob("*"):
                if fp.is_file() and not fp.name.startswith("."):
                    zf.write(fp, fp.relative_to(ROOT).as_posix())

zip_bytes = ZIP_PATH.read_bytes()
print(f"      Archive created successfully: {len(zip_bytes):,} bytes ({len(zip_bytes) / 1024 / 1024:.2f} MB)")

headers = {
    "Authorization": f"Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AirnovaDeployer/1.0"
}

try:
    # Step 2: Request Upload URL
    print(f"\n[2/5] Requesting upload URL from Hostinger Developers API...")
    req_data = json.dumps({"username": USERNAME, "domain": DOMAIN}).encode()
    req = urllib.request.Request(
        "https://developers.hostinger.com/api/hosting/v1/files/upload-urls",
        data=req_data,
        headers=headers,
        method="POST"
    )
    with urllib.request.urlopen(req) as resp:
        res = json.loads(resp.read().decode())

    tus_base_url = res.get("url", "").rstrip("/")
    tus_url = f"{tus_base_url}/{PKG_NAME}"
    auth_key = res.get("auth_key")
    rest_auth_key = res.get("rest_auth_key")
    meta_filename = base64.b64encode(PKG_NAME.encode()).decode()

    # Step 3: Initiate TUS Upload
    print(f"[3/5] Uploading package via TUS protocol...")
    tus_headers = {
        "Tus-Resumable": "1.0.0",
        "Upload-Length": str(len(zip_bytes)),
        "Upload-Metadata": f"filename {meta_filename}",
        "X-Auth": auth_key,
        "X-Auth-Rest": rest_auth_key,
        "User-Agent": "Mozilla/5.0"
    }
    req_init = urllib.request.Request(tus_url, data=b"", headers=tus_headers, method="POST")
    with urllib.request.urlopen(req_init) as resp_init:
        loc = resp_init.headers.get("Location")
        loc_url = ("https://srv1835-files.hstgr.io/rest" + loc) if (loc and not loc.startswith("http")) else loc

        patch_headers = {
            "Tus-Resumable": "1.0.0",
            "Upload-Offset": "0",
            "Content-Type": "application/offset+octet-stream",
            "Content-Length": str(len(zip_bytes)),
            "X-Auth": auth_key,
            "X-Auth-Rest": rest_auth_key,
            "User-Agent": "Mozilla/5.0"
        }
        req_patch = urllib.request.Request(loc_url, data=zip_bytes, headers=patch_headers, method="PATCH")
        with urllib.request.urlopen(req_patch) as resp_patch:
            print(f"      Upload complete (Status: {resp_patch.status})")

    # Step 4: Deploy archive
    print(f"\n[4/5] Extracting and deploying archive to {DOMAIN}...")
    deploy_url = f"https://developers.hostinger.com/api/hosting/v1/accounts/{USERNAME}/websites/{DOMAIN}/deploy"
    deploy_data = json.dumps({"archive_path": PKG_NAME}).encode()
    deploy_req = urllib.request.Request(deploy_url, data=deploy_data, headers=headers, method="POST")
    with urllib.request.urlopen(deploy_req) as deploy_resp:
        print(f"      Deployment executed (Status: {deploy_resp.status})")

    # Step 5: Cache Clear
    print(f"\n[5/5] Purging website cache...")
    purge_url = f"https://developers.hostinger.com/api/hosting/v1/accounts/{USERNAME}/websites/{DOMAIN}/cache/clear"
    purge_req = urllib.request.Request(purge_url, headers=headers, method="DELETE")
    try:
        with urllib.request.urlopen(purge_req) as purge_resp:
            print(f"      Cache purged successfully (Status: {purge_resp.status})")
    except Exception as e:
        print(f"      Cache purge notice: {e}")

    print(f"\n=======================================================")
    print(f"  SUCCESS! Airnova is live at: https://{DOMAIN}")
    print(f"=======================================================\n")

except urllib.error.HTTPError as err:
    print(f"\n[x] Deployment Failed with HTTP {err.code}: {err.reason}")
    try:
        body = err.read().decode()
        print(f"    Details: {body}")
    except:
        pass
    sys.exit(1)
except Exception as e:
    print(f"\n[x] Unexpected error: {e}")
    sys.exit(1)
finally:
    ZIP_PATH.unlink(missing_ok=True)
