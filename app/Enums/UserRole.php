<?php

namespace App\Enums;

enum UserRole:string{
    case TENANTADMIN = 'tenant_admin';
    case STAFF =  'staff';
    case READONLY = 'read_only';
}