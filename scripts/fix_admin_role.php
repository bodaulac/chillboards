<?php
// fix_admin_role.php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$id = 1; // Assuming Admin is ID 1
$user = User::find($id);
if ($user) {
    $user->role = 'admin';
    $user->save();
    echo "User ID $id role set to admin.\n";
} else {
    echo "User ID $id not found. Trying to find by email...\n";
    $user = User::where('email', 'admin@example.com')->first();
    if ($user) {
        $user->role = 'admin';
        $user->save();
        echo "User " . $user->email . " role set to admin.\n";
    } else {
        echo "Admin user not found.\n";
    }
}
