const { Client } = require('ssh2');
const fs = require('fs');
const path = require('path');

const config = {
    host: '4.194.223.85',
    username: 'qcts',
    password: 'xekdev-4befqy-buxpoD',
};

const localFile = 'test_sync.php';
const remotePath = `/home/qcts/laravel_app/${localFile}`;

const phpContent = `<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Foundation\Application::class)->bootstrapWith([
    Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables::class,
    Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
    Illuminate\Foundation\Bootstrap\HandleExceptions::class,
    Illuminate\Foundation\Bootstrap\RegisterFacades::class,
    Illuminate\Foundation\Bootstrap\RegisterProviders::class,
    Illuminate\Foundation\Bootstrap\BootProviders::class,
]);

use App\Services\WalmartService;

echo "Syncing TrieuLD...\n";
try {
    $service = app(WalmartService::class);
    $result = $service->syncStore('TrieuLD', 7);
    print_r($result);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nSyncing 305-DEPOT-LLC...\n";
try {
    $result = $service->syncStore('305-DEPOT-LLC', 7);
    print_r($result);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
`;

const conn = new Client();

conn.on('ready', () => {
    console.log('✅ Connected to VPS');

    conn.sftp((err, sftp) => {
        if (err) throw err;

        console.log(`Uploading -> ${remotePath}`);

        const writeStream = sftp.createWriteStream(remotePath);

        writeStream.on('close', () => {
            console.log(`✅ Uploaded`);

            // Execute the script
            console.log(`Running php ${remotePath}...`);
            conn.exec(`php ${remotePath}`, (err, stream) => {
                if (err) throw err;

                stream.on('close', (code, signal) => {
                    console.log(`\n✅ Command finished with code: ${code}`);
                    conn.exec(`rm ${remotePath}`, () => {
                        conn.end();
                        process.exit(code);
                    });
                }).on('data', (data) => {
                    process.stdout.write(data);
                }).stderr.on('data', (data) => {
                    process.stderr.write(data);
                });
            });
        });

        writeStream.end(phpContent);
    });
}).on('error', (err) => {
    console.error('Connection Error:', err);
    process.exit(1);
}).connect(config);
