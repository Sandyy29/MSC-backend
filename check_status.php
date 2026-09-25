<?php
$u = \App\Models\User::find(2);
echo "Manager 2 is_active: " . ($u->is_active ? 'true' : 'false') . "\n";
