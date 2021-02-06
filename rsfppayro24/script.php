<?php
/**
 * payro24 payment plugin
 *
 * @developer JMDMahdi, vispa, mnbp1371
 * @publisher payro24
 * @package VirtueMart
 * @subpackage payment
 * @copyright (C) 2020 payro24
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2 or later
 *
 * http://payro24.ir
 */
defined( '_JEXEC' ) or die( 'Restricted access' );

class plgSystemRSFPpayro24InstallerScript
{
	public function preflight($type, $parent) {
		if ($type != 'uninstall') {
			$app = JFactory::getApplication();
			
			if (!file_exists(JPATH_ADMINISTRATOR.'/components/com_rsform/helpers/rsform.php')) {
				$app->enqueueMessage('Please install the RSForm! Pro component before continuing.', 'error');
				return false;
			}
			
			if (!file_exists(JPATH_ADMINISTRATOR.'/components/com_rsform/helpers/version.php')) {
				$app->enqueueMessage('Please upgrade RSForm! Pro to at least R45 before continuing!', 'error');
				return false;
			}
			
			if (!file_exists(JPATH_PLUGINS.'/system/rsfppayment/rsfppayment.php')) {
				$app->enqueueMessage('Please install the RSForm! Pro Payment Plugin first!', 'error');
				return false;
			}
			
			$jversion = new JVersion();
			if (!$jversion->isCompatible('3.6.0')) {
				$app->enqueueMessage('Please upgrade to at least Joomla! 3.6.x before continuing!', 'error');
				return false;
			}
		}
		
		return true;
	}
	
	public function postflight($type, $parent) {
		if ($type == 'update') {
			$db = JFactory::getDbo();
			$db->setQuery("SELECT * FROM #__rsform_config WHERE `SettingName`='payro24.return'");
			if (!$db->loadResult()) {
				$db->setQuery("INSERT IGNORE INTO `#__rsform_config` (`SettingName`, `SettingValue`) VALUES ('payro24.return', '')");
				$db->execute();
			}
		}
	}
}
