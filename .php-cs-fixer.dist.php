<?php

$finder = PhpCsFixer\Finder::create()
	->files()
	->in(__DIR__ . '/src')
	->in(__DIR__ . '/test')
	->in(__DIR__ . '/example')
	->name('*.php');

$config = Corpus\PhpCsFixer\Config::php7x4();

if( method_exists($config, 'setUnsupportedPhpVersionAllowed') ) {
	$config->setUnsupportedPhpVersionAllowed(true);
}

return $config->setFinder($finder);
