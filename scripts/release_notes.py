#!/usr/bin/env python3

import json
import os
import re
import subprocess
from pathlib import Path


CATEGORIES = ("Added", "Improved", "Fixed")


def run(*args: str) -> str:
    return subprocess.check_output(args, text=True).strip()


def clean_items(lines: list[str], limit: int = 12) -> list[str]:
    cleaned: list[str] = []
    for raw in lines:
        line = raw.strip()
        if not line or line.startswith("<!--") or line.startswith("-->"):
            continue
        line = re.sub(r"^[-*+]\s+", "", line).strip()
        if not line or line.lower() in {"none", "n/a", "not applicable"}:
            continue
        if line not in cleaned:
            cleaned.append(line)
        if len(cleaned) >= limit:
            break
    return cleaned


def extract_release_notes(body: str) -> dict[str, list[str]]:
    sections = {category: [] for category in CATEGORIES}
    if not body:
        return sections

    lines = body.splitlines()
    in_release_notes = False
    current_category = ""

    for raw in lines:
        line = raw.strip()
        if re.match(r"^##\s+Release Notes\s*$", line, flags=re.IGNORECASE):
            in_release_notes = True
            current_category = ""
            continue
        if in_release_notes and re.match(r"^##\s+", line):
            break
        if not in_release_notes:
            continue

        category_match = re.match(r"^###\s+(Added|Improved|Fixed)\s*$", line, flags=re.IGNORECASE)
        if category_match:
            current_category = category_match.group(1).title()
            continue

        if current_category:
            sections[current_category].append(raw)

    return {category: clean_items(items) for category, items in sections.items()}


def extract_summary(body: str) -> list[str]:
    if not body:
        return []

    lines = body.splitlines()
    summary_lines: list[str] = []
    in_summary = False

    for raw in lines:
        line = raw.strip()
        if re.match(r"^##\s+Summary\s*$", line, flags=re.IGNORECASE):
            in_summary = True
            continue
        if in_summary and line.startswith("## "):
            break
        if in_summary and line:
            summary_lines.append(line)

    if not summary_lines:
        for raw in lines:
            line = raw.strip()
            if not line or line.startswith("#"):
                continue
            summary_lines.append(line)
            if len(summary_lines) >= 4:
                break

    return clean_items(summary_lines, limit=6)


def main() -> None:
    previous_tag = os.environ.get("PREVIOUS_TAG", "").strip()
    repository = os.environ["GITHUB_REPOSITORY"]
    version = os.environ["NEXT_VERSION"]

    commit_range = f"{previous_tag}..HEAD" if previous_tag else "HEAD"
    subjects = run("git", "log", commit_range, "--merges", "--pretty=%s").splitlines()

    pr_numbers: list[int] = []
    for subject in subjects:
        match = re.search(r"Merge pull request #(\d+)", subject)
        if match:
            number = int(match.group(1))
            if number not in pr_numbers:
                pr_numbers.append(number)

    categorized: dict[str, list[str]] = {category: [] for category in CATEGORIES}
    legacy_entries: list[str] = []

    for number in reversed(pr_numbers):
        payload = run("gh", "api", f"repos/{repository}/pulls/{number}")
        pr = json.loads(payload)
        title = pr.get("title", f"Pull request #{number}").strip()
        body = pr.get("body") or ""
        url = pr.get("html_url", "")
        release_sections = extract_release_notes(body)
        has_release_notes = any(release_sections.values())
        pr_link = f"[#{number}]({url})" if url else f"#{number}"

        if has_release_notes:
            for category in CATEGORIES:
                for item in release_sections[category]:
                    categorized[category].append(f"- {item} ({pr_link})")
            continue

        summary = extract_summary(body)
        heading = f"### {title} ({pr_link})"
        legacy_entries.extend([heading, ""])
        if summary:
            legacy_entries.extend(f"- {item}" for item in summary)
        else:
            legacy_entries.append("- No release description was provided in the pull request.")
        legacy_entries.append("")

    entries: list[str] = []
    for category in CATEGORIES:
        if categorized[category]:
            entries.extend([f"### {category}", "", *categorized[category], ""])

    if legacy_entries:
        entries.extend(["### Additional Changes", "", *legacy_entries])

    if not entries:
        fallback = run("git", "log", commit_range, "--pretty=- %s", "--no-merges")
        entries = [fallback or "- Maintenance release", ""]

    notes = "\n".join(entries).rstrip() + "\n"
    release_date = run("date", "-u", "+%Y-%m-%d")

    existing = Path("CHANGELOG.md").read_text() if Path("CHANGELOG.md").exists() else "# Changelog\n"
    existing_body = re.sub(r"^# Changelog\s*", "", existing, count=1).lstrip()

    changelog = (
        "# Changelog\n\n"
        f"## [{version}] - {release_date}\n\n"
        f"{notes}\n"
        f"{existing_body}"
    )
    Path("CHANGELOG.md").write_text(changelog)
    Path("RELEASE_NOTES.md").write_text(f"## What's changed\n\n{notes}")


if __name__ == "__main__":
    main()
