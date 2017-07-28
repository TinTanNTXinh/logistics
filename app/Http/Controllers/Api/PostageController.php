<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Services\PostageServiceInterface;
use App\Common\Interfaces\ICrud;
use App\Common\Helpers\HttpStatusCodeHelper;
use Route;

class PostageController extends Controller implements ICrud
{
    private $table_name;

    protected $postageService;

    public function __construct(PostageServiceInterface $postageService)
    {
        $this->postageService       = $postageService;

        $this->table_name = 'postage';
    }

    /** ===== API METHOD ===== */
    public function getReadAll()
    {
        $arr_datas = $this->readAll();
        return response()->json($arr_datas, HttpStatusCodeHelper::$ok);
    }

    public function getReadOne()
    {
        $id  = Route::current()->parameter('id');
        $one = $this->readOne($id);
        return response()->json($one, HttpStatusCodeHelper::$ok);
    }

    public function postCreateOne(Request $request)
    {
        $data      = $request->input($this->table_name);

        $validate_data = $this->createOne($data);
        if (!$validate_data['status'])
            return response()->json(['errors' => $validate_data['errors']], HttpStatusCodeHelper::$unprocessableEntity);

        $arr_datas = $this->readAll();
        return response()->json($arr_datas, HttpStatusCodeHelper::$created);
    }

    public function putUpdateOne(Request $request)
    {
        $data      = $request->input($this->table_name);

        $validate_data = $this->updateOne($data);
        if (!$validate_data['status'])
            return response()->json(['errors' => $validate_data['errors']], HttpStatusCodeHelper::$unprocessableEntity);

        $arr_datas = $this->readAll();
        return response()->json($arr_datas, HttpStatusCodeHelper::$ok);
    }

    public function patchDeactivateOne(Request $request)
    {
        $id = $request->input('id');

        $validate_data = $this->deactivateOne($id);
        if (!$validate_data['status'])
            return response()->json(['errors' => $validate_data['errors']], HttpStatusCodeHelper::$unprocessableEntity);

        $arr_datas = $this->readAll();
        return response()->json($arr_datas, HttpStatusCodeHelper::$ok);
    }

    public function deleteDeleteOne(Request $request)
    {
        $id = Route::current()->parameter('id');

        $validate_data = $this->deleteOne($id);
        if (!$validate_data['status'])
            return response()->json(['errors' => $validate_data['errors']], HttpStatusCodeHelper::$unprocessableEntity);

        $arr_datas = $this->readAll();
        return response()->json($arr_datas, HttpStatusCodeHelper::$ok);
    }

    public function getSearchOne()
    {
        $filter    = (array)json_decode($_GET['query']);
        $arr_datas = $this->searchOne($filter);
        return response()->json($arr_datas, HttpStatusCodeHelper::$ok);
    }

    /** ===== LOGIC METHOD ===== */
    public function readAll()
    {
        return $this->postageService->readAll();
    }

    public function readOne($id)
    {
        return $this->postageService->readOne($id);
    }

    public function createOne($data)
    {
        return $this->postageService->createOne($data);
    }

    public function updateOne($data)
    {
        return $this->postageService->updateOne($data);
    }

    public function deactivateOne($id)
    {
        return $this->postageService->deactivateOne($id);
    }

    public function deleteOne($id)
    {
        return $this->postageService->deleteOne($id);
    }

    public function searchOne($filter)
    {
        return $this->postageService->searchOne($filter);
    }

    /** ===== MY FUNCTION ===== */
    public function getReadByCustomerId()
    {
        $customer_id = Route::current()->parameter('customer_id');
        $one         = $this->readByCustomerId($customer_id);
        return response()->json($one, HttpStatusCodeHelper::$ok);
    }

    private function readByCustomerId($customer_id)
    {
        return $this->postageService->readByCustomerId($customer_id);
    }

    private function sortRule($i_formulas)
    {
        return $this->sortRule($i_formulas);
    }
}
