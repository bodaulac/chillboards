const { Client } = require('ssh2');
const fs = require('fs');
const path = require('path');

const config = {
    host: '4.194.223.85',
    username: 'qcts',
    password: 'xekdev-4befqy-buxpoD',
};

const projectPath = '/home/qcts/laravel_app';

const filesToUpload = [
    {
        local: 'app/Console/Commands/SyncWalmartOrders.php',
        remote: `${projectPath}/app/Console/Commands/SyncWalmartOrders.php`
    },
    {
        local: 'routes/console.php',
        remote: `${projectPath}/routes/console.php`
    }
];

const conn = new Client();

conn.on('ready', () => {
    console.log('✅ Connected to VPS');

    conn.sftp(async (err, sftp) => {
        if (err) throw err;

        for (const file of filesToUpload) {
            console.log(`Uploading ${file.local} -> ${file.remote}`);
            await new Promise((resolve, reject) => {
                const readStream = fs.createReadStream(path.resolve(__dirname, file.local));
                const writeStream = sftp.createWriteStream(file.remote);
                writeStream.on('close', resolve);
                writeStream.on('error', reject);
                readStream.pipe(writeStream);
            });
        }

        console.log('✅ All files uploaded.');

        // 2. Setup Crontab
        const cronLine = `* * * * * cd ${projectPath} && php artisan schedule:run >> /dev/null 2>&1`;
        console.log(`Setting up crontab: ${cronLine}`);

        conn.exec(`(crontab -l 2>/dev/null; echo "${cronLine}") | crontab -`, (err, stream) => {
            if (err) throw err;

            stream.on('close', (code) => {
                console.log(`✅ Crontab updated (exit code: ${code})`);

                // 3. Verify
                console.log('Verifying schedule:list...');
                conn.exec(`cd ${projectPath} && php artisan schedule:list`, (err, vStream) => {
                    vStream.on('close', () => {
                        conn.end();
                    }).on('data', (data) => {
                        process.stdout.write(data);
                    });
                });
            }).on('data', (data) => process.stdout.write(data));
        });
    });
}).on('error', (err) => {
    console.error('Connection Error:', err);
    process.exit(1);
}).connect(config);
