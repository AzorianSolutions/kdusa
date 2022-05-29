<?php

namespace Spidermatt\SSH;

class SSH
{
	protected $_outputCollector = null;

	protected $_identityPubKeyFile = null;

	protected $_identityPrivKeyFile = null;

	protected $_sshHost = null;

	protected $_sshUsername = null;

	protected $_sshPassword = null;

	protected $_sshConnection = null;

	protected $_sshStreams = [];

	public function __construct($sshHost, $sshUsername, $sshPassword, $identityPubKeyFile = null, $identityPrivKeyFile = null)
	{
		$this->_sshHost = $sshHost;
		$this->_sshUsername = $sshUsername;
		$this->_sshPassword = $sshPassword;
		$this->_identityPubKeyFile = $identityPubKeyFile;
		$this->_identityPrivKeyFile = $identityPrivKeyFile;
	}

	public function getConnection()
	{
		return $this->_sshConnection;
	}

	public function setupConnection(&$outputCollector = null)
	{
		// Properly close any existing streams and/or SSH connection made by this instance
		$this->closeConnection();

		if($outputCollector instanceof OutputCollector)
			$this->_outputCollector = $outputCollector;

		// Open a connection to the configured SSH host
		$this->_sshConnection = ssh2_connect($this->_sshHost);

		// Authenticate the newly opened connection with an identity key pair if defined or fallback to username and password otherwise
		if (strlen(trim($this->_identityPubKeyFile)) && strlen(trim($this->_identityPrivKeyFile))
			&& is_readable($this->_identityPubKeyFile) && is_readable($this->_identityPrivKeyFile)) {
			ssh2_auth_pubkey_file($this->_sshConnection, $this->_sshUsername, $this->_identityPubKeyFile, $this->_identityPrivKeyFile);
		} else {
			ssh2_auth_password($this->_sshConnection, $this->_sshUsername, $this->_sshPassword);
		}

	}

	public function closeConnection()
	{
		$this->_closeAllStreams();
		if ($this->_sshConnection != null)
			ssh2_disconnect($this->_sshConnection);
	}

	public function executeCommand($cmd)
	{
		$result = new \stdClass();
		$result->stdOut = '';
		$result->stdErr = '';

		$cmd = str_replace('sudo ', 'sudo -S <<< "' . $this->_sshPassword . '" ', $cmd);

		$stream = $this->_sshStreams[] = ssh2_exec($this->_sshConnection, $cmd);

		if ($stream === false) {
			echo 'Failed to execute remote SSH command: ', $cmd, "\n";
			return false;
		}

		$error = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);

		if ($error === false) {
			echo 'Failed to retrieve the error sub-stream of the SSH connection.', "\n";
			fclose($stream);
			return false;
		}

		if (stream_set_blocking($stream, false) && stream_set_blocking($error, false)) {
			$wait = 0;

			while (!feof($stream) or !feof($error)) {
				if ($wait) usleep($wait);

				$wait = 50000;

				if (!feof($stream)) {
					if (($one = stream_get_contents($stream)) === false) {
						break;
					}

					if ($one != '') {
						$result->stdOut .= $one;
						$wait = 0;
					}
				}

				if (!feof($error)) {
					if (($one = stream_get_contents($error)) === false) {
						break;
					}

					if ($one != '') {
						$result->stdErr .= $one;
						$wait = 0;
					}
				}
			}
		}

		stream_set_blocking($stream, true);
		stream_set_blocking($error, true);
		stream_get_contents($stream);
		stream_get_contents($error);

		fclose($error);
		fclose($stream);

		if($this->_outputCollector instanceof OutputCollector)
			$this->_outputCollector->addResult($result);

		return $result;
	}

	protected function _closeAllStreams()
	{
		return false;
		if (is_array($this->_sshStreams) && count($this->_sshStreams))
			foreach ($this->_sshStreams as $stream)
				if($stream)
					fclose($stream);
	}
}