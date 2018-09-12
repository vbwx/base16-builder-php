<?php
/**
 * A PHP implementation of a Base16 Theme Builder
 * Chris Kempson (http://chriskempson.com)
 *
 * Follows the conventions at
 *     http://chriskempson.com/projects/base16/builder.md
 */

namespace Base16;

use Symfony\Component\Yaml\Yaml;
use Mexitek\PHPColors\Color;
use Cocur\Slugify\Slugify;

class Builder
{
	private $slugify;

	/**
	 * Parses a YAML file
	 */
	static public function parse($path)
	{
		return Yaml::parse( file_get_contents($path) );
	}

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->slugify = new Slugify();
	}

	/**
	 * Uses git to fetch template or scheme sources
	 */
	public function fetchSources($url_list, $path)
	{
		foreach ($url_list as $name => $url) {
			if (!file_exists("$path/$name")) {
				exec("git clone $url $path/$name\n");
			}
		}
	}

	/**
	 * Uses git to update template or scheme sources
	 */
	public function updateSources($url_list, $path)
	{
		foreach ($url_list as $name => $url) {
			echo "\n-- $path/$name\n";

			if (file_exists("$path/$name")) {
				echo exec("git -C $path/$name pull\n") . "\n";
			} else {
				$this->fetchSources($url_list, $path);
			}
		}
	}

	/**
	 * Renders a template using Mustache
	 */
	public function renderTemplate($path, $template_data)
	{
		$mustache = new \Mustache_Engine();
		$tpl = $mustache->loadTemplate($this->readFile($path));
		return $tpl->render($template_data);
	}

	/**
	 * Uses git to fetch template or scheme sources
	 */
	public function buildTemplateData($scheme_data)
	{
		$vars['scheme-name'] = $scheme_data["scheme"];
		$vars['scheme-author'] = $scheme_data["author"];
		$vars['scheme-slug'] = $this->slugify($scheme_data["scheme"]);

		$bases = array('00', '01', '02', '03', '04', '05', '06', '07', '08',
			'09', '0A', '0B', '0C', '0D', '0E', '0F');

		foreach ($bases as $base) {
			$base_key = 'base' . $base;
			$color = new Color($scheme_data[$base_key]);

			$vars[$base_key . '-hex'] = $color->getHex();
			$vars[$base_key . '-hex-r'] = substr($color->getHex(), 0, 2);
			$vars[$base_key . '-hex-g'] = substr($color->getHex(), 2, 2);
			$vars[$base_key . '-hex-b'] = substr($color->getHex(), 4, 2);
			$vars[$base_key . '-rgb-r'] = $color->getRgb()['R'];
			$vars[$base_key . '-rgb-g'] = $color->getRgb()['G'];
			$vars[$base_key . '-rgb-b'] = $color->getRgb()['B'];
			$vars[$base_key . '-srgb-r'] = str_replace(".0000000000", "", number_format($color->getRgb()['R'] / 255, 10));
			$vars[$base_key . '-srgb-g'] = str_replace(".0000000000", "", number_format($color->getRgb()['G'] / 255, 10));
			$vars[$base_key . '-srgb-b'] = str_replace(".0000000000", "", number_format($color->getRgb()['B'] / 255, 10));
			$vars[$base_key . '-hsl-h'] = $color->getHsl()['H'];
			$vars[$base_key . '-hsl-s'] = $color->getHsl()['S'];
			$vars[$base_key . '-hsl-l'] = $color->getHsl()['L'];
		}

		return $vars;
	}

	/**
	 * Reads a file
	 */
	public function readFile($path)
	{
		return file_get_contents($path);
	}

	/**
	 * Writes a file
	 */
	public function writeFile($file_path, $file_name, $contents)
	{
		if (!is_dir($file_path)) mkdir($file_path);
		file_put_contents($file_path . '/' . $file_name, $contents);
	}

	/**
	 * Slugify a string
	 */
	public function slugify($string)
	{
		return $this->slugify->slugify($string);
	}

}
