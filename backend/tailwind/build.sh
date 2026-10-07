#!/bin/sh
# Przebudowa assets/css/tailwind.css po dodaniu nowych klas Tailwinda w PHP/JS.
# Kompilator: ~/tools/tailwind/tailwindcss (v3.4.17 standalone, ta sama wersja co dawny skrypt).
cd "$(dirname "$0")" && "${TAILWIND_BIN:-$HOME/tools/tailwind/tailwindcss}" -c tailwind.config.js -i input.css -o ../../assets/css/tailwind.css --minify
