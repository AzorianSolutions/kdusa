<?php

namespace Spidermatt\CLI;

class ConfigManager
{
	protected $_configDir = null;

	protected $_configFiles = [];

	protected $_selectedConfigFile = null;

	protected $_config = [];
	
	public function __construct($configDir)
	{
		$this->setConfigDir($configDir);

		IO::setDefaultOutputFormat(new OutputFormat(str_repeat('-', 120) . "\n", str_repeat('-', 120) . "\n", null, "\n"));
	}

	public function getConfigDir()
	{
		return $this->_configDir;
	}

	public function setConfigDir($value)
	{
		// Append the proper directory separator to the end of the given value if not present
		if($value[strlen($value) - 1] != DIRECTORY_SEPARATOR) $value = $value . DIRECTORY_SEPARATOR;
		$this->_configDir = $value;

	}

	public function getSelectedConfigFile()
	{
		return $this->_selectedConfigFile;
	}

	public function setSelectedConfigFile($value)
	{
		$this->_selectedConfigFile = $value;
	}

	public function getConfig()
	{
		return $this->_config;
	}

	public function setConfig($value)
	{
		$this->_config = $value;
	}

	public function loadConfig()
	{
		$this->_config = yaml_parse_file($this->_selectedConfigFile);
	}

	public function saveConfig()
	{
		yaml_emit_file($this->getSelectedConfigFile(), $this->getConfig());
	}

	public function startManager()
	{
		$this->_configFiles = $this->_scanConfigDirectory($this->getConfigDir());

		if(count($this->_configFiles)) {

			$validations = [];

			// If there are any existing config files, add a validation rule for each entry in the file list
			if(count($this->_configFiles)) {
				for($a = 1; $a <= count($this->_configFiles); $a++) {
					$validations[] = '/^' . $a . '$/';
				}
			}

			// Determine what config file to load

			$label = "Would you like to load an existing setup configuration file?\n" . str_repeat('-', 120) . "\n";

			$inputLabel = 'Enter the corresponding number of the config you wish to load: ';
			$response = IO::requestInputSelectList($label, $inputLabel, $this->_configFiles, $validations);

			$configFileName = $this->_configFiles[((int) $response) - 1];
			$this->_selectedConfigFile = $this->getConfigDir() . $configFileName;
			$this->loadConfig();

			// Determine where to save config file

			$label = "What file name would you like to save this configuration as?\n"
				. str_repeat('-', 120) . "\n\n"
				. "The file will be saved to the \"" . $this->getConfigDir() . "\" path.\n\n";

			$validations[] = '/^[a-z0-9_\-\.]+(\.yaml){1}$/i';

			$inputLabel = 'Enter the corresponding number of the existing file or the name of a new file: ';
			$response = IO::requestInputSelectList($label, $inputLabel, $this->_configFiles, $validations);

			if(is_numeric($response)) {
				$configFileName = $this->_configFiles[((int) $response) - 1];
				$this->_selectedConfigFile = $this->getConfigDir() . $configFileName;
			} else {
				$this->_selectedConfigFile = $this->getConfigDir() . $response;
			}

		} else {
			// TODO: Handle exception for no config files being available
		}
	}

