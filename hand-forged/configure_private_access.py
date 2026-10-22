"""Keep only the Hand-Forged service behind IAP after every release.

Human IAP membership is managed separately; never store personal emails here.
"""
import json
import subprocess
import tempfile

PROJECT = 'game-loadouts'
REGION = 'us-east4'
SERVICE = 'gameloadouts-hand-forged'
IAP_AGENT = 'serviceAccount:service-438745124966@gcp-sa-iap.iam.gserviceaccount.com'
ANONYMOUS = {'allUsers', 'allAuthenticatedUsers'}


def private_policy(policy):
    bindings = []
    for binding in policy.get('bindings', []):
        binding = dict(binding)
        binding['members'] = sorted(set(binding.get('members', [])) - ANONYMOUS)
        if binding['members']:
            bindings.append(binding)
    invoker = next((b for b in bindings if b['role'] == 'roles/run.invoker'
                    and not b.get('condition')), None)
    if invoker is None:
        invoker = {'role': 'roles/run.invoker', 'members': []}
        bindings.append(invoker)
    invoker['members'] = sorted(set(invoker['members']) | {IAP_AGENT})
    return dict(policy, bindings=bindings)


def main():
    command = ['gcloud', 'run', 'services']
    flags = ['--project=' + PROJECT, '--region=' + REGION, '--format=json']
    service = json.loads(subprocess.check_output(command + ['describe', SERVICE] + flags))
    assert service['metadata']['name'] == SERVICE
    assert service['metadata']['annotations'].get('run.googleapis.com/iap-enabled') == 'true'
    assert service['metadata']['annotations'].get('run.googleapis.com/invoker-iam-disabled') != 'true'
    policy = json.loads(subprocess.check_output(command + ['get-iam-policy', SERVICE] + flags))
    updated = private_policy(policy)
    if updated != policy:
        with tempfile.NamedTemporaryFile(mode='w', suffix='.json') as stream:
            json.dump(updated, stream)
            stream.flush()
            subprocess.run(command + ['set-iam-policy', SERVICE, stream.name] + flags + ['--quiet'],
                           check=True, stdout=subprocess.DEVNULL)
    print('Hand-Forged only: anonymous IAM removed; IAP agent can invoke.')


if __name__ == '__main__':
    main()
