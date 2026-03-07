<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\User;

echo "--- RBAC DIAGNOSTIC ---\n";

// Check Tables
$tables = ['teams', 'store_team', 'store_user'];
foreach ($tables as $t) {
    if (Schema::hasTable($t)) {
        echo "[OK] Table '$t' exists.\n";
    } else {
        echo "[FAIL] Table '$t' MISSING.\n";
    }
}

// Check Column
if (Schema::hasColumn('users', 'role')) {
    echo "[OK] Column 'role' in 'users' exists.\n";
} else {
    echo "[FAIL] Column 'role' in 'users' MISSING.\n";
}

// Check Admin
$admin = User::find(1);
if ($admin) {
    echo "[INFO] User ID 1 Role: " . $admin->role . "\n";
} else {
    echo "[WARN] User ID 1 not found.\n";
}

// Check Team Count
$count = DB::table('teams')->count();
echo "[INFO] Total Teams: $count\n";

echo "--- END DIAGNOSTIC ---\n";
