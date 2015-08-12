<?php
require_once __DIR__ . '/vendor/libphutil/src/__phutil_library_init__.php';
require_once __DIR__ . '/vendor/autoload.php';

if (count($argv) < 3)
{
    echo "Usage: php maniphest_bulk_add.php project_id /path/to/tasks_file\n";
    exit(1);
}

$project = $argv[1];
$tasksFile = $argv[2];
$config = Symfony\Component\Yaml\Yaml::parse(file_get_contents(__DIR__ . '/config.yml'));

$conduit = new ConduitClient($config['PHABRICATOR_URL']);
$conduit->callMethodSynchronous(
    'conduit.connect',
    [
        'client' => 'Maniphest Bulk Add',
        'clientVersion' => 1,
        'user' => $config['USER'],
        'certificate' => $config['CONDUIT_CERTIFICATE']
    ]
);

$projectQuery = $conduit->callMethodSynchronous('project.query', ['ids' => [$project]]);
if (empty($projectQuery['data']))
{
    echo "Could not find project $project";
    exit(1);
}

$targetProject = reset($projectQuery['data']);

$taskIDs = array_filter(array_map(
    function($line)
    {
        preg_match("/\d+$/", $line, $matches);
        if (!empty($matches)) return $matches[0];
    },
    explode("\n", file_get_contents($tasksFile))
));

$tasks = $conduit->callMethodSynchronous('maniphest.query', ['ids' => $taskIDs]);
if (count($tasks) !== count($taskIDs))
{
    echo "Couldn't find all the tasks. Aborting :(\n";
    exit(1);
}

foreach ($tasks as $task) {
    $conduit->callMethodSynchronous(
        'maniphest.update',
        [
            'id' => $task['id'],
            'projectPHIDs' => array_merge($task['projectPHIDs'], [$targetProject['phid']])
        ]
    );
}

echo 'Added ' . count($tasks) . ' tasks to "' . $targetProject['name'] . "\"\n";
