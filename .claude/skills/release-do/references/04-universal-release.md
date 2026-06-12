**Procedure — release:do**

1.  Confirm the target branch — the tag must be created from the correct
    branch:

    - Run `git branch --show-current`.

    - For a release on `main` (normal or MAJOR): stay on `main`.

    - For a backport release on an older major (e.g. `2.4.1` while `3.x`
      is on `main`): check out the maintenance branch first
      (`git checkout 2.x`). All remaining steps — including the tag and
      GitHub release — execute on that branch.

2.  Determine the new version number from commits since the last tag
    using SemVer rules:

    - Any breaking change (`!` or `BREAKING CHANGE`) → MAJOR

    - Any `feat` commit → MINOR

    - Only `fix`, `deps`, `refactor`, `docs` commits → PATCH

3.  If this is a **MAJOR** bump (e.g. `2.x → 3.0.0`): create a
    maintenance branch for the outgoing major **before** making any
    other changes:

    ``` console
    git checkout -b N.x          # e.g. git checkout -b 2.x  (snapshot of current main)
    git push origin N.x
    git checkout main            # tag 3.0.0 will be set from main
    ```

4.  Identify the version file for this project. Common locations:

    - `package.json` (Node.js)

    - `extension.json` / `composer.json` (MediaWiki extension)

    - `composer.json` (PHP library)

    - If unclear, ask the user before proceeding.

5.  Bump the version number in the version file.

6.  Update `CHANGELOG.md`:

    - Rename `[Unreleased]` to `[X.Y.Z] - YYYY-MM-DD` (today’s date, ISO
      8601).

    - Add a new empty `[Unreleased]` section at the top.

    - If this is a MAJOR release: rotate the previous major’s entries
      into `CHANGELOG-PREV.x.md` and add an "Older releases" link at the
      bottom of `CHANGELOG.md` (see Changelog Convention).

    - Update the compare links at the bottom:

          [Unreleased]: https://github.com/org/repo/compare/X.Y.Z...HEAD
          [X.Y.Z]: https://github.com/org/repo/compare/PREV...X.Y.Z

7.  Draft the release notes:

    - Write a short introductory sentence summarising the release theme
      (optional but recommended for notable releases).

    - Ensure each entry has a commit hash link; add an issue/PR link
      where applicable.

    - Present the full `[X.Y.Z]` changelog section inside a fenced
      markdown code block for easy review.

    - Do not proceed until the user explicitly approves.

8.  After approval — commit all changes:

        prepare X.Y.Z [skip ci]

9.  Push the branch.

10. Create and push the git tag:

    ``` console
    git tag X.Y.Z
    git push origin X.Y.Z
    ```

11. Create the GitHub release using the approved changelog section as
    body:

    ``` console
    gh release create X.Y.Z --title "X.Y.Z" --notes "<approved changelog section>"
    ```
