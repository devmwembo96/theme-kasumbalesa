#!/bin/bash
# Parité des structures : détecteurs du carnet (PHP) contre ceux du labo (Python, testés contre le regard
# vers l'avenir), sur les mêmes bougies. Usage : tests/structure-parity.sh candles.json [instant_unix]
set -e
D="$(cd "$(dirname "$0")" && pwd)"
J="$1"
NOW="${2:-$(date +%s)}"
php "$D/structure-parity-php.php" "$J" "$NOW" > /tmp/cx_php.json
python3 "$D/structure-parity-lab.py" "$J" "$NOW" > /tmp/cx_py.json
python3 - /tmp/cx_php.json /tmp/cx_py.json <<'PY'
import json, sys
a, b = (json.load(open(p)) for p in sys.argv[1:3])
bad = 0
# NAS100 : les fenêtres de séance (nuit, RTH, IB) et donc les sweeps diffèrent du labo (Asie, Londres) ;
# la parité porte sur les détecteurs eux-mêmes et sur les niveaux du jour et de la semaine.
skip = lambda x: x[0].split('_')[0] in ('asie', 'londres', 'nuit', 'rth', 'ib') or x[0] in ('sweep', 'cassure')
for tf in a:
    k = lambda x: tuple(x[:4])
    da = {k(x): x[4] for x in a[tf] if not skip(x)}
    db = {k(x): x[4] for x in b[tf] if not skip(x)}
    e = len(set(da) ^ set(db)) + sum(abs(da[x] - db[x]) > 0.011 for x in set(da) & set(db))
    bad += e
    print(tf, len(da), "objets,", e, "écarts")
sys.exit(1 if bad else 0)
PY
