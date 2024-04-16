#!/usr/bin/env php

<?php

/**
 * For PHP 7.4 version compatibility, we need to define these functions.
 */
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle): bool
    {
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

/**
 * For PHP 7.4 version compatibility, we need to define these functions.
 */
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return strpos($haystack, $needle) !== false;
    }
}

function ask(string $question, string $default = ''): string
{
    $answer = readline($question . ($default ? " ($default)" : null) . ': ');

    if (!$answer) {
        return $default;
    }

    return $answer;
}

function confirm(string $question, bool $default = false): bool
{
    $answer = ask($question . ' (' . ($default ? 'Y/n' : 'y/N') . ')');

    if (!$answer) {
        return $default;
    }

    return strtolower($answer) === 'y';
}

function writeln(string $line): void
{
    echo $line . PHP_EOL;
}

function run(string $command): string
{
    return trim((string)shell_exec($command));
}

function replace_in_file(string $file, array $replacements): void
{
    $contents = file_get_contents($file);

    file_put_contents(
        $file,
        str_replace(
            array_keys($replacements),
            array_values($replacements),
            $contents
        )
    );
}

function remove_readme_paragraphs(string $file): void
{
    $contents = file_get_contents($file);

    file_put_contents(
        $file,
        preg_replace('/<!--delete-->.*<!--\/delete-->/s', '', $contents) ?: $contents
    );
}

function replaceForWindows(): array
{
    return preg_split('/\\r\\n|\\r|\\n/', run('dir /S /B * | findstr /v /i .git\ | findstr /v /i vendor | findstr /v /i ' . basename(__FILE__) . ' | findstr /r /i /M /F:/ ":author_name :package_name :package_description :depends_on :apps :tags"'));
}

function replaceForAllOtherOSes(): array
{
    return explode(PHP_EOL, run('grep -E -r -l -i ":author_name|:package_name|:package_description|:depends_on|:apps|:tags" --exclude-dir=vendor ./* | grep -v ' . basename(__FILE__)));
}

function stringToArray(string $string = '')
{
    $result = str_replace(' ', '', $string);

    if (strlen($result) === 0) {
        return [];
    }

    if (!str_contains($string, ',')) {
        return [$result];
    }

    return json_encode(explode(',', $result));
}

$renamedDirectory = confirm('Have you already renamed the directory to the package name ?');

if (!$renamedDirectory) {
    writeln('Please rename the directory to the package name before running this script.');
    exit(1);
}

$gitName = run('git config user.name');
$authorName = ask('Author name', $gitName);

$gitEmail = run('git config user.email');
$authorEmail = ask('Author email', $gitEmail);


$currentDirectory = getcwd();
$folderName = basename($currentDirectory);
$packageName = ask('Package name', $folderName);
$description = ask('Package description', "This is my package $packageName");

$depends_on = stringToArray(ask("From which app you depends on ? (use comma separated like: \"core, finance, sale, inventory \")", 'core'));

$apps = stringToArray(ask("Do you want to create some apps ? (use comma separated like: \"core, finance, sale, inventory \")"));

$tags = stringToArray(ask("Do you want to add some tags ? (use comma separated like: \"core, finance, sale, inventory \")"));

$controllers = stringToArray(ask("Do you want to create some controllers ? (use comma separated like: \"model_update, model_create, model_delete \")"));

$views = confirm('Do you want to create views ?');
$entities = confirm('Do you want to create classe (entities) ?');
$api_routes = confirm('Do you want to create api routes ?');
$seeders = confirm('Do you want to seed into database some datas ?');
$i18n = confirm('Do you need traduction files (i18n) ?');
$tests = confirm('Do you want to create tests ?');

writeln('------');
writeln("Author     : $authorName ($authorEmail)");
writeln("Package    : $packageName <$description>");
writeln('------ manifest.json ------');
writeln("depends_on : " . '[' . implode(', ', $depends_on)) . ']';
writeln("apps       : " . '[' . implode(', ', $apps)) . ']';
writeln("tags       : " . '[' . implode(', ', $tags)) . ']';
writeln('------');
writeln('Controllers: ');
foreach ($controllers as $controller) {
    writeln(" - $controller");
}
writeln('------ Added Folders ------');
writeln('views      : ' . ($views ? '✅' : '❌'));
writeln('entities   : ' . ($entities ? '✅' : '❌'));
writeln('api_routes : ' . ($api_routes ? '✅' : '❌'));
writeln('seeders    : ' . ($seeders ? '✅' : '❌'));
writeln('i18n       : ' . ($i18n ? '✅' : '❌'));
writeln('tests      : ' . ($tests ? '✅' : '❌'));
writeln('------');

writeln('This script will replace the above values in all relevant files in the project directory.');

if (!confirm('Modify files?', true)) {
    exit(1);
}

if (is_array($apps) && count($apps)) {
    mkdir('apps', 0755);
    foreach ($apps as $app) {
        mkdir("apps/$app", 0755);
    }
}

if (is_array($controllers) && count($controllers)) {
    mkdir('actions', 0755);

    foreach ($controllers as $controller) {
        if (str_contains($controller, '_')) {
            $directories = explode('_', $controller);
            $controller = array_pop($directories);

            foreach ($directories as $directory) {
                mkdir("actions/$directory", 0755);
            }

            $controller_path = implode('/', $directories) . '/' . $controller . '.php';

            touch("actions/$controller_path");
            chmod("actions/$controller_path", 644);
        } else {
            touch("actions/$controller.php");
            chmod("actions/$controller.php", 644);
        }
    }
}

if ($views) {
    mkdir('views', 0755);
}

if ($entities) {
    mkdir('classes', 0755);
}

if ($api_routes) {
    mkdir('init/routes', 0755, true);
}

if ($seeders) {
    mkdir('init/data', 0755, true);
}

if ($i18n) {
    mkdir('i18n', 0755);
}

if ($tests) {
    mkdir('tests', 0755);
}

$files = (str_starts_with(strtoupper(PHP_OS), 'WIN') ? replaceForWindows() : replaceForAllOtherOSes());

foreach ($files as $file) {
    replace_in_file($file, [
        ":author_name" => $authorName,
        ":package_name" => $packageName,
        ":package_description" => $description,
        ":depends_on" => json_encode($depends_on),
        ":apps" => json_encode($apps),
        ":tags" => json_encode($tags)
    ]);

    switch (true) {
        case str_contains($file, 'README.md'):
            remove_readme_paragraphs($file);
            break;
        default:
            // default case
            break;
    }
}

confirm('Execute init_package ?') && run('equal.run --do=init_package --package=' . $packageName . ' --import=true');

confirm('Let this script delete itself?', true) && unlink(__FILE__);