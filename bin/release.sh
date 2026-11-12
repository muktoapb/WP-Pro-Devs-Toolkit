#!/usr/bin/env bash
#
# Release helper for WP Pro Devs Toolkit.
#
# Bumps the version in every place it lives, rebuilds the admin assets,
# commits, tags, and pushes. The pushed tag is what triggers the self-hosted
# auto-update on every site running the plugin.
#
# Usage:
#   bin/release.sh X.Y.Z        e.g. bin/release.sh 1.1.0
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

VERSION="${1:-}"
if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
	echo "Usage: bin/release.sh X.Y.Z   (semantic version, e.g. 1.1.0)" >&2
	exit 1
fi
TAG="v$VERSION"

BRANCH="$(git rev-parse --abbrev-ref HEAD)"
if [[ "$BRANCH" != "main" ]]; then
	echo "Refusing to release from branch '$BRANCH' (expected 'main')." >&2
	exit 1
fi

if git rev-parse "$TAG" >/dev/null 2>&1; then
	echo "Tag $TAG already exists. Choose a new version." >&2
	exit 1
fi

echo "==> Bumping version to $VERSION"
perl -pi -e "s/^(\s*\*\s*Version:\s*).*/\${1}$VERSION/"                               wp-pro-devs-toolkit.php
perl -pi -e "s/(define\(\s*'WPPDT_VERSION',\s*')[^']*('\s*\))/\${1}$VERSION\${2}/"    wp-pro-devs-toolkit.php
perl -pi -e "s/^(Stable tag:\s*).*/\${1}$VERSION/"                                    readme.txt

echo "==> Building admin assets"
if [[ -f package.json ]]; then
	npm run build
fi

echo "==> Committing and tagging"
git add -A
git commit -m "Release $TAG"
git tag -a "$TAG" -m "Release $TAG"

echo "==> Pushing to origin"
git push origin main
git push origin "$TAG"

echo ""
echo "==> Released $TAG."
echo "    Sites auto-update within ~12h (wp-cron), or instantly via 'Check for updates'."
