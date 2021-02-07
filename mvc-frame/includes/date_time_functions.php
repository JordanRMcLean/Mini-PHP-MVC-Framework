<?php
// get_date_time($date) - returns a datetime object from a string, or existing object.
// format_date(DateTime) - return date string formatted in admin specified format.
// format_db_date(DateTime) - return the date as a string matching our DB format.
// format_day(DateTime) - return day as specified in admin panel.
// format_time(DateTime) - return time string as admin specified format
// format_relative_time($time) - return a more user friendly time difference. e.g 'Yesterday 5:45pm'


function get_date_time($date = null) {
	if($date instanceof DateTimeInterface) {
		return $date;
	}

	if(is_string($date)) {
		if($date = new DateTime($date)) {
			return $date;
		}

		if($date = DateTime::createFromFormat(CONFIG::DATABASE_DATE_FORMAT, $date)) {
			return $date;
		}
	}

	return new DateTime();
}



function format_date($date, $type = 'date') {

	//this allows us to safely use this func with DB results
	//without having to check if its empty every time.
	if(!$date || empty($date)) {
		return '';
	}

	$date = get_date_time($date);

	$format = 'd-m-Y';

	if($type === 'time') {
		$format = 'H:i';
	}
	elseif($type === 'day') {
		$format = 'F';
	}

	return $date->format($format);
}


function format_db_date($date, $time = false) {
	return $date->format(CONFIG::DATABASE_DATE_FORMAT . ($time ? ' H:i' : ''));
}



function format_day($date) {
	return format_date($date, 'day');
}



function format_time($date) {
	return format_date($date, 'time');
}



//return a friendly relative user-readable time.
//using the date format and time format set in admin settings.
function format_relative_time($then, $time_zone = '+00:00') {

	//this allows us to safely use this func with DB results
	//without having to check if its empty every time.
	if(!$then || empty($then)) {
		return '';
	}

	$now = new DateTime();
	$then = get_date_time($then);

	//alter the timezones to ensure timezone of both
	//is matching. Without altering DB settings.
	$tz = new DateTimeZone(preg_match('/^[+-]\d?\d:\d\d$/', $time_zone) ? $time_zone : '+00:00');
	$now->setTimezone($tz);
	$then->setTimezone($tz);

	$difference = $now->diff($then);

	//if there is more than a year difference, return the date.
	if($difference->y >= 1) {
		return format_time($then) . ' ' . format_date($then);
	}

	//if there is over a months difference.
	//but less than a year.
	if($difference->m >= 1) {
		return $then->format('F jS ') . format_time($then);
	}

	$days = $difference->d;

	//over a week ago.
	if($days > 6) {
		return $then->format('F jS ') . format_time($then);
	}

	//if there is over a days difference.
	if($days > 1) {
		return format_time($then) . $then->format(' l jS');
	}

	if($days === 1) {
		return 'Yesterday ' . format_time($then);
	}

	//if there is over 1 hours difference.
	if($difference->h > 1) {
		return $difference->h . ' hours' . ($difference->invert ? ' ago' : '');
	}

	$minutes = $difference->i;

	//this is over 1 minute but under 2 hours.
	if($minutes >= 1 || $difference->h === 1) {

		$t = $minutes .  ' minutes' . ($difference->invert ? ' ago' : '');

		//if its over 1 hour ago, add that. 1 hour xx minutes
		if($difference->h === 1) {
			$t = "1 hour $t";
		}

		return $t;
	}

	//must be less than 1 minute ago.
	return $difference->s . ' seconds' . ($difference->invert ? ' ago' : '');
}
