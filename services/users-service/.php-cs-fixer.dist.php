<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude('vendor')
    ->exclude('var')
    ->exclude('public/bundles')
    ->notPath('bin/console');

$config = new PhpCsFixer\Config();
return $config->setRules([
        '@Symfony' => true,
        'array_syntax' => ['syntax' => 'short'], 
    ])
    ->setFinder($finder);