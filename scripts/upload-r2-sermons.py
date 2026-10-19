#!/usr/bin/env python3
"""Import historical sermon MP4s to Cloudflare R2 by canonical YouTube ID.

Requires AWS CLI v2 configured with bucket-scoped R2 credentials in environment:
  AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION=auto
The AWS CLI automatically uses multipart uploads for large videos.
"""
import argparse
import csv
import os
import re
import subprocess
import sys
from pathlib import Path

VIDEO_ID = re.compile(r"^[A-Za-z0-9_-]{11}$")


def aws(endpoint, *args, capture=True):
    proc = subprocess.run(
        ["aws", "--endpoint-url", endpoint, *args],
        text=True, capture_output=capture, check=False,
    )
    return proc


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--manifest", required=True, type=Path,
                        help="CSV exported from Surfside Tools, with source_path populated")
    parser.add_argument("--root", required=True, type=Path, help="Directory containing source MP4s")
    parser.add_argument("--account-id", required=True, help="32-character Cloudflare account ID")
    parser.add_argument("--bucket", required=True, help="R2 bucket name")
    parser.add_argument("--upload", action="store_true",
                        help="Actually upload; omit for dry-run validation")
    args = parser.parse_args()
    if not re.fullmatch(r"[0-9a-fA-F]{32}", args.account_id):
        parser.error("Invalid Cloudflare account ID")
    if not re.fullmatch(r"[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]", args.bucket):
        parser.error("Invalid R2 bucket name")
    if args.upload and not (os.environ.get("AWS_ACCESS_KEY_ID") and os.environ.get("AWS_SECRET_ACCESS_KEY")):
        parser.error("Set AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY in your shell, not in the manifest")
    os.environ.setdefault("AWS_DEFAULT_REGION", "auto")
    endpoint = "https://" + args.account_id + ".r2.cloudflarestorage.com"
    root = args.root.expanduser().resolve()
    seen = set()
    failures = 0
    ready = 0
    skipped = 0
    with args.manifest.open(newline="", encoding="utf-8-sig") as file:
        reader = csv.DictReader(file)
        if not {"video_id", "source_path"}.issubset(reader.fieldnames or []):
            parser.error("CSV must have video_id and source_path columns")
        for line, row in enumerate(reader, start=2):
            video_id = (row.get("video_id") or "").strip()
            source = (row.get("source_path") or "").strip()
            if not VIDEO_ID.fullmatch(video_id):
                print(f"Line {line}: invalid YouTube ID", file=sys.stderr)
                failures += 1
                continue
            if video_id in seen:
                print(f"Line {line}: duplicate ID {video_id}", file=sys.stderr)
                failures += 1
                continue
            seen.add(video_id)
            if not source:
                skipped += 1
                continue
            path = (root / source).resolve()
            if not path.is_relative_to(root) or not path.is_file() or path.suffix.lower() != ".mp4":
                print(f"{video_id}: file missing/not an MP4/outside --root: {source}", file=sys.stderr)
                failures += 1
                continue
            key = f"sermons/{video_id}.mp4"
            size = path.stat().st_size
            if size <= 0:
                print(f"{video_id}: empty file", file=sys.stderr)
                failures += 1
                continue
            if not args.upload:
                print(f"[DRY RUN] {video_id}: {path} ({size:,} bytes) -> {key}")
                ready += 1
                continue

            destination = f"s3://{args.bucket}/{key}"
            head = aws(endpoint, "s3api", "head-object", "--bucket", args.bucket, "--key", key,
                       "--query", "[ContentLength,ContentType]", "--output", "text")
            if head.returncode == 0:
                cols = head.stdout.strip().split()
                if len(cols) >= 2 and cols[0].isdigit() and int(cols[0]) == size and cols[1] == "video/mp4":
                    print(f"[SKIP EXISTS] {video_id}: same size and content type")
                    skipped += 1
                    continue
                print(f"[CONFLICT] {video_id}: existing R2 copy differs. Manual review required.", file=sys.stderr)
                failures += 1
                continue
            if "404" not in head.stderr and "Not Found" not in head.stderr:
                print(f"[ERROR] {video_id}: failed to check object: {head.stderr.strip()}", file=sys.stderr)
                failures += 1
                continue

            print(f"[UPLOAD] {video_id}: {size:,} bytes", flush=True)
            result = aws(endpoint, "s3", "cp", str(path), destination,
                         "--content-type", "video/mp4", "--only-show-errors")
            if result.returncode != 0:
                print(f"[ERROR] {video_id}: {result.stderr.strip()}", file=sys.stderr)
                failures += 1
                continue
            verify = aws(endpoint, "s3api", "head-object", "--bucket", args.bucket, "--key", key,
                         "--query", "[ContentLength,ContentType]", "--output", "text")
            cols = verify.stdout.strip().split()
            if verify.returncode != 0 or len(cols) < 2 or not cols[0].isdigit() or int(cols[0]) != size or cols[1] != "video/mp4":
                print(f"[ERROR] {video_id}: post-upload verification failed", file=sys.stderr)
                failures += 1
                continue
            print(f"[DONE] {video_id}")
            ready += 1
    print(f"\n{'Uploaded / ready' if args.upload else 'Ready to upload'}: {ready}; skipped: {skipped}; errors: {failures}")
    print("After uploads, open Surfside Tools > Manage Website > Roku Sermon Copies > Reconcile R2 objects.")
    return 1 if failures else 0


if __name__ == "__main__":
    sys.exit(main())
