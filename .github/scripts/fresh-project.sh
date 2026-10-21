#!/usr/bin/env bash
#
# Create a project from ShipKit's installer archive and prove it is fully enforced on day one:
# `composer ci:check` passes, and fails when ShipKit's testsuite is unregistered or a guarded
# model is swapped for an unguarded one.
#
# SHIPKIT_CORE_SOURCE says where the project gets shipkit-core:
#
#   checkout   (default) the packages/shipkit-core folder of this checkout, through a path repository.
#   split      the folder SHIPKIT_CORE_PATH, pretending to be SHIPKIT_CORE_VERSION. A release uses it
#              to prove the release commit against the split package before that is tagged.
#   packagist  the published package, from the release commit's own composer.json and lock file.
#
# Usage: .github/scripts/fresh-project.sh [project-directory]
# SHIPKIT_ARCHIVE_REF picks the commit to archive (default HEAD); `git stash create` archives
# uncommitted changes to tracked files. For a release, it is the release commit.

set -euo pipefail

checkout="$(git rev-parse --show-toplevel)"
core="$checkout/packages/shipkit-core"
project="${1:-$(mktemp -d)/fresh-project}"
ref="${SHIPKIT_ARCHIVE_REF:-HEAD}"
core_source="${SHIPKIT_CORE_SOURCE:-checkout}"

step() {
    printf '\n\033[1m==> %s\033[0m\n' "$1"
}

fail() {
    printf '\n\033[31mFAIL: %s\033[0m\n' "$1" >&2
    exit 1
}

# Run `composer ci:check` and require it to fail with the given message in its output.
expect_ci_check_failure() {
    local description="$1" message="$2" output

    if output="$(composer ci:check --no-interaction 2>&1)"; then
        printf '%s\n' "$output"
        fail "composer ci:check passed although $description."
    fi

    if ! grep -qF -- "$message" <<<"$output"; then
        printf '%s\n' "$output"
        fail "composer ci:check failed, but not with the expected message: $message"
    fi

    echo "composer ci:check failed as expected: $message"
}

case "$core_source" in
    checkout) ;;
    split)
        core="${SHIPKIT_CORE_PATH:?SHIPKIT_CORE_PATH must name the split package for SHIPKIT_CORE_SOURCE=split}"
        core_version="${SHIPKIT_CORE_VERSION:?SHIPKIT_CORE_VERSION must name the version for SHIPKIT_CORE_SOURCE=split}"
        ;;
    packagist) ;;
    *) fail "Unknown SHIPKIT_CORE_SOURCE '$core_source'; use checkout, split, or packagist." ;;
esac

step "Building the installer archive of $ref into $project"
if [ -e "$project" ] && [ -n "$(ls -A "$project")" ]; then
    fail "$project already exists and is not empty."
fi
mkdir -p "$project"
git -C "$checkout" archive --format=tar "$ref" | tar -x -C "$project"
cd "$project"

step "Creating the project the way composer create-project does"
php -r "file_exists('.env') || copy('.env.example', '.env');"

# The locked version, dist type, and dist URL of shipkit-core.
lockedCore() {
    jq -r '.packages[] | select(.name == "shipwithian/shipkit-core") | "\(.version) \(.dist.type) \(.dist.url)"' composer.lock
}

if [ "$core_source" = "packagist" ]; then
    # The archive is a release: its composer.json and lock file already name the published package.
    jq -e '.repositories == null' composer.json >/dev/null || fail "The archive still lists a repository for shipkit-core."
    [ ! -e packages ] || fail "The archive still contains packages/."
    composer install --no-interaction --no-progress

    installed="$(lockedCore)"
    case "$installed" in
        *" path "*) fail "shipkit-core is locked to a path repository: $installed" ;;
        "") fail "shipkit-core is not in composer.lock." ;;
    esac
else
    # Copy rather than symlink, as a Packagist install does: Pest cannot register arch() tests from a
    # file that resolves outside the project. A split package has no tag yet, so give it its version.
    jq --arg url "$core" --arg version "${core_version:-}" '
        .repositories = [{
            "name": "shipkit-core", "type": "path", "url": $url,
            "options": ({"symlink": false} + (if $version == "" then {} else {"versions": {"shipwithian/shipkit-core": $version}} end))
        }]
    ' composer.json >composer.json.tmp
    mv composer.json.tmp composer.json

    # The lock file records a copy that is not the one under test, so resolve shipkit-core again from it.
    composer update shipwithian/shipkit-core --no-interaction --no-progress

    installed="$(lockedCore)"
    case "$installed" in
        *" path $core") ;;
        *) fail "shipkit-core is installed from '$installed', not from $core." ;;
    esac
    diff -rq "$core" vendor/shipwithian/shipkit-core || fail "vendor/shipwithian/shipkit-core differs from $core."
fi

composer run-script post-create-project-cmd --no-interaction
echo "shipkit-core is installed: $installed"

npm ci --no-audit --no-fund
npm run build

# The Laravel installer starts every project as a git repository, and ci:check diffs generated files against it.
git init --quiet
git add --all
git -c user.name='ShipKit CI' -c user.email='ci@shipkit.invalid' commit --quiet -m 'Set up the project'

step "composer ci:check passes in the new project"
composer ci:check --no-interaction

step "composer ci:check fails when ShipKit's testsuite is not registered"
cp phpunit.xml phpunit.xml.orig
perl -0pi -e 's#\s*<testsuite name="ShipKit">.*?</testsuite>##s' phpunit.xml
if grep -qF 'vendor/shipwithian/shipkit-core/tests' phpunit.xml; then
    fail "Could not remove ShipKit's testsuite from phpunit.xml."
fi
expect_ci_check_failure "ShipKit's testsuite is not registered" "Register ShipKit's checks in phpunit.xml"
mv phpunit.xml.orig phpunit.xml

step "composer ci:check fails when a mapped model does not use GuardsInvariants"
cp config/shipkit.php config/shipkit.php.orig
perl -pi -e "s#'user' => User::class#'user' => Illuminate\\\\Foundation\\\\Auth\\\\User::class#" config/shipkit.php
if ! grep -qF "'user' => Illuminate\\Foundation\\Auth\\User::class" config/shipkit.php; then
    fail "Could not map the user model to an unguarded class in config/shipkit.php."
fi
expect_ci_check_failure "a mapped model is unguarded" "does not use GuardsInvariants"
mv config/shipkit.php.orig config/shipkit.php

step "The new project is fully enforced"
