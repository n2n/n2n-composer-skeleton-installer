<?php
namespace n2n\composer\skeleton;

use Composer\Composer;
use Composer\Plugin\PluginInterface;
use Composer\IO\IOInterface;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Script\Event;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Installer;
use Composer\Package\Version\VersionParser;
use Composer\Factory;
use Composer\Json\JsonFile;
use Composer\Package\Link;

class SkeletonPlugin implements PluginInterface, EventSubscriberInterface {
	private $composer;
	private $io;
	
	public function activate(Composer $composer, IOInterface $io) {
		$this->composer = $composer;
		$this->io = $io;
		$this->versionParser = new VersionParser();
	}
	
	public static function getSubscribedEvents() {
		return array(
				'post-install-cmd' => 'postInstall',
				'post-update-cmd' => 'postUpdate');
	}
	
	/**
	 * Install optional dependencies, if any.
	 *
	 * @param ScriptEvent $event
	 */
	public function postInstall(Event $event) {
		echo 'POST INSTALL HOLERADIO ' . spl_object_hash($this);
		
		$this->installOptionalPackages();
	}
	
	/**
	 * Remove the installer after project installation.
	 *
	 * @param ScriptEvent $event
	 */
	public function postUpdate(Event $event) {
		echo 'POST UPDATE HOLERADIO ' . spl_object_hash($this);
		
		$this->installOptionalPackages();
	}
	
	private function getOptionalPackageDefs() {
		$rootPackage = $this->composer->getPackage();
		$extra = $rootPackage->getExtra();
		
		if (!isset($extra['n2n/n2n-composer-skeleton-installer']['optional'])) return array(); 
		
		if (!is_array($extra['n2n/n2n-composer-skeleton-installer']['optional'])) {
			throw new \InvalidArgumentException('Invalid extra def for n2n/n2n-composer-skeleton-installer');
		}
		
		return $extra['n2n/n2n-composer-skeleton-installer']['optional'];
	}
	
	private function installOptionalPackages() {
		$requiredLinks = array();
		$additonalRequires = array();
		foreach ($this->getOptionalPackageDefs() as $name => $version) {
			if (!$this->io->askConfirmation('Do you want to install ' . $name . '? [y,n] (default: y): ', true)) {
				continue;
			}
			
			$requiredLinks[$name] = new Link('__root__', $name, $this->versionParser->parseConstraints($version), 
					$name, $version);
			$additonalRequires[$name] = $version;
		}
		
		if (empty($requiredLinks)) return;
		
		$this->composer->getPackage()->setRequires($requiredLinks);
		
		$this->install(array_keys($requiredLinks));
		
		$this->updateJson($additonalRequires);
	}
	
	private function install(array $packageNames) {
		$installer = new Installer($this->io, $this->composer->getConfig(), $this->composer->getPackage(),
				$this->composer->getDownloadManager(), $this->composer->getRepositoryManager(),
				$this->composer->getLocker(), $this->composer->getInstallationManager(),
				new EventDispatcher($this->composer, $this->io), $this->composer->getAutoloadGenerator());
		
		$installer->disablePlugins();
		$installer->setUpdate();
		$installer->setUpdateWhitelist($packageNames);
		
		if (0 !== $installer->run()) {
			$this->io->writeError('Failed to install additional packages.');
		}
    }
    
    private function updateJson(array $additonalRequires) {
    	$composerJsonFile = new JsonFile(Factory::getComposerFile());
    	
    	$jsonData = $composerJsonFile->read();
    	
    	unset($jsonData['extra']['n2n/n2n-composer-skeleton-installer']);
    	
    	if (!isset($jsonData['require'])) {
    		$jsonData['require'] = array();
    	}
    	unset($jsonData['require']['n2n/n2n-composer-skeleton-installer']);
    	$jsonData['require'] = array_merge($jsonData['require'], $additonalRequires);
    	$composerJsonFile->write($jsonData);
    }
}