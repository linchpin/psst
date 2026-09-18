#!/usr/bin/env bash
#
# Run phpcbf over the staged PHP files, without failing the commit for having
# fixed something.
#
# phpcbf signals through its exit code rather than reserving it for failure:
#
#   0  nothing needed fixing
#   1  every fixable error was fixed
#   2  fixes applied, some errors remain that phpcbf cannot fix
#   3  phpcbf itself failed
#
# Only 3 is an error. Passing 1 and 2 straight through — which is what calling
# `phpcbf` directly from lint-staged does — aborts the commit precisely when the
# hook has done its job, so the formatting lands in the working tree and the
# commit that would have carried it is rejected. That is the behaviour this
# repository's `phpcbf.yml` autofixer existed to work around, and it is why every
# push arrived with a second "Auto Fix Formatting" pull request behind it.
#
# lint-staged re-stages whatever a task modifies, so the fixes are part of the
# commit. The `phpcs` task that runs after this one is the actual gate: anything
# phpcbf could not fix still stops the commit, with the sniff that caused it.

set -uo pipefail

if [ "$#" -eq 0 ]; then
	exit 0
fi

./vendor/bin/phpcbf --standard=phpcs.xml.dist "$@"
status=$?

if [ "$status" -ge 3 ]; then
	echo "phpcbf failed (exit ${status})." >&2
	exit "$status"
fi

exit 0
