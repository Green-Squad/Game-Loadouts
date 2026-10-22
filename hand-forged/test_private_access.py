"""Access-policy regressions using synthetic identities only."""
import copy
import unittest

from configure_private_access import ANONYMOUS, IAP_AGENT, private_policy


class PrivatePolicyTests(unittest.TestCase):
    def test_preserves_concurrency_and_existing_permissions(self):
        policy = {'version': 3, 'etag': 'synthetic-etag', 'bindings': [
            {'role': 'roles/run.invoker', 'members': [
                'allUsers', 'allAuthenticatedUsers', 'serviceAccount:existing@example.invalid']},
            {'role': 'roles/run.viewer', 'members': ['user:viewer@example.invalid'],
             'condition': {'title': 'preserve', 'expression': 'true'}},
        ]}
        original = copy.deepcopy(policy)
        updated = private_policy(policy)
        self.assertEqual(policy, original)
        self.assertEqual(updated['etag'], policy['etag'])
        self.assertEqual(updated['version'], policy['version'])
        self.assertEqual(updated['bindings'][1], policy['bindings'][1])
        self.assertEqual(set(updated['bindings'][0]['members']), {
            'serviceAccount:existing@example.invalid', IAP_AGENT})
        self.assertFalse(any(ANONYMOUS.intersection(b['members']) for b in updated['bindings']))
        self.assertEqual(private_policy(updated), updated)

    def test_does_not_make_a_conditional_grant_unconditional(self):
        binding = {'role': 'roles/run.invoker', 'members': ['user:limited@example.invalid'],
                   'condition': {'title': 'limited', 'expression': 'false'}}
        updated = private_policy({'bindings': [binding]})
        self.assertEqual(updated['bindings'][0], binding)
        self.assertEqual(updated['bindings'][1], {'role': 'roles/run.invoker', 'members': [IAP_AGENT]})

    def test_empty_policy_has_only_the_iap_agent(self):
        self.assertEqual(private_policy({})['bindings'], [
            {'role': 'roles/run.invoker', 'members': [IAP_AGENT]}])


if __name__ == '__main__':
    unittest.main()
