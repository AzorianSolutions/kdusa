<?php

namespace Spidermatt\CLI;

class OutputFormat
{
	protected $_headerText = null;
	
	protected $_footerText = null;
	
	protected $_linePrefix = null;
	
	protected $_lineSuffix = null;
	
	public function __construct($headerText = null, $footerText =  null, $linePrefix = null, $lineSuffix = null)
	{
		if($headerText != null) $this->setHeaderText($headerText);
		if($footerText != null) $this->setFooterText($footerText);
		if($linePrefix != null) $this->setLinePrefix($linePrefix);
		if($lineSuffix != null) $this->setLineSuffix($lineSuffix);
	}

	public function getHeaderText()
	{
		return $this->_headerText;
	}

	public function setHeaderText($value)
	{
		$this->_headerText = $value;
	}

	public function getFooterText()
	{
		return $this->_footerText;
	}

	public function setFooterText($value)
	{
		$this->_footerText = $value;
	}

	public function getLinePrefix()
	{
		return $this->_linePrefix;
	}

	public function setLinePrefix($value)
	{
		$this->_linePrefix = $value;
	}

	public function getLineSuffix()
	{
		return $this->_lineSuffix;
	}

	public function setLineSuffix($value)
	{
		$this->_lineSuffix = $value;
	}
}