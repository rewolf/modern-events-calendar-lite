<?php
/** no direct access **/
defined('MECEXEC') or die();

/**
 * Webnus MEC schedule class.
 * @author Webnus <info@webnus.biz>
 */
class MEC_schedule extends MEC_base
{
    private $db;
    private $main;
    private $render;

    public function __construct()
    {
        $this->db = $this->getDB();
        $this->main = $this->getMain();
        $this->render = $this->getRender();
    }

    public function cron()
    {
        // Get All Events
        $events = $this->main->get_events();

        // Append Schedule for Events
        foreach($events as $event) $this->append($event->ID, 50);
    }

    public function reschedule($event_id, $maximum = 200)
    {
        // Clean Current Schedule
        $this->clean($event_id);

        // Event Start Date
        $start = get_post_meta($event_id, 'mec_start_date', true);

        if(trim($start) == '' or $start == '0000-00-00') $start = date('Y-m-d', strtotime('-1 Year'));
        else $start = date('Y-m-d', strtotime('-1 Day', strtotime($start)));

        // New Schedule
        $this->schedule($event_id, $start, $maximum);
    }

    public function append($event_id, $maximum = 25)
    {
        // Get Start Date
        $start = $this->time($event_id, 'max', 'Y-m-d');

        // Append Schedule
        $this->schedule($event_id, $start, $maximum);
    }

    public function schedule($event_id, $start, $maximum = 100)
    {
        // Get event dates
        $dates = $this->render->dates($event_id, NULL, $maximum, $start);

        // No new date found!
        if(!is_array($dates) or (is_array($dates) and !count($dates))) return false;

        foreach($dates as $date)
        {
            $sd = $date['start']['date'];
            $ed = $date['end']['date'];

            $start_hour = isset($date['start']['hour']) ? sprintf("%02d", $date['start']['hour']) : '08';
            $start_minute = isset($date['start']['minutes']) ? sprintf("%02d", $date['start']['minutes']) : '00';
            $start_ampm = isset($date['start']['ampm']) ? $date['start']['ampm'] : 'AM';

            if($start_hour == '00')
            {
                $start_hour = '';
                $start_minute = '';
                $start_ampm = '';
            }

            $start_time = $start_hour.':'.$start_minute.' '.$start_ampm;

            $end_hour = isset($date['end']['hour']) ? sprintf("%02d", $date['end']['hour']) : '06';
            $end_minute = isset($date['end']['minutes']) ? sprintf("%02d", $date['end']['minutes']) : '00';
            $end_ampm = isset($date['end']['ampm']) ? $date['end']['ampm'] : 'PM';

            if($end_hour == '00')
            {
                $end_hour = '';
                $end_minute = '';
                $end_ampm = '';
            }

            $end_time = $end_hour.':'.$end_minute.' '.$end_ampm;

            $st = strtotime(trim($date['start']['date'].' '.$start_time, ' :'));
            $et = strtotime(trim($date['end']['date'].' '.$end_time, ' :'));

            $date_id = $this->db->select("SELECT `id` FROM `#__mec_dates` WHERE `post_id`='$event_id' AND `dstart`='$sd' AND `dend`='$ed'", 'loadResult');

            // Add new Date
            if(!$date_id) $this->db->q("INSERT INTO `#__mec_dates` (`post_id`,`dstart`,`dend`,`tstart`,`tend`) VALUES ('$event_id','$sd','$ed','$st','$et');");
            // Update Existing Record
            else $this->db->q("UPDATE `#__mec_dates` SET `tstart`='$st', `tend`='$et' WHERE `id`='$date_id';");
        }

        return true;
    }

    public function clean($event_id)
    {
        // Remove All Scheduled Dates
        return $this->db->q("DELETE FROM `#__mec_dates` WHERE `post_id`='$event_id'");
    }

    public function time($event_id, $type = 'max', $format = 'Y-m-d')
    {
        $time = $this->db->select("SELECT ".(strtolower($type) == 'min' ? 'MIN' : 'MAX')."(`tstart`) FROM `#__mec_dates` WHERE `post_id`='$event_id'", 'loadResult');
        if(!$time) $time = time();

        return date($format, $time);
    }

    public function get_reschedule_maximum($repeat_type)
    {
        if($repeat_type == 'daily') return 370;
        elseif($repeat_type == 'weekday') return 270;
        elseif($repeat_type == 'weekend') return 150;
        elseif($repeat_type == 'certain_weekdays') return 150;
        elseif($repeat_type == 'advanced') return 120;
        elseif($repeat_type == 'weekly') return 100;
        elseif($repeat_type == 'monthly') return 50;
        elseif($repeat_type == 'yearly') return 25;
        else return 50;
    }
}