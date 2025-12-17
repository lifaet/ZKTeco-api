<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class StaffSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $now = Carbon::now();
        $rows = [
            [ 'id' => 101, 'name' => 'Major Md. Rafiqul Islam (Retd)', 'title' => 'Secretary General', 'department' => 'Secretary General' ],
            [ 'id' => 106, 'name' => 'Ms. Sharif Nawrin Akter', 'title' => 'AGM', 'department' => 'Service' ],
            [ 'id' => 126, 'name' => 'Md. Abdullah Hil Baki', 'title' => 'Sr. Manager', 'department' => 'Accounts & Admin' ],
            [ 'id' => 130, 'name' => 'Abu Fattah Md. Issa', 'title' => 'Sr. Manager', 'department' => 'Compliance' ],
            [ 'id' => 108, 'name' => 'Mrs Jakia Begum', 'title' => 'Deputy Manager', 'department' => 'Service' ],
            [ 'id' => 109, 'name' => 'Mr. Bishmoy Saha', 'title' => 'Deputy Manager', 'department' => 'Service' ],
            [ 'id' => 118, 'name' => 'Md. Ibrahim Khalil', 'title' => 'Deputy Manager', 'department' => 'Compliance' ],
            [ 'id' => 128, 'name' => 'Md. Aminur Rahman', 'title' => 'Assistant Manager', 'department' => 'Compliance' ],
            [ 'id' => 111, 'name' => 'Mr. Md. Shahadat Hossain', 'title' => 'Sr. Officer', 'department' => 'Service' ],
            [ 'id' => 113, 'name' => 'Ms. Rashmin Rob Rini', 'title' => 'Sr. Executive', 'department' => 'Service' ],
            [ 'id' => 117, 'name' => 'Anika Afrin Swarna', 'title' => 'Sr. Executive', 'department' => 'Research & Policy' ],
            [ 'id' => 121, 'name' => 'Md. Nahidul Islam', 'title' => 'Executive', 'department' => 'Accounts & Admin' ],
            [ 'id' => 125, 'name' => 'Mr. Shoriful Islam', 'title' => 'Executive', 'department' => 'Compliance' ],
            [ 'id' => 112, 'name' => 'Mr. Md. Ishrafil Khan', 'title' => 'Office Assistant', 'department' => 'Accounts & Admin' ],
            [ 'id' => 123, 'name' => 'Md. Forhad Hossain', 'title' => 'Office Assistant', 'department' => 'Accounts & Admin' ],
            [ 'id' => 131, 'name' => 'Md Khairujjaman Tushar', 'title' => 'Sr. Executive', 'department' => 'Compliance' ],
        ];

        foreach ($rows as $r) {
            DB::table('staff')->updateOrInsert(['id' => $r['id']], array_merge($r, ['created_at' => $now, 'updated_at' => $now]));
        }
    }
}
