> Estate-wide invariants: [`../../../shadow-agent-markdown/protocols/GLOBAL-RULES.md`](../../../shadow-agent-markdown/protocols/GLOBAL-RULES.md). Git lanes: [`../../../shadow-agent-markdown/protocols/EMDASH.md`](../../../shadow-agent-markdown/protocols/EMDASH.md). Not restated here.

# Broadside — block theme (shared by both digests)

`cannabisdigest.net` and `marksmansdigest.com` run **identical theme code**; per-site identity = Customizer + site-repo `scripts/configure-theme.php`. **No hard-coded brand strings.**

**Incident (required reading):** [docs/INCIDENT-2026-07-13-vps-outage.md](docs/INCIDENT-2026-07-13-vps-outage.md) — shared VPS `93.95.229.147`.

## Hard rules

1. **Never `do_blocks()` / `the_content` inside a render callback** — read raw `post_content`. CI/deploy gate enforces.
2. **Render locally before deploy:** `./scripts/local-wp.sh up` → `local-smoke.sh` → `local-assert.php`.
3. **Deploy only via** `./scripts/deploy.sh <domain>` (five gates — see incident doc). Never hand rsync.

## Dev commands

```bash
./scripts/local-wp.sh up
./scripts/local-smoke.sh
php scripts/local-assert.php
./scripts/deploy.sh cannabisdigest.net    # then marksmansdigest.com after verify
```

## Architecture notes

Block theme: `theme.json`, `templates/`, `inc/customizer.php` (all per-site settings), `shadow_digest_*` / `SHADOW_DIGEST_*` prefixes, namespace `shadow-digest/`. Bump `SHADOW_DIGEST_VERSION` with `style.css`/`readme.txt`. Use `shadow_digest_avatar()` not `get_avatar()` on firewalled hosts. Block renames need `migrate-slug.php`; slug renames need `migrate-modkeys.php`.

**WP.org:** `./scripts/package.sh` rules — no `accessibility-ready` tag; Theme URI only when live on directory.
