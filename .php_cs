<?php
$finder = PhpCsFixer\Finder::create()
    ->exclude('vendor')
	->exclude('node_modules')
	->exclude('bin')
    ->in(__DIR__)
;

return PhpCsFixer\Config::create()
    ->setRules([
        'native_function_invocation' => true,
		'native_constant_invocation' => true,
    ])
    ->setFinder($finder)
;
