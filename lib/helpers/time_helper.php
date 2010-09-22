<?php
/*
	time and date-related helpers
*/

namespace HalfMoon;

class TimeHelper extends Helper {
	/* reports the approximate distance between two times */
	public function distance_of_time_in_words($from_time, $to_time = 0,
	$include_seconds = false) {
		if (!in_array(get_class($from_time), array("DateTime",
		"ActiveRecord\\DateTime")))
			$from_time = new \DateTime($from_time);
		
		if (!in_array(get_class($to_time), array("DateTime",
		"ActiveRecord\\DateTime")))
			$to_time = new \DateTime($to_time);

		$seconds_diff = intval($to_time->format("U")) -
			intval($from_time->format("U"));

		$distance_in_minutes = round(abs($seconds_diff / 60));
		$distance_in_seconds = round(abs($seconds_diff));

		if (Utils::is_or_between($distance_in_minutes, array(0, 1))) {
			if (!$include_seconds)
				return ($distance_in_minutes == 0 ? "less than 1 minute" :
					$distance_in_minutes . " minute"
					. ($distance_in_minutes == 1 ? "" : "s"));

			if (Utils::is_or_between($distance_in_seconds, array(0, 4)))
				return "less than 5 seconds";
			elseif (Utils::is_or_between($distance_in_seconds, array(5, 9)))
				return "less than 10 seconds";
			elseif (Utils::is_or_between($distance_in_seconds, array(10, 19)))
				return "less than 20 seconds";
			elseif (Utils::is_or_between($distance_in_seconds, array(20, 39)))
				return "less than half a minute";
			else
				return "1 minute";
		}

		elseif (Utils::is_or_between($distance_in_minutes, array(2, 44)))
			return $distance_in_minutes . " minutes";

		elseif (Utils::is_or_between($distance_in_minutes, array(45, 89)))
			return "about 1 hour";

		elseif (Utils::is_or_between($distance_in_minutes, array(90, 1439)))
			return "about " . round($distance_in_minutes / 60) . " hours";

		elseif (Utils::is_or_between($distance_in_minutes, array(1440, 2879)))
			return "about 1 day";

		elseif (Utils::is_or_between($distance_in_minutes, array(2880, 43199)))
			return "about " . round($distance_in_minutes / 1440) . " days";

		elseif (Utils::is_or_between($distance_in_minutes, array(43200, 86399)))
			return "about 1 month";

		elseif (Utils::is_or_between($distance_in_minutes, array(86400, 525599)))
			return "about " . round($distance_in_minutes / 43200) . " months";

		elseif (Utils::is_or_between($distance_in_minutes, array(525600, 1051199)))
			return "about 1 year";

		else
			return "over " . round($distance_in_minutes / 525600) . " years";
	}

	/* like distance_of_time_in_words, but where to_time is fixed to now */
	public function time_ago_in_words($from_time, $include_seconds = false) {
		$now = new \DateTime("now");

		if (in_array(get_class($from_time), array("DateTime",
		"ActiveRecord\\DateTime")))
			@$now->setTimezone($from_time->getTimezone());
		elseif (is_int($from_time))
			$from_time = new \DateTime($from_time);

		return TimeHelper::distance_of_time_in_words($from_time, $now,
			$include_seconds);
	}
}

?>
