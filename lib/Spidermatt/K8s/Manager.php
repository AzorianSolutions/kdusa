<?php

namespace Spidermatt\K8s;

use Spidermatt\CLI\IO;

class Manager
{
    protected $_configManager;

    protected $_collectors = [];

    protected $_rootPath;

    public function __construct($rootPath)
    {
        // Cache the root path of the app
        $this->_rootPath = $rootPath;

        // Setup command output collectors
        $this->_collectors['setup'] = new \Spidermatt\SSH\OutputCollector();
        $this->_collectors['init'] = new \Spidermatt\SSH\OutputCollector();
        $this->_collectors['node'] = new \Spidermatt\SSH\OutputCollector();
    }

    public function startConfiguration()
    {
        $configured = false;

        // Setup the configuration manager to look non-recursively for configuration files in the given path
        $this->_configManager = new \Spidermatt\CLI\ConfigManager($this->_rootPath . '/config/');

        // Start the configuration loader CLI app
        $this->_configManager->startManager();

        while (!$configured) {
            // Start the configuration builder CLI app
            $this->_configManager->startConfiguration();

            // Update the $configured variable to true if user confirms current configuration
            if ($this->_configManager->confirmConfiguration())
                $configured = true;
        }

        // The configuration loading process should be complete at this point, retrieve loaded configuration object
        $config = $this->_configManager->getConfig();

        // Apply defaults to various configuration items

        if (!isset($config['options']['nodes']['controlPlane']) || !is_array($config['options']['nodes']['controlPlane']))
            $config['options']['nodes']['controlPlane'] = [];

        if (!isset($config['options']['nodes']['workers']) || !is_array($config['options']['nodes']['workers']))
            $config['options']['nodes']['workers'] = [];

        // Update the root configuration object with the previous changes
        $this->_configManager->setConfig($config);
    }

    public function startProcess($processName = null)
    {
        $processName = '_process' . ucfirst($processName);
        $reflectionClass = new \ReflectionClass(get_class($this));

        try {
            $reflectionClass->getMethod($processName);
            $this->$processName();
        } catch (\Exception $e) {
            echo 'Exception: ', $e->getMessage(), "\n\n";
        }
    }

