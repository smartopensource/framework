<?php

namespace Modules\Logs\Database\Seeds;

use Nova\Database\Seeder;
use Nova\Database\ORM\Model;


class LogsDatabaseSeeder extends Seeder
{
    /**
     * Run the Database Seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

        //
        $this->call('Modules\Logs\Database\Seeds\LogGroupsTableSeeder');
    }
}
