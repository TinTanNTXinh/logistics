<?php

namespace App\Services\Implement;

use App\Services\AuthServiceInterface;
use App\Services\CostLubeServiceInterface;
use App\Repositories\CostLubeRepositoryInterface;
use App\Repositories\LubeRepositoryInterface;
use App\Repositories\TruckRepositoryInterface;
use App\Common\Helpers\DateTimeHelper;
use App\Common\Helpers\FilterHelper;
use DB;
use League\Flysystem\Exception;

class CostLubeService implements CostLubeServiceInterface
{
    private $user;
    private $table_name;

    protected $authService, $costLubeRepo, $lubeRepo, $truckRepo;

    public function __construct(AuthServiceInterface $authService
        , CostLubeRepositoryInterface $costLubeRepo
        , LubeRepositoryInterface $lubeRepo
        , TruckRepositoryInterface $truckRepo)
    {
        $this->authService  = $authService;
        $this->costLubeRepo = $costLubeRepo;
        $this->lubeRepo     = $lubeRepo;
        $this->truckRepo    = $truckRepo;

        $jwt_data = $this->authService->getCurrentUser();
        if ($jwt_data['status']) {
            $user_data = $this->authService->getInfoCurrentUser($jwt_data['user']);
            if ($user_data['status'])
                $this->user = $user_data['user'];
        }

        $this->table_name = 'cost_lube';
    }

    public function readAll()
    {
        $all = $this->costLubeRepo->findAllActiveSkeleton();

        $trucks = $this->truckRepo->findAllActiveSkeleton();

        $lube = $this->lubeRepo->findOneActiveByApplyDate();

        return [
            'cost_lubes' => $all,
            'trucks'     => $trucks,
            'lube'       => $lube
        ];
    }

    public function readOne($id)
    {
        $one = $this->costLubeRepo->findOneActiveSkeleton($id);

        return [
            $this->table_name => $one
        ];
    }

    public function createOne($data)
    {
        $validates = $this->validateInput($data);
        if (!$validates['status'])
            return $validates;

        $result = [
            'status' => false,
            'errors' => []
        ];

        try {
            DB::beginTransaction();

            $i_one = [
                'code'      => $this->costLubeRepo->generateCode('COSTLUBE'),
                'type'      => 'LUBE',
                'vat'       => $data['vat'],
                'after_vat' => $data['after_vat'],

                'fuel_id'       => $data['fuel_id'],
                'quantum_liter' => $data['quantum_liter'],
                'refuel_date'   => DateTimeHelper::toStringDateTimeClientForDB($data['refuel_date']),

                'checkin_date'  => null,
                'checkout_date' => null,
                'total_day'     => null,

                'note'         => $data['note'],
                'created_by'   => $this->user->id,
                'updated_by'   => 0,
                'created_date' => date('Y-m-d'),
                'updated_date' => null,
                'active'       => true,
                'truck_id'     => $data['truck_id'],
                'invoice_id'   => 0
            ];

            $one = $this->costLubeRepo->createOne($i_one);

            if (!$one) {
                DB::rollback();
                return $result;
            }

            DB::commit();
            $result['status'] = true;
            return $result;
        } catch (Exception $ex) {
            DB::rollBack();
            return $result;
        }
    }

    public function updateOne($data)
    {
        $validates = $this->validateInput($data);
        if (!$validates['status'])
            return $validates;

        $result = [
            'status' => false,
            'errors' => []
        ];
        try {
            DB::beginTransaction();

            // Validate
            $validate_data = $this->validateUpdateOne($data['id']);
            if (!$validate_data['status']) {
                return $validate_data;
            }

            $one = $this->costLubeRepo->findOneActive($data['id']);

            $i_one = [
                'type'      => 'LUBE',
                'vat'       => $data['vat'],
                'after_vat' => $data['after_vat'],

                'fuel_id'       => $data['fuel_id'],
                'quantum_liter' => $data['quantum_liter'],
                'refuel_date'   => DateTimeHelper::toStringDateTimeClientForDB($data['refuel_date']),

                'note'         => $data['note'],
                'updated_by'   => $this->user->id,
                'updated_date' => date('Y-m-d'),
                'active'       => true,
                'truck_id'     => $data['truck_id']
            ];

            $one = $this->costLubeRepo->updateOne($one->id, $i_one);

            if (!$one) {
                DB::rollback();
                return $result;
            }

            DB::commit();
            $result['status'] = true;
            return $result;
        } catch (Exception $ex) {
            DB::rollBack();
            return $result;
        }
    }

