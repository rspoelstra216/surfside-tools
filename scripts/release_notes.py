#!/usr/bin/env python3

import json
import os
import re
import subprocess
from pathlib import Path


def run(*args: str) -> str:
    return subprocess.check_output(args, text=True).strip()


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

    cleaned: list[str] = []
    for line in summary_lines:
        line = re.sub(r"^[-*+]\s+", "", line).strip()
        if line and line not in cleaned:
            cleaned.append(line)
        if len(cleaned) >= 6:
            break

    return cleaned


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

    entries: list[str] = []
    for number in reversed(pr_numbers):
        payload = run("gh", "api", f"repos/{repository}/pulls/{number}")
        pr = json.loads(payload)
        title = pr.get("title", f"Pull request #{number}").strip()
        body = pr.get("body") or ""
        url = pr.get("html_url", "")
        summary = extract_summary(body)

        heading = f"### {title} ([#{number}]({url}))" if url else f"### {title} (#{number})"
        entries.append(heading)
        entries.append("")

        if summary:
            entries.extend(f"- {item}" for item in summary)
        else:
            entries.append("- No release description was provided in the pull request.")
        entries.append("")

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
