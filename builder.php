<?php

use Base16\Builder;

/**
 * Base16 Builder CLI (Command Line Interface)
 */

// Source paths
$sources_list = 'sources.yaml';
$schemes_list = 'sources/schemes/list.yaml';
$templates_list = 'sources/templates/list.yaml';

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
	echo "You must run 'composer install' before using base16-builder-php.\n";
	exit(1);
}

use Base16\Builder;
use CFPropertyList\CFTypeDetector;
use CFPropertyList\CFPropertyList;
use CFPropertyList\CFData;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;
require __DIR__ . '/vendor/autoload.php';

$builder = new Builder;

// Global functions
function printerr ($message, $code = 0) {
	fwrite(STDERR, $message . "\n");
	if ($code) {
		exit($code);
	}
}

// Parse sources lists
$src_list = Builder::parse($sources_list);
$sch_list = [];
$tpl_list = [];

if (file_exists($schemes_list)) {
	$sch_list = Builder::parse($schemes_list);
}

if (file_exists($templates_list)) {
	$tpl_list = Builder::parse($templates_list);
}

/**
 * Switches between functions based on supplied argument
 */
switch (@$argv[1]) {

	/**
	* Displays a help message
	*/
	case '-h':
		echo "Base16 Builder PHP CLI\n";
		echo "https://github.com/chriskempson/base16-builder-php\n";
		break;

	/**
	* Updates template and scheme sources
	*/
	case 'update':
		$builder->updateSources($src_list, 'sources');

		// Parse source lists incase the sources have just been fetched
		if (file_exists($schemes_list)) {
			$sch_list = Builder::parse($schemes_list);
		}

		if (file_exists($templates_list)) {
			$tpl_list = Builder::parse($templates_list);
		}

		$builder->updateSources($sch_list, 'schemes');
		$builder->updateSources($tpl_list, 'templates');
		break;

	/**
	* Build all themes and schemes
	*/
	default:
		if (count($sch_list) == 0) {
			echo "Warning: Could not parse schemes or missing $schemes_list, did you do `php ${argv[0]} update`?\n";
		}
		if (count($tpl_list) == 0) {
			echo "Warning: Could not parse templates or missing $templates_list, did you do `php ${argv[0]} update`?\n";
		}


		$term_plist = null;
		$color_plist = null;
		$detector = new CFTypeDetector();
		exec('plutil -help', $out, $no_plutil);
		unset($out);

		if (@$argv[1] === '-p') {
			if (empty($argv[2])) {
				printerr("PLIST file argument is missing", 1);
				exit(1);
			}
			if ($no_plutil) {
				printerr("Option -p doesn't work without plutil!");
			}
			else {
				try {
					// PHP can't parse XML when it contains escape characters
					$temp = tempnam(sys_get_temp_dir(), "term");
					file_put_contents($temp, str_replace("\033", "\\033", file_get_contents($argv[2])));
					$term_plist = new CFPropertyList($temp);
				}
				catch (CFPropertyList\IOException $ex) {
					printerr("Cannot read PLIST file " . $argv[2], 66);
				}
				catch (Exception $ex) {
					printerr("Cannot parse PLIST file " . $argv[2], 66);
				}
				if (!$term_plist) {
					printerr("PLIST file " . $argv[2] . " is empty", 66);
				}
			}
		}

		// Loop templates repositories
		foreach ($tpl_list as $tpl_name => $tpl_url) {

			$tpl_confs = Builder::parse(
				"templates/$tpl_name/templates/config.yaml");

			// Loop template files
			foreach ($tpl_confs as $tpl_file => $tpl_conf) {

				if (@$tpl_conf['colorTemplate'] && @$tpl_conf['plistTemplate']) {
					if ($no_plutil) {
						echo "Skipping $tpl_name/$tpl_file because plutil is not installed\n";
						continue;
					}
					if (@$argv[1] !== '-p') {
						try {
							$term_plist = new CFPropertyList("templates/$tpl_name/templates/" . $tpl_conf['plistTemplate']);
						}
						catch (CFPropertyList\IOException $ex) {
							printerr("Cannot read PLIST file" . $tpl_conf['plistTemplate'], 66);
						}
						catch (Exception $ex) {
							printerr("Cannot parse PLIST file" . $tpl_conf['plistTemplate'], 66);
						}
						if (!$term_plist) {
							printerr("PLIST file" . $tpl_conf['plistTemplate'] . " is empty", 66);
						}
					}
					try {
						$color_plist = new CFPropertyList("templates/$tpl_name/templates/" . $tpl_conf['colorTemplate']);
					}
					catch (CFPropertyList\IOException $ex) {
						printerr("Cannot read PLIST file" . $tpl_conf['colorTemplate'], 66);
					}
					catch (Exception $ex) {
						printerr("Cannot parse PLIST file" . $tpl_conf['colorTemplate'], 66);
					}
					if (!$color_plist) {
						printerr("PLIST file" . $tpl_conf['colorTemplate'] . " is empty", 66);
					}
				}

				$file_path = "templates/$tpl_name/" . $tpl_conf['output'];
				$temp = tempnam(sys_get_temp_dir(), "color");

				// Remove all previous output
				array_map('unlink', glob(
					"$file_path/" . (@$tpl_conf['noPrefix'] ? '' : 'base16-') . '*' . $tpl_conf['extension']
				));

				// Loop scheme repositories
				foreach ($sch_list as $sch_name => $sch_url) {

					// Loop scheme files
					foreach (glob("schemes/$sch_name/*.yaml") as $sch_file) {

						$sch_data = Builder::parse($sch_file);
						$tpl_data = $builder->buildTemplateData($sch_data);

						$file_name = (@$tpl_conf['noPrefix'] ? '' : 'base16-')
							. (@$tpl_conf['niceFilename'] ?
								ucwords(strtr($tpl_data['scheme-slug'], '-', ' ')) : $tpl_data['scheme-slug'])
							. $tpl_conf['extension'];

						$render = $builder->renderTemplate("templates/$tpl_name/templates/$tpl_file.mustache", $tpl_data);

						if ($term_plist && $color_plist) {
							try {
								$data = Yaml::parse($render);
							}
							catch (ParseException $ex) {
								printerr("Cannot parse YAML data generated from $tpl_name/templates/$tpl_file.mustache", 1);
							}
							try {
								foreach ($data as $key => $val) {
									if (($prop = $term_plist->get(0)->get($key))) {
										if (substr($key, -5) == 'Color') {
											$color_plist->get(0)->get('$objects')->get(1)->get('NSRGB')->setValue($val);
											// This is what should work:
											// $prop->setValue($color_plist->toBinary());
											// Here is what we have to do instead, else the color is an invalid plist
											$color_plist->saveXML($temp);
											exec('plutil -convert binary1 ' . escapeshellarg($temp));
											$prop->setValue(file_get_contents($temp));
										}
										else {
											$prop->setValue($val);
										}
									}
									else {
										if (substr($key, -5) == 'Color') {
											$color_plist->get(0)->get('$objects')->get(1)->get('NSRGB')->setValue($val);
											// This is what should work:
											// $term_plist->get(0)->add($key, new CFData($color_plist->toBinary()));
											// Here is what we have to do instead, else the color is an invalid plist
											$color_plist->saveXML($temp);
											exec('plutil -convert binary1 ' . escapeshellarg($temp));
											$term_plist->get(0)->add($key, new CFData(file_get_contents($temp)));
										}
										else {
											$term_plist->get(0)->add($key, $detector->toCFType($val));
										}
									}
								}
								// Replace octal codes with escape characters
								$render = str_replace("\\033", "\033", $term_plist->toXML());
							}
							catch (Exception $ex) {
								printerr("PLIST file is not a valid Terminal profile", 1);
							}
						}
						$builder->writeFile($file_path, $file_name, $render);

						echo "Built " . $tpl_conf['output'] . "/$file_name\n";
					}
				}
				if (@$argv[1] !== '-p') {
					$term_plist = null;
				}
				$color_plist = null;
			}
		}
		break;
}
