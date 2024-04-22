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

function stringToArray(string $string = ''): array
{
    $result = str_replace(' ', '', $string);

    if (strlen($result) === 0) {
        $result = [];
    } elseif (!str_contains($string, ',')) {
        $result = [$result];
    } else {
        $result = explode(',', $result);
    }

    return $result;
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

$apps = stringToArray(ask("Do you want to create some apps ? (use comma separated like: \"welcome, finance, sale, inventory \")"));

$tags = stringToArray(ask("Do you want to add some tags ? (use comma separated like: \"equal, food, login, social \")"));

$action_controllers = stringToArray(ask("Do you want to create some actions controllers ? (use comma separated like: \"model_update, model_create, model_delete \")"));

$data_controllers = stringToArray(ask("Do you want to create some data controllers ? (use comma separated like: \"userinfo, appinfo, envinfo \")"));

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
writeln("depends_on : " . json_encode($depends_on));
writeln("apps       : " . json_encode($apps));
writeln("tags       : " . json_encode($tags));
writeln('------ Action controllers: ------');
foreach ($action_controllers as $controller) {
    writeln(" - $controller");
}
writeln('------ Data controllers: ------');
foreach ($data_controllers as $controller) {
    writeln(" - $controller");
}
writeln('------ Added Folders ------');
writeln('views      : ' . ($views ? '   ✅   ' : '   ❌   '));
writeln('entities   : ' . ($entities ? '   ✅   ' : '   ❌   '));
writeln('api_routes : ' . ($api_routes ? '   ✅   ' : '   ❌   '));
writeln('seeders    : ' . ($seeders ? '   ✅   ' : '   ❌   '));
writeln('i18n       : ' . ($i18n ? '   ✅   ' : '   ❌   '));
writeln('tests      : ' . ($tests ? '   ✅   ' : '   ❌   '));
writeln('------');

writeln('This script will replace the above values in all relevant files in the project directory.');

if (!confirm('Modify files?', true)) {
    exit(1);
}

/**
 * @param array $controllers
 * @param string $controller_type
 * Can be "actions" or "data"
 * @param string $packageName
 * @return void
 */
function createControllers(array $controllers, string $packageName, string $controller_type = 'actions'): void
{
    if (count($controllers)) {
        mkdir($controller_type, 0755);

        foreach ($controllers as $controller) {
            if (str_contains($controller, '_')) {
                $directories = explode('_', $controller);
                $controller = array_pop($directories);

                $directoryPath = implode('/', $directories);

                @mkdir($controller_type . "/" . $directoryPath, 0755, true);

                $controller_file = $directoryPath . '/' . $controller . '.php';

                $namespace = $packageName . '\\' . $controller_type . '\\' . implode('\\', $directories);
            } else {
                $controller_file = $controller . '.php';
                $namespace = $packageName . '\\' . $controller_type;
            }
            $controller_content = sprintf("<?php

namespace %s;

list( \$params, \$providers ) = eQual::announce( [
    'description' => '',
    'params'      => [],
    'response'    => [
        'content-type'      => 'application/json',
        'charset'           => 'UTF-8',
        'accept-origin'     => '*'
    ],
    'constants'   => [],
    'access'      => [
        'visibility' => 'public',
    ],
    'providers'   => []
]);
", $namespace);

            file_put_contents($controller_type . "/$controller_file", $controller_content);
            chmod($controller_type . "/$controller_file", 0644);
        }
    }
}

createControllers($action_controllers, $packageName);
createControllers($data_controllers, $packageName, 'data');

if (is_array($apps) && count($apps)) {
    mkdir('apps', 0755);
    foreach ($apps as $app) {
        mkdir("apps/$app", 0755);
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