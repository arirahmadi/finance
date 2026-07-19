<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $table = 'emp_master';

    protected $fillable = [
        'employee_no',
        'hti_id',
        'first_name',
        'last_name',
        'fullname',
        'date_of_birth',
        'place_of_birth',
        'sex',
        'religion',
        'marital_status',
        'nationality',
        'permanent_address',
        'permanent_city',
        'correspondence_address',
        'correspondence_city',
        'telp_no',
        'handphone',
        'email',
        'ktp_no',
        'passport_no',
        'npwp_no',
        'jamsostek_no',
        'tax_status',
        'division',
        'employee_status',
        'rehired_date',
        'start_date',
        'end_date',
        'resign_date',
        'temp_ext',
        'status',
        'is_freelance'
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'rehired_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'resign_date' => 'date',
        'is_freelance' => 'boolean'
    ];
}
