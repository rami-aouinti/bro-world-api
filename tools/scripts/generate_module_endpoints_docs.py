#!/usr/bin/env python3
"""Generate endpoint inventory markdown from Symfony Route attributes."""

from __future__ import annotations

import argparse
import re
from pathlib import Path
from typing import Iterable

ROUTE_BLOCK = re.compile(r"#\[Route\((.*?)\)\]", re.S)
PATH_NAMED = re.compile(r"path:\s*'([^']+)'")
PATH_POSITIONAL = re.compile(r"^\s*'([^']+)'")
METHODS = re.compile(r"methods:\s*\[(.*?)\]", re.S)
METHOD_TOKEN = re.compile(r"Request::METHOD_([A-Z]+)")


def extract_entries(root: Path) -> list[tuple[str, str, str]]:
    entries: list[tuple[str, str, str]] = []

    for file_path in sorted(root.rglob("*.php")):
        content = file_path.read_text(encoding="utf-8")

        for match in ROUTE_BLOCK.finditer(content):
            block = match.group(1)
            path_match = PATH_NAMED.search(block) or PATH_POSITIONAL.search(block)
            path = path_match.group(1) if path_match else "(class-prefix)"

            methods_match = METHODS.search(block)
            methods = "-"
            if methods_match:
                parsed_methods = METHOD_TOKEN.findall(methods_match.group(1))
                methods = "|".join(parsed_methods) if parsed_methods else "-"

            entries.append((methods, path, file_path.as_posix()))

    return sorted(entries, key=lambda item: (item[0], item[1], item[2]))


def build_markdown(module: str, source_root: Path, entries: Iterable[tuple[str, str, str]]) -> str:
    lines = [
        f"# {module} module endpoints",
        "",
        f"Liste extraite automatiquement des attributs `#[Route(...)]` du module `{source_root.as_posix()}`.",
        "Chemin HTTP final exposé par l’API : `/api` + `Path`.",
        "",
        "| Method(s) | Path | Controller file |",
        "|---|---|---|",
    ]

    for methods, path, file_path in entries:
        lines.append(f"| `{methods}` | `{path}` | `{file_path}` |")

    return "\n".join(lines) + "\n"


def parse_module_arg(value: str) -> tuple[str, Path, Path]:
    try:
        name, src, out = value.split(":", maxsplit=2)
    except ValueError as exc:
        raise argparse.ArgumentTypeError(
            "Expected format: ModuleName:source_dir:output_file"
        ) from exc

    return name, Path(src), Path(out)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument(
        "--module",
        action="append",
        required=True,
        type=parse_module_arg,
        help="ModuleName:source_dir:output_file",
    )
    args = parser.parse_args()

    for module_name, source_root, output_file in args.module:
        entries = extract_entries(source_root)
        output_file.parent.mkdir(parents=True, exist_ok=True)
        output_file.write_text(build_markdown(module_name, source_root, entries), encoding="utf-8")
        print(f"{module_name}: {len(entries)} routes -> {output_file}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
