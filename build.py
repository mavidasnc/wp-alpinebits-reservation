#!/usr/bin/env python3
"""
Script di build per il plugin WP AlpineBits Reservation.

Uso: python build.py
Crea: wp-alpinebits-reservation-vX.Y.Z.zip nella cartella del plugin.

Processo:
1. git archive HEAD → archivio con tutti i file tracciati
2. Copia composer.lock (gitignored ma necessario per installazione riproducibile)
3. composer install --no-dev --optimize-autoloader
4. Crea ZIP finale con la struttura wp-alpinebits-reservation/

"""
import os
import re
import shutil
import subprocess
import sys
import tempfile
import zipfile

PLUGIN_DIR = os.path.dirname(os.path.abspath(__file__))
PLUGIN_SLUG = "wp-alpinebits-reservation"


def get_version() -> str:
    """Legge la versione dal file principale del plugin."""
    main_php = os.path.join(PLUGIN_DIR, f"{PLUGIN_SLUG}.php")
    with open(main_php, encoding="utf-8") as f:
        content = f.read()
    match = re.search(r"define\(\s*'WPAR_VERSION',\s*'([^']+)'", content)
    if not match:
        raise RuntimeError("Versione non trovata nel file principale del plugin.")
    return match.group(1)


def main() -> None:
    version = get_version()
    zip_name = f"{PLUGIN_SLUG}-v{version}.zip"
    zip_path = os.path.join(PLUGIN_DIR, zip_name)

    print(f"Build v{version} -> {zip_name}")

    # Directory temporanea di build
    build_root = tempfile.mkdtemp(prefix="wpar-build-")
    build_plugin = os.path.join(build_root, PLUGIN_SLUG)
    os.makedirs(build_plugin)

    try:
        # 1. Estrai tutti i file tracciati da git
        print("1. git archive...")
        archive_zip = os.path.join(build_root, "source.zip")
        subprocess.run(
            ["git", "archive", "--format=zip", f"--prefix={PLUGIN_SLUG}/", "HEAD", "-o", archive_zip],
            cwd=PLUGIN_DIR, check=True
        )
        with zipfile.ZipFile(archive_zip, "r") as zf:
            zf.extractall(build_root)
        os.unlink(archive_zip)

        # Verifica che il file principale sia presente
        main_php = os.path.join(build_plugin, f"{PLUGIN_SLUG}.php")
        if not os.path.isfile(main_php):
            raise RuntimeError(f"File principale mancante: {main_php}")
        print(f"   OK: {PLUGIN_SLUG}.php trovato")

        # 2. Copia composer.lock (gitignored ma necessario per build riproducibile)
        lock_src = os.path.join(PLUGIN_DIR, "composer.lock")
        if os.path.isfile(lock_src):
            shutil.copy2(lock_src, os.path.join(build_plugin, "composer.lock"))
            print("2. composer.lock copiato")

        # 3. composer install --no-dev
        print("3. composer install --no-dev...")
        composer_cmd = shutil.which("composer") or shutil.which("composer.bat") or "composer.bat"
        subprocess.run(
            [composer_cmd, "install", "--no-dev", "--optimize-autoloader", "--quiet"],
            cwd=build_plugin, check=True, shell=(os.name == "nt")
        )
        print("   Dipendenze installate")

        # 4. Rimuovi file non necessari in produzione
        for unwanted in ["phpcs.xml.dist", "build.py", ".gitignore", ".gitattributes"]:
            path = os.path.join(build_plugin, unwanted)
            if os.path.isfile(path):
                os.unlink(path)

        # 5. Crea ZIP finale
        print(f"4. Creazione {zip_name}...")
        if os.path.isfile(zip_path):
            os.unlink(zip_path)

        with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as zf:
            for dirpath, dirnames, filenames in os.walk(build_plugin):
                # Escludi __pycache__ e .git
                dirnames[:] = [d for d in dirnames if d not in ("__pycache__", ".git")]
                for filename in filenames:
                    abs_path = os.path.join(dirpath, filename)
                    rel_path = os.path.relpath(abs_path, build_root)
                    zf.write(abs_path, rel_path)

        size_kb = round(os.path.getsize(zip_path) / 1024)
        print(f"\nZIP creato: {zip_path} ({size_kb} KB)")

        # Mostra i file nella root del plugin dentro lo ZIP
        print(f"\nFile nella root ({PLUGIN_SLUG}/):")
        with zipfile.ZipFile(zip_path, "r") as zf:
            root_files = sorted(
                e for e in zf.namelist()
                if e.startswith(f"{PLUGIN_SLUG}/")
                and e.count("/") == 1
                and not e.endswith("/")
            )
            for f in root_files:
                print(f"  {f}")

    finally:
        shutil.rmtree(build_root, ignore_errors=True)


if __name__ == "__main__":
    main()
