#!/bin/sh
# Vérifie la syntaxe des EA MQL5 avec g++ (MetaEditor ne tourne pas dans le conteneur de Claude).
# Les numéros de ligne des erreurs sont ceux du fichier MQL5 + 1.
# Usage : sh tests/mqlcheck/check.sh assets/mt5/CalculateurXAU.mq5.txt
set -e
d=$(cd "$(dirname "$0")" && pwd)
t=$(mktemp -d)
python3 -I "$d/mql2cpp.py" "$1" "$t/ea.cpp"
cp "$d/mql5mock.h" "$t/"
g++ -std=c++17 -fsyntax-only -w -fmax-errors=50 "$t/ea.cpp" && echo "Syntaxe correcte : $1"
rm -rf "$t"
