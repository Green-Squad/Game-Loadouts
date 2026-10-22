"""Check platform readiness and the sign-in gate without granting CI app access.

The exact image's application behavior is checked by isolated CI, and Cloud Run's
container startup probe checks HTTP /. A sign-in redirect alone is not app health.
"""
import json
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request

from configure_private_access import ANONYMOUS, PROJECT, REGION, SERVICE


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def main():
    flags = ['--project=' + PROJECT, '--region=' + REGION, '--format=json']
    command = ['gcloud', 'run', 'services']
    opener = urllib.request.build_opener(NoRedirect())
    for attempt in range(12):
        try:
            service = json.loads(subprocess.check_output(command + ['describe', SERVICE] + flags))
            assert service['metadata']['name'] == SERVICE
            assert service['metadata']['annotations'].get('run.googleapis.com/iap-enabled') == 'true'
            status = service['status']
            assert status['observedGeneration'] == service['metadata']['generation']
            assert any(c['type'] == 'Ready' and c['status'] == 'True' for c in status['conditions'])
            assert status['latestReadyRevisionName'] == status['latestCreatedRevisionName']
            policy = json.loads(subprocess.check_output(command + ['get-iam-policy', SERVICE] + flags))
            assert not any(ANONYMOUS.intersection(b['members']) for b in policy.get('bindings', []))
            urls = {status['url'], 'https://handforged.gameloadouts.com'}
            urls.update(t['url'] for t in status.get('traffic', []) if t.get('url'))
            for url in urls:
                parsed = urllib.parse.urlsplit(url)
                assert parsed.scheme == 'https'
                assert parsed.hostname == 'handforged.gameloadouts.com' or parsed.hostname.endswith('.run.app')
                try:
                    response = opener.open(url + '/', timeout=20)
                except urllib.error.HTTPError as error:
                    response = error
                assert response.code in (302, 303), 'Expected Google sign-in redirect'
                assert urllib.parse.urlsplit(response.headers.get('Location', '')).hostname == 'accounts.google.com'
                assert not response.headers.get('X-Powered-By'), 'Anonymous request reached PHP'
                response.close()
            print('Hand-Forged platform ready; all published endpoints require Google sign-in. '
                  'Application behavior checked in isolated CI and by the container startup probe.')
            return
        except (AssertionError, KeyError, OSError, ValueError):
            if attempt == 11:
                raise
            time.sleep(5)


if __name__ == '__main__':
    main()