	public function startConfiguration()
	{
		// Kubelet Version

		$kubeletVersion = $this->getConfig()['options']['softwareVersions']['kubelet'];
		$label = "What version of Kubelet do you want to install?\n\nLeave blank to keep the current value of \"$kubeletVersion\".";

		if(!empty($response = IO::requestInputText($label, null, true, $kubeletVersion))) {
			$this->_config['options']['softwareVersions']['kubelet'] = $response;
			$this->saveConfig();
		}

		// Kubeadm Version

		$kubeadmVersion = $this->getConfig()['options']['softwareVersions']['kubeadm'];
		$label = "What version of Kubeadm do you want to install?\n\nLeave blank to keep the current value of \"$kubeadmVersion\".";

		if(!empty($response = IO::requestInputText($label, null, true, $kubeadmVersion))) {
			$this->_config['options']['softwareVersions']['kubeadm'] = $response;
			$this->saveConfig();
		}

		// Kubectl Version

		$kubectlVersion = $this->getConfig()['options']['softwareVersions']['kubectl'];
		$label = "What version of Kubectl do you want to install?\n\nLeave blank to keep the current value of \"$kubectlVersion\".";

		if(!empty($response = IO::requestInputText($label, null, true, $kubectlVersion))) {
			$this->_config['options']['softwareVersions']['kubectl'] = $response;
			$this->saveConfig();
		}

		// Cluster Control Plane Endpoint

		$controlPlaneEndpoint = $this->getConfig()['options']['controlPlaneEndpoint'];
		$label = "What domain name or IP address will be used for the control plane load balancer?\n\nLeave blank to keep the current value of \"$controlPlaneEndpoint\".";

		if(!empty($response = IO::requestInputText($label, null, true, $controlPlaneEndpoint))) {
			$this->_config['options']['controlPlaneEndpoint'] = $response;
			$this->saveConfig();
		}

		// Cluster Search Domain

		$clusterDomain = $this->getConfig()['options']['clusterDomain'];
		$label = "What search domain should be used for the cluster?\n\nLeave blank to keep the current value of \"$clusterDomain\".";

		if(!empty($response = IO::requestInputText($label, null, true, $clusterDomain))) {
			$this->_config['options']['clusterDomain'] = $response;
			$this->saveConfig();
		}

		// Pod Network CIDR

		$podNetworkCIDR = $this->getConfig()['options']['ipam']['podNetworksCIDR'];
		$label = "What IPv4 allocation should be used for Kubernetes pod networks?\n\nLeave blank to keep the current value of \"$podNetworkCIDR\".";

		if(!empty($response = IO::requestInputText($label, null, true, $podNetworkCIDR))) {
			$this->_config['options']['ipam']['podNetworksCIDR'] = $response;
			$this->saveConfig();
		}

		// Service Network CIDR

		$serviceNetworksCIDR = $this->getConfig()['options']['ipam']['serviceNetworksCIDR'];
		$label = "What IPv4 allocation should be used for Kubernetes service networks?\n\nLeave blank to keep the current value of \"$serviceNetworksCIDR\".";

		if(!empty($response = IO::requestInputText($label, null, true, $serviceNetworksCIDR))) {
			$this->_config['options']['ipam']['serviceNetworksCIDR'] = $response;
			$this->saveConfig();
		}

		// Load Balancer Network CIDR

		$loadBalancerNetworksCIDR = $this->getConfig()['options']['ipam']['loadBalancerNetworksCIDR'];
		$label = "What IPv4 allocation should be used for Kubernetes load balancer networks?\n\nLeave blank to keep the current value of \"$loadBalancerNetworksCIDR\".";

		if(!empty($response = IO::requestInputText($label, null, true, $loadBalancerNetworksCIDR))) {
			$this->_config['options']['ipam']['loadBalancerNetworksCIDR'] = $response;
			$this->saveConfig();
		}

		// Control Plane Nodes

		$label = "Enter each control plane node's IP address, one at a time!\n\nWhen you have finished entering control plane nodes, leave the input blank and press enter.\n\n";

		if(is_array($this->_config['options']['nodes']['controlPlane']) && count($this->_config['options']['nodes']['controlPlane'])) {
			$label .= 'Here are the current control plane nodes that are configured:' . "\n";

			foreach($this->_config['options']['nodes']['controlPlane'] as $nodeIP) {
				$label .= '- ' . $nodeIP . "\n";
			}

			$label .= "\n";
		}

		$this->_config['options']['nodes']['controlPlane'] = IO::requestInputList($label, 'Enter a control plane node IP address: ', null, $this->_config['options']['nodes']['controlPlane']);
		$this->saveConfig();

		// Worker Nodes

		$label = "Enter each worker node's IP address, one at a time!\n\nWhen you have finished entering worker nodes, leave the input blank and press enter.\n\n";

		if(is_array($this->_config['options']['nodes']['workers']) && count($this->_config['options']['nodes']['workers'])) {
			$label .= 'Here are the current worker nodes that are configured:' . "\n";

			foreach($this->_config['options']['nodes']['workers'] as $nodeIP) {
				$label .= '- ' . $nodeIP . "\n";
			}

			$label .= "\n";
		}

		$this->_config['options']['nodes']['workers'] = IO::requestInputList($label, 'Enter a worker node IP address: ', null, $this->_config['options']['nodes']['workers']);
		$this->saveConfig();

		// SSH Username

		$sshUsername = $this->getConfig()['options']['ssh']['username'];
		$label = "What username has sudo privileges and SSH access on each node?\n\nLeave blank to keep the current value of \"$sshUsername\".";

		if(!empty($response = IO::requestInputText($label, null, true, $sshUsername))) {
			$this->_config['options']['ssh']['username'] = $response;
			$this->saveConfig();
		}

		// SSH Password

		$sshPassword = $this->getConfig()['options']['ssh']['password'];
		$label = "What password is used to login to SSH with the username \"" . $this->_config['options']['ssh']['username'] . "\"?\n\nLeave blank to keep the current value of \"$sshPassword\".";

		if(!empty($response = IO::requestInputText($label, null, true, $sshPassword))) {
			$this->_config['options']['ssh']['password'] = $response;
			$this->saveConfig();
		}
	}

