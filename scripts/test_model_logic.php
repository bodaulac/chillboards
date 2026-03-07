<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

echo "--- MODEL LOGIC DIAGNOSTIC ---\n";

try {
    $user = User::find(1);
    if (!$user) {
        echo "User 1 not found.\n";
        exit(1);
    }

    echo "User 1 found: " . $user->email . "\n";
    echo "Role attribute: " . $user->role . "\n";

    if (method_exists($user, 'isAdmin')) {
        echo "Method isAdmin() exists.\n";
        $isAdm = $user->isAdmin();
        echo "isAdmin() returned: " . ($isAdm ? 'TRUE' : 'FALSE') . "\n";
    } else {
        echo "Method isAdmin() DOES NOT EXIST.\n";
    }

} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

echo "--- END DIAGNOSTIC ---\n";
