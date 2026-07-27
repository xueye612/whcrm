<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
namespace think\helper;
class Time
{
    protected static function getEndTime($end, $isTimestamp = true)
    {
        $endTime = strtotime($end) + 86400;
        return $isTimestamp ? $endTime : $endTime - 1;
    }
    protected static function getStartTime($start, $isTimestamp = true)
    {
        return $isTimestamp ? strtotime($start) : strtotime($start);
    }
    public static function today($isTimestamp = true)
    {
        $start = date('Y-m-d');
        return [$isTimestamp ? strtotime($start) : $start, $isTimestamp ? time() : date('Y-m-d H:i:s')];
    }
    public static function yesterday($isTimestamp = true)
    {
        $start = date('Y-m-d', strtotime('-1 day'));
        $end = date('Y-m-d', time());
        return [$isTimestamp ? strtotime($start) : $start, $isTimestamp ? strtotime($end) - 1 : $end];
    }
    public static function week($isTimestamp = true)
    {
        $start = date('Y-m-d', (time() - ((date('w') == 0 ? 7 : date('w')) - 1) * 24 * 3600));
        $end = date('Y-m-d', (time() + (7 - (date('w') == 0 ? 7 : date('w'))) * 24 * 3600));
        return [$isTimestamp ? strtotime($start) : $start, $isTimestamp ? strtotime($end) + 86399 : $end];
    }
    public static function lastWeek($isTimestamp = true)
    {
        $start = date('Y-m-d', (time() - ((date('w') == 0 ? 7 : date('w')) - 1) * 24 * 3600 - 7 * 24 * 3600));
        $end = date('Y-m-d', (time() - (date('w') == 0 ? 7 : date('w')) * 24 * 3600));
        return [$isTimestamp ? strtotime($start) : $start, $isTimestamp ? strtotime($end) + 86399 : $end];
    }
    public static function month($isTimestamp = true)
    {
        $start = date('Y-m-01');
        $end = date('Y-m-t');
        return [$isTimestamp ? strtotime($start) : $start, $isTimestamp ? strtotime($end) + 86399 : $end];
    }
    public static function lastMonth($isTimestamp = true)
    {
        $start = date('Y-m-01', strtotime('-1 month'));
        $end = date('Y-m-t', strtotime('-1 month'));
        return [$isTimestamp ? strtotime($start) : $start, $isTimestamp ? strtotime($end) + 86399 : $end];
    }
    public static function year($isTimestamp = true)
    {
        $start = date('Y-01-01');
        $end = date('Y-12-31');
        return [$isTimestamp ? strtotime($start) : $start, $isTimestamp ? strtotime($end) + 86399 : $end];
    }
    public static function lastYear($isTimestamp = true)
    {
        $start = date('Y-01-01', strtotime('-1 year'));
        $end = date('Y-12-31', strtotime('-1 year'));
        return [$isTimestamp ? strtotime($start) : $start, $isTimestamp ? strtotime($end) + 86399 : $end];
    }
    public static function daysAgo($n, $isTimestamp = true)
    {
        $start = date('Y-m-d', time() - 86400 * $n);
        $end = date('Y-m-d');
        return [$isTimestamp ? strtotime($start) : $start, $isTimestamp ? time() : $end];
    }
}
