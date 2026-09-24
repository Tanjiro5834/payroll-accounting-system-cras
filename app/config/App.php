<?php
namespace App\Config;

class App{
    const NAME = 'Coronacion Payroll and Accounting System';
    const TIMEZONE = 'Asia/Manila';
    const WORK_START = '08:00:00';       
    const WORK_END = '17:00:00';         
    const LUNCH_START = '12:00:00';
    const LUNCH_END = '13:00:00';
    const REGULAR_HOURS_PER_DAY = 8;
    const LATE_THRESHOLD_MINUTES = 15;
    const WORK_DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
}