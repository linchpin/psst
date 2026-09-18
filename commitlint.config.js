/**
 * Commit linting for this project.
 *
 * The rules live in @linchpinagency/commitlint-config so every Linchpin project
 * lints commits the same way.
 *
 * Format: feat(PROJ-123): Add new feature — or NO-TASK / #42 as the scope.
 */
module.exports = {
	extends: [ '@linchpinagency/commitlint-config' ],
};
