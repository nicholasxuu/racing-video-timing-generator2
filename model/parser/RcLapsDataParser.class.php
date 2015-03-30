<?php

// [WIP] not yet finished. Won't run correctly yet.

/**
 * parse RcLaps formatted result data file into RaceResult object
 *
 * @author Nicholas Xu
 *
 */


namespace Model\Parser;
use Model\Event;

class RcLapsDataParser
{
	/** @var Event\TotalResult */
	public $totalResult;

	/** @var string[] */
	public $fileContent;

	/**
	 * Get array of car# based on driver section's headerline.
	 * @param string $line
	 * @return array
	 */
	function get_driver_section_header_array($line) {
		$driSectLine = trim($line);
		$driSectLine = trim($driSectLine, "_");
		$driSectLine = str_replace(" ", "", $driSectLine);
		$driSectArr = preg_split('/_+/u', $driSectLine);
		return $driSectArr;
	}

	/**
	 * Get array of car# based on lap section's headerline.
	 * @param string $line
	 * @return array
	 */
	function get_lap_section_header_array($line) {
		$lapSectLine = trim($line);
		$lapSectLine = trim($lapSectLine, "_");
		$lapSectLine = str_replace(" ", "", $lapSectLine);
		$lapSectArr = preg_split('/_+/u', $lapSectLine);
		return $lapSectArr;
	}

	/**
	 * Get the index of delimiters in a line of lap section headerline,
	 * Later laptime lines will be in the same index format.
	 * @param string $line
	 * @param string $delimiter
	 * @return array
	 */
	function get_lap_section_index($line, $delimiter = " ") {
		$last_i = 0;
		$lapSectIndex = array();
		while (($i = mb_strpos($line, $delimiter, $last_i)) !== false) {
			$last_i = $i + 1;
			array_push($lapSectIndex, $i);
		}
		return $lapSectIndex;
	}

	/**
	 * Convert column name in data to field name in event data.
	 * @param string $input
	 * @return bool
	 */
	function getRaceDriverDataMapping($input) {
		$raceDriverDataMapping = array(
			"Driver" 	=> "name",
			"FastLap" 	=> "bestLap",
			"Laps" 		=> "totalLaps",
			"RaceTime" 	=> "totalTime",
			"Car#" 		=> "carNum",
			"Behind" 	=> "behind",
		);

		if (isset($raceDriverDataMapping[$input])) {
			return $raceDriverDataMapping[$input];
		} else {
			return false;
		}
	}

	/**
	 * Default constructor
	 * @param string[] $fileContent
	 */
	function __construct($fileContent) {

		$this->fileContent = $fileContent;
		$this->totalResult = new Event\TotalResult();

		$section = "start";
		$driSectArr = array();
		$lapSectArr = array();
		$lapSectIndex = array();
		$type2input = false;

		$currRaceId = 0;
		$finish_position = 0; // set variable

		// process input file content
		foreach ($fileContent as $line)
		{
			if (trim($line) != "")
			{
				$originalLine = $line;
				$line = trim($line);

				if (mb_strstr($line, "Race Results for") !== false)
				{

				}
			}

			if (trim($line) != "" || $section === "lap_results")
			{
				$originalLine = $line;
				$line = trim($line);
				if (mb_strstr($line, "Race Results for") !== false) // race title
				{
					// start new race
					$section = "start";

					$currRaceName = str_replace("Race Results for ", "", $line);
					//$total_data[$curr_race] = array();
					$currRaceId = (string) $this->totalResult->addRace($currRaceName);
				}
				else if (preg_match('/.*Position.*Laps.*/u', $line))
				{

					$section = "race_results_start";

					$driSectArr = $this->get_driver_section_header_array($line);

					$finish_position = 1;

				}
				else if (preg_match('/Race Laptimes.*/u', $line))
				{
					$section = "lap_results";

					$lapSectArr = $this->get_lap_section_header_array($originalLine);

					$lapSectIndex = $this->get_lap_section_index($originalLine);
				}
				else if ($section === "lap_results" && preg_match('/----/u', $line))
				{
					$section = "race_end";
				}
				else if ($section === "race_results_start")
				{
					$elementArr = preg_split('/\s+/u', $line);
					$i = 1;
					$name = true;
					$currData = new Event\RaceDriverData();

					$driverName = "";
					foreach ($elementArr as $e)
					{
						// Assuming all start with driver's name
						if ($name && (! preg_match('/#\d/u', $e)))
						{
							$driverName .= " " . $e;
						}
						else
						{
							$name = false;

							$mapped = $this->getRaceDriverDataMapping($driSectArr[$i]);
							if (!empty($mapped)) {
								$currData->$mapped = $e;
							}

							$i++;
						}
					}

					$currData->name = trim($driverName);
					$currData->carNum = str_replace("#", "", $currData->carNum);
					$currData->finishPosition = $finish_position;
					$finish_position++;
					$this->totalResult->$currRaceId->addDriver($currData);
				}
				else if ($section === "lap_results")
				{
					if (empty($line)) {
						$type2input = true;
					}
					else
					{
						$first_ending_splitter_index = 1; // first delimiter is in the beginning of the line, so we start from 1. this index is fixed for $lapSectArr
						for ($i = $first_ending_splitter_index; $i < count($lapSectIndex); $i++) {

							$plap_section = mb_substr($originalLine, $lapSectIndex[$i-1], $lapSectIndex[$i] - $lapSectIndex[$i-1]);
							$plap_section = trim($plap_section);

							if (!empty($plap_section)) {
								$plap_section = explode("/", $plap_section);
								//var_dump($plap_section);
								$this->totalResult->raceResultList[$currRaceId]->$lapSectArr[$i-1]->lapData->addLapTime(floatval($plap_section[1]));
							}
						}
					}
				}
				else if ($section === "race_end")
				{
					// when new race starts, finish last race first
					if ($type2input) { // if it's second type of input, need to remove un-needed lines of data
						foreach ($lapSectArr as $carNum) {
							if (!is_null($this->totalResult->$currRaceId->$carNum)) {
								$this->totalResult->$currRaceId->$carNum->lapData->removeEveryOtherLapTime();
							}
						}
					}

					$section = "end"; // 4 means end of one race
				}
			}

		}

		foreach($this->totalResult->raceResultList as $currRaceId => $result) {
			$this->totalResult->raceResultList[$currRaceId]->cleanUpDriver();
		}

		unset($this->fileContent);
	}
}