    public function deactivateOne($id)
    {
        $result = [
            'status' => false,
            'errors' => []
        ];
        try {
            DB::beginTransaction();

            // Validate
            $validate_data = $this->validateDeactivateOne($id);
            if (!$validate_data['status']) {
                return $validate_data;
            }

            $one = $this->costLubeRepo->deactivateOne($id);

            if (!$one) {
                DB::rollback();
                return $result;
            }

            DB::commit();
            $result['status'] = true;
            return $result;
        } catch (Exception $ex) {
            DB::rollBack();
            return $result;
        }
    }

    public function deleteOne($id)
    {
        $result = [
            'status' => false,
            'errors' => []
        ];
        try {
            DB::beginTransaction();

            // Validate
            $validate_data = $this->validateDeleteOne($id);
            if (!$validate_data['status']) {
                return $validate_data;
            }

            $one = $this->costLubeRepo->destroyOne($id);

            if (!$one) {
                DB::rollback();
                return $result;
            }

            DB::commit();
            $result['status'] = true;
            return $result;
        } catch (Exception $ex) {
            DB::rollBack();
            return $result;
        }
    }

    public function searchOne($filter)
    {
        $from_date = $filter['from_date'];
        $to_date   = $filter['to_date'];
        $range     = $filter['range'];
        $truck_id  = $filter['truck_id'];

        $filtered = $this->costLubeRepo->findAllActiveSkeleton();

        $filtered = FilterHelper::filterByFromDateToDate($filtered, 'created_date', $from_date, $to_date);

        $filtered = FilterHelper::filterByRangeDate($filtered, 'created_date', $range);

        if ($truck_id != 0)
            $filtered = $filtered->where('truck_id', $truck_id);

        return [
            'cost_lubes' => $filtered->values()
        ];
    }

    /** ===== VALIDATE BASIC ===== */
    public function validateInput($data)
    {
        if (!$this->validateEmpty($data))
            return ['status' => false, 'errors' => 'Dữ liệu không hợp lệ.'];

        $msgs = $this->validateLogic($data);
        return $msgs;
    }

    public function validateEmpty($data)
    {
//        if (!$data['name']) return false;
        return true;
    }

    public function validateLogic($data)
    {
        $msg_error = [];

//        $skip_id = isset($data['id']) ? [$data['id']] : [];
//
//        if ($data['code'] && $this->userRepo->existsValue('code', $data['code'], $skip_id))
//            array_push($msg_error, 'Mã đã tồn tại.');

//        if ($this->userRepo->existsValue('name', $data['name'], $skip_id))
//            array_push($msg_error, 'Tên đã tồn tại.');

        return [
            'status' => count($msg_error) > 0 ? false : true,
            'errors' => $msg_error
        ];
    }

    /** ===== VALIDATE ADVANCED ===== */
    public function validateUpdateOne($id)
    {
        return $this->validateDeactivateOne($id);
    }

    public function validateDeactivateOne($id)
    {
        $msg_error = [];

        $one = $this->costLubeRepo->findOneActive($id);
        if ($one->invoice_id != 0) {
            array_push($msg_error, 'Không thể sửa hay xóa chi phí nhớt đã xuất phiếu thanh toán.');
        }

        return [
            'status' => count($msg_error) > 0 ? false : true,
            'errors' => $msg_error
        ];
    }

    public function validateDeleteOne($id)
    {
        return $this->validateDeactivateOne($id);
    }

    /** ===== MY FUNCTION ===== */

}