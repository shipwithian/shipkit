#!/usr/bin/env bash
#
# Turn a checkout of main into a ShipKit release: a commit that requires the published
# shipwithian/shipkit-core instead of the copy in packages/, tagged v<version>.
#
# main keeps developing against the path repository. The release commit is not part of main's
# history: it exists only so the tag, which is what Packagist and `laravel new --using` install,
# carries a composer.json that works outside this repository.
#
# Usage: .github/scripts/prepare-release.sh <version> <directory>
#
# <directory> must be a clean git checkout of main; it is modified and committed in place, and
# nothing is pushed. SHIPKIT_RELEASE_SKIP_LOCK=1 leaves composer.lock alone, for tests that run
# without Packagist.

set -euo pipefail

version="${1:?Usage: prepare-release.sh <version> <directory>}"
directory="${2:?Usage: prepare-release.sh <version> <directory>}"

if ! [[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "The version must look like 1.0.0, got: $version" >&2
    exit 1
fi

constraint="^${version%.*}"

cd "$directory"

if [ -n "$(git status --porcelain)" ]; then
    echo "$directory has uncommitted changes; prepare a release from a clean checkout." >&2
    exit 1
fi

if git rev-parse --verify --quiet "refs/tags/v$version" >/dev/null; then
    echo "The tag v$version already exists." >&2
    exit 1
fi

echo "Requiring shipwithian/shipkit-core $constraint from Packagist instead of packages/shipkit-core"

jq --arg constraint "$constraint" '
    .require["shipwithian/shipkit-core"] = $constraint
    | del(.repositories)
' composer.json >composer.json.tmp
mv composer.json.tmp composer.json

git rm -r -q packages/shipkit-core

# The checks that listed the package's folders for the development checkout.
sed -i.bak '/packages\/shipkit-core/d' phpstan.neon rector.php phpunit.xml
rm -f phpstan.neon.bak rector.php.bak phpunit.xml.bak

if grep -rn 'packages/shipkit-core' phpstan.neon rector.php phpunit.xml composer.json; then
    echo "A configuration file still names packages/shipkit-core." >&2
    exit 1
fi

if [ "${SHIPKIT_RELEASE_SKIP_LOCK:-}" != "1" ]; then
    composer update shipwithian/shipkit-core --no-scripts --no-interaction --no-progress
fi

git add --all
git -c user.name='ShipKit Release' -c user.email='release@shipkit.invalid' \
    commit --quiet -m "release: v$version"
git -c user.name='ShipKit Release' -c user.email='release@shipkit.invalid' \
    tag -a "v$version" -m "ShipKit v$version"

echo "Prepared v$version at $(git rev-parse --short HEAD); push the tag to publish it."
