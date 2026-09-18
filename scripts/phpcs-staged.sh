#!/usr/bin/env bash
#
# The pre-commit half of the coding-standards gate.
#
# Wraps `composer check-staged-cs`, which works out the changed lines itself, so the
# file paths lint-staged appends to every command are deliberately dropped here rather
# than narrowing a set that is already correct.

set -uo pipefail

exec composer check-staged-cs --
