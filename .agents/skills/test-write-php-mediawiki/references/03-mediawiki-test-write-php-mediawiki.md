**Procedure — test:write · MediaWiki**

Before making any code changes to fix a bug or implement a feature:

1.  Check whether an existing test already covers the described
    behavior.

2.  If not, write or adapt a test that reproduces the issue — it must
    fail first.

3.  Only after a failing test exists, make the code changes.

4.  Re-run the test to confirm it passes (green).

**MediaWiki test base classes**

Use the appropriate base class:

- `MediaWikiUnitTestCase` — pure unit tests (no database, no service
  container); fastest

- `MediaWikiIntegrationTestCase` — integration tests that need the
  service container or database

- `MediaWikiLangTestCase` — when language handling is under test

Do **not** extend `MediaWikiIntegrationTestCase` by default — use
`MediaWikiUnitTestCase` unless integration with MW services is required.

**Test fixtures**

- Use `setUp()` and `tearDown()` for test-scoped fixtures

- For database fixtures, use `addDBDataOnce()` (run once per class) or
  `addDBData()` (run per test)

- Use `getMockBuilder()` / `createMock()` for dependencies; prefer
  constructor injection so mocks can be passed in

**Running tests**

See the **Execution — Install Dependencies · MediaWiki** and **Execution
— Run Tests (PHPUnit) · MediaWiki** reference files loaded by this
skill.
