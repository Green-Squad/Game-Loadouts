"""Retire only the old disposable demo after the US replacement is healthy."""
import json, subprocess, time, urllib.request, urllib.parse

PROJECT = 'game-loadouts'
SERVICE = 'gameloadouts-hand-forged'

def gcloud(*args):
    return subprocess.check_output(['gcloud', 'run', 'services', *args, '--project=' + PROJECT], text=True)

service = json.loads(gcloud('describe', SERVICE, '--region=us-east4', '--format=json'))
url = service['status']['url']
parsed = urllib.parse.urlsplit(url)
assert parsed.scheme == 'https' and parsed.hostname.endswith('.run.app')
assert service['metadata']['name'] == SERVICE
assert service['metadata']['labels']['cloud.googleapis.com/location'] == 'us-east4'
for attempt in range(12):
    try:
        with urllib.request.urlopen(url + '/', timeout=20) as response:
            home = response.read().decode()
        assert 'Game Loadouts' in home and 'Hand-Forged Edition' not in home
        with urllib.request.urlopen(url + '/search_weapons?query=', timeout=20) as response:
            catalog = json.load(response)
        assert len(catalog['suggestions']) == 843
        break
    except Exception:
        if attempt == 11:
            raise
        time.sleep(5)
old = json.loads(gcloud('list', '--region=europe-west1', '--filter=metadata.name=' + SERVICE, '--format=json'))
assert all(item['metadata']['name'] == SERVICE for item in old)
assert len(old) <= 1
if old:
    gcloud('delete', SERVICE, '--region=europe-west1', '--quiet')
    print('US demo is healthy; retired only its disposable Europe replacement.')
else:
    print('US demo is healthy; no old Europe demo remains.')
