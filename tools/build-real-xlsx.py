"""Generates a genuine .xlsx with openpyxl for cross-validation.

openpyxl writes files with a real Excel-compatible structure (styles, theme,
calcChain-free but full workbook parts), which is a good external check for
the plugin's hand-written reader.

Usage: python build-real-xlsx.py [output-path]
"""

import sys

try:
    from openpyxl import Workbook
except ImportError:
    sys.stderr.write("openpyxl is not installed. Run: pip install openpyxl\n")
    sys.exit(1)


def main(path: str) -> None:
    wb = Workbook()
    ws = wb.active
    ws.title = "Sheet1"

    # 9 columns + 2 extra columns that must be ignored.
    ws.append(
        [
            "HDR1",
            "HDR2",
            "HDR3",
            "HDR4",
            "HDR5",
            "HDR6",
            "HDR7",
            "HDR8",
            "HDR9",
            "EXTRA1",
            "EXTRA2",
        ]
    )
    ws.append(
        ["R1C1", "R1C2", "R1C3", 5000, "R1C5", "R1C6", "R1C7", "R1C8", "R1C9", "x1", "x2"]
    )
    # Empty first cell -> must be reported as skipped.
    ws.append(
        [None, "R2C2", "R2C3", 7000, "R2C5", "R2C6", "R2C7", "R2C8", "R2C9", "y1", "y2"]
    )
    ws.append(
        ["R3C1", "R3C2", "R3C3", "12345.5", "R3C5", "R3C6", "R3C7", "R3C8", "R3C9", "z1", "z2"]
    )
    # Blank row -> must be ignored.
    ws.append([])
    ws.append(
        ["R5C1", "R5C2", "R5C3", 900, "R5C5", "R5C6", "R5C7", "R5C8", "R5C9", "w1", "w2"]
    )

    wb.save(path)
    print(path)


if __name__ == "__main__":
    main(sys.argv[1] if len(sys.argv) > 1 else "real.xlsx")
