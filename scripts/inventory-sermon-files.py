#!/usr/bin/env python3
"""Create a small, read-only NAS MP4 inventory for Surfside Tools.

Usage:
  python3 scripts/inventory-sermon-files.py --root "/Volumes/Backup/DESKTOP-NE2CG3V/E" --output sermon-inventory.csv

Only relative MP4 paths and byte sizes go into the CSV; original files are not changed.
Use a root containing the entire Year/Video/Month tree, not a single month.
"""
import argparse
import csv
import os
from pathlib import Path


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--root", required=True, type=Path, help="Folder containing all archived sermon MP4s")
    parser.add_argument("--output", required=True, type=Path, help="Output CSV for Surfside Tools")
    args = parser.parse_args()
    root = args.root.expanduser().resolve()
    if not root.is_dir():
        parser.error("--root must point to an existing directory")
    output = args.output.expanduser().resolve()
    if output == root or root in output.parents:
        parser.error("--output must be outside --root so it is not included in backups")
    count = 0
    total = 0
    with output.open("w", encoding="utf-8", newline="") as stream:
        writer = csv.writer(stream)
        writer.writerow(["source_path", "size_bytes"])
        for current, dirs, names in os.walk(root, followlinks=False):
            dirs[:] = sorted(d for d in dirs if not (Path(current) / d).is_symlink())
            for name in sorted(names):
                path = Path(current) / name
                if path.is_symlink() or path.suffix.lower() != ".mp4":
                    continue
                relative = path.relative_to(root).as_posix()
                size = path.stat().st_size
                if not size:
                    continue
                writer.writerow([relative, size])
                count += 1
                total += size
    print(f"Inventory saved to {output}: {count} MP4 files, {total:,} bytes")
    print("Upload this CSV to Surfside Tools > Manage Website > Message Archive > Roku Copies.")


if __name__ == "__main__":
    main()
