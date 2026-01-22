<?php

namespace App\Helpers;


use Illuminate\Database\Eloquent\Model;

class DBHelper extends Model
{
    public static function getERPDBLink(): string
    {
        $serverIP = $_SERVER['SERVER_ADDR'];
        if ($serverIP == '10.10.47.61') {
            return 'HRP2EBS';
        } elseif ($serverIP == '10.10.47.100') {
            return 'HRP2EBS';
        }
        return 'HRP2EBS';
    }

    public static function getEOfficeLink(): string
    {
        $serverIP = $_SERVER['SERVER_ADDR'];
        if ($serverIP == '10.10.47.61') {
            return 'https://eoffice.nssf.go.tz:8081/';
        } elseif ($serverIP == '10.10.47.100') {
            return 'https://demo-eoffice.nssf.go.tz:8082/';
        }
        return 'https://demo-eoffice.nssf.go.tz:8082/';
    }

    public static function getHrmsApi(): string
    {
        $serverIP = $_SERVER['SERVER_ADDR'];
        if ($serverIP == '10.10.47.61') {
            return 'https://hrmas-api.nssf.go.tz/api';
        } elseif ($serverIP == '10.10.47.158') {
            return 'https://hrms-new-api.nssf.go.tz/api';
        }
        return 'https://hrms-new-api.nssf.go.tz/api';
    }

    public static function getIctmsLink(): string
    {
        $serverIP = $_SERVER['SERVER_ADDR'];
        if ($serverIP == '10.10.47.61') {
            return 'https://ictmspre-api.nssf.go.tz/';
        } elseif ($serverIP == '10.10.47.100') {
            return 'https://ictmspre-api.nssf.go.tz/';
        }
        return 'https://ictmspre-api.nssf.go.tz/';
    }

    public static function getBudgetLink(): string
    {
        $serverIP = $_SERVER['SERVER_ADDR'];
        if ($serverIP == '10.10.47.61') {
            return 'https://budget-api.nssf.go.tz';
        } elseif ($serverIP == '10.10.47.100') {
            return 'https://planb-pre.nssf.go.tz/api/web';
        }
        return 'https://planb-pre.nssf.go.tz/api/web';
    }

    public static function getFMSLink(): string
    {
        $serverIP = $_SERVER['SERVER_ADDR'];
        if ($serverIP == '10.10.47.61') {
            return 'https://fms-api.nssf.go.tz/api';
        } elseif ($serverIP == '10.10.47.100') {
            return 'https://imprestpre-api.nssf.go.tz/api';
        }
        return 'https://payments-api-pre.nssf.go.tz/api';
    }

    public static function getSystemId(string $system, string $module)
    {
        $serverIP = $_SERVER['SERVER_ADDR'];

        $map = [
            '10.10.47.61' => [
                'Budget' => [
                    'Medical Management' => 22, 
                    'Expense Management' => 11
                ],
                'FMS' => [
                    'Medical Management' => 102, 
                    'Expense Management' => 101
                ],
            ],
            '10.10.47.100' => [
                'Budget' => [
                    'Medical Management' => 100005, 
                    'Expense Management' => 100006
                ],
                'FMS' => [
                    'Medical Management' => 123, 
                    'Expense Management' => 122
                ],
            ],
        ];

        return $map[$serverIP][$system][$module] ?? null;
    }

    public static function getSystemPassphrase(string $system, string $module)
    {
        $serverIP = $_SERVER['SERVER_ADDR'];

        $map = [
            '10.10.47.61' => [
                'Budget' => [
                    'Medical Management' => 'cNDw#um2*ZUC9tGW', 
                    'Expense Management' => '3;JQQ}!|e)sCtEe|'
                ],
                'FMS' => [
                    'Medical Management' => 'mdc146$p455wd', 
                    'Expense Management' => 'hrm46$p455wd'
                ],
            ],
            '10.10.47.100' => [
                'Budget' => [
                    'Medical Management' => 'cNDw#um2*ZUC9tGW', 
                    'Expense Management' => '3;JQQ}!|e)sCtEe|'
                ],
                'FMS' => [
                    'Medical Management' => '$zZ]+&HS>K74ZHM', 
                    'Expense Management' => 'z6#uwTqWCcsnyGv&'
                ],
            ],
        ];

        return $map[$serverIP][$system][$module] ?? null;
    }
}
