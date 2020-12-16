<?php
/**
 * @package       resetpassword
 * @author        Alexandre ELISÉ <contact@alexandre-elise.fr>
 * @link          https://alexandre-elise.fr
 * @copyright (c) 2020 . Alexandre ELISÉ . Tous droits réservés.
 * @license       GPL-2.0-and-later GNU General Public License v2.0 or later
 * Created Date : 14/12/2020
 * Created Time : 21:44
 */

use Joomla\CMS\Application\CliApplication;
use Joomla\CMS\Factory;

define('SUPERCLI_MINIMUM_PHP', '7.4.0');

if (version_compare(PHP_VERSION, SUPERCLI_MINIMUM_PHP, '<'))
{
	die('Your host needs to use PHP ' . SUPERCLI_MINIMUM_PHP . ' or higher to run this version of contentcli script');
}


// Set flag that this is a parent file.
const _JEXEC = 1;

error_reporting(E_ALL | E_NOTICE);
ini_set('display_errors', 1);

// Load system defines
if (file_exists(dirname(__DIR__) . '/defines.php'))
{
	require_once dirname(__DIR__) . '/defines.php';
}

if (!defined('_JDEFINES'))
{
	define('JPATH_BASE', dirname(__DIR__));
	require_once JPATH_BASE . '/includes/defines.php';
}

require_once JPATH_LIBRARIES . '/import.legacy.php';
require_once JPATH_LIBRARIES . '/cms.php';

// Load the configuration
require_once JPATH_CONFIGURATION . '/configuration.php';

if (!class_exists('SuperCli'))
{
	/**
	 * Class ResetPassword
	 */
	class SuperCli extends CliApplication
	{
		
		/**
		 * The username of the Super User
		 * of whom you want to reset the password
		 *
		 * @var string $username
		 * @since version
		 */
		private string $username = 'admin';
		
		/**
		 * Method to run the application routines.  Most likely you will want to instantiate a controller
		 * and execute it, or perform some sort of task directly.
		 *
		 * @return  void
		 *
		 * @since       3.4 (CMS)
		 * @deprecated  4.0  The default concrete implementation of doExecute() will be removed, subclasses will need to provide their own implementation.
		 */
		public function doExecute(): void
		{
			parent::doExecute();
			$this->out(date(DATE_RFC822) . ' Starting task');
			$this->out('Reset Super User password');
			$this->out('Super user username to reset ?');
			$this->setUsername((string) $this->in());
			
			//actually updating password
			$this->out('Attempting to update password...');
			try
			{
				$this->updateSuperUserPassword();
			}
			catch (Exception $exception)
			{
				$this->out($exception->getMessage());
				
				return;
			}
			$this->out('You must absolutely modify it when logged back in.');
			
			$this->out(date(DATE_RFC822) . ' Task Done.');
			
		}
		
		/**
		 * @return string
		 */
		public function getUsername(): string
		{
			return $this->username;
		}
		
		/**
		 * @param   string  $username
		 */
		public function setUsername(string $username): void
		{
			if (empty($username))
			{
				throw new InvalidArgumentException('Username cannot be empty', 422);
			}
			
			$this->username = $username;
		}
		
		/**
		 * Update Super User password corresponding to the username given if exists
		 * @return void
		 * @since version
		 */
		private function updateSuperUserPassword(): void
		{
			// HIGHLY INSECURE DO NOT USE IN PRODUCTION
			$defaultHash = password_hash('admin', PASSWORD_BCRYPT);
			
			$db    = Factory::getDbo();
			$query = $db->getQuery(true);
			
			$query->select('a.id');
			$query->from($db->qn('#__users', 'a'));
			$query->where($db->qn('a.username') . '=' . $db->q($this->getUsername()));
			$db->setQuery($query, 0, 1);
			$exists = $db->loadResult();
			if (empty($exists))
			{
				throw new InvalidArgumentException('User does not exists. Cannot proceed.', '404');
			}
			$query->clear();
			$query->update($db->qn('#__users', 'u'));
			$query->join('INNER', $db->qn('#__user_usergroup_map', 'ugm') . 'on' . $db->qn('ugm.group_id') . '=8');
			$query->set($db->qn('u.password') . '=' . $db->q($defaultHash));
			// admin as password is highly insecure require reset password when connected back
			$query->set($db->qn('u.requireReset') . '=1');
			$query->where($db->qn('u.username') . '=' . $db->q($this->getUsername()));
			$db->setQuery($query);
			$db->execute();
		}

	}
}

CliApplication::getInstance('SuperCli')->execute();
