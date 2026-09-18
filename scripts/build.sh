#!/usr/bin/env bash
#
# Build the distributable plugin into build/psst, and zip it.
#
# The shape Linchpin plugins use (mantle, discovery, block-alchemy): an
# allow-list of what ships, `.distignore` as the second layer pruning strays
# from inside the copied directories, a set of assertions guarding every
# runtime path that would otherwise fail silently, and one command that the
# release workflow, the publish step and a developer all run.

set -euo pipefail

SLUG="psst"
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_DIR="${REPO_ROOT}/build"
OUTPUT_DIR="${BUILD_DIR}/${SLUG}"
ZIP_PATH="${BUILD_DIR}/${SLUG}.zip"

cd "${REPO_ROOT}"

echo "Building ${SLUG}..."

# Only the artifact is removed, never the whole of build/: wp-scripts writes
# the admin app there too, and build/ is gitignored.
rm -rf "${OUTPUT_DIR}" "${ZIP_PATH}"
mkdir -p "${OUTPUT_DIR}"

for tool in npm composer zip rsync php; do
	if ! command -v "${tool}" > /dev/null 2>&1; then
		echo "::error::${tool} is not installed, so the distributable cannot be built."
		exit 1
	fi
done

if [ ! -d "node_modules" ] || [ ! -d "blocks/node_modules" ]; then
	echo "Installing JavaScript dependencies..."
	npm run install:ci
fi

echo "Building the bundles..."
npm run build:all

# Runtime dependencies only. Action Scheduler is load-bearing: it is what
# expires secrets on time.
echo "Installing runtime Composer dependencies..."
composer install --no-dev --optimize-autoloader --quiet

copy_list=(
	psst.php
	uninstall.php
	includes
	patterns
	languages
	vendor
	composer.json
	LICENSE
	README.md
)

# `build` and `blocks/build` are handled after this loop: build/ is the
# artifact's own parent, so listing it here would copy the staging directory
# into itself.
for item in "${copy_list[@]}"; do
	if [ ! -e "${item}" ]; then
		echo "::warning::${item} is in the copy list but does not exist"
		continue
	fi

	# One entry at a time: rsync's --files-from turns recursion off unless -r is
	# given explicitly, and whether -a counts varies between versions.
	rsync -a --exclude-from=".distignore" "${item}" "${OUTPUT_DIR}/"
done

mkdir -p "${OUTPUT_DIR}/blocks"
rsync -a "blocks/build" "${OUTPUT_DIR}/blocks/"

# The admin app. Everything in build/ except the artifact being assembled.
mkdir -p "${OUTPUT_DIR}/build"
rsync -a --exclude "/${SLUG}" --exclude "/${SLUG}.zip" "build/" "${OUTPUT_DIR}/build/"

# Assertions, each guarding a runtime path with nothing else behind it.
for required in \
	psst.php \
	uninstall.php \
	includes/Core/Bootstrap.php \
	vendor/autoload.php \
	vendor/woocommerce/action-scheduler/action-scheduler.php \
	blocks/build/blocks-manifest.php \
	build/admin.js \
	build/admin.asset.php; do
	if [ ! -f "${OUTPUT_DIR}/${required}" ]; then
		echo "::error::${required} is missing from the build; the plugin would not work."
		exit 1
	fi
done

# Blocks register from their *built* block.json; without these the front end
# renders nothing, silently.
for block in secret-form secret-viewer; do
	if [ ! -f "${OUTPUT_DIR}/blocks/build/${block}/block.json" ]; then
		echo "::error::blocks/build/${block}/block.json is missing; the ${block} block would never register."
		exit 1
	fi
	if [ ! -f "${OUTPUT_DIR}/blocks/build/${block}/render.php" ]; then
		echo "::error::blocks/build/${block}/render.php is missing; --webpack-copy-php did not run."
		exit 1
	fi
done

for forbidden in node_modules .npmrc auth.json .git src tests; do
	if [ -e "${OUTPUT_DIR}/${forbidden}" ]; then
		echo "::error::${forbidden} is in the build and must not ship."
		exit 1
	fi
done

# Boot the packaged autoloader and make it resolve the plugin's classes and
# Action Scheduler's — the piece a copy-list mistake breaks silently.
php -r '
	require "'"${OUTPUT_DIR}"'/vendor/autoload.php";
	$missing = [];
	foreach ( [ "Linchpin\\Psst\\Core\\Bootstrap", "ActionScheduler_Versions" ] as $class ) {
		if ( ! class_exists( $class ) ) {
			$missing[] = $class;
		}
	}
	if ( $missing ) {
		fwrite( STDERR, "::error::The packaged autoloader cannot resolve: " . implode( ", ", $missing ) . PHP_EOL );
		exit( 1 );
	}
'

# The header is the version SatisPress publishes.
HEADER_VERSION="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([^[:space:]]*\).*/\1/p' "${OUTPUT_DIR}/psst.php" | head -1 | tr -d '\r')"
MANIFEST_VERSION="$(sed -n 's/.*"\."[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' "${REPO_ROOT}/.release-please-manifest.json" | head -1)"

if [ -z "${HEADER_VERSION}" ] || [ -z "${MANIFEST_VERSION}" ]; then
	echo "::error::Could not read the version from the plugin header or the release-please manifest."
	exit 1
fi

if [ "${HEADER_VERSION}" != "${MANIFEST_VERSION}" ]; then
	echo "::error::Plugin header says ${HEADER_VERSION}, release-please manifest says ${MANIFEST_VERSION}."
	exit 1
fi

echo "Built ${OUTPUT_DIR} ($(find "${OUTPUT_DIR}" -type f | wc -l | tr -d ' ') files, version ${HEADER_VERSION})"

# Zipped from inside build/ so the archive holds a single top-level psst/
# directory. The name carries no version: SatisPress derives the slug, and so
# the Composer package name, from the directory inside it.
( cd "${BUILD_DIR}" && zip -rq -X "${SLUG}.zip" "${SLUG}" )

echo "Built ${ZIP_PATH} ($(du -h "${ZIP_PATH}" | cut -f1 | tr -d ' '))"

if [ -z "${CI:-}" ]; then
	echo "Restoring development dependencies..."
	composer install --quiet
fi