    protected function _processUninstall()
    {
        $config = $this->_configManager->getConfig();
        $setupNodes = array_merge($config['options']['nodes']['controlPlane'], $config['options']['nodes']['workers']);

        IO::writeInfoLineToOutputStream('Uninstall Kubernetes from all cluster nodes', false);

        IO::writeLineToOutputStream('Cluster Nodes:', false);

        IO::writeLineToOutputStream('- ' . implode("\n- ", $setupNodes), false);

        foreach ($setupNodes as $nodeIP) {
            IO::writeInfoLineToOutputStream('Removing Kubernetes from host ' . $nodeIP, false);

            $ssh = new \Spidermatt\SSH\SSH($nodeIP, $config['options']['ssh']['username'], $config['options']['ssh']['password']);

            $ssh->setupConnection($this->_collectors['setup']);

            $kubeadmPath = trim($ssh->executeCommand('which kubeadm')->stdOut);

            IO::writeLineToOutputStream('- Removing Calico from cluster', false);

            $ssh->executeCommand('curl https://docs.projectcalico.org/manifests/calico.yaml > $HOME/.as-k8s/calico.yaml');
            $ssh->executeCommand('kubectl delete -f $HOME/.as-k8s/calico.yaml');

            IO::writeLineToOutputStream('- Removing $HOME/.kube/ directory from current user', false);

            $ssh->executeCommand('rm -fr $HOME/.kube/');

            IO::writeLineToOutputStream('- Removing Spidernetes installation directory', false);

            $ssh->executeCommand('rm -fr $HOME/.as-k8s');

            // Run the Kubeadm reset function if Kubeadm is available
            if (strlen($kubeadmPath)) {
                IO::writeLineToOutputStream('- Running Kubeadm reset function', false);
                $ssh->executeCommand('sudo ' . $kubeadmPath . ' reset -f');
            }

            IO::writeLineToOutputStream('- Removing all CNI configuration files', false);

            // Remove any lingering Container Networking Interface drop-ins (likely from Calico)
            $ssh->executeCommand('sudo rm -fr /etc/cni/net.d/*');

            IO::writeLineToOutputStream('- Flushing Linux IPTables firewall', false);

            // Flush all the rules for the Linux IPTables firewall (reset)
            $ssh->executeCommand('sudo iptables -F');

            IO::writeLineToOutputStream('- Purging APT packages for Kubernetes', false);

            // Purge existing Kubernetes packages
            $ssh->executeCommand('sudo apt purge -y --allow-change-held-packages kubelet kubeadm kubectl kubernetes-cni');

            IO::writeLineToOutputStream('- Removing Kubernetes repositories', false);

            // Remove existing PPA for Kubernetes components
            $ssh->executeCommand('sudo rm -f /etc/apt/sources.list.d/kubernetes.list');

            IO::writeLineToOutputStream('- Running APT autoremove function', false);

            // Perform auto-remove cleanup for APT
            $ssh->executeCommand('sudo apt autoremove -y');

            IO::writeLineToOutputStream('- Removing kernel module and variable configurations', false);

            // Remove kernel module configuration
            $ssh->executeCommand('sudo rm -fr /etc/modules-load.d/k8s.conf');

            // Remove kernel variable configuration
            $ssh->executeCommand('sudo rm -fr /etc/sysctl.d/99-k8s.conf');

            IO::writeLineToOutputStream('- Updating APT', false);

            // Update APT package cache
            $ssh->executeCommand('sudo apt update');

            IO::writeLineToOutputStream('- Removing Calico control scripts', false);

            // Remove Calico control script symlink to Kubernetes module
            $ssh->executeCommand('sudo rm -fr /usr/local/bin/calicoctl');

            // Remove Calico control script for Kubernetes module
            $ssh->executeCommand('sudo rm -fr /usr/local/bin/kubectl-calico');

            IO::writeLineToOutputStream('- Scheduling machine reboot using shutdown -r command', false);

            // Reboot machine
            $ssh->executeCommand('sudo shutdown -r');

            $ssh->closeConnection();
        }
    }

