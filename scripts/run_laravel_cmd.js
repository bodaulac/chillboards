const { Client } = require('ssh2');

const config = {
    host: '4.194.223.85',
    username: 'qcts',
    password: 'xekdev-4befqy-buxpoD',
};

const cmd = process.argv.slice(2).join(' ');

if (!cmd) {
    console.error('Please provide a command to run.');
    process.exit(1);
}

const conn = new Client();

conn.on('ready', () => {
    console.log(`✅ Connected to VPS: ${config.host}`);
    console.log(`Running: ${cmd}`);

    conn.exec(cmd, (err, stream) => {
        if (err) throw err;

        stream.on('close', (code, signal) => {
            console.log(`\n✅ Command exited with code: ${code}`);
            conn.end();
            process.exit(code);
        }).on('data', (data) => {
            process.stdout.write(data);
        }).stderr.on('data', (data) => {
            process.stderr.write(data);
        });
    });
}).on('error', (err) => {
    console.error('Connection Error:', err);
    process.exit(1);
}).connect(config);
