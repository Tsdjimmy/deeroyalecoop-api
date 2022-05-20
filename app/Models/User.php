<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
//use Laravel\Sanctum\HasApiTokens;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'first_name',
        'last_name',
        'middle_name',
        'id_card_number',
        'branch',
        'residential_address',
        'nickname',
        'sex',
        'marital_status',
        'office_address',
        'state_of_origin',
        'local_government',
        'phone',
        'bvn',
        'sms_alert',
        'id_type',
        'nok',
        'nok_address',
        'nok_residential_status',
        'nok_sex',
        'nok_phone',
        'nok_relationship'


    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
}
