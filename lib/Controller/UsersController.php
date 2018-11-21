<?php
/**
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Clark Tomlinson <fallen013@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <icewind@owncloud.com>
 * @author Roeland Jago Douma <rullzer@owncloud.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Ujjwal Bhardwaj <ujjwalb1996@gmail.com>
 * @author Vincent Petry <pvince81@owncloud.com>
 * @author Sujith Haridasan <sharidasan@owncloud.com>
 *
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
 *
 */

namespace OCA\UserManagement\Controller;

use OC\AppFramework\Http;
use OC\User\Service\CreateUserService;
use OC\User\User;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Mail\IMailer;
use OCP\IAvatarManager;
use OCP\Security\ISecureRandom;
use OCP\User\Exceptions\CannotCreateUserException;
use OCP\User\Exceptions\InvalidEmailException;
use OCP\User\Exceptions\UserAlreadyExistsException;
use OCP\Util;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * @package OC\Settings\Controller
 */
class UsersController extends Controller {
	/** @var IL10N */
	private $l10n;
	/** @var IUserSession */
	private $userSession;
	/** @var CreateUserService */
	private $createUserService;
	/** @var bool */
	private $isAdmin;
	/** @var IUserManager */
	private $userManager;
	/** @var IGroupManager */
	private $groupManager;
	/** @var IConfig */
	private $config;
	/** @var ILogger */
	private $log;
	/** @var \OC_Defaults */
	private $defaults;
	/** @var IMailer */
	private $mailer;
	/** @var string */
	private $fromMailAddress;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var bool contains the state of the encryption app */
	private $isEncryptionAppEnabled;
	/** @var bool contains the state of the admin recovery setting */
	private $isRestoreEnabled = false;
	/** @var IAvatarManager */
	private $avatarManager;
	/** @var EventDispatcher */
	private $eventDispatcher;
	/** @var ISecureRandom */
	protected $secureRandom;
	/** @var ITimeFactory */
	protected $timeFactory;

	/**
	 * UsersController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param IUserManager $userManager
	 * @param IGroupManager $groupManager
	 * @param IUserSession $userSession
	 * @param CreateUserService $createUserService
	 * @param IConfig $config
	 * @param ISecureRandom $secureRandom
	 * @param IL10N $l10n
	 * @param ILogger $log
	 * @param \OC_Defaults $defaults
	 * @param IMailer $mailer
	 * @param ITimeFactory $timeFactory
	 * @param IURLGenerator $urlGenerator
	 * @param IAppManager $appManager
	 * @param IAvatarManager $avatarManager
	 * @param EventDispatcherInterface $eventDispatcher
	 */
	public function __construct($appName,
								IRequest $request,
								IUserManager $userManager,
								IGroupManager $groupManager,
								IUserSession $userSession,
								CreateUserService $createUserService,
								IConfig $config,
								ISecureRandom $secureRandom,
								IL10N $l10n,
								ILogger $log,
								\OC_Defaults $defaults,
								IMailer $mailer,
								ITimeFactory $timeFactory,
								IURLGenerator $urlGenerator,
								IAppManager $appManager,
								IAvatarManager $avatarManager,
								EventDispatcherInterface $eventDispatcher) {
		parent::__construct($appName, $request);
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->userSession = $userSession;
		$this->createUserService = $createUserService;
		$this->config = $config;
		$this->l10n = $l10n;
		$this->secureRandom = $secureRandom;
		$this->log = $log;
		$this->defaults = $defaults;
		$this->mailer = $mailer;
		$this->timeFactory = $timeFactory;
		$this->fromMailAddress = Util::getDefaultEmailAddress('no-reply');
		$this->urlGenerator = $urlGenerator;
		$this->avatarManager = $avatarManager;
		$this->eventDispatcher = $eventDispatcher;
		$this->isAdmin = $this->isAdmin();

		// check for encryption state - TODO see formatUserForIndex
		$this->isEncryptionAppEnabled = $appManager->isEnabledForUser('encryption');
		if ($this->isEncryptionAppEnabled) {
			// putting this directly in empty is possible in PHP 5.5+
			$result = $config->getAppValue('encryption', 'recoveryAdminEnabled', 0);
			$this->isRestoreEnabled = !empty($result);
		}
	}

