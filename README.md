# Kdusa K8s Cluster Deployment Tool

Kdusa (pronounced kay doosa), is a quick and dirty CLI app that can create a Kubernetes cluster out of an arbitrary number of Ubuntu 20.04 nodes.

I would have wrote this in Python where it would have seemingly made the most sense but I was in
a hurry and also didn't know Python at that time. I come from a strong PHP backend but this
app is hardly a representation of that. It's sloppy and dirty at best but it get's a semi-complicated
job done very quickly.

## Cluster Pre-requisites

- Linux only
- Install PHP 7 or greater

## Deploying a new cluster

The initial process to setup a new cluster is fairly automated.
You just need to answer a small amount of simple questions beforehand
and the rest will be taken care of.

It is important to remember that you should keep the Kubernetes tools
and CRI-O package versions as close to in-sync as you can.

Here are the steps to deploy a new cluster;

0. Open a terminal
0. Assuming that the path to this file is $KDUSA/README.md,
execute the following command:
	- $KDUSA/bin/setup install-init
0. A command line program should begin prompting you for input. Once all
required input is collected, a confirmation screen will be provided before
continuing.
0. Once you confirm the configuration, the setup process will begin. Get
some coffee or something, it will be probably be around 30 minutes depending
on hardware resources, node count, and Internet connection capabilities.

That's it! Once that process finishes, there will be a base level Kubernetes
cluster running with a Calico networking layer installed. No services aside
from the automatically created DNS service will exist.

If you need further help with setting up some bells and whistles for your cluster, check out
my blog at [https://azorian.blog](https://azorian.blog).
