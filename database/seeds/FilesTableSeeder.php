<?php

use Illuminate\Database\Seeder;
use App\Repositories\FileRepositoryInterface;

class FilesTableSeeder extends Seeder
{
    protected $repository;
    public function __construct(FileRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user_ids = \App\User::all()->pluck('id')->toArray();

        foreach (array_slice($user_ids, 2) as $key => $user_id) {
            \App\File::create([
                'code'         => $this->repository->generateCode('FILE'),
                'name'         => '',
                'extension'    => 'png',
                'mime_type'    => 'image/png',
                'path'         => 'assets/img/a' . 'default' . '.png',
                'size'         => 0,
                'table_name'   => 'users',
                'table_id'     => $user_id,
                'note'         => '',
                'created_by'   => 1,
                'updated_by'   => 0,
                'created_date' => date('Y-m-d H:i:s'),
                'updated_date' => null,
                'active'       => true
            ]);
        }
    }
}