    protected function _processDependencyInstall()
    {
        $config = $this->_configManager->getConfig();
        $setupNodes = array_merge($config['options']['nodes']['controlPlane'], $config['options']['nodes']['workers']);

        IO::writeInfoLineToOutputStream('Running initial setup of all cluster nodes', false);

        IO::writeLineToOutputStream('Cluster Nodes:', false);

        IO::writeLineToOutputStream('- ' . implode("\n- ", $setupNodes), false);

        foreach ($setupNodes as $nodeIP) {
            IO::writeInfoLineToOutputStream('Installing dependencies for host ' . $nodeIP, false);

            $ssh = new \Spidermatt\SSH\SSH($nodeIP, $config['options']['ssh']['username'], $config['options']['ssh']['password']);

            $ssh->setupConnection($this->_collectors['setup']);

            // Create directory to hold various files for this tool

            IO::writeLineToOutputStream('- Installing Azorian Solutions Kubernetes files', false);
            $ssh->executeCommand('mkdir -p $HOME/.as-k8s');
            
            // Disable swap

            IO::writeLineToOutputStream('- Disabling swap space', false);
            $ssh->executeCommand("sudo sed -i 's/^\/swap/#\/swap/g' /etc/fstab", true);
            $ssh->executeCommand('sudo swapoff -a');

            // Install operating system updates

            IO::writeLineToOutputStream('- Installing operating system updates', false);
            $ssh->executeCommand('sudo apt update');
            $ssh->executeCommand('sudo apt upgrade -y');

            // Install base packages with APT

            IO::writeLineToOutputStream('- Installing base software packages', false);
            $ssh->executeCommand('sudo apt install -y apt-transport-https ca-certificates curl gnupg lsb-release '
                . 'net-tools containerd');

            // TODO: Setup NTP client

            // Setup kernel modules

            IO::writeLineToOutputStream('- Installing kernel module boot configuration', false);
            $ssh->executeCommand('cat > ~/.as-k8s/modules-load-k8s.conf << EOF
overlay
br_netfilter
EOF');
            $ssh->executeCommand('sudo cp ~/.as-k8s/modules-load-k8s.conf /etc/modules-load.d/k8s.conf');
            $ssh->executeCommand('sudo chown root:root /etc/modules-load.d/k8s.conf');

            // Loading kernel modules

            IO::writeLineToOutputStream('- Loading kernel modules', false);
            $ssh->executeCommand('sudo modprobe overlay');
            $ssh->executeCommand('sudo modprobe br_netfilter');

            // Setup and load kernel configurations

            IO::writeLineToOutputStream('- Installing kernel configuration', false);
            $ssh->executeCommand('cat > ~/.as-k8s/sysctl-k8s.conf << EOF
net.bridge.bridge-nf-call-iptables  = 1
net.ipv4.ip_forward                 = 1
net.bridge.bridge-nf-call-ip6tables = 1
EOF');
            $ssh->executeCommand('sudo mv ~/.as-k8s/sysctl-k8s.conf /etc/sysctl.d/99-k8s.conf');
            $ssh->executeCommand('sudo chown root:root /etc/sysctl.d/99-k8s.conf');
            $ssh->executeCommand('sudo sysctl --system');

            IO::writeLineToOutputStream('- Installing package repositories', false);

            $ssh->executeCommand('sudo curl -fsSLo /usr/share/keyrings/kubernetes-archive-keyring.gpg '
                . 'https://packages.cloud.google.com/apt/doc/apt-key.gpg');

            $ssh->executeCommand('cat > ~/.as-k8s/kubernetes.list << EOF
deb [signed-by=/usr/share/keyrings/kubernetes-archive-keyring.gpg] https://apt.kubernetes.io/ kubernetes-xenial main
EOF');

            $ssh->executeCommand('sudo mv /home/' . $config['options']['ssh']['username'] . '/.as-k8s/kubernetes.list /etc/apt/sources.list.d/');
            
            $ssh->executeCommand('sudo chown root:root /etc/apt/sources.list.d/kubernetes.list');
            
            sleep(1);

            IO::writeLineToOutputStream('- Updating package cache from package repositories.', false);

            $ssh->executeCommand('sudo apt update');

            IO::writeLineToOutputStream('- Installing Kubernetes packages', false);

            /*
             * Many of the following settings have been deprecated in favor of a kubeadm config equivalent. So,
             * any applicable settings should be moved to the config that is produced by the cluster init command
             */
            $kubeletVars = 'KUBELET_EXTRA_ARGS=\'--feature-gates="AllAlpha=false,RunAsGroup=true" '
                . '--container-runtime=remote --cgroup-driver=systemd --runtime-request-timeout=5m '
                . '--container-runtime-endpoint="unix:///var/run/containerd/containerd.sock"\'';
            $kubeletVersion = $config['options']['softwareVersions']['kubelet'];
            $kubeadmVersion = $config['options']['softwareVersions']['kubeadm'];
            $kubectlVersion = $config['options']['softwareVersions']['kubectl'];

            $ssh->executeCommand("$kubeletVars\n sudo apt install -y --allow-change-held-packages "
                . "kubelet=$kubeletVersion kubeadm=$kubeadmVersion kubectl=$kubectlVersion kubernetes-cni");

            /*
            IO::writeLineToOutputStream('- Installing Calico control script', false);

            $ssh->executeCommand('curl -A "Mozilla/5.0 (X11; Linux x86_64; rv:60.0) Gecko/20100101 Firefox/81.0" -o kubectl-calico -L  '
                . 'https://github.com/projectcalico/calico/releases/download/v3.22.0/calicoctl-linux-amd64');
            $ssh->executeCommand('chmod +x kubectl-calico');
            $ssh->executeCommand('sudo mv kubectl-calico /usr/local/bin/');
            $ssh->executeCommand('sudo ln -s /usr/local/bin/kubectl-calico /usr/local/bin/calicoctl');
            */

            $ssh->closeConnection();

            IO::writeLineToOutputStream('- Finished dependency installation', false);
        }

        if (strlen(trim($this->_collectors['setup']->getStdOut()))) {
            echo str_repeat('*', 120), "\nSETUP STD OUT\n", str_repeat('*', 120), "\n\n",
            $this->_collectors['setup']->getStdOut();
        }

        if (strlen(trim($this->_collectors['setup']->getStdError()))) {
            echo str_repeat('*', 120), "\nSETUP STD ERROR\n", str_repeat('*', 120), "\n\n",
            $this->_collectors['setup']->getStdError();
        }
    }

    protected function _processInitCluster()
    {
        $config = $this->_configManager->getConfig();
        $manager1IP = null;

        if (is_array($config['options']['nodes']['controlPlane']) && count($config['options']['nodes']['controlPlane'])) {
            $manager1IP = $config['options']['nodes']['controlPlane'][0];
        }

        if ($manager1IP != null) {
            IO::writeInfoLineToOutputStream('Running cluster initialization on host ' . $manager1IP, false);

            $sshManager = new \Spidermatt\SSH\SSH($manager1IP, $config['options']['ssh']['username'], $config['options']['ssh']['password']);

            $sshManager->setupConnection($this->_collectors['init']);

            // Create directory to hold various files for this tool
            $sshManager->executeCommand('mkdir -p $HOME/.as-k8s');

            $sshManager->executeCommand('sudo rm -fr ~/.as-k8s/kubeadm-config.yaml');

            $sshManager->executeCommand('cat > ~/.as-k8s/kubeadm-config.yaml << EOF
kind: InitConfiguration
apiVersion: kubeadm.k8s.io/v1beta2
nodeRegistration:
  criSocket: /var/run/containerd/containerd.sock
---
kind: ClusterConfiguration
apiVersion: kubeadm.k8s.io/v1beta2
controlPlaneEndpoint: "' . $config['options']['controlPlaneEndpoint'] . ':6443"
networking:
  serviceSubnet: "' . $config['options']['ipam']['serviceNetworksCIDR'] . '"
  podSubnet: "' . $config['options']['ipam']['podNetworksCIDR'] . '"
  dnsDomain: "' . $config['options']['clusterDomain'] . '"
---
kind: KubeletConfiguration
apiVersion: kubelet.config.k8s.io/v1beta1
cgroupDriver: systemd
---
kind: KubeProxyConfiguration
apiVersion: kubeproxy.config.k8s.io/v1alpha1
EOF');

            IO::writeLineToOutputStream('- Initializing Kubernetes cluster...', false);

            $clusterInit = $sshManager->executeCommand('sudo kubeadm init --config ~/.as-k8s/kubeadm-config.yaml --upload-certs');

            IO::writeLineToOutputStream('- Setting up current user with kubectl config.', false);

            $sshManager->executeCommand('mkdir -p $HOME/.kube');
            $sshManager->executeCommand('sudo cp -i /etc/kubernetes/admin.conf $HOME/.kube/config');
            $sshManager->executeCommand('sudo chown $(id -u):$(id -g) $HOME/.kube/config');

            IO::writeLineToOutputStream('- Deploying Calico networking layer.', false);

            $sshManager->executeCommand('curl https://docs.projectcalico.org/manifests/calico.yaml > ~/.as-k8s/calico.yaml');
            $sshManager->executeCommand('kubectl apply -f ~/.as-k8s/calico.yaml');

            $regExCP = '/kubeadm join ' . $config['options']['controlPlaneEndpoint'] . ':6443 --token [a-z0-9]{6}\.[a-z0-9]{16} \\\[\s]+--discovery-token-ca-cert-hash sha256:[a-z0-9]{64} \\\[\s]+--control-plane --certificate-key [a-z0-9]{64}/';
            $regExWorker = '/kubeadm join ' . $config['options']['controlPlaneEndpoint'] . ':6443 --token [a-z0-9]{6}\.[a-z0-9]{16} \\\[\s]+--discovery-token-ca-cert-hash sha256:[a-z0-9]{64} \\\[\s]+/';

            $controlPlaneMatches = [];
            $workerMatches = [];

            preg_match_all($regExCP, $clusterInit->stdOut, $controlPlaneMatches);
            preg_match_all($regExWorker, $clusterInit->stdOut, $workerMatches);

            if (count($controlPlaneMatches) && is_array($controlPlaneMatches[0]) && count($controlPlaneMatches[0])) {
                $controlPlaneJoinCmd = preg_replace('/[\s\\\]{2,}/', ' ', $controlPlaneMatches[0][0]);
            }

            if (count($workerMatches) && is_array($workerMatches[0]) && count($workerMatches[0])) {
                $workerJoinCmd = preg_replace('/[\s\\\]{2,}/', ' ', $workerMatches[0][0]);
            }

            if (isset($workerJoinCmd) && is_array($config['options']['nodes']['workers']) && count($config['options']['nodes']['workers'])) {
                foreach ($config['options']['nodes']['workers'] as $nodeIP) {

                    IO::writeLineToOutputStream('- Joining worker node to cluster: ' . $nodeIP, false);

                    $nodeSSH = new \Spidermatt\SSH\SSH($nodeIP, $config['options']['ssh']['username'], $config['options']['ssh']['password']);

                    $nodeSSH->setupConnection($this->_collectors['node']);

                    $nodeSSH->executeCommand('sudo ' . $workerJoinCmd);

                    $nodeSSH->closeConnection();
                }
            }

            if (isset($controlPlaneJoinCmd) && is_array($config['options']['nodes']['controlPlane']) && count($config['options']['nodes']['controlPlane'])) {
                foreach ($config['options']['nodes']['controlPlane'] as $nodeIP) {

                    // Skip the first control plane node since it is already configured
                    if ($nodeIP == $manager1IP)
                        continue;

                    IO::writeLineToOutputStream('- Joining control plane node to cluster: ' . $nodeIP, false);

                    $nodeSSH = new \Spidermatt\SSH\SSH($nodeIP, $config['options']['ssh']['username'], $config['options']['ssh']['password']);

                    $nodeSSH->setupConnection($this->_collectors['node']);

                    $nodeSSH->executeCommand('sudo ' . $controlPlaneJoinCmd);
                    $nodeSSH->executeCommand('mkdir -p $HOME/.kube');
                    $nodeSSH->executeCommand('sudo cp -i /etc/kubernetes/admin.conf $HOME/.kube/config');
                    $nodeSSH->executeCommand('sudo chown $(id -u):$(id -g) $HOME/.kube/config');

                    $nodeSSH->closeConnection();
                }
            }

            $sleep = 25;

            IO::writeLineToOutputStream('- Waiting for ' . $sleep . ' seconds to allow for service startup.', false);

            sleep($sleep);

            $nodesResult = $sshManager->executeCommand('kubectl get nodes -o wide');

            IO::writeInfoLineToOutputStream($nodesResult->stdOut, false);

            IO::writeInfoLineToOutputStream($nodesResult->stdErr, false);

            $sshManager->closeConnection();

            if (strlen(trim($this->_collectors['init']->getStdOut()))) {
                echo str_repeat('*', 120), "\nINIT STD OUT\n", str_repeat('*', 120), "\n\n", $this->_collectors['init']->getStdOut();
            }

            if (strlen(trim($this->_collectors['init']->getStdError()))) {
                echo str_repeat('*', 120), "\nINIT STD ERROR\n", str_repeat('*', 120), "\n\n", $this->_collectors['init']->getStdError();
            }

            if (strlen(trim($this->_collectors['node']->getStdOut()))) {
                echo str_repeat('*', 120), "\nNODE STD OUT\n", str_repeat('*', 120), "\n\n", $this->_collectors['node']->getStdOut();
            }

            if (strlen(trim($this->_collectors['node']->getStdError()))) {
                echo str_repeat('*', 120), "\nNODE STD ERROR\n", str_repeat('*', 120), "\n\n", $this->_collectors['node']->getStdError();
            }

        } else {
            IO::writeErrorLineToOutputStream('No control plane nodes defined in configuration so there is nothing left to do.', false);
        }
    }
}
