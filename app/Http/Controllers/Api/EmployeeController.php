<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the employees.
     */
    public function index(): JsonResponse
    {
        $employees = Employee::orderBy('employee_no', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $employees,
        ]);
    }

    /**
     * Store a newly created employee in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'hti_id' => 'nullable|string',
            'first_name' => 'required|string',
            'last_name' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'place_of_birth' => 'nullable|string',
            'sex' => 'nullable|string',
            'religion' => 'nullable|string',
            'marital_status' => 'nullable|string',
            'nationality' => 'nullable|string',
            'permanent_address' => 'nullable|string',
            'permanent_city' => 'nullable|string',
            'correspondence_address' => 'nullable|string',
            'correspondence_city' => 'nullable|string',
            'telp_no' => 'nullable|string',
            'handphone' => 'nullable|string',
            'email' => 'nullable|email',
            'ktp_no' => 'nullable|string',
            'passport_no' => 'nullable|string',
            'npwp_no' => 'nullable|string',
            'jamsostek_no' => 'nullable|string',
            'tax_status' => 'nullable|string',
            'division' => 'nullable|string',
            'employee_status' => 'nullable|string',
            'rehired_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'resign_date' => 'nullable|date',
            'temp_ext' => 'nullable|string',
            'status' => 'nullable|string',
            'is_freelance' => 'nullable|boolean',
        ]);

        DB::transaction(function() use (&$data) {
            $lastEmployee = Employee::where('employee_no', 'like', 'BS%')
                ->orderBy('employee_no', 'desc')
                ->first();

            $nextNumber = 1;
            if ($lastEmployee) {
                $lastNum = intval(substr($lastEmployee->employee_no, 2));
                $nextNumber = $lastNum + 1;
            }

            $data['employee_no'] = sprintf('BS%04d', $nextNumber);
            $data['fullname'] = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
            $data['is_freelance'] = isset($data['is_freelance']) ? (bool)$data['is_freelance'] : false;

            $employee = Employee::create($data);
            $data['id'] = $employee->id;
        });

        return response()->json([
            'success' => true,
            'message' => 'Karyawan baru berhasil ditambahkan!',
            'data' => $data,
        ], 201);
    }

    /**
     * Display the specified employee.
     */
    public function show($id): JsonResponse
    {
        $employee = Employee::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $employee,
        ]);
    }

    /**
     * Update the specified employee in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);

        $data = $request->validate([
            'hti_id' => 'nullable|string',
            'first_name' => 'required|string',
            'last_name' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'place_of_birth' => 'nullable|string',
            'sex' => 'nullable|string',
            'religion' => 'nullable|string',
            'marital_status' => 'nullable|string',
            'nationality' => 'nullable|string',
            'permanent_address' => 'nullable|string',
            'permanent_city' => 'nullable|string',
            'correspondence_address' => 'nullable|string',
            'correspondence_city' => 'nullable|string',
            'telp_no' => 'nullable|string',
            'handphone' => 'nullable|string',
            'email' => 'nullable|email',
            'ktp_no' => 'nullable|string',
            'passport_no' => 'nullable|string',
            'npwp_no' => 'nullable|string',
            'jamsostek_no' => 'nullable|string',
            'tax_status' => 'nullable|string',
            'division' => 'nullable|string',
            'employee_status' => 'nullable|string',
            'rehired_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'resign_date' => 'nullable|date',
            'temp_ext' => 'nullable|string',
            'status' => 'nullable|string',
            'is_freelance' => 'nullable|boolean',
        ]);

        $data['fullname'] = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
        $data['is_freelance'] = isset($data['is_freelance']) ? (bool)$data['is_freelance'] : false;

        $employee->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Data karyawan berhasil diperbarui!',
            'data' => $employee,
        ]);
    }

    /**
     * Remove the specified employee from storage.
     */
    public function destroy($id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $employee->delete();

        return response()->json([
            'success' => true,
            'message' => 'Karyawan berhasil dihapus!',
        ]);
    }
}
