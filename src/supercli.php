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
		public function doExecute()
		{
			parent::doExecute();
			$this->out(date(DATE_RFC822) . ' Starting task');
			$this->out('Reset Super User password');
			$this->out('Super user username to reset ?');
			$username = (string) $this->in();

			//actually updating password
			$this->out('Attempting to update password...');
			try
			{
				$this->updateSuperUserPassword($username);
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
		 * @param   string  $username
		 */
		private function updateSuperUserPassword($username)
		{
			// HIGHLY INSECURE DO NOT USE IN PRODUCTION
			$default_hash = password_hash('admin', PASSWORD_BCRYPT);

			$db    = Factory::getDbo();
			$query = $db->getQuery(true);

			$query->select('a.id');
			$query->from($db->qn('#__users', 'a'));
			$query->where($db->qn('a.username') . '=' . $db->q((string) $username));
			$db->setQuery($query, 0, 1);
			$exists = $db->loadResult();
			if (empty($exists))
			{
				throw new InvalidArgumentException('User does not exists. Cannot proceed.', '404');
			}
			$query->clear();
			$query->update($db->qn('#__users', 'u'));
			$query->join('INNER', $db->qn('#__user_usergroup_map', 'ugm') . 'on' . $db->qn('ugm.group_id') . '=8');
			$query->set($db->qn('u.password') . '=' . $db->q($default_hash));
			// admin as password is highly insecure require reset password when connected back
			$query->set($db->qn('u.requireReset') . '=1');
			$query->where($db->qn('u.username') . '=' . $db->q($username));
			$db->setQuery($query);
			$db->execute();
		}

	}
}

CliApplication::getInstance('SuperCli')->execute();
