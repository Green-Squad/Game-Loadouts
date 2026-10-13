# Disposable database deployment

This branch preserves the original application controllers, models, admin definitions, routes, HTML, styling and assets. No banner, new screens, integration substitutes or framework modernization are added. Deployment configuration supplies an independent application secret and host-only demo session, disables real email delivery, and forces a localhost database connection. No production database or API credentials are bundled. External CAPTCHA, search, comments and weather API behavior remains the original implementation and requires its original integrations; these are not emulated.

The private MySQL 5.7.44 sidecar runs alongside the application in the same Cloud Run instance. All data is bundled in the images. Startup populates ephemeral database storage and checks the seed before opening HTTP. The database port is not public. Database edits and uploaded files disappear when the instance is replaced; closing a browser does not reset them. Visitors on the same instance share state.

Seed: 11 public games, 843 weapons, 5,855 attachments and 28,269 compatibility relationships, with synthetic users, loadouts and votes. Original bundled artwork is retained. Visitor: `demo@handforged.invalid` / `HandForgedDemo!`.

Administrator: `admin@handforged.invalid` / `HandForgedDemo!`.

Only the `hand-forged` branch deploys through `hand-forged/cloudbuild.yaml`, guarded to the fixed demo service. Production and Rust deployments remain separate. Cloud Build waits for successful validation of the exact commit. The service uses second-generation execution, request-based billing, minimum zero, maximum one, and database-before-web startup ordering, with 256 MiB per container and a bounded memory database volume. No managed database, persistent storage or dedicated load balancer is created.

`bash hand-forged/check-container.sh` verifies seeded public pages, original admin login, JSON or supported original forms, resource measurements, clean shutdown, and database/file reset on replacement. Original application limitations are retained. Tests run in isolated Linux containers on GitHub Actions. Registry storage, build minutes, startup/request compute and traffic may incur costs; scale-to-zero does not guarantee zero cost.

Game Loadouts demo runs in `us-east4`; its image packages use the existing `us-central1` registry in the same project. The original production and Rust services are unchanged.

After deployment, the workflow verifies the US service returns its populated catalog before deleting only the old `gameloadouts-hand-forged` service in `europe-west1`. This retirement is idempotent; production and Rust service names cannot be selected.
