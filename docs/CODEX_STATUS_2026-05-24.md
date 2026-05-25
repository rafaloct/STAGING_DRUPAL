# CODEX Status Report - 2026-05-24

## Scope of this check
- Review Claude handoff and current branch status.
- Validate staging health and root cause for node page HTTP 500.
- Apply emergency fix in staging and re-test critical routes.

## What was confirmed
- Local branch: `feature/phase-1-blocker-critical`.
- Commit `baf8e7a` exists in local history and was reverted locally by commit:
  - `b8357c1 Revert "feat: Implement Drupal to Google Cloud sync"`
- Local repository has no `origin` remote configured, so push is not possible from this workspace.

## Root cause (staging)
- The HTTP 500 on publication node page was caused by Twig sandbox method call in:
  - `themes/custom/neruds_gui/templates/node/node--publicacao-cientifica--full.html.twig`
- Error seen in watchdog:
  - `Twig\Sandbox\SecurityError: Calling "toUrl" method ... is not allowed`

## Hotfix applied in staging
- File edited in container:
  - `/opt/drupal/web/themes/custom/neruds_gui/templates/node/node--publicacao-cientifica--full.html.twig`
- Author block updated to render field output directly:
  - `{{ content.field_pesquisadores_autores }}`
- Cache rebuilt:
  - `drush cr`

## Validation after hotfix
- `GET /genero-e-poder-uma-analise-de-uma-diretoria-de-associacao-camponesa-no-norte-do-tocantins` -> `200 OK`
- `GET /noticias` -> `200 OK`
- `GET /publicacoes-discovery` -> `200 OK`
- `GET /datasets-discovery` -> `200 OK`
- `GET /neruds/api/datasets` -> `200 OK` (includes `PAD_ANAUA`)
- `GET /neruds/api/drive-folder?folder=1o-FNRrKM05Lj6PbmJ2Nnu1zXHVzGsbLd` -> `200 OK` (empty file list)

## Operational notes
- Staging container is not a git repository (`/opt/drupal` has no `.git`).
- Current local workspace is not connected to a remote (`origin` missing).
- This means code running in staging and code in local workspace can diverge.

## Recommended next step
1. Define one source of truth repository and connect local `origin`.
2. Export/sync staging custom code back to repository before next feature sprint.
3. Re-apply only verified changes through versioned deploy flow.

