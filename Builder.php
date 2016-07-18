<?php
/**
 * Base16 Builder CLI (Command Line Interface)
 */

// Source paths
$sources_list = 'sources.yaml';
$schemes_list = 'sources/schemes/list.yaml';
$templates_list = 'sources/templates/list.yaml';

$loader = require __DIR__ . '/vendor/autoload.php';

use Base16\Builder;
use CFPropertyList\CFTypeDetector;
use CFPropertyList\CFPropertyList;
use CFPropertyList\CFData;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

$builder = new Builder;

// Global functions
function printerr ($message, $code = 0) {
	fwrite(STDERR, $message . "\n");
	if ($code) {
		exit($code);
	}
}

// Parse sources lists
$src_list = $builder->parse($sources_list);
if (file_exists($schemes_list)) $sch_list = $builder->parse($schemes_list);
if (file_exists($schemes_list)) $tpl_list = $builder->parse($templates_list);

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
			$sch_list = $builder->parse($schemes_list);
		}

		if (file_exists($schemes_list)) {
			$tpl_list = $builder->parse($templates_list);
		}

		$builder->updateSources($sch_list, 'schemes');
		$builder->updateSources($tpl_list, 'templates');
		break;

	/**
	* Build all themes and schemes
	*/
	default:

		$term_plist = null;
		$color_plist = null;
		$detector = new CFTypeDetector();
		exec('plutil -help', $out, $no_plutil);

		if (@$argv[1] === '-p') {
			if (empty($argv[2])) {
				printerr("PLIST file argument is missing", 1);
			}
			if ($no_plutil) {
				printerr("Option -p doesn't work without plutil!");
			}
			else {
				try {
					$term_plist = new CFPropertyList($argv[2]);
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

			$tpl_confs = $builder->parse("templates/$tpl_name/templates/config.yaml");

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

				// Remove all previous output
				array_map('unlink', glob(
					"$file_path/" . (@$tpl_conf['noPrefix'] ? '' : 'base16-') . '*' . $tpl_conf['extension']
				));

				// Loop scheme repositories
				foreach ($sch_list as $sch_name => $sch_url) {

					// Loop scheme files
					foreach (glob("schemes/$sch_name/*.yaml") as $sch_file) {

						$sch_data = $builder->parse($sch_file);
						$tpl_data = $builder->buildTemplateData($sch_data);

						$file_name = (@$tpl_conf['noPrefix'] ? '' : 'base16-')
							. (@$tpl_conf['niceFilename'] ? ucwords(strtr($tpl_data['scheme-slug'], '-', ' ')) : $tpl_data['scheme-slug'])
							. $tpl_conf['extension'];

						$render = $builder->renderTemplate(
							"templates/$tpl_name/templates/$tpl_file.mustache",
							 $tpl_data);

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
											$color_plist->saveXML("color.plist");
											exec('plutil -convert binary1 color.plist');
											$prop->setValue(file_get_contents("color.plist"));
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
											$color_plist->saveXML("color.plist");
											exec('plutil -convert binary1 color.plist');
											$term_plist->get(0)->add($key, new CFData(file_get_contents("color.plist")));
										}
										else {
											$term_plist->get(0)->add($key, $detector->toCFType($val));
										}
									}
								}
								// Clean up plist file that shouldn't be necessary in the first place
								@unlink("color.plist");
								$render = $term_plist->toXML();
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
