<?php

namespace Spidermatt\CLI;

class IO
{
	protected static $_defaultOutputFormat = null;

	public static function getDefaultOutputFormat()
	{
		return self::$_defaultOutputFormat;
	}

	public static function setDefaultOutputFormat($value)
	{
		if($value instanceof OutputFormat)
			self::$_defaultOutputFormat = $value;
	}

	public static function readLineFromInputStream()
	{
		return trim(fgets(STDIN));
	}

	public static function writeLineToOutputStream($line, $outputFormat = null)
	{
		if($outputFormat !== false && !($outputFormat instanceof OutputFormat) && self::getDefaultOutputFormat() instanceof OutputFormat)
			$outputFormat = self::getDefaultOutputFormat();

		$output = '';

		if ($outputFormat instanceof OutputFormat) {
			if ($outputFormat->getHeaderText() != null)
				$output .= $outputFormat->getHeaderText();

			if ($outputFormat->getLinePrefix() != null)
				$output .= $outputFormat->getLinePrefix();

			$output .= $line;

			if ($outputFormat->getLineSuffix() != null)
				$output .= $outputFormat->getLineSuffix();

			if ($outputFormat->getFooterText() != null)
				$output .= $outputFormat->getFooterText();

			fwrite(STDOUT, $output);
		} else {
			fwrite(STDOUT, $line . "\n");
		}
	}

	public static function writeInfoLineToOutputStream($line, $outputFormat = null)
	{
		if($outputFormat !== false && !($outputFormat instanceof OutputFormat) && self::getDefaultOutputFormat() instanceof OutputFormat)
			$outputFormat = self::getDefaultOutputFormat();

		self::writeLineToOutputStream("\n" . str_repeat('*', 60) . "\n $line\n" . str_repeat('*', 60) . "\n", $outputFormat);
	}

	public static function writeErrorLineToOutputStream($line, $outputFormat = null)
	{
		if($outputFormat !== false && !($outputFormat instanceof OutputFormat) && self::getDefaultOutputFormat() instanceof OutputFormat)
			$outputFormat = self::getDefaultOutputFormat();

		self::writeLineToOutputStream("\n" . str_repeat('*', 60) . "\n $line\n" . str_repeat('*', 60) . "\n", $outputFormat);
	}

	public static function requestInputText($label, $validResponses, $allowEmpty = false, $defaultResponse = null, $outputFormat = null)
	{
		if(!($outputFormat instanceof OutputFormat) && self::getDefaultOutputFormat() instanceof OutputFormat)
			$outputFormat = self::getDefaultOutputFormat();

		self::writeLineToOutputStream($label, $outputFormat);

		$response = self::readLineFromInputStream();
		$pass = true;

		if (!$allowEmpty && empty($response)) {
			$pass = false;
			self::writeErrorLineToOutputStream("An empty response is not permitted.");
		} elseif (!empty($response) && $pass && is_array($validResponses) && count($validResponses)) {
			$valid = false;

			foreach ($validResponses as $pattern) {
				if (preg_match($pattern, $response) === 1) {
					$valid = true;
				}
			}

			if (!$valid) {
				$pass = false;
				self::writeErrorLineToOutputStream("The given input of \"$response\" is not valid.");
			}
		}

		if (!$pass)
			return self::requestInputText($label, $validResponses, $allowEmpty, $defaultResponse, $outputFormat);

		return $response;
	}

	public static function requestInputList($label, $inputLabel, $validResponses = null, $items = [], $outputFormat = null)
	{
		if(!($outputFormat instanceof OutputFormat) && self::getDefaultOutputFormat() instanceof OutputFormat)
			$outputFormat = self::getDefaultOutputFormat();

		$output = $label . $inputLabel;

		self::writeLineToOutputStream($output, $outputFormat);

		$response = self::readLineFromInputStream();

		if($response == '')
			return $items;

		if (is_array($validResponses) && count($validResponses)) {
			$valid = false;

			foreach ($validResponses as $pattern) {
				if (preg_match($pattern, $response) === 1) {
					$valid = true;
				}
			}

			if (!$valid) {
				self::writeErrorLineToOutputStream("The given input of \"$response\" is not valid.");
			} else {
				$items[] = $response;
			}
		} else {
			$items[] = $response;
		}

		return self::requestInputList('', $inputLabel, $validResponses, $items, $outputFormat);
	}

	public static function requestInputSelectList($label, $inputLabel, $options, $validResponses, $allowEmpty = false, $defaultResponse = null, $outputFormat = null)
	{
		if(!($outputFormat instanceof OutputFormat) && self::getDefaultOutputFormat() instanceof OutputFormat)
			$outputFormat = self::getDefaultOutputFormat();

		$output = $label;

		$a = 1;
		foreach ($options as $option) {
			$output .= "[$a] $option\n";
			$a++;
		}

		$output .= "\n$inputLabel";

		self::writeLineToOutputStream($output, $outputFormat);

		$response = self::readLineFromInputStream();
		$pass = true;

		if (!$allowEmpty && empty($response)) {
			$pass = false;
			self::writeErrorLineToOutputStream("An empty response is not permitted.");
		} elseif (!empty($response) && $pass && is_array($validResponses) && count($validResponses)) {
			$valid = false;

			foreach ($validResponses as $pattern) {
				if (preg_match($pattern, $response) === 1) {
					$valid = true;
				}
			}

			if (!$valid) {
				$pass = false;
				self::writeErrorLineToOutputStream("The given input of \"$response\" is not valid.");
			}
		}

		if (!$pass)
			return self::requestInputSelectList($label, $inputLabel, $options, $validResponses, $allowEmpty, $defaultResponse, $outputFormat);

		return $response;
	}
}