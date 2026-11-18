"""Builds an installable WordPress plugin zip for the idea-network plugin.

The archive root contains a single folder `idea-network/` and all entry
paths use forward slashes (PowerShell's Compress-Archive uses backslashes,
which WordPress cannot extract correctly on Linux servers).

Usage: python build-plugin-zip.py [output-path]
"""

import os
import sys
import zipfile

HERE = os.path.dirname(os.path.abspath(__file__))
PLUGIN_DIR = os.path.join(os.path.dirname(HERE), "idea-network")
DEFAULT_OUT = os.path.join(os.path.dirname(HERE), "idea-network.zip")

EXCLUDE_DIRS = {"__pycache__", ".git", ".svn"}


def build(output_path: str) -> str:
    if not os.path.isfile(os.path.join(PLUGIN_DIR, "idea-network.php")):
        raise SystemExit("idea-network.php not found in %s" % PLUGIN_DIR)

    parent = os.path.dirname(PLUGIN_DIR)

    with zipfile.ZipFile(output_path, "w", zipfile.ZIP_DEFLATED) as archive:
        for root, dirs, files in os.walk(PLUGIN_DIR):
            dirs[:] = [d for d in dirs if d not in EXCLUDE_DIRS]
            for name in sorted(files):
                full = os.path.join(root, name)
                # arcname like: idea-network/includes/class-excel-reader.php
                arcname = os.path.relpath(full, parent).replace(os.sep, "/")
                archive.write(full, arcname)

    return output_path


if __name__ == "__main__":
    out = sys.argv[1] if len(sys.argv) > 1 else DEFAULT_OUT
    path = build(out)

    # Verify what WordPress will see.
    with zipfile.ZipFile(path) as archive:
        names = archive.namelist()
        header_ok = False
        if "idea-network/idea-network.php" in names:
            data = archive.read("idea-network/idea-network.php").decode("utf-8")
            header_ok = "Plugin Name:" in data

    print("zip: %s (%d bytes)" % (path, os.path.getsize(path)))
    for name in names:
        print("  " + name)
    print("entries with backslash: %d" % sum(1 for n in names if "\\" in n))
    print("plugin header reachable at depth 1: %s" % header_ok)
