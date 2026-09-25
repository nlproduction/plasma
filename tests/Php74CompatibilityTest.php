<?php

it('keeps every runtime source file parseable as PHP 7.4', function () {
    $parser = (new PhpParser\ParserFactory())->createForVersion(
        PhpParser\PhpVersion::fromString('7.4')
    );
    $errors = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            dirname(__DIR__) . '/src',
            FilesystemIterator::SKIP_DOTS
        )
    );

    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        try {
            $parser->parse(file_get_contents($file->getPathname()));
        } catch (Throwable $error) {
            $errors[] = $file->getPathname() . ': ' . $error->getMessage();
        }
    }

    expect($errors)->toBe([]);
});
