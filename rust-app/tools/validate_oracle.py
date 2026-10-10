#!/usr/bin/env python3
"""Compare PHP query oracle with Rust fixture HTML (BeautifulSoup required)."""
import argparse,json,urllib.request
from bs4 import BeautifulSoup
p=argparse.ArgumentParser();p.add_argument('oracle');p.add_argument('--base',default='http://127.0.0.1:8080');a=p.parse_args()
if not a.base.startswith(('http://127.0.0.1:','http://localhost:')):p.error('Fixture comparisons require a local service')
oracle=json.load(open(a.oracle))
def page(path):return BeautifulSoup(urllib.request.urlopen(a.base+path).read(),'html.parser')
home=page('/');actual={}
for col in home.select('.panel-group'):
 game=col.parent.select_one('a.loadoutSmall img')['alt'];actual[game]=[]
 for panel in col.select('.panel'):
  link=panel.select_one('.panel-collapse a')['href'];votes=int(panel.select_one('small').get_text().strip('() ').split()[0]);actual[game].append({'id':int(link.rsplit('/',1)[1]),'count':votes})
assert actual=={k:[{'id':int(l['id']),'count':int(l['count'])} for l in v] for k,v in oracle['popular'].items()},(actual,oracle['popular'])
weapon=page('/Call+of+Duty+Modern+Warfare/M4A1')
actual=[{'id':int(l['data-loadout-id']),'count':int(l['data-votes']),'attachments':[x.get_text(' ',strip=True) for x in l.select('.attachment')]} for l in weapon.select('.loadout')]
expected=[{'id':int(l['id']),'count':int(l['count']),'attachments':[x['name'] for x in l['attachments']]} for l in oracle['weapon']]
assert actual==expected,(actual,expected)
print('PASS: original PHP query rankings, vote counts and attachment relationships match Rust HTML')
