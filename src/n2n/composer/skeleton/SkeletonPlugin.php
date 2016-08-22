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
		echo 'POST INSTALL HOLERADIO';
		
		$this->installOptionalPackages();
	}
	
	/**
	 * Remove the installer after project installation.
	 *
	 * @param ScriptEvent $event
	 */
	public function postUpdate(Event $event) {
		echo 'POST UPDATE HOLERADIO';
		
		$this->installOptionalPackages();
	}
	
	private function getOptionalPackageDefs() {
		$rootPackage = $this->composer->getPackage();
		$extra = $rootPackage->getExtra();
		
		if (!isset($extra['n2n/n2n-composer-skeleton-installer']['optional'])) return array(); 
		
		if (!is_array($extra['n2n/n2n-composer-skeleton-installer']['optional'])) {
			throw new \InvalidArgumentException('Invalid extra def for n2n/n2n-composer-skeleton-installer');
		}
		
		$packageDefs = array();
		foreach ($extra['n2n/n2n-composer-skeleton-installer']['optional'] as $name => $version) {
			$packageDefs[] = new PackageDef($name, $version);
		}
		return $packageDefs;
	}
	
	private function installOptionalPackages() {
		
		$requiredLinks = array();
		$additonalRequires = array();
		foreach ($this->getOptionalPackageDefs() as $packageDef) {
			$requiredLinks[$packageDef->getName()] = new Link('__root__', $packageDef->getName(),
					$this->versionParser->parseConstraints($packageDef->getVersion()), 'INST: ' . $packageDef->getName(),
					$packageDef->getVersion());
			$additonalRequires[$packageDef->getName()] = $packageDef->getVersion(); 
			
			$this->io->write('Stuff: ' . $packageDef->getName());
		}
		
		if (empty($requiredLinks)) return;
		
		$this->composer->getPackage()->setRequires($requiredLinks);
		
		
		$installer = $this->createInstaller();
		$installer->disablePlugins();
		$installer->setUpdate();
		$installer->setUpdateWhitelist(array_keys($requiredLinks));
		
		if (0 !== $installer->run()) {
			$this->io->writeError('Failed to install additional packages.');
		}
		
		$composerJsonFile = new JsonFile(Factory::getComposerFile());
		
		$jsonData = $composerJsonFile->read();

		unset($jsonData['extra']['n2n/n2n-composer-skeleton-installer']);
		unset($jsonData['requires']['n2n/n2n-composer-skeleton-installer']);
		
		if (!isset($jsonData['requires'])) {
			$jsonData['requires'] = array();
		}
		$jsonData['requires'] = array_merge($jsonData['requires'], $additonalRequires);
		$composerJsonFile->write($jsonData);
	}
	
	private function createInstaller() {
        return new Installer($this->io, $this->composer->getConfig(), $this->composer->getPackage(), 
        		$this->composer->getDownloadManager(), $this->composer->getRepositoryManager(),
            	$this->composer->getLocker(), $this->composer->getInstallationManager(),
            	new EventDispatcher($this->composer, $this->io), $this->composer->getAutoloadGenerator());
    }
}

class PackageDef {
	private $name;
	private $version;
	
	public function __construct($name, $version) {
		$this->name = $name;
		$this->version = $version;
	}
	
	public function getName() {
		return $this->name;
	}
	
	public function getVersion() {
		return $this->version;
	}
}