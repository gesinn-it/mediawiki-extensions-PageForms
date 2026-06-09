**Procedure — release:do**

1.  Determine the new version number from commits since the last tag
    using SemVer rules:

    - Any breaking change (`!` or `BREAKING CHANGE`) → MAJOR

    - Any `feat` commit → MINOR

    - Only `fix`, `deps`, `refactor`, `docs` commits → PATCH

2.  Identify the version file for this project. Common locations:

    - `package.json` (Node.js)

    - `extension.json` / `composer.json` (MediaWiki extension)

    - `composer.json` (PHP library)

    - If unclear, ask the user before proceeding.

3.  Bump the version number in the version file.

4.  Update `CHANGELOG.md`:

    - Rename `[Unreleased]` to `[X.Y.Z] - YYYY-MM-DD` (today’s date, ISO
      8601).

    - Add a new empty `[Unreleased]` section at the top.

    - Update the compare links at the bottom:

          [Unreleased]: https://github.com/org/repo/compare/X.Y.Z...HEAD
          [X.Y.Z]: https://github.com/org/repo/compare/PREV...X.Y.Z

5.  Present the `[X.Y.Z]` changelog section as the release notes draft
    for user approval. Present inside a fenced markdown code block for
    easy review. Do not proceed until the user explicitly approves.

6.  After approval — commit all changes:

        prepare X.Y.Z [skip ci]

7.  Push the branch.

8.  Create and push the git tag:

    ``` console
    git tag X.Y.Z
    git push origin X.Y.Z
    ```

9.  Create the GitHub release using the approved changelog section as
    body:

    ``` console
    gh release create X.Y.Z --title "X.Y.Z" --notes "<approved changelog section>"
    ```
