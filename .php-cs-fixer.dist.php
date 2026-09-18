<?php
/**
 * php-cs-fixer configuration.
 *
 * WordPress coding standards are PHPCS's job, through phpcs.xml.dist. What is
 * left for php-cs-fixer is the handful of things PHPCS does not cover, so the
 * rule list is short — the same arrangement as mantle and discovery.
 *
 * @package Linchpin\Psst
 */

$finder = PhpCsFixer\Finder::create()->in( __DIR__ );

$finder->exclude(
	[
		'vendor',
		'node_modules',
		'blocks/node_modules',
		'build',
		'blocks/build',
		'.phpunit.cache',
	]
);

$config = new PhpCsFixer\Config();

return $config->setRules(
	[
		'strict_param' => false,
		'array_syntax' => [ 'syntax' => 'short' ],
	]
)->setFinder( $finder );
