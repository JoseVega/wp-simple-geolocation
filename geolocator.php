<?php

if (!defined('ABSPATH')) {
	exit;
}

use GeoIp2\Database\Reader;


/**
 * GeoLocate class.
 * 
 * Easy access to geolocation data from ip.
 *
 * @author Jose Vega
 */
class VG_GeoLocator {

	static $db_reader;
	static $db_file_name;
	static $db_file_path;
	public $ip;
	public $user_record;
	static $db_url;

		
	function get_user_ip() {
		return ($_SERVER["HTTP_CF_CONNECTING_IP"]) ? $_SERVER["HTTP_CF_CONNECTING_IP"] : $_SERVER['REMOTE_ADDR'];
	}
	public function __construct($ip = false, $lite_init = false) {
		self::$db_file_name = 'GeoLite2-City.mmdb';
		self::$db_file_path = trailingslashit(dirname(__FILE__)) . self::$db_file_name;
		self::$db_url = 'http://geolite.maxmind.com/download/geoip/database/GeoLite2-City.mmdb.gz';

		if (!file_exists(self::$db_file_path)) {
			return;
		}
		if ($ip == false) {
			$this->ip = $this->get_user_ip();
		} elseif (filter_var($ip, FILTER_VALIDATE_IP)) {
			$this->ip = $ip;
		}

																													

		if (!$lite_init) {
			$this->start_geolocate($this->ip);
		}
	}

	function download_database() {

		set_time_limit(0);

		if (!current_user_can('manage_options')) {
			return;
		}

		$file_name = 'tmp_db.gz';
		$file_full_path = self::$db_file_path;
		if (file_exists($file_full_path)) {
			unlink($file_full_path);
		}
		$url = self::$db_url;
		$file_instance = fopen($file_name, "w");
// Get The Zip File From Server
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_FAILONERROR, true);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 500);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_FILE, $file_instance);
		$page = curl_exec($ch);
		$out = true;
		if (!$page) {
			$out = new WP_Error('vg_geolocator', curl_error($ch));
		}
		curl_close($ch);

		return $out;
	}

	function uncompress_database() {

		set_time_limit(0);

		if (!current_user_can('manage_options')) {
			return;
		}

		$file_name = 'tmp_db.gz';

		if (!file_exists(trailingslashit(dirname(__FILE__)) . $file_name)) {
			return;
		}

// Raising this value may increase performance
		$buffer_size = 4096; // read 4kb at a time
		$out_file_name = self::$db_file_name;
// Open our files (in binary mode)
		$file = gzopen($file_name, 'rb');
		$out_file = fopen($out_file_name, 'wb');
// Keep repeating until the end of the input file
		while (!gzeof($file)) {
// Read buffer-size bytes
// Both fwrite and gzread and binary-safe
			fwrite($out_file, gzread($file, $buffer_size));
		}
// Files are done, close files
		fclose($out_file);
		gzclose($file);


		unlink(trailingslashit(dirname(__FILE__)) . $file_name);
	}

	function start_geolocate($ip) {
		self::$db_reader = new Reader(self::$db_file_path);
		$this->user_record = self::$db_reader->city($ip);
	}

	function get_city() {
		return $this->user_record->city->name;
	}

	function get_coordinates() {
		$coor = array(
			'lat' => $this->user_record->location->latitude,
			'long' => $this->user_record->location->longitude
		);
		return $coor;
	}

	function get_country($format = 'isoCode') {
		if ($format === 'isoCode') {
			return $this->user_record->country->isoCode;
		} elseif ($format === 'name') {
			return $this->user_record->country->name;
		}
	}

}

/*
 * Usage:
  $test = new VG_GeoLocator('190.87.185.12');
  var_dump($test->get_city());
  $test2 = new GeoLocate('128.101.101.101');
  var_dump($test2->get_city());
 * */
