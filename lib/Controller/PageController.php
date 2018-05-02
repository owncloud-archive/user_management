<?php
/**
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @copyright Copyright (c) 2018, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 */

namespace OCA\UserManagement\Controller;

use OCA\UserManagement\MetaData;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class PageController extends Controller {

	/** @var IGroupManager */
	private $groupManager;
	/** @var IConfig */
	private $config;
	/** @var IUserSession */
	private $userSession;
	/** @var IAppManager */
	private $appManager;
	/** @var EventDispatcherInterface */
	private $eventDispatcher;

	public function __construct($appName,
								IRequest $request,
								IGroupManager $groupManager,
								IConfig $config,
								IUserSession $userSession,
								IAppManager $appManager,
								EventDispatcherInterface $eventDispatcher) {
		parent::__construct($appName, $request);
		$this->groupManager = $groupManager;
		$this->config = $config;
		$this->userSession = $userSession;
		$this->appManager = $appManager;
		$this->eventDispatcher = $eventDispatcher;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index() {

		// Set the sort option: SORT_USERCOUNT or SORT_GROUPNAME
		$sortGroupsBy = MetaData::SORT_USERCOUNT;

		if (\OC_App::isEnabled('user_ldap')) {
			$isLDAPUsed =
				$this->groupManager->isBackendUsed('\OCA\User_LDAP\Group_LDAP')
				|| $this->groupManager->isBackendUsed('\OCA\User_LDAP\Group_Proxy');
			if ($isLDAPUsed) {
				// LDAP user count can be slow, so we sort by group name here
				$sortGroupsBy = MetaData::SORT_GROUPNAME;
			}
		}

		$isAdmin = $this->groupManager->isAdmin($this->userSession->getUser()->getUserId());

		$groupsInfo = new MetaData(
			$this->userSession->getUser()->getUserId(),
			$isAdmin,
			$this->groupManager,
			$this->userSession
		);
		$groupsInfo->setSorting($sortGroupsBy);
		list($adminGroup, $groups) = $groupsInfo->get();

		$recoveryAdminEnabled = $this->appManager->isEnabledForUser('encryption') &&
			$this->config->getAppValue( 'encryption', 'recoveryAdminEnabled', null );

		if($isAdmin) {
			$subAdmins = $this->groupManager->getSubAdmin()->getAllSubAdmins();
			// New class returns IUser[] so convert back
			$result = [];
			foreach ($subAdmins as $subAdmin) {
				$result[] = [
					'gid' => $subAdmin['group']->getGID(),
					'uid' => $subAdmin['user']->getUID(),
				];
			}
			$subAdmins = $result;
		}else{
			/* Retrieve group IDs from $groups array, so we can pass that information into \OC::$server->getGroupManager()->displayNamesInGroups() */
			$gids = [];
			foreach($groups as $group) {
				if (isset($group['id'])) {
					$gids[] = $group['id'];
				}
			}
			$subAdmins = false;
		}

		// load preset quotas
		$quotaPreset=$this->config->getAppValue('files', 'quota_preset', '1 GB, 5 GB, 10 GB');
		$quotaPreset=explode(',', $quotaPreset);
		foreach($quotaPreset as &$preset) {
			$preset=trim($preset);
		}
		$quotaPreset=array_diff($quotaPreset, ['default', 'none']);

		$defaultQuota=$this->config->getAppValue('files', 'default_quota', 'none');
		$defaultQuotaIsUserDefined = !in_array($defaultQuota, $quotaPreset) && !in_array($defaultQuota, ['none', 'default']);

		$this->eventDispatcher->dispatch('OC\Settings\Users::loadAdditionalScripts');

		$params = [
			'groups' => $groups,
			'sortGroups' => $sortGroupsBy,
			'adminGroup' => $adminGroup,
			'isAdmin' => (int)$isAdmin,
			'subadmins' => $subAdmins,
			'numofgroups' => count($groups) + count($adminGroup),
			'quota_preset' => $quotaPreset,
			'default_quota' => $defaultQuota,
			'defaultQuotaIsUserDefined' => $defaultQuotaIsUserDefined,
			'recoveryAdminEnabled' => $recoveryAdminEnabled,
			'enableAvatars' => \OC::$server->getConfig()->getSystemValue('enable_avatars', true) === true,
			'show_is_enabled' => $this->config->getAppValue('core', 'umgmt_show_is_enabled', 'false'),
			'show_storage_location' => $this->config->getAppValue('core', 'umgmt_show_storage_location', 'false'),
			'show_last_login' => $this->config->getAppValue('core', 'umgmt_show_last_login', 'false'),
			'show_email' => $this->config->getAppValue('core', 'umgmt_show_email', 'false'),
			'show_backend' => $this->config->getAppValue('core', 'umgmt_show_backend', 'false'),
			'send_email' => $this->config->getAppValue('core', 'umgmt_send_email', 'false')
			];

		return new TemplateResponse('user_management', 'main', $params, 'user');

	}
}