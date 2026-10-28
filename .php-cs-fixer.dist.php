<?php

$finder = PhpCsFixer\Finder::create()
	->files()
	->in(__DIR__ . '/src')
	->in(__DIR__ . '/test')
	->in(__DIR__ . '/example')
	->name('*.php');

$config = Corpus\PhpCsFixer\Config::php8x1();

$config->setRules(array_merge($config->getRules(), [
	'@PHPUnit10x0Migration:risky' => true,
]));

$config->setUnsupportedPhpVersionAllowed(true);

return $config->setFinder($finder);
