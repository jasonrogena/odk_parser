<?php
class LogHandler {
	
	private $TAG = "log.php";
	private $ROOT = "../";
	private $settingsDir;
	private $logs;
	private $timeZone;
	
	public function __construct() {
		$this->settingsDir = $this->ROOT."config/main.ini";
		$this->getSettings();
	}
	
	public function log($level, $tag, $message) {
		if( $level <= $this->logs['log_level']  && $level > 0) {
			$logFile = $this->ROOT.$this->logs['log_dir'].$this->logs[$level];
			$fileHandler = fopen($logFile, 'a') or die($this->TAG.": unable open log file, exiting.");
			
			$message = "[".$this->getTime()."] [".$tag."] ".$message."\n";
			fwrite($fileHandler, $message) or die($this->TAG.": unable write to log file, exiting.");
			fclose($fileHandler);
		}
	}
	
	private function getSettings() {
		if(file_exists($this->settingsDir)) {
			$settings = parse_ini_file($this->settingsDir);
			$this->timeZone = $settings['time_zone'];
                        
			//get log settings
			if(file_exists($this->ROOT."config/".$settings['log_settings'])) {
				$this->logs = parse_ini_file($this->ROOT."config/".$settings['log_settings']);
			}
			else {
				die($this->TAG.": unable to load settings, exiting.");
			}
		}
		else {
			
		}
	}
	
	private function getTime($format = 'l\, jS F Y H:i:s') {
		$time = new DateTime('now', new DateTimeZone($this->timeZone));
		return $time->format($format);
	}
}
?>
