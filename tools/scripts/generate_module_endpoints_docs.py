#!/usr/bin/env python3
"""Generate endpoint inventory markdown from Symfony Route attributes.

Also expands REST trait actions used by controllers (e.g. Actions\Admin\FindAction)
so docs include inherited CRUD endpoints, not only routes declared directly in controller files.
"""

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
IMPORT_USE = re.compile(r"^use\s+([^;]+);$", re.M)
CLASS_TRAIT_USE = re.compile(r"\n\s*use\s+([A-Za-z0-9_\\]+)\s*;", re.M)


def _parse_route_block(block: str) -> tuple[str, str]:
    path_match = PATH_NAMED.search(block) or PATH_POSITIONAL.search(block)
    path = path_match.group(1) if path_match else "(class-prefix)"

    methods_match = METHODS.search(block)
    methods = "-"
    if methods_match:
        parsed_methods = METHOD_TOKEN.findall(methods_match.group(1))
        methods = "|".join(parsed_methods) if parsed_methods else "-"

    return methods, path


def _join_paths(prefix: str, suffix: str) -> str:
    if suffix == "":
        return prefix
    if prefix.endswith("/") and suffix.startswith("/"):
        return prefix[:-1] + suffix
    if not prefix.endswith("/") and not suffix.startswith("/"):
        return prefix + "/" + suffix
    return prefix + suffix


def _trait_file_from_fqcn(fqcn: str) -> Path | None:
    if not fqcn.startswith("App\\General\\Transport\\Rest\\Traits\\"):
        return None

    relative = fqcn.removeprefix("App\\General\\Transport\\Rest\\Traits\\").replace("\\", "/")
    file_path = Path("src/General/Transport/Rest/Traits") / f"{relative}.php"

    return file_path if file_path.exists() else None


def _expand_trait_routes(controller_file: Path, content: str, entries: set[tuple[str, str, str]]) -> None:
    # Class-level route path used as prefix for inherited trait endpoints.
    class_prefix = None
    for block in ROUTE_BLOCK.findall(content):
        methods, path = _parse_route_block(block)
        if methods == "-" and path.startswith("/"):
            class_prefix = path
            break

    if class_prefix is None:
        return

    import_map: dict[str, str] = {}
    for raw in IMPORT_USE.findall(content):
        raw = raw.strip()
        alias = raw.split("\\")[-1]
        import_map[alias] = raw

    actions_root = import_map.get("Actions")
    if actions_root is None:
        return

    for trait_ref in CLASS_TRAIT_USE.findall(content):
        if not trait_ref.startswith("Actions\\"):
            continue

        trait_suffix = trait_ref.removeprefix('Actions\\')
        fqcn = actions_root + '\\' + trait_suffix
        trait_file = _trait_file_from_fqcn(fqcn)
        if trait_file is None:
            continue

        trait_content = trait_file.read_text(encoding="utf-8")
        for block in ROUTE_BLOCK.findall(trait_content):
            methods, suffix = _parse_route_block(block)
            full_path = _join_paths(class_prefix, suffix if suffix != "(class-prefix)" else "")
            entries.add((methods, full_path, f"{controller_file.as_posix()} (via {trait_file.as_posix()})"))


def extract_entries(root: Path) -> list[tuple[str, str, str]]:
    entries: set[tuple[str, str, str]] = set()

    for file_path in sorted(root.rglob("*.php")):
        content = file_path.read_text(encoding="utf-8")

        # Direct routes declared in the controller file.
        for block in ROUTE_BLOCK.findall(content):
            methods, path = _parse_route_block(block)
            entries.add((methods, path, file_path.as_posix()))

        # Inherited REST trait routes used by the controller class.
        _expand_trait_routes(file_path, content, entries)

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
