<!-- THIS FILE IS AUTO-GENERATED. Edit .github/copilot-instructions-source.adoc instead. -->

# Test Workflow

Before making any code changes to fix a bug or implement a feature:

1.  Check whether an existing test already covers the described
    behavior.

2.  If not, write or adapt a test that reproduces the issue — it must
    fail first.

3.  Only after a failing test exists, make the code changes.

4.  Re-run the test to confirm it passes (green).

**Test-first approach**

All tests run inside a containerized MediaWiki environment managed via
[docker-compose-ci](https://github.com/gesinn-it-pub/docker-compose-ci)
(the `build/` submodule). Never run tests directly against a local PHP
or Node.js installation.

Always run `make install` before executing tests to ensure that the
latest file changes are copied into the container. Changes to source or
test files on the host are **not** automatically reflected in a running
container.

``` console
make install
```

**PHPUnit tests**

Run all PHPUnit tests:

``` console
make install composer-phpunit
```

Run a single test class or method (filtered):

``` console
make install composer-phpunit COMPOSER_PARAMS="-- --filter YourTestName"
```

Run a specific test suite:

``` console
make install composer-phpunit COMPOSER_PARAMS="-- --testsuite your-suite-name"
```

For interactive use, bash into the running container:

``` console
make bash
> composer phpunit -- --filter YourTestName
```

**Node QUnit tests**

Run all JavaScript tests:

``` console
make install npm-test
```

There is no direct `make` target for filtering individual tests. Bash
into the running container to run a specific test file or test case:

``` console
make bash
> npm run node-qunit -- tests/node-qunit/yourtest.test.js
```

Filter by test description:

``` console
make bash
> npx qunit --require ./tests/node-qunit/setup.js 'tests/node-qunit/**/*.test.js' --filter "your test description"
```

**Pre-commit validation gate**

Before every commit, run the full CI suite to confirm nothing is broken:

``` console
make ci
```

**Coverage analysis**

Coverage reports require PHP 7.4 / MW 1.35, because PHPUnit 8.5 does not
support code coverage on PHP 8. Always pass the matching versions
explicitly.

Run the full coverage suite (generates HTML + Clover XML under
`build/coverage/php/`):

``` console
make install composer-test-coverage MW_VERSION=1.35 PHP_VERSION=7.4 SMW_VERSION=4.2.0 DT_VERSION=3.1 PS_VERSION=0.6.1
```

The coverage output is mounted from the container to the host and is
available at `build/coverage/php/` after the run:

- `build/coverage/php/index.html` — browsable HTML report

- `build/coverage/php/coverage.xml` — Clover XML (used by Codecov)

To analyse coverage for a **specific file**, bash into the running
container after the `make install` step and run PHPUnit directly with
`--coverage-text` and a `--filter` matching the test class name:

``` console
make install MW_VERSION=1.35 PHP_VERSION=7.4 SMW_VERSION=4.2.0 DT_VERSION=3.1 PS_VERSION=0.6.1
make bash
> XDEBUG_MODE=coverage composer phpunit -- --coverage-text --filter PFAutocompleteAPI
```

<div class="note">

`--filter` matches against test **class and method names**, not source
file names. To cover `PF_AutocompleteAPI.php` filter for the test class
`PFAutocompleteAPI`.

</div>

To extract exact covered/missed line numbers from the generated Clover
XML on the host:

``` console
python3 - <<'EOF'
import xml.etree.ElementTree as ET
tree = ET.parse('build/coverage/php/coverage.xml')
covered, missed = [], []
for f in tree.getroot().iter('file'):
    if 'PF_AutocompleteAPI' in f.get('name', ''):
        for line in f.iter('line'):
            (covered if int(line.get('count', 0)) > 0 else missed).append(int(line.get('num')))
print(f'Covered ({len(covered)}): {covered}')
print(f'Missed  ({len(missed)}):  {missed}')
EOF
```

# Conventional Commits

Commit messages follow the [Conventional Commits
specification](https://www.conventionalcommits.org/).

Commit format:

`type(scope): short description`

The scope is optional and should describe the affected subsystem,
module, or dependency when useful.

Examples:

- feat(api): add autocomplete endpoint

- fix(parser): handle empty token lists

- docs(readme): explain input architecture

- refactor(parser): simplify token parsing

- deps(smw): bump from 5.1.0 to 5.2.0

- ci(github): update workflow configuration

- test(api): add autocomplete tests

Recommended commit types:

- `feat` — new functionality

- `fix` — bug fixes

- `deps` — dependency updates

- `docs` — documentation changes

- `refactor` — internal code changes without behavioral change

- `test` — tests added or updated

- `ci` — changes to continuous integration configuration

- `chore` — repository maintenance tasks without impact on runtime
  behavior

Dependency updates:

- Use the `deps` type for dependency upgrades

- The scope should identify the dependency being updated

- Include the version change when applicable

Example:

- deps(smw): bump from 5.1.0 to 5.2.0

Guidelines:

- Use the imperative mood (e.g. "add feature", not "added feature")

- Keep the subject line concise

- Use the commit body to explain **why**, not only **what**

- Scopes should be short, lowercase identifiers (e.g. `api`, `parser`,
  `smw`, `mediawiki`, `docker`)

- Use `chore` only for repository maintenance tasks that do not affect
  runtime behavior, dependencies, CI configuration, or tests

# Versioning and Releases

This project follows [Semantic Versioning](https://semver.org/).

Version numbers follow the format:

`MAJOR.MINOR.PATCH`

Version increment rules:

- MAJOR — incompatible or breaking changes

- MINOR — backwards-compatible feature additions

- PATCH — backwards-compatible bug fixes

Breaking changes include (but are not limited to):

- incompatible API changes

- removal or renaming of public interfaces

- behavior changes that may break existing integrations

- increased minimum runtime or dependency requirements

- incompatible configuration or data format changes

- dependency upgrades that introduce breaking changes for users

Breaking changes must always increment the MAJOR version.
