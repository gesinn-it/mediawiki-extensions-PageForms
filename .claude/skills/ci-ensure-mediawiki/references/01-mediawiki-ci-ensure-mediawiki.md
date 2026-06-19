**Procedure — ci:ensure · MediaWiki**

Ensures a fully working, idempotent GitHub Actions CI workflow for a
MediaWiki extension, backed by
[docker-compose-ci](https://github.com/gesinn-it-pub/docker-compose-ci)
(DCI).

Run this procedure in two phases:

1.  **Assess** — run all detection commands (Gather facts + Steps 1–8),
    collect findings, present a status summary to the user, and wait for
    explicit approval.

2.  **Apply** — only after the user confirms, make changes and commit.

Never start writing files or running mutating commands before the user
has approved the plan.

# Gather facts

Collect the values needed throughout the procedure:

``` bash
# Extension name (must match the directory name in extensions/)
cat extension.json | python3 -c "import json,sys; print(json.load(sys.stdin)['name'])"

# GitHub org/repo slug (e.g. gesinn-it-pub/mediawiki-extensions-Arrays)
git remote get-url origin | sed 's|.*github.com[:/]\(.*\)\.git|\1|'

# Default branch
git remote show origin | grep 'HEAD branch' | awk '{print $NF}'

# Existing MW versions in use (check extension.json)
cat extension.json | python3 -c "import json,sys; d=json.load(sys.stdin); print(d.get('requires',{}))"
```

You need:

- `EXTENSION` — e.g. `Arrays`

- `GITHUB_ORG_REPO` — e.g. `gesinn-it-pub/mediawiki-extensions-Arrays`

- `DEFAULT_BRANCH` — typically `master` or `main`

- Whether the extension uses JS tests (check for package.json /
  Gruntfile.js)

- Whether Phan is configured (check for .phan/ directory)

# Step 1: Add the DCI submodule

Check:

``` bash
test -d build && echo "exists" || echo "missing"
```

If missing:

``` bash
git submodule add https://github.com/gesinn-it-pub/docker-compose-ci.git build
git submodule update --init build
```

If it exists but may be outdated:

``` bash
git submodule update --init --remote build
git add build
```

Only stage `build` if the pointer actually changed.

# Step 2: Create or update Makefile

Check:

``` bash
test -f Makefile && echo "exists" || echo "missing"
```

The Makefile must set DCI variables and include `build/Makefile`. The
auto-init guard ensures the submodule is available even after a plain
`git clone` (without `--recursive`).

If missing, create `Makefile` with:

``` makefile
-include .env
export

ifeq (,$(wildcard ./build/))
    $(shell git submodule update --init --remote)
endif

EXTENSION      = <EXTENSION>

# docker images
MW_VERSION    ?= 1.43
PHP_VERSION   ?= 8.3
DB_TYPE       ?= mysql
DB_IMAGE      ?= "mysql:8"

# composer
COMPOSER_EXT  ?= true

# nodejs — set to true if the extension has JS tests
NODE_JS       ?= true

include build/Makefile
```

If Makefile exists, verify that:

- `EXTENSION` is set to the correct name

- `include build/Makefile` is present

- The auto-init guard (`ifeq (,$(wildcard ./build/))`) is present

# Step 3: Verify composer.json scripts

`make ci-coverage` calls `composer test-coverage` inside the container.
This script must exist and must produce a Clover XML file at
`coverage/php/coverage.xml` (relative to the extension directory inside
the container, which maps to `build/coverage/php/coverage.xml` on the
host).

Required scripts in `composer.json`:

``` json
"scripts": {
    "test": [
        "@lint",
        "@phpcs",
        "@phpunit"
    ],
    "test-coverage": [
        "@lint",
        "@phpcs",
        "@phpunit-coverage"
    ],
    "lint": [
        "parallel-lint . --exclude vendor --exclude node_modules",
        "minus-x check ."
    ],
    "phpcs": "phpcs -sp",
    "phpunit": "php -d memory_limit=512M ../../tests/phpunit/phpunit.php -c phpunit.xml.dist --testdox",
    "phpunit-coverage": "php -d memory_limit=512M ../../tests/phpunit/phpunit.php -c phpunit.xml.dist --testdox --coverage-text --coverage-html coverage/php --coverage-clover coverage/php/coverage.xml"
}
```

<div class="note">

The coverage output path `coverage/php/` is **relative to the extension
directory inside the container**. DCI’s `docker-compose-ci.yml` overlay
maps `./coverage` inside the container to `build/coverage/` on the host.
The GitHub Actions step therefore finds the file at
`build/coverage/php/coverage.xml`.

</div>

Check whether `test-coverage` and `phpunit-coverage` are present:

``` bash
cat composer.json | python3 -c "import json,sys; s=json.load(sys.stdin).get('scripts',{}); print('ok' if 'test-coverage' in s and 'phpunit-coverage' in s else 'MISSING')"
```

# Step 4: Verify package.json scripts (JS extensions)

Skip if the extension has no JS tests.

`make ci-coverage` also calls `npm run test-coverage`. Add it if
missing:

``` json
"scripts": {
    "test": "grunt test",
    "test-coverage": "grunt test"
}
```

For extensions without separate coverage tooling, `test-coverage` is
identical to `test`. Do not omit it — DCI calls it unconditionally when
`NODE_JS` is set.

# Step 5: Verify phpunit.xml.dist

Check that:

1.  Every `<testsuite>` entry points to a directory that actually
    exists. A reference to a missing directory causes PHPUnit to fail
    silently or with a confusing error.

    ``` bash
    python3 -c "
    import xml.etree.ElementTree as ET, os
    tree = ET.parse('phpunit.xml.dist')
    for d in tree.findall('.//directory'):
        path = d.text.strip()
        print('OK' if os.path.isdir(path) else 'MISSING: ' + path)
    "
    ```

    Remove any `<testsuite>` block whose `<directory>` does not exist.

2.  The `<coverage>` section excludes build artefact directories:

    ``` xml
    <coverage>
        <include>
            <directory suffix=".php">.</directory>
        </include>
        <exclude>
            <directory>vendor</directory>
            <directory>node_modules</directory>
            <directory>tests</directory>
            <directory>build</directory>
            <directory>docs</directory>
        </exclude>
    </coverage>
    ```

# Step 6: Exclude coverage output from ESLint / Gruntfile

When coverage is enabled, PHPUnit writes HTML files to
`build/coverage/`. Those files contain JS that ESLint would otherwise
lint and fail on.

Check `Gruntfile.js` for exclusions:

``` bash
grep -E "coverage" Gruntfile.js || echo "MISSING exclusions"
```

If missing, add !coverage/\*\* and !build/coverage/\*\* to the ESLint
`all` file glob. Example:

``` javascript
all: [
    '**/*.{js,json}',
    '!node_modules/**',
    '!vendor/**',
    '!coverage/**',
    '!build/coverage/**'
]
```

# Step 7: Create or update .github/workflows/ci.yml

Check:

``` bash
test -f .github/workflows/ci.yml && echo "exists" || echo "missing"
```

The canonical workflow for a MediaWiki extension with MySQL and
coverage:

``` yaml
name: CI

on:
  push:
    branches: [ <DEFAULT_BRANCH> ]
    paths-ignore:
      - '**/*.adoc'
  pull_request:
    branches: [ <DEFAULT_BRANCH> ]
    paths-ignore:
      - '**/*.adoc'
  workflow_dispatch:

jobs:
  test:
    runs-on: ubuntu-latest
    continue-on-error: ${{ matrix.experimental }}

    strategy:
      matrix:
        include:
          - mediawiki_version: '1.39'
            php_version: '8.1'
            database_type: mysql
            database_image: "mysql:8"
            coverage: false
            experimental: false
          - mediawiki_version: '1.43'
            php_version: '8.3'
            database_type: mysql
            database_image: "mysql:8"
            coverage: true
            experimental: false

    env:
      MW_VERSION: ${{ matrix.mediawiki_version }}
      PHP_VERSION: ${{ matrix.php_version }}
      DB_TYPE: ${{ matrix.database_type }}
      DB_IMAGE: ${{ matrix.database_image }}

    steps:
      - uses: actions/checkout@v4
        with:
          submodules: recursive

      - name: Run CI
        run: make ci
        if: matrix.coverage == false

      - name: Run CI with coverage
        run: mkdir -p build/coverage && make ci-coverage
        if: matrix.coverage == true

      - name: Upload coverage
        if: matrix.coverage == true
        uses: codecov/codecov-action@v7
        with:
          token: ${{ secrets.CODECOV_TOKEN }}
          slug: <GITHUB_ORG_REPO>
          files: build/coverage/php/coverage.xml
          disable_search: true
```

Key points when creating or reviewing this file:

- `runs-on: ubuntu-latest` — not `ubuntu-22.04` (stays evergreen)

- `submodules: recursive` in the checkout step — required for the
  `build/` submodule; no separate "Update submodules" step needed

- `mkdir -p build/coverage` before `make ci-coverage` — DCI’s bind mount
  fails if the host directory does not exist

- `codecov/codecov-action@v7` — v4 is outdated and fails token
  validation

- `token: ${{ secrets.CODECOV_TOKEN }}` — the org-level secret is
  assumed to be set; do not omit even for public repos (required for
  protected branches)

- `slug: <GITHUB_ORG_REPO>` — must be set explicitly; without it Codecov
  cannot identify the repository when the action runs from a fork or
  after a rename

- `disable_search: true` — prevents the action from auto-discovering and
  uploading unrelated XML files (e.g. from node_modules or vendor)

- Do **not** set `NODE_JS` in the workflow env; it is controlled in the
  Makefile and activated by DCI when needed

# Step 8: Detect and apply repo-specific configuration

Run all detection commands first, then apply what the signals show. Only
ask the user when a signal is ambiguous or a version is unspecified.

## 8a: Detect dependency extensions

``` bash
# Check extension.json for declared dependencies
cat extension.json | python3 -c "
import json, sys
d = json.load(sys.stdin)
reqs = d.get('requires', {}).get('extensions', {})
for ext, ver in reqs.items():
    print(ext, ver)
"

# Check whether a Makefile already pins any bundled DCI extension
grep -E "SMW_VERSION|DT_VERSION|SRF_VERSION|PF_VERSION|AL_VERSION|AR_VERSION|ECHO_VERSION|LINGO_VERSION|MAPS_VERSION|MM_VERSION|PS_VERSION|SCRIBUNTO_VERSION" Makefile 2>/dev/null || echo "none"

# Custom extensions not bundled in DCI
test -f extensions.local.json && cat extensions.local.json || echo "none"

# Custom LocalSettings.php additions
test -f __setup_extension__ && cat __setup_extension__ || echo "none"
```

DCI bundles these extensions — activate by setting the version variable:

| Extension             | Makefile variable   |
|-----------------------|---------------------|
| SemanticMediaWiki     | `SMW_VERSION`       |
| DisplayTitle          | `DT_VERSION`        |
| SemanticResultFormats | `SRF_VERSION`       |
| PageForms             | `PF_VERSION`        |
| AdminLinks            | `AL_VERSION`        |
| ApprovedRevs          | `AR_VERSION`        |
| Echo                  | `ECHO_VERSION`      |
| Lingo                 | `LINGO_VERSION`     |
| Maps                  | `MAPS_VERSION`      |
| Mermaid               | `MM_VERSION`        |
| PageSchemas           | `PS_VERSION`        |
| Scribunto             | `SCRIBUNTO_VERSION` |

For each required extension:

1.  If it is in the DCI bundle table, add `VAR_VERSION ?= X.Y.Z` to the
    Makefile and the same variable to the `env:` block in `ci.yml`.

2.  If a version is already pinned in the Makefile, use that value.

3.  If the version is unknown and cannot be read from `composer.json` or
    `extension.json`, ask the user which version to pin before
    continuing.

4.  If the extension is not in the DCI bundle, add it to
    `extensions.local.json` (see DCI README for the format).

## 8b: Detect private Composer packages

``` bash
cat composer.json | python3 -c "
import json, sys
d = json.load(sys.stdin)
repos = d.get('repositories', [])
for r in repos:
    if r.get('type') == 'vcs' or 'github.com' in str(r.get('url','')):
        print(r)
" 2>/dev/null || echo "none"
```

If private VCS repositories are declared, add `COMPOSER_AUTH` to the
workflow so Composer can authenticate:

``` yaml
      - name: Run CI
        run: make ci
        env:
          COMPOSER_AUTH: ${{ secrets.COMPOSER_AUTH }}
```

Apply to both the plain `Run CI` and `Run CI with coverage` steps. The
secret is expected to be set at organisation level as a JSON string in
the format Composer expects
(`{"github-oauth": {"github.com": "TOKEN"}}`).

## 8c: Detect extra PHP extensions or OS packages

``` bash
grep -E "PHP_EXTENSIONS|OS_PACKAGES" Makefile 2>/dev/null || echo "none"
```

If set, carry them into the `env:` block of `ci.yml` as well:

``` yaml
    env:
      PHP_EXTENSIONS: "gd intl"
      OS_PACKAGES: "libgd-dev"
```

## 8d: Detect Phan

``` bash
test -d .phan && echo "configured" || echo "not configured"
```

If `.phan/` exists, add a dedicated Phan step on the coverage matrix
row, **after** the coverage step. Phan runs only on the coverage row to
avoid baseline mismatches across MW versions. Do **not** add `phan` to
the `composer test` script — it must be a separate step so failures are
visible independently.

``` yaml
      - name: Run Phan
        if: matrix.coverage == true
        run: make composer-phan
        env:
          MW_VERSION: ${{ matrix.mediawiki_version }}
          PHP_VERSION: ${{ matrix.php_version }}
```

Pass the same version variables used in the coverage step.

## 8e: Ask only when ambiguous

After running all detection commands, ask the user only if:

- A required bundled extension was detected but its version cannot be
  determined from the repository files.

- `extensions.local.json` is absent but `extension.json` lists
  extensions that are **not** in the DCI bundle — ask whether they are
  needed for tests.

Do not ask about extensions that are already pinned or clearly not
needed.

# Present findings and await approval

After completing all detection steps above, present a structured status
summary to the user:

- For each item: current state (OK / missing / needs update) and the
  planned action (create, update, skip).

- List any open questions (ambiguous extension versions, etc.).

- State the full list of files that will be created or modified.

Then **stop and wait** for the user to confirm before making any
changes. Do not proceed to Step 9 or beyond until the user explicitly
approves (e.g. "yes", "go ahead", "looks good").

If the user requests adjustments, revise the plan and present it again
before proceeding.

# Step 9: Activate Codecov

## 9a: Create codecov.yml

The coverage XML produced by PHPUnit inside the Docker container
contains absolute paths of the form
`/var/www/html/extensions/<EXTENSION>/…​`. Codecov cannot match these to
the repository’s source paths unless a path fix is declared.

Check:

``` bash
test -f codecov.yml && cat codecov.yml || echo "missing"
```

If missing (or if no `fixes:` entry is present), create `codecov.yml` in
the repository root:

``` yaml
fixes:
  - "/var/www/html/extensions/<EXTENSION>/::"
```

The trailing `::` strips the prefix and maps the remaining path to the
repository root. Replace `<EXTENSION>` with the exact extension
directory name (same value as `EXTENSION` in the Makefile).

Without this file, Codecov will accept the upload but show 0% coverage
or fail to annotate source lines — even when the XML file is valid and
the upload step reports success.

## 9b: Activate the repository on Codecov

1.  Open <https://app.codecov.io> and sign in with the GitHub
    organisation account.

2.  Find the repository in the list. If it is not listed, click **Add
    repository** and install the Codecov GitHub App for the
    organisation.

3.  Activate the repository by clicking the toggle next to its name.

4.  Verify that `CODECOV_TOKEN` is set as an organisation-level Actions
    secret (Settings → Secrets → Actions → Organisation secrets). If it
    is missing, generate a new token from the Codecov repository
    settings and add it.

5.  After the first successful CI run, check that the coverage report
    appears on the Codecov dashboard. If the upload succeeds but no
    report appears, confirm that the `slug` in `ci.yml` matches exactly
    the `org/repo` path shown in Codecov.

# Step 10: Validate locally

``` bash
# Submodule present and initialised
test -d build && git submodule status build

# Composer scripts present
composer validate --no-check-publish
cat composer.json | python3 -c "import json,sys; s=json.load(sys.stdin)['scripts']; [print(k) for k in ['test','test-coverage','phpunit','phpunit-coverage'] if k not in s and print('MISSING: '+k) is None]"

# PHPUnit test directories exist
python3 -c "
import xml.etree.ElementTree as ET, os
tree = ET.parse('phpunit.xml.dist')
for d in tree.findall('.//directory'):
    p = d.text.strip()
    print(('OK   ' if os.path.isdir(p) else 'MISS ') + p)
"

# Workflow file present
test -f .github/workflows/ci.yml && echo "ci.yml OK"
```

# Step 11: Commit

Stage only changed files — do not use `git add .`:

``` bash
git add .gitmodules build Makefile \
        composer.json package.json \
        phpunit.xml.dist Gruntfile.js \
        .github/workflows/ci.yml \
        codecov.yml
git commit -m "ci: ensure docker-compose-ci integration and GitHub Actions workflow"
```

Omit files that were not changed. If only a subset of files was touched,
commit with a more specific message (e.g.
`ci(codecov): add slug and disable auto-search`).
