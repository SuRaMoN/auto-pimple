<?php

namespace AutoPimple;

class StringUtil
{
	public static function camelize($name)
	{
		$name = preg_replace_callback('/(\.|__|_|-|^)(.)/', function ($m) {
			$name = ('.' == $m[1] ? '\\' : '');
			if ($m['1'] == '__') {
				return $name . '_' . strtoupper($m[2]);
			} else {
				return $name . ('-' == $m[1] ? $m[2] : strtoupper($m[2]));
			}
		}, $name);
		return $name;
	}

	public static function underscore($name)
	{
		$name = str_replace('\\', '.', $name);
		$name = preg_replace_callback('/(?<!^|\.)[A-Z]/', function ($m) {
			return '_' . $m[0];
		}, $name);
		$name = preg_replace_callback('/(^|\.)([a-z])/', function ($m) {
			return '-' . $m[2];
		}, $name);
		return strtolower($name);
	}
}
