---
name: ci-ensure-mediawiki
description: >
  Ensure a fully working, idempotent GitHub Actions CI workflow for a MediaWiki extension, backed by docker-compose-ci (DCI). Use when CI is missing, broken, or needs to be brought up to the current standard — sets up the DCI submodule, Makefile, composer/npm scripts, phpunit.xml.dist, and ci.yml from scratch or repairs an existing setup. Idempotent: safe to run on repos already configured. Also checks for repo-specific deviations (custom extensions, Phan, etc.) and guides through Codecov activation.
---

Load the following reference files before starting work:

- `references/01-mediawiki-ci-ensure-mediawiki.md`
