<?php

namespace App\Enums;

enum BookingStatus:string {

case PENDING='pending';
case CONFIRMED='confirmed';
case ACTIVE='active';

case COMPLETED='completed';
case CANCELLED= 'cancelled';

}