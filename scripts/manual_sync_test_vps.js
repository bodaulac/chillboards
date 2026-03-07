const { Client } = require('ssh2');
const fs = require('fs');
const path = require('path');

const config = {
    host: '4.194.223.85',
    username: 'qcts',
    password: 'xekdev-4befqy-buxpoD',
};

const phpContent = `<?php
define('LARAVEL_START', microtime(true));
require __DIR__ . '/vendor/autoload.php';
\$app = require_once __DIR__ . '/bootstrap/app.php';
\$kernel = \$app->make(Illuminate\\Contracts\\Console\\Kernel::class);
\$kernel->bootstrap();

use App\\Services\\WalmartService;
use App\\Models\\Store;

\$storeId = 'TrieuLD';
echo "--- Testing Sync for Store: \$storeId ---\\n";

try {
    \$store = Store::where('store_id', \$storeId)->first();
    if (!\$store) {
        die("Store \$storeId not found in DB\\n");
    }
    
    \$service = app(WalmartService::class);
    echo "Calling syncStore()...\\n";
    \$result = \$service->syncStore(\$storeId, 30); // Check last 30 days
    
    echo "Result:\\n";
    print_r(\$result);
    
} catch (\\Exception \$e) {
    echo "EXCEPTION: " . \$e->getMessage() . "\\n";
    echo \$e->getTraceAsString() . "\\n";
}
`;

const localFile = 'manual_sync_test.php';
const remotePath = `/home/qcts/laravel_app/${localFile}`;

const conn = new Client();

conn.on('ready', () => {
    console.log('✅ Connected to VPS');

    conn.sftp((err, sftp) => {
        if (err) throw err;

        console.log(`Uploading -> ${remotePath}`);
        const writeStream = sftp.createWriteStream(remotePath);

        writeStream.on('close', () => {
            console.log(`✅ Uploaded`);

            console.log(`Running php ${remotePath}...`);
            conn.exec(`php ${remotePath}`, (err, stream) => {
                if (err) throw err;

                stream.on('close', (code, signal) => {
                    console.log(`\n✅ Finished with code: ${code}`);
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
