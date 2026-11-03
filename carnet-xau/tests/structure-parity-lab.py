import json, sys
sys.path.insert(0, '/home/user/research-lab')
import numpy as np, pandas as pd
from engine import objects as O
d = json.load(open(sys.argv[1])); now = int(sys.argv[2])
SEC = {'D1': 86400, 'H4': 14400, 'H1': 3600, 'M15': 900, 'M5': 300}
out = {}
for tf, sec in SEC.items():
    rows = [r for r in d['tf'][tf] if int(r[0]) + sec <= now]
    t = pd.to_datetime([int(r[0]) for r in rows], unit='s', utc=True)
    df = pd.DataFrame({'t': t, 't_close': t + pd.Timedelta(seconds=sec), 'open': [r[1] for r in rows], 'high': [r[2] for r in rows],
                       'low': [r[3] for r in rows], 'close': [r[4] for r in rows]})
    ts = (df['t'].dt.tz_convert('America/New_York') + pd.Timedelta(hours=7)).dt.tz_localize(None)
    df['time_server'] = ts; df['server_day'] = ts.dt.normalize()
    piv = O.pivots(df, 2); eq = O.equal_levels(df, piv); disp = O.displacement(df); st = O.structure(df, piv)
    f = O.fvg(df); ob = O.order_blocks(df, st, disp)
    parts = [piv, eq, disp, st, f, ob]
    ses = []
    if sec <= 3600:
        ses = [O.asia_range(df), O.session_range(df, 'london'), O.day_levels(df), O.week_levels(df)]
        ses[1] = ses[1].assign(type=ses[1]['type'].str.replace('london', 'londres'))
        parts += ses
    lv_src = pd.concat([piv, eq] + ([ses[0], ses[2]] if ses else []), ignore_index=True)
    lv = O.levels_from(lv_src, types_high=('pivot_haut', 'egaux_hauts', 'jour_haut', 'asie_haut'), types_low=('pivot_bas', 'egaux_bas', 'jour_bas', 'asie_bas'))
    parts.append(O.sweeps(df, lv))
    ev = []
    for p in parts:
        for r in p.itertuples():
            ev.append([r.type, int(r.direction), int(pd.Timestamp(r.t_origin).timestamp()), int(pd.Timestamp(r.t_confirm).timestamp()), round(float(r.price), 2)])
    out[tf] = ev
print(json.dumps(out))
