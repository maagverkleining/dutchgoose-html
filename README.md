# Dutch Goose live site

Source content is maintained in `site/`.

For compatibility with the current Plesk setup, the same static site is also
published at the repository root. This ensures deployments work whether the
document root points at the repo root or directly at `site/`.

When making content edits, update `site/` first and then sync those files to
the repository root before pushing.