	/**
	 * @param IUser $user
	 * @param array $userGroups
	 * @return array
	 */
	private function formatUserForIndex(IUser $user, array $userGroups = null) {

		// TODO: eliminate this encryption specific code below and somehow
		// hook in additional user info from other apps

		// recovery isn't possible if admin or user has it disabled and encryption
		// is enabled - so we eliminate the else paths in the conditional tree
		// below
		$restorePossible = false;

		if ($this->isEncryptionAppEnabled) {
			if ($this->isRestoreEnabled) {
				// check for the users recovery setting
				$recoveryMode = $this->config->getUserValue($user->getUID(), 'encryption', 'recoveryEnabled', '0');
				// method call inside empty is possible with PHP 5.5+
				$recoveryModeEnabled = !empty($recoveryMode);
				if ($recoveryModeEnabled) {
					// user also has recovery mode enabled
					$restorePossible = true;
				}
			}
		} else {
			// recovery is possible if encryption is disabled (plain files are
			// available)
			$restorePossible = true;
		}

		$subAdminGroups = $this->groupManager->getSubAdmin()->getSubAdminsGroups($user);
		foreach ($subAdminGroups as $key => $subAdminGroup) {
			$subAdminGroups[$key] = $subAdminGroup->getGID();
		}

		$displayName = $user->getEMailAddress();
		if ($displayName === null) {
			$displayName = '';
		}

		$avatarAvailable = false;
		if ($this->config->getSystemValue('enable_avatars', true) === true) {
			try {
				$avatarAvailable = $this->avatarManager->getAvatar($user->getUID())->exists();
			} catch (\Exception $e) {
				//No avatar yet
			}
		}

		return [
			'name' => $user->getUID(),
			'displayname' => $user->getDisplayName(),
			'groups' => (empty($userGroups)) ? $this->groupManager->getUserGroupIds($user, 'management') : $userGroups,
			'subadmin' => $subAdminGroups,
			'isEnabled' => $user->isEnabled(),
			'quota' => $user->getQuota(),
			'storageLocation' => $user->getHome(),
			'lastLogin' => $user->getLastLogin() * 1000,
			'backend' => $user->getBackendClassName(),
			'email' => $displayName,
			'isRestoreDisabled' => !$restorePossible,
			'isAvatarAvailable' => $avatarAvailable,
		];
	}

	/**
	 * @param array $userIDs Array with schema [$uid => $displayName]
	 * @return IUser[]
	 */
	private function getUsersForUID(array $userIDs) {
		$users = [];
		foreach ($userIDs as $uid => $displayName) {
			$users[$uid] = $this->userManager->get($uid);
		}
		return $users;
	}