	public function confirmConfiguration()
	{
		$options = $this->getConfig()['options'];

		$label = "Please confirm all of the following settings are correct.\n";

		$label .= str_repeat('-', 120) . "\n";

		$label .= 'Software Versions Selected:' . "\n";

		$label .= '- Kubelet: ' . $options['softwareVersions']['kubelet'] . "\n";

		$label .= '- Kubeadm: ' . $options['softwareVersions']['kubeadm'] . "\n";

		$label .= '- Kubectl: ' . $options['softwareVersions']['kubectl'] . "\n";

		$label .= 'Cluster Control Plane Endpoint: ' . $options['controlPlaneEndpoint'] . "\n";

		$label .= 'Cluster Search Domain: ' . $options['clusterDomain'] . "\n";

		$label .= 'IPAM Configuration:' . "\n";

		$label .= '- Pod Networks CIDR: ' . $options['ipam']['podNetworksCIDR'] . "\n";

		$label .= '- Service Networks CIDR: ' . $options['ipam']['serviceNetworksCIDR'] . "\n";

		$label .= '- Load Balancer Networks CIDR: ' . $options['ipam']['loadBalancerNetworksCIDR'] . "\n";

		$label .= 'Control Plane Nodes:' . "\n";

		if(is_array($options['nodes']['controlPlane']) && count($options['nodes']['controlPlane']))
			$label .= '- ' . implode("\n- ", $options['nodes']['controlPlane']) . "\n";
		else
			$label .= '- No control plane nodes';

		$label .= 'Worker Nodes:' . "\n";

		if(is_array($options['nodes']['workers']) && count($options['nodes']['workers']))
			$label .= '- ' . implode("\n- ", $options['nodes']['workers']) . "\n";
		else
			$label .= '- No worker nodes';

		$label .= 'Worker Nodes:' . "\n";

		$label .= 'SSH Username: ' . $options['ssh']['username'] . "\n";

		$label .= 'SSH Password: ' . $options['ssh']['password'] . "\n";

		$label .= str_repeat('-', 120) . "\n";

		$label .= "If everything appears correct, please enter \"yes\" and press enter.\n\nOtherwise, enter \"no\" and press enter to restart the configuration process: ";

		$response = IO::requestInputText($label, ['/(y|yes|true|1|n|no|false|0){1}/i']);

		$confirmed = false;

		if (in_array($response, ['y', 'yes', 'true', '1'])) {
			$confirmed = true;
		} elseif (in_array($response, ['n', 'no', 'false', '0'])) {
			$confirmed = false;
		}

		return $confirmed;
	}

	protected function _scanConfigDirectory($path)
	{
		$files = [];

		// Append the proper directory separator to the end of the given path if not present
		if($path[strlen($path) - 1] != DIRECTORY_SEPARATOR) $path = $path . DIRECTORY_SEPARATOR;

		$scan = scandir($path);

		if($scan === false || !is_array($scan)) {
			if(!is_dir($path)) {
				IO::writeLineToOutputStream("ERROR: The given path ($path) is not a valid directory!");
			} elseif (!is_readable($path)) {
				IO::writeLineToOutputStream("ERROR: The given path ($path) does not have the proper permissions for reading!");
			}

			return $files;
		}

		foreach($scan as $name) {
			// Ignore automatic tree references and anything that isn't an actual file
			if($name == '.' || $name == '..' || !is_file($path . $name)) continue;
			$files[] = $name;
		}

		return $files;
	}
}