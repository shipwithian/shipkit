#!/usr/bin/env bash
#
# Publish packages/shipkit-core to its read-only repository by subtree split, so Packagist can
# serve it as shipwithian/shipkit-core.
#
# Usage: .github/scripts/split-core.sh [version]
#
# Pushes the split of HEAD to the repository's main branch, and with a version (such as 1.0.0) also
# tags that commit v1.0.0. SHIPKIT_CORE_SPLIT_TOKEN must be able to push to the repository; the
# checkout must have full history (fetch-depth: 0).

set -euo pipefail

repository="${SHIPKIT_CORE_REPOSITORY:-shipwithian/shipkit-core}"
version="${1:-}"

if [ -z "${SHIPKIT_CORE_SPLIT_TOKEN:-}" ]; then
    echo "SHIPKIT_CORE_SPLIT_TOKEN is not set: add a token that can push to $repository as a repository secret." >&2
    exit 1
fi

if [ -n "$version" ] && ! [[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "The version must look like 1.0.0, got: $version" >&2
    exit 1
fi

echo "::add-mask::$SHIPKIT_CORE_SPLIT_TOKEN"
authorization="AUTHORIZATION: basic $(printf 'x-access-token:%s' "$SHIPKIT_CORE_SPLIT_TOKEN" | base64 | tr -d '\n')"
echo "::add-mask::$authorization"

remote="https://github.com/$repository.git"
split="$(git subtree split --prefix=packages/shipkit-core HEAD)"
echo "packages/shipkit-core at $(git rev-parse --short HEAD) splits to $split"

git -c "http.extraheader=$authorization" push "$remote" "$split:refs/heads/main"

if [ -n "$version" ]; then
    git -c "http.extraheader=$authorization" push "$remote" "$split:refs/tags/v$version"
    echo "Tagged $repository v$version at $split"
fi
