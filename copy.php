<?php

date_default_timezone_set('UTC');

require __DIR__ . '/vendor/autoload.php';

$climate = new League\CLImate\CLImate;
$climate->description(
    'Fly Copy: Copies a file from OpenStack to the local filesystem if it has been modified'
);
$climate->arguments->add([
    'env' => [
        'prefix' => 'e',
        'longPrefix' => 'env',
        'description' => 'Defines the environment file basename, defaults to ""',
        'defaultValue' => '',
    ],
    'help' => [
        'prefix' => '?',
        'longPrefix' => 'help',
        'description' => 'Prints a usage statement',
        'noValue' => true,
    ],
]);
$climate->arguments->parse();

if ($climate->arguments->get('help')) {
    $climate->usage();
    exit();
}

try {
    $dotenv = new Dotenv\Dotenv(__DIR__, $climate->arguments->get('env') . '.env');
    $dotenv->load();
    $dotenv->required(
        [
            'FLYCOPY_FILE',
            'FLYCOPY_OPENSTACK_ENDPOINT',
            'FLYCOPY_OPENSTACK_USERNAME',
            'FLYCOPY_OPENSTACK_PASSWORD',
            'FLYCOPY_OPENSTACK_TENANTNAME',
            'FLYCOPY_OPENSTACK_SERVICENAME',
            'FLYCOPY_OPENSTACK_REGION',
            'FLYCOPY_OPENSTACK_CONTAINER',
            'FLYCOPY_LOCAL_ROOT',
        ]
    );
} catch (Exception $e) {
    $climate->red()->error($e->getMessage());
    exit();
}

$adapter = new League\Flysystem\Adapter\Local(getenv('FLYCOPY_LOCAL_ROOT'));
$destination = new League\Flysystem\Filesystem($adapter);

try {
    $existingFileTimestamp = $destination->getTimestamp(getenv('FLYCOPY_FILE'));
} catch (League\Flysystem\FileNotFoundException $e) {
    // there isn't an existing file at the destination
    $existingFileTimestamp = 0;
}

$client = new OpenCloud\OpenStack(
    getenv('FLYCOPY_OPENSTACK_ENDPOINT'),
    [
        'username' => getenv('FLYCOPY_OPENSTACK_USERNAME'),
        'password' => getenv('FLYCOPY_OPENSTACK_PASSWORD'),
        'tenantName' => getenv('FLYCOPY_OPENSTACK_TENANTNAME'),
    ]
);

$store = $client->objectStoreService(
    getenv('FLYCOPY_OPENSTACK_SERVICENAME'),
    getenv('FLYCOPY_OPENSTACK_REGION')
);
$container = $store->getContainer(getenv('FLYCOPY_OPENSTACK_CONTAINER'));

$source = new League\Flysystem\Filesystem(
    new League\Flysystem\Rackspace\RackspaceAdapter($container)
);

try {
    $newFileTimestamp = $source->getTimestamp(getenv('FLYCOPY_FILE'));
    if ($newFileTimestamp <= $existingFileTimestamp) {
        $climate->yellow()->out('File has not been modified');
    } else {
        $content = $source->read(getenv('FLYCOPY_FILE'));
        $destination->put(getenv('FLYCOPY_FILE'), $content);
        $climate->green()->out('File copied');
    }
} catch (League\Flysystem\FileNotFoundException $e) {
    $climate->red()->error('Source file does not exist');
}
