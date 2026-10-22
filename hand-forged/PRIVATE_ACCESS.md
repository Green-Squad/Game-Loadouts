# Optional private access, independent of the PHP upgrade

Google Identity-Aware Proxy (IAP) can protect the existing Cloud Run demo before requests reach PHP, while preserving its original Laravel login/admin screens. Enable IAP directly on the service rather than only at a CDN, so the default run.app origin is also protected. No load balancer is proposed.

Use a service-scoped allowlist for the owner's two specified Google accounts. Keep exact personal emails and OAuth credentials outside this public repository. This project has no Google organization parent, so consumer Google accounts require the documented external OAuth setup. The existing public production Rust service must remain outside this policy.

Activation needs a separate reviewed infrastructure change: enable IAP, configure external OAuth, audit inherited access, grant the IAP service agent invocation on only the demo, and remove anonymous invocation. Persist that configuration in service.yaml and cloudbuild.yaml: the current release pipeline explicitly re-adds allUsers, which must not reopen a private demo on its next deployment. Its anonymous post-deploy catalog check also needs an authenticated replacement.

Verify signed-out and unlisted accounts cannot reach the custom domain, default URLs or tagged revisions, then test both approved users. Restricting access does not patch the PHP runtime. This document applies no access changes, credentials, paid features or deployment.

Official setup: https://docs.cloud.google.com/run/docs/securing/identity-aware-proxy-cloud-run
Pricing: https://cloud.google.com/iap/pricing
