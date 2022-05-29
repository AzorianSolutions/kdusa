<?php

namespace Spidermatt\SSH;

class OutputCollector
{
	protected $_results = [];

	protected $_stdOut = '';

	protected $_stdError = '';

	public function addResult($commandResult)
	{
		$this->_results[] = $commandResult;
		$this->_stdOut .= "\n" . $commandResult->stdOut;
		$this->_stdError .= "\n" . $commandResult->stdErr;
	}

	public function getResults()
	{
		return $this->_results;
	}

	public function setResults($value)
	{
		if(!is_array($value))
			return false;

		$this->_results = $value;
	}

	public function getStdOut()
	{
		return $this->_stdOut;
	}

	public function getStdError()
	{
		return $this->_stdError;
	}
}