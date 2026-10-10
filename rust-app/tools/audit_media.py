#!/usr/bin/env python3
"""Audit DB-provided image paths exported by a read-only query, never assume Git is complete."""
import argparse,pathlib,urllib.parse
p=argparse.ArgumentParser();p.add_argument('paths',help='one DB image/thumbnail/header path per line');p.add_argument('--assets',default='public');a=p.parse_args()
missing=[];external=[]
for line in pathlib.Path(a.paths).read_text().splitlines():
 if not line:continue
 if urllib.parse.urlsplit(line).scheme:external.append(line);continue
 # Paths in the DB are literal filesystem names (including PHP-uploaded '+' names).
 if not (pathlib.Path(a.assets)/line.lstrip('/')).is_file():missing.append(line)
print(f'{len(missing)} missing local media paths; {len(external)} external media paths')
for path in sorted(set(missing)):print(path)
raise SystemExit(bool(missing))
