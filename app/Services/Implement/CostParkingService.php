<?php

namespace App\Services\Implement;

use App\Services\AuthServiceInterface;
use App\Services\CostParkingServiceInterface;
use App\Repositories\CostParkingRepositoryInterface;
use App\Repositories\TruckRepositoryInterface;
use App\Common\Helpers\DateTimeHelper;
use App\Common\Helpers\FilterHelper;
use DB;
use League\Flysystem\Exception;

class CostParkingService implements CostParkingServiceInterface
{
    private $user;
    private $table_name;

    protected $authService, $costParkingRepo, $truckRepo;

    public function __construct(AuthServiceInterface $authService
        , CostParkingRepositoryInterface $costParkingRepo
        , TruckRepositoryInterface $truckRepo)
    {
        $this->authService     = $authService;
        $this->costParkingRepo = $costParkingRepo;
        $this->truckRepo       = $truckRepo;

        $jwt_data = $this->authService->getCurrentUser();
        if ($jwt_data['status']) {
            $user_data = $this->authService->getInfoCurrentUser($jwt_data['user']);
            if ($user_data['status'])
                $this->user = $user_data['user'];
        }

        $this->table_name = 'cost_parking';
    }

    public function readAll()
    {
        $all = $this->costParkingRepo->findAllActiveSkeleton();

        $trucks = $this->truckRepo->findAllActiveSkeleton();

        return [
            'cost_parkings' => $all,
            'trucks'        => $trucks
        ];
    }

    public function readOne($id)
    {
        $one = $this->costParkingRepo->findOneActiveSkeleton($id);

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
                'code'      => $this->costParkingRepo->generateCode('COSTPARKING'),
                'type'      => 'PARKING',
                'vat'       => 0,
                'after_vat' => $data['after_vat'],

                'fuel_id'       => null,
                'quantum_liter' => null,
                'refuel_date'   => null,

                'checkin_date'  => DateTimeHelper::toStringDateTimeClientForDB($data['checkin_date']),
                'checkout_date' => DateTimeHelper::toStringDateTimeClientForDB($data['checkout_date']),
                'total_day'     => $data['total_day'],

                'note'         => $data['note'],
                'created_by'   => $this->user->id,
                'updated_by'   => 0,
                'created_date' => date('Y-m-d'),
                'updated_date' => null,
                'active'       => true,
                'truck_id'     => $data['truck_id'],
                'invoice_id'   => 0
            ];

            $one = $this->costParkingRepo->createOne($i_one);

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

            $one = $this->costParkingRepo->findOneActive($data['id']);

            $i_one = [
                'type'      => 'PARKING',
                'after_vat' => $data['after_vat'],

                'checkin_date'  => DateTimeHelper::toStringDateTimeClientForDB($data['checkin_date']),
                'checkout_date' => DateTimeHelper::toStringDateTimeClientForDB($data['checkout_date']),
                'total_day'     => $data['total_day'],

                'note'         => $data['note'],
                'updated_by'   => $this->user->id,
                'updated_date' => date('Y-m-d'),
                'active'       => true,
                'truck_id'     => $data['truck_id']
            ];

            $one = $this->costParkingRepo->updateOne($one->id, $i_one);

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

            $one = $this->costParkingRepo->deactivateOne($id);

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

            $one = $this->costParkingRepo->destroyOne($id);

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

        $filtered = $this->costParkingRepo->findAllActiveSkeleton();

        $filtered = FilterHelper::filterByFromDateToDate($filtered, 'created_date', $from_date, $to_date);

        $filtered = FilterHelper::filterByRangeDate($filtered, 'created_date', $range);

        if ($truck_id != 0)
            $filtered = $filtered->where('truck_id', $truck_id);

        return [
            'cost_parkings' => $filtered->values()
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

        $one = $this->costParkingRepo->findOneActive($id);
        if ($one->invoice_id != 0) {
            array_push($msg_error, 'Không thể sửa hay xóa chi phí đậu bãi đã xuất phiếu thanh toán.');
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