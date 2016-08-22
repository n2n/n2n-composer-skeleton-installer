<?php
namespace n2n\composer\skeleton;

use Composer\Plugin\PluginInterface;
use Composer\IO\IOInterface;

class SkeletonPlugin implements PluginInterface {
	
	public function activate(\Composer\Composer $composer, IOInterface $io) {
		$installer = new ModuleInstaller($io, $composer);
		$composer->getInstallationManager()->addInstaller($installer);
	}
}