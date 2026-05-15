<?php
$count = DB::table('employees')
    ->whereNotNull('nadlezne_osobe')
    ->whereNotNull('pass_approvers')
    ->count();
echo 'Employees with both fields: ' . $count . PHP_EOL;

// Check HR user employee record
$u = DB::table('users')->where('funkcija','HR')->first();
if ($u) {
    echo 'HR user: ' . $u->name . ' email: ' . $u->email . PHP_EOL;
    $emp = DB::table('employees')->where('email', $u->email)->select('id','dept','firstName','lastName')->first();
    echo 'Employee: ' . json_encode($emp) . PHP_EOL;
} else {
    echo 'No HR user found' . PHP_EOL;
}
// Show sample dept values
echo 'Sample dept values: ' . PHP_EOL;
DB::table('employees')->whereNotNull('dept')->select('dept')->groupBy('dept')->get()->each(function($e){
    echo $e->dept . PHP_EOL;
});