	/**
	 * @param string $token
	 * @param string $userId
	 * @throws \Exception
	 */
	private function checkEmailChangeToken($token, $userId) {
		$user = $this->userManager->get($userId);

		if ($user === null) {
			throw new \Exception($this->l10n->t('Couldn\'t change the email address because the user does not exist'));
		}

		$splittedToken = \explode(':', $this->config->getUserValue($userId, 'owncloud', 'changeMail', null));
		if (\count($splittedToken) !== 3) {
			$this->config->deleteUserValue($userId, 'owncloud', 'changeMail');
			throw new \Exception($this->l10n->t('Couldn\'t change the email address because the token is invalid'));
		}

		if ($splittedToken[0] < ($this->timeFactory->getTime() - 60*60*12)) {
			$this->config->deleteUserValue($userId, 'owncloud', 'changeMail');
			throw new \Exception($this->l10n->t('Couldn\'t change the email address because the token is invalid'));
		}

		if (!\hash_equals($splittedToken[1], $token)) {
			$this->config->deleteUserValue($userId, 'owncloud', 'changeMail');
			throw new \Exception($this->l10n->t('Couldn\'t change the email address because the token is invalid'));
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $offset
	 * @param int $limit
	 * @param string $gid GID to filter for
	 * @param string $pattern Pattern to find in the account table (userid, displayname, email, additional search terms)
	 * @param string $backend Backend to filter for (class-name)
	 * @return DataResponse
	 *
	 * TODO: Tidy up and write unit tests - code is mainly static method calls
	 */
	public function index($offset = 0, $limit = 10, $gid = '', $pattern = '', $backend = '') {
		// FIXME: The JS sends the group '_everyone' instead of no GID for the "all users" group.
		if ($gid === '_everyone') {
			$gid = '';
		}

		// Remove backends
		if (!empty($backend)) {
			$activeBackends = $this->userManager->getBackends();
			$this->userManager->clearBackends();
			foreach ($activeBackends as $singleActiveBackend) {
				if ($backend === \get_class($singleActiveBackend)) {
					$this->userManager->registerBackend($singleActiveBackend);
					break;
				}
			}
		}

		$users = [];
		if ($this->isAdmin) {
			if ($gid !== '') {
				$batch = $this->getUsersForUID($this->groupManager->displayNamesInGroup($gid, $pattern, $limit, $offset));
			} else {
				$batch = $this->userManager->find($pattern, $limit, $offset);
			}

			foreach ($batch as $user) {
				$users[] = $this->formatUserForIndex($user);
			}
		} else {
			$subAdminOfGroups = $this->groupManager->getSubAdmin()->getSubAdminsGroups($this->userSession->getUser());
			// New class returns IGroup[] so convert back
			$gids = [];
			foreach ($subAdminOfGroups as $group) {
				$gids[] = $group->getGID();
			}
			$subAdminOfGroups = $gids;

			// Set the $gid parameter to an empty value if the subadmin has no rights to access a specific group
			if ($gid !== '' && !\in_array($gid, $subAdminOfGroups)) {
				$gid = '';
			}

			// Batch all groups the user is subadmin of when a group is specified
			$batch = [];
			if ($gid === '') {
				foreach ($subAdminOfGroups as $group) {
					$groupUsers = $this->groupManager->displayNamesInGroup($group, $pattern, $limit, $offset);

					foreach ($groupUsers as $uid => $displayName) {
						$batch[$uid] = $displayName;
					}
				}
			} else {
				$batch = $this->groupManager->displayNamesInGroup($gid, $pattern, $limit, $offset);
			}
			$batch = $this->getUsersForUID($batch);

			foreach ($batch as $user) {
				// Only add the groups, this user is a subadmin of
				$userGroups = \array_values(\array_intersect(
					$this->groupManager->getUserGroupIds($user),
					$subAdminOfGroups
				));
				$users[] = $this->formatUserForIndex($user, $userGroups);
			}
		}

		return new DataResponse($users);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $username
	 * @param string $password
	 * @param array $groups
	 * @param string $email
	 * @return DataResponse
	 */
	public function create($username, $password, array $groups= [], $email='') {
		try {
			$user = $this->createUserService->createUser($username, $password, $email);
			if ($user instanceof User) {
				$preFailedGroups = [];
				$currentUser = $this->userSession->getUser();
				if ($currentUser !== null) {
					if (!$this->groupManager->isAdmin($currentUser->getUID())) {
						/**
						 * If the user is not an admin, then we restrict groups which
						 * are part of the subadmin, other groups should be removed.
						 */
						foreach ($groups as $key => $group) {
							$groupObject = $this->groupManager->get($group);
							if (!$this->groupManager->getSubAdmin()->isSubAdminofGroup($currentUser, $groupObject)) {
								$preFailedGroups[] = $groups[$key];
								unset($groups[$key]);
							}
						}
					}
				}
				$failedGroups = $this->createUserService->addUserToGroups($user, $groups);
				$failedGroups = \array_merge($failedGroups, $preFailedGroups);
				if (\count($failedGroups) > 0) {
					$this->log->error("The user " . $username . " was not added to the groups" . \implode(',', $failedGroups) . ".", ['app' => 'settings']);
				}
				$userGroups = $this->groupManager->getUserGroupIds($user);
				return new DataResponse(
					$this->formatUserForIndex($user, $userGroups),
					Http::STATUS_CREATED
				);
			}
		} catch (CannotCreateUserException $e) {
			return new DataResponse(
				[
					'message' => (string)$this->l10n->t($e->getMessage())
				],
				Http::STATUS_FORBIDDEN
			);
		} catch (InvalidEmailException $e) {
			return new DataResponse(
				[
					'message' => (string)$this->l10n->t($e->getMessage())
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		} catch (UserAlreadyExistsException $e) {
			return new DataResponse(
				[
					'message' => (string)$this->l10n->t($e->getMessage())
				],
				Http::STATUS_CONFLICT
			);
		}

		return new JSONResponse(['status' => 'success']);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $id
	 * @return DataResponse
	 */
	public function destroy($id) {
		$userId = $this->userSession->getUser()->getUID();
		$user = $this->userManager->get($id);

		if ($userId === $id) {
			return new DataResponse(
				[
					'status' => 'error',
					'data' => [
						'message' => (string)$this->l10n->t('Unable to delete user.')
					]
				],
				Http::STATUS_FORBIDDEN
			);
		}

		if (!$this->isAdmin && !$this->groupManager->getSubAdmin()->isUserAccessible($this->userSession->getUser(), $user)) {
			return new DataResponse(
				[
					'status' => 'error',
					'data' => [
						'message' => (string)$this->l10n->t('Authentication error')
					]
				],
				Http::STATUS_FORBIDDEN
			);
		}

		if ($user) {
			if ($user->delete()) {
				return new DataResponse(
					[
						'status' => 'success',
						'data' => [
							'username' => $id
						]
					],
					Http::STATUS_NO_CONTENT
				);
			}
		}

		return new DataResponse(
			[
				'status' => 'error',
				'data' => [
					'message' => (string)$this->l10n->t('Unable to delete user.')
				]
			],
			Http::STATUS_FORBIDDEN
		);
	}

	/**
	 * Set the mail address of a user
	 *
	 * @NoAdminRequired
	 * @NoSubadminRequired
	 *
	 * @param string $id
	 * @param string $mailAddress
	 * @return DataResponse
	 */
	public function setMailAddress($id, $mailAddress) {
		$userId = $this->userSession->getUser()->getUID();
		$user = $this->userManager->get($id);

		if ($userId !== $id
			&& !$this->isAdmin
			&& !$this->groupManager->getSubAdmin()->isUserAccessible($this->userSession->getUser(), $user)) {
			return new DataResponse(
				[
					'status' => 'error',
					'data' => [
						'message' => (string)$this->l10n->t('Forbidden')
					]
				],
				Http::STATUS_FORBIDDEN
			);
		}

		if ($mailAddress !== '' && !$this->mailer->validateMailAddress($mailAddress)) {
			return new DataResponse(
				[
					'status' => 'error',
					'data' => [
						'message' => (string)$this->l10n->t('Invalid mail address')
					]
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}

		if (!$user) {
			return new DataResponse(
				[
					'status' => 'error',
					'data' => [
						'message' => (string)$this->l10n->t('Invalid user')
					]
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}

		// this is the only permission a backend provides and is also used
		// for the permission of setting a email address
		if (!$user->canChangeDisplayName()) {
			return new DataResponse(
				[
					'status' => 'error',
					'data' => [
						'message' => (string)$this->l10n->t('Unable to change mail address')
					]
				],
				Http::STATUS_FORBIDDEN
			);
		}

		// admins can set email without verification
		if ($mailAddress === '' || $this->isAdmin) {
			$this->setEmailAddress($userId, $mailAddress);
			return new DataResponse(
				[
					'status' => 'success',
					'data' => [
						'message' => (string)$this->l10n->t('Email has been changed successfully.')
					]
				],
				Http::STATUS_OK
			);
		}

		try {
			if ($this->sendEmail($userId, $mailAddress)) {
				return new DataResponse(
					[
						'status' => 'success',
						'data' => [
							'username' => $id,
							'mailAddress' => $mailAddress,
							'message' => (string) $this->l10n->t('An email has been sent to this address for confirmation. Until the email is verified this address will not be set.')
						]
					],
					Http::STATUS_OK
				);
			} else {
				return new DataResponse(
					[
						'status' => 'error',
						'data' => [
							'username' => $id,
							'mailAddress' => $mailAddress,
							'message' => (string) $this->l10n->t('No email was sent because you already sent one recently. Please try again later.')
						]
					],
					Http::STATUS_OK
				);
			}
		} catch (\Exception $e) {
			return new DataResponse(
				[
					'status' => 'error',
					'data' => [
						'message' => (string)$e->getMessage()
					]
				]
			);
		}
	}

	/**
	 * Count all unique users visible for the current admin/subadmin.
	 *
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function stats() {
		$userCount = 0;
		if ($this->isAdmin) {
			$countByBackend = $this->userManager->countUsers();

			if (!empty($countByBackend)) {
				foreach ($countByBackend as $count) {
					$userCount += $count;
				}
			}
		} else {
			$groups = $this->groupManager->getSubAdmin()->getSubAdminsGroups($this->userSession->getUser());

			$uniqueUsers = [];
			foreach ($groups as $group) {
				foreach ($group->getUsers() as $uid => $displayName) {
					$uniqueUsers[$uid] = true;
				}
			}

			$userCount = \count($uniqueUsers);
		}

		return new DataResponse(
			[
				'totalUsers' => $userCount
			]
		);
	}

	/**
	 * Set the displayName of a user
	 *
	 * @NoAdminRequired
	 * @NoSubadminRequired
	 *
	 * @param string $username
	 * @param string $displayName
	 * @return DataResponse
	 */
	public function setDisplayName($username, $displayName) {
		$currentUser = $this->userSession->getUser();

		if ($username === null) {
			$username = $currentUser->getUID();
		}

		$user = $this->userManager->get($username);

		if ($user === null ||
			!$user->canChangeDisplayName() ||
			(
				!$this->groupManager->isAdmin($currentUser->getUID()) &&
				!$this->groupManager->getSubAdmin()->isUserAccessible($currentUser, $user) &&
				$currentUser !== $user)
			) {
			return new DataResponse([
				'status' => 'error',
				'data' => [
					'message' => $this->l10n->t('Authentication error'),
				],
			]);
		}

		if ($user->setDisplayName($displayName)) {
			return new DataResponse([
				'status' => 'success',
				'data' => [
					'message' => $this->l10n->t('Your full name has been changed.'),
					'username' => $username,
					'displayName' => $displayName,
				],
			]);
		} else {
			return new DataResponse([
				'status' => 'error',
				'data' => [
					'message' => $this->l10n->t('Unable to change full name'),
					'displayName' => $user->getDisplayName(),
				],
			]);
		}
	}

	/**
	 * @param string $userId
	 * @param string $mailAddress
	 * @throws \Exception
	 * @return boolean
	 */
	public function sendEmail($userId, $mailAddress) {
		$token = $this->config->getUserValue($userId, 'owncloud', 'changeMail');
		if ($token !== '') {
			$splittedToken = \explode(':', $token);
			if ((\count($splittedToken)) === 3 && $splittedToken[0] > ($this->timeFactory->getTime() - 60 * 5)) {
				$this->log->alert('The email is not sent because an email change confirmation mail was sent recently.');
				return false;
			}
		}

		$token = $this->secureRandom->generate(21,
			ISecureRandom::CHAR_DIGITS .
			ISecureRandom::CHAR_LOWER .
			ISecureRandom::CHAR_UPPER);
		$this->config->setUserValue($userId, 'owncloud', 'changeMail', $this->timeFactory->getTime() . ':' . $token . ':' . $mailAddress);

		$link = $this->urlGenerator->linkToRouteAbsolute('settings.Users.changeMail', ['userId' => $userId, 'token' => $token]);

		$tmpl = new \OC_Template('user_management', 'changemail/email');
		$tmpl->assign('link', $link);
		$msg = $tmpl->fetchPage();

		try {
			$message = $this->mailer->createMessage();
			$message->setTo([$mailAddress => $userId]);
			$message->setSubject($this->l10n->t('%s email address confirm', [$this->defaults->getName()]));
			$message->setPlainBody($msg);
			$message->setFrom([$this->fromMailAddress => $this->defaults->getName()]);
			$this->mailer->send($message);
		} catch (\Exception $e) {
			throw new \Exception($this->l10n->t(
				'Couldn\'t send email address change confirmation mail. Please contact your administrator.'
			));
		}
		return true;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $id
	 * @param string $mailAddress
	 * @return JSONResponse
	 */
	public function setEmailAddress($id, $mailAddress) {
		$user = $this->userManager->get($id);

		// Only Admin and SubAdmins are allowed to set email
		if ($this->isAdmin ||
			($this->groupManager->getSubAdmin()->isSubAdmin($this->userSession->getUser()) &&
				$this->groupManager->getSubAdmin()->isUserAccessible($this->userSession->getUser(), $user)) ||
				($this->userSession->getUser()->getUID() === $id)) {
			$user->setEMailAddress($mailAddress);
			if ($this->config->getUserValue($id, 'owncloud', 'changeMail') !== '') {
				$this->config->deleteUserValue($id, 'owncloud', 'changeMail');
			}
			return new JSONResponse();
		}

		return new JSONResponse([
			'error' => 'cannotSetEmailAddress',
			'message' => 'Cannot set email address for user'
		], HTTP::STATUS_NOT_FOUND);
	}

	/**
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 * @NoSubadminRequired
	 *
	 * @param $token
	 * @param $userId
	 * @return RedirectResponse
	 * @throws \Exception
	 */
	public function changeMail($token, $userId) {
		$user = $this->userManager->get($userId);
		$sessionUser = $this->userSession->getUser();

		if ($user !== $sessionUser) {
			$this->log->error("The logged in user is different than expected.", ['app' => 'settings']);
			return new RedirectResponse($this->urlGenerator->linkToRoute('settings.SettingsPage.getPersonal', ['changestatus' => 'error']));
		}
		try {
			$this->checkEmailChangeToken($token, $userId);
		} catch (\Exception $e) {
			$this->log->error($e->getMessage(), ['app' => 'settings']);
			return new RedirectResponse($this->urlGenerator->linkToRoute('settings.SettingsPage.getPersonal', ['changestatus' => 'error']));
		}

		$oldEmailAddress = $user->getEMailAddress();

		$splittedToken = \explode(':', $this->config->getUserValue($userId, 'owncloud', 'changeMail', null));
		$mailAddress = $splittedToken[2];

		$this->setEmailAddress($userId, $mailAddress);

		if ($oldEmailAddress !== null && $oldEmailAddress !== '') {
			$tmpl = new \OC_Template('user_management', 'changemail/notify');
			$tmpl->assign('mailAddress', $mailAddress);
			$msg = $tmpl->fetchPage();

			try {
				$message = $this->mailer->createMessage();
				$message->setTo([$oldEmailAddress => $userId]);
				$message->setSubject($this->l10n->t('%s email address changed successfully', [$this->defaults->getName()]));
				$message->setPlainBody($msg);
				$message->setFrom([$this->fromMailAddress => $this->defaults->getName()]);
				$this->mailer->send($message);
			} catch (\Exception $e) {
				throw new \Exception($this->l10n->t(
					'Couldn\'t send email address change notification mail. Please contact your administrator.'
				));
			}
		}
		return new RedirectResponse($this->urlGenerator->linkToRoute('settings.SettingsPage.getPersonal', ['changestatus' => 'success', 'user' => $userId]));
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $id
	 * @return DataResponse
	 */
	public function setEnabled($id, $enabled) {
		$userId = $this->userSession->getUser()->getUID();
		$user = $this->userManager->get($id);

		if ($userId === $id ||
			(!$this->isAdmin &&
				!$this->groupManager->getSubAdmin()->isUserAccessible($this->userSession->getUser(), $user))) {
			return new DataResponse(
				[
					'status' => 'error',
					'data' => [
						'message' => (string)$this->l10n->t('Forbidden')
					]
				],
				Http::STATUS_FORBIDDEN
			);
		}

		if (!$user) {
			return new DataResponse(
				[
					'status' => 'error',
					'data' => [
						'message' => (string)$this->l10n->t('Invalid user')
					]
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}

		$value = \filter_var($enabled, FILTER_VALIDATE_BOOLEAN);
		if (!isset($value) || $value === null) {
			return new DataResponse(
				[
					'status' => 'error',
					'data' => [
						'message' => (string)$this->l10n->t('Unable to enable/disable user.')
					]
				],
				Http::STATUS_FORBIDDEN
			);
		}

		$user->setEnabled($value);

		return new DataResponse(
			[
				'status' => 'success',
				'data' => [
					'username' => $id,
					'enabled' => $enabled
				]
			],
			Http::STATUS_OK
		);
	}

	public function show() {
	}

	private function isAdmin() {
		// Check if current user (active and not in incognito mode)
		// is an admin
		$activeUser = $this->userSession->getUser();
		if ($activeUser !== null) {
			return $this->groupManager->isAdmin($activeUser->getUID());
		}
		\OC::$CLI;
		return false;
	}
}